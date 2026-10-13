<?php

namespace App\Http\Controllers\MSWDO;

use App\Http\Controllers\Controller;
use App\Models\AnnualFund;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetSubAllocation;
use App\Models\Disbursement;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BudgetController extends Controller
{
    private const TYPES = ['Medical', 'Burial', 'Educational', 'Livelihood'];

    public function index(Request $request)
    {
        $years = $this->fiscalYears();
        $year  = (int) $request->get('year', now()->year);

        // A hand-edited ?year= in the address bar would otherwise show an empty
        // table for a year the dropdown never offers.
        if (! in_array($year, $years, true)) {
            $year = now()->year;
        }

        // Narrows the whole page to one assistance type: the stat boxes, the
        // allocation table and the releases list all show only that type.
        $type = $request->get('type');
        if (! in_array($type, self::TYPES, true)) {
            $type = null;
        }

        $allBudgets = Budget::withSum('disbursements', 'amount_released')
            ->where('fiscal_year', $year)
            ->withSum('subAllocations', 'allocated_amount')
            ->with(['subAllocations' => fn ($q) => $q->orderByDesc('allocated_amount')])
            ->orderBy('assistance_type')
            ->get();

        $budgets = $type ? $allBudgets->where('assistance_type', $type)->values() : $allBudgets;

        $fund = AnnualFund::forYear($year);

        $releases = Disbursement::with(['request.client', 'budget', 'releasedBy'])
            ->whereHas('budget', fn ($q) => $q->where('fiscal_year', $year)
                ->when($type, fn ($q) => $q->where('assistance_type', $type)))
            ->orderByDesc('released_at')
            ->paginate(15)
            ->withQueryString();

        return view('mswdo.budget.index', [
            'budgets'      => $budgets,
            'allBudgets'   => $allBudgets,
            'year'         => $year,
            'years'        => $years,
            'type'         => $type,
            'types'        => self::TYPES,
            'fund'         => $fund,
            'releases'     => $releases,
        ]);
    }

    /**
     * Set the year's whole fund. Saving again for the same year replaces the
     * figure; it can never be set below what is already allocated out of it.
     */
    public function storeFund(Request $request)
    {
        $this->ensureOfficer();

        $data = $request->validate([
            'fiscal_year' => 'required|integer|min:2020|max:2099',
            'amount'      => 'required|numeric|min:1|max:999999999999',
        ]);

        $fund = AnnualFund::firstOrNew(['fiscal_year' => $data['fiscal_year']]);
        $allocated = (float) Budget::where('fiscal_year', $data['fiscal_year'])->sum('allocated_amount');

        if ((float) $data['amount'] < $allocated) {
            throw ValidationException::withMessages([
                'amount' => 'The fund cannot be lower than the ₱' . number_format($allocated, 2)
                    . ' already allocated to assistance types for ' . $data['fiscal_year'] . '.',
            ]);
        }

        $previous = $fund->exists ? (float) $fund->amount : null;
        $fund->amount = $data['amount'];
        $fund->created_by = auth()->id();
        $fund->save();

        AuditLog::record(
            "Set the {$fund->fiscal_year} annual fund to " . number_format((float) $fund->amount, 2)
            . ($previous !== null ? ' (was ' . number_format($previous, 2) . ')' : ''),
            'Budget',
            $fund->id
        );

        return redirect()->route('mswdo.budget.index', ['year' => $fund->fiscal_year])
            ->with('success', 'The ' . $fund->fiscal_year . ' fund is now ₱'
                . number_format((float) $fund->amount, 2) . '.');
    }

    /**
     * Refuse an allocation that would take a year's types past its fund.
     *
     * $newAmount is the figure the budget would hold afterwards; $budget is the
     * row being changed, left out of the sum so it is not counted twice.
     */
    private function ensureWithinFund(int $year, float $newAmount, ?Budget $budget = null): void
    {
        $fund = AnnualFund::forYear($year);

        if (! $fund) {
            throw ValidationException::withMessages([
                'allocated_amount' => "Set the {$year} annual fund first. Allocations are drawn from it.",
            ]);
        }

        $available = (float) $fund->amount - $fund->allocatedTotal($budget?->id);
        if ($newAmount > $available + 0.001) {
            throw ValidationException::withMessages([
                'allocated_amount' => 'Only ₱' . number_format(max(0, $available - (float) ($budget?->allocated_amount ?? 0)), 2)
                    . " of the {$year} fund is left to allocate.",
            ]);
        }
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
            'assistance_type'  => 'required|in:Medical,Burial,Educational,Livelihood',
            'allocated_amount' => 'required|numeric|min:1',
            'fiscal_year'      => 'required|integer|min:2020|max:2099',
        ]);

        $budget = Budget::firstOrNew([
            'assistance_type' => $data['assistance_type'],
            'fiscal_year' => $data['fiscal_year'],
        ]);
        $toppedUp = $budget->exists;
        $newAmount = $toppedUp
            ? (float) $budget->allocated_amount + (float) $data['allocated_amount']
            : (float) $data['allocated_amount'];

        // Drawn out of the year's fund, so this can only use what is left of it.
        $this->ensureWithinFund((int) $data['fiscal_year'], $newAmount, $toppedUp ? $budget : null);

        $budget->allocated_amount = $newAmount;
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

        // Lowering an allocation hands money back to the fund and is always
        // allowed; raising it has to fit in what the fund has left.
        if ((float) $data['allocated_amount'] > $previous) {
            $this->ensureWithinFund($budget->fiscal_year, (float) $data['allocated_amount'], $budget);
        }

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

    /**
     * Add a named portion to a budget — "College", "TUPAD", "School Supplies".
     *
     * Earmarking only: releases are still recorded against the parent budget,
     * so this changes what the money is set aside for, never what has been
     * paid out.
     */
    public function storeSubAllocation(Request $request, Budget $budget)
    {
        $this->ensureOfficer();

        $data = $request->validate([
            'label'            => 'required|string|max:100',
            'allocated_amount' => 'required|numeric|min:1',
        ]);

        $label = trim($data['label']);

        // Case-insensitive, so "College" and "college" cannot both exist and
        // split the same earmark across two rows.
        $existing = $budget->subAllocations()
            ->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])
            ->first();

        if ($existing) {
            return back()->with('error', "“{$existing->label}” already exists under {$budget->assistance_type}. Edit it instead of adding a second one.");
        }

        // The parts cannot promise more than the whole, or the page would show
        // a fund earmarked beyond what was ever allocated to it.
        $remaining = $budget->unallocated;
        if ((float) $data['allocated_amount'] > $remaining) {
            return back()->with('error', 'Only ₱' . number_format($remaining, 2)
                . ' of the ' . $budget->assistance_type . ' allocation is still unassigned.');
        }

        $budget->subAllocations()->create([
            'label'            => $label,
            'allocated_amount' => $data['allocated_amount'],
            'created_by'       => auth()->id(),
        ]);

        AuditLog::record(
            "Added sub-allocation “{$label}” to {$budget->assistance_type} {$budget->fiscal_year}",
            'Budget',
            $budget->id
        );

        return redirect()->route('mswdo.budget.index', ['year' => $budget->fiscal_year])
            ->with('success', 'Earmarked ₱' . number_format((float) $data['allocated_amount'], 2)
                . " for “{$label}” under {$budget->assistance_type}.");
    }

    public function destroySubAllocation(Budget $budget, BudgetSubAllocation $subAllocation)
    {
        $this->ensureOfficer();

        // Guards against a hand-typed URL pairing a budget with another
        // budget's sub-allocation.
        abort_unless($subAllocation->budget_id === $budget->id, 404);

        $label = $subAllocation->label;
        $subAllocation->delete();

        AuditLog::record(
            "Removed sub-allocation “{$label}” from {$budget->assistance_type} {$budget->fiscal_year}",
            'Budget',
            $budget->id
        );

        return redirect()->route('mswdo.budget.index', ['year' => $budget->fiscal_year])
            ->with('success', "Removed “{$label}” from {$budget->assistance_type}.");
    }

    private function ensureOfficer(): void
    {
        abort_unless(auth()->user()?->role === 'mswdo_officer', 403);
    }
}
