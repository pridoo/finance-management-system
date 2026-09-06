<?php

namespace App\Http\Controllers\MSWDO;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $years = $this->fiscalYears();
        $year  = (int) $request->get('year', now()->year);

        // A hand-edited ?year= in the address bar would otherwise show an empty
        // table for a year the dropdown never offers.
        if (! in_array($year, $years, true)) {
            $year = now()->year;
        }

        $budgets = Budget::withSum('disbursements', 'amount_released')
            ->where('fiscal_year', $year)
            ->orderBy('assistance_type')
            ->get();

        return view('mswdo.budget.index', compact('budgets', 'year', 'years'));
    }

    /**
     * The fiscal years offered in the dropdowns, newest first.
     *
     * The current year is the ceiling — a budget cannot be recorded against a
     * year that has not begun, so in 2026 the list stops at 2026 and only
     * gains 2027 once that year actually arrives. Below it sit the past years,
     * which stay selectable so previous allocations remain readable.
     */
    private function fiscalYears(): array
    {
        $current  = (int) now()->year;
        $earliest = (int) (Budget::min('fiscal_year') ?: $current);

        // Always offer a few years of history even on a fresh database, but
        // never let a stray back-dated record stretch the list indefinitely.
        $start = max(min($earliest, $current - 4), $current - 10);

        return array_reverse(range($start, $current));
    }

    public function store(Request $request)
    {
        $this->ensureOfficer();

        $data = $request->validate([
            'assistance_type'  => 'required|in:Medical,Burial,Educational,Emergency,Livelihood',
            'allocated_amount' => 'required|numeric|min:1',
            'fiscal_year'      => 'required|integer|min:2020|max:2099',
        ]);

        $budget = Budget::firstOrNew([
            'assistance_type' => $data['assistance_type'],
            'fiscal_year' => $data['fiscal_year'],
        ]);
        $toppedUp = $budget->exists;
        $budget->allocated_amount = $toppedUp
            ? (float) $budget->allocated_amount + (float) $data['allocated_amount']
            : $data['allocated_amount'];
        $budget->created_by = auth()->id();
        $budget->save();
        AuditLog::record("Created budget for {$budget->assistance_type} {$budget->fiscal_year}", 'Budget', $budget->id);

        // Says which of the two things happened. "Saved" left the officer
        // unsure whether their figure had replaced the old one or been added
        // to it — the exact confusion the form warns about.
        return redirect()->route('mswdo.budget.index', ['year' => $budget->fiscal_year])
            ->with('success', $toppedUp
                ? 'Added ₱' . number_format((float) $data['allocated_amount'], 2) . ' to the '
                  . $budget->assistance_type . ' ' . $budget->fiscal_year . ' allocation, now ₱'
                  . number_format((float) $budget->allocated_amount, 2) . '.'
                : 'Created the ' . $budget->assistance_type . ' ' . $budget->fiscal_year
                  . ' allocation at ₱' . number_format((float) $budget->allocated_amount, 2) . '.');
    }

    /**
     * The releases behind a row, for the "View releases" modal. Returned as
     * JSON so the table can stay a single page load — opening the breakdown is
     * a look-up, not a navigation, and the clerk keeps their place in the list.
     */
    public function show(Budget $budget)
    {
        $budget->loadSum('disbursements', 'amount_released');

        $releases = $budget->disbursements()
            ->with(['request.client', 'releasedBy'])
            ->orderByDesc('released_at')
            ->get()
            ->map(fn ($d) => [
                'id'          => $d->id,
                'request_id'  => $d->request_id,
                'beneficiary' => $d->request?->client?->full_name ?? '—',
                'amount'      => number_format((float) $d->amount_released, 2),
                'released_at' => $d->released_at?->format('M d, Y'),
                'released_by' => $d->releasedBy?->name ?? '—',
                'remarks'     => $d->remarks,
            ]);

        return response()->json([
            'assistance_type' => $budget->assistance_type,
            'fiscal_year'     => $budget->fiscal_year,
            'allocated'       => number_format((float) $budget->allocated_amount, 2),
            'released'        => number_format($budget->total_released, 2),
            'balance'         => number_format($budget->remaining_balance, 2),
            'releases'        => $releases,
        ]);
    }

    /**
     * Sets the allocation to an exact figure, unlike store() which tops up.
     * Correcting a mistyped allocation is a different intent from adding
     * funds, so it gets its own action rather than a flag on the same one.
     */
    public function update(Request $request, Budget $budget)
    {
        $this->ensureOfficer();

        $data = $request->validate([
            'allocated_amount' => 'required|numeric|min:0',
        ]);

        $released = $budget->total_released;

        // Allowing this would report a negative balance and make the
        // utilisation bar exceed 100% — the figures would say the office
        // released money it never had.
        if ((float) $data['allocated_amount'] < $released) {
            throw ValidationException::withMessages([
                'allocated_amount' => 'The allocation cannot be lower than the ₱'
                    . number_format($released, 2) . ' already released from it.',
            ]);
        }

        $previous = (float) $budget->allocated_amount;
        $budget->update(['allocated_amount' => $data['allocated_amount']]);

        AuditLog::record(
            "Updated {$budget->assistance_type} {$budget->fiscal_year} allocation from "
            . number_format($previous, 2) . ' to ' . number_format((float) $budget->allocated_amount, 2),
            'Budget',
            $budget->id
        );

        return redirect()->route('mswdo.budget.index', ['year' => $budget->fiscal_year])
            ->with('success', 'The ' . $budget->assistance_type . ' ' . $budget->fiscal_year
                . ' allocation is now ₱' . number_format((float) $budget->allocated_amount, 2)
                . ' (was ₱' . number_format($previous, 2) . ').');
    }

    public function destroy(Budget $budget)
    {
        $this->ensureOfficer();

        if ($budget->disbursements()->exists()) {
            return back()->with('error', 'A budget with released funds cannot be deleted.');
        }

        AuditLog::record("Deleted budget #{$budget->id}", 'Budget', $budget->id);
        $type = $budget->assistance_type;
        $year = $budget->fiscal_year;
        $budget->delete();

        return redirect()->route('mswdo.budget.index', ['year' => $year])
            ->with('success', "The {$type} {$year} allocation has been deleted.");
    }

    private function ensureOfficer(): void
    {
        abort_unless(auth()->user()?->role === 'mswdo_officer', 403);
    }
}
