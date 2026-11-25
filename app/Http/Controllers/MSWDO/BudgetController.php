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

/**
 * Budget Tracking module (page: resources/views/mswdo/budget/index.blade.php).
 *
 * Manages the money side of the MSWDO:
 *  - the Annual Fund: the whole assistance fund for one fiscal year (AnnualFund model);
 *  - the per-type budgets ("fund sources") carved out of that fund, one per
 *    assistance type per year: Medical, Burial, Educational, Livelihood (Budget model);
 *  - optional named portions of a budget, e.g. "College" (BudgetSubAllocation model);
 *  - the list of releases (Disbursement model) paid out of those budgets.
 *
 * Routes (all under /mswdo, need the 'budget' module permission):
 *  GET    /mswdo/budget                                     index()                mswdo.budget.index
 *  POST   /mswdo/budget                                     store()                mswdo.budget.store
 *  GET    /mswdo/budget/{budget}                            show()   (JSON)        mswdo.budget.show
 *  PUT    /mswdo/budget/{budget}                            update()               mswdo.budget.update
 *  DELETE /mswdo/budget/{budget}                            destroy()              mswdo.budget.destroy
 *  POST   /mswdo/budget-fund                                storeFund()            mswdo.budget.fund.store
 *  POST   /mswdo/budget/{budget}/sub-allocations            storeSubAllocation()   mswdo.budget.sub-allocations.store
 *  DELETE /mswdo/budget/{budget}/sub-allocations/{sub}      destroySubAllocation() mswdo.budget.sub-allocations.destroy
 *
 * Anyone with the module can view the page; only the MSWDO Officer can change
 * figures (see ensureOfficer()).
 */
class BudgetController extends Controller
{
    // The four assistance types the office gives. Used to check the ?type= filter
    // and to fill the type dropdown on the page.
    private const TYPES = ['Medical', 'Burial', 'Educational', 'Livelihood'];

    /** The system's first fiscal year; earlier years are not recorded. */
    public const FIRST_YEAR = 2026;
    // Highest year accepted. Also used by DashboardController for its year picker.
    public const LAST_YEAR = 2099;

    /**
     * Shows the Budget Tracking page for one fiscal year.
     *
     * Called when the user opens Budget Tracking from the sidebar, or changes
     * the year / assistance type filter (?year=2026&type=Medical).
     *
     * Returns: the view mswdo.budget.index.
     */
    public function index(Request $request)
    {
        // Typed in rather than picked from a list. Nothing before the first
        // fiscal year the system covers is accepted, so a typo like 2016 lands
        // on 2026 instead of an empty page.
        $year = (int) $request->get('year', now()->year); // the fiscal year being viewed (defaults to this year)
        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
            $year = max(self::FIRST_YEAR, (int) now()->year);
        }

        // Narrows the whole page to one assistance type: the stat boxes, the
        // allocation table and the releases list all show only that type.
        $type = $request->get('type'); // null means "all types"
        if (! in_array($type, self::TYPES, true)) {
            $type = null;
        }

        // Every budget (fund source) of the year, with:
        //  - disbursements_sum_amount_released: total money already released from it
        //  - sub_allocations_sum_allocated_amount: total earmarked into named portions
        //  - its sub-allocations, biggest first
        $allBudgets = Budget::withSum('disbursements', 'amount_released')
            ->where('fiscal_year', $year)
            ->withSum('subAllocations', 'allocated_amount')
            ->with(['subAllocations' => fn ($q) => $q->orderByDesc('allocated_amount')])
            ->orderBy('assistance_type')
            ->get();

        // The budgets shown in the table: only the chosen type if a filter is set,
        // otherwise all of them. $allBudgets is still passed to the view for
        // figures that must cover the whole year.
        $budgets = $type ? $allBudgets->where('assistance_type', $type)->values() : $allBudgets;

        // The year's Annual Fund record (null if not set yet).
        $fund = AnnualFund::forYear($year);

        // The releases list at the bottom of the page: every disbursement paid
        // from a budget of this year (and of the chosen type, if filtered),
        // newest first, 15 per page. Loads the beneficiary, budget and the
        // user who released it to avoid extra queries per row.
        $releases = Disbursement::with(['request.client', 'budget', 'releasedBy'])
            ->whereHas('budget', fn ($q) => $q->where('fiscal_year', $year)
                ->when($type, fn ($q) => $q->where('assistance_type', $type)))
            ->orderByDesc('released_at')
            ->paginate(15)
            ->withQueryString(); // keeps ?year= and ?type= in the pagination links

        return view('mswdo.budget.index', [
            'budgets'      => $budgets,
            'allBudgets'   => $allBudgets,
            'year'         => $year,
            'firstYear'    => self::FIRST_YEAR,
            'type'         => $type,
            'types'        => self::TYPES,
            'fund'         => $fund,
            'releases'     => $releases,
        ]);
    }

    /**
     * Set the year's whole fund. Saving again for the same year replaces the
     * figure; it can never be set below what is already allocated out of it.
     *
     * Called by the "Set annual fund" prompt on Budget Tracking (POST /mswdo/budget-fund).
     * Officer only. Returns: redirect back to Budget Tracking for that year.
     */
    public function storeFund(Request $request)
    {
        $this->ensureOfficer();

        // fiscal_year: a whole number between FIRST_YEAR and LAST_YEAR.
        // amount: at least ₱1, at most ₱999,999,999,999.
        $data = $request->validate([
            'fiscal_year' => 'required|integer|min:' . self::FIRST_YEAR . '|max:' . self::LAST_YEAR,
            'amount'      => 'required|numeric|min:1|max:999999999999',
        ]);

        // The existing fund for that year, or a new unsaved one if none yet.
        $fund = AnnualFund::firstOrNew(['fiscal_year' => $data['fiscal_year']]);
        // Total already given out to the assistance types for that year.
        $allocated = (float) Budget::where('fiscal_year', $data['fiscal_year'])->sum('allocated_amount');

        if ((float) $data['amount'] < $allocated) {
            throw ValidationException::withMessages([
                'amount' => 'The fund cannot be lower than the ₱' . number_format($allocated, 2)
                    . ' already allocated to assistance types for ' . $data['fiscal_year'] . '.',
            ]);
        }

        // The old amount (for the audit log), or null if this is a new fund.
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
     *
     * Helper used by store() and update(). Returns nothing; throws a
     * validation error (shown on the form) when the fund is missing or too small.
     */
    private function ensureWithinFund(int $year, float $newAmount, ?Budget $budget = null): void
    {
        $fund = AnnualFund::forYear($year);

        if (! $fund) {
            throw ValidationException::withMessages([
                'allocated_amount' => "Set the {$year} annual fund first. Allocations are drawn from it.",
            ]);
        }

        // Money in the fund not yet given to the OTHER assistance types.
        $available = (float) $fund->amount - $fund->allocatedTotal($budget?->id);
        if ($newAmount > $available + 0.001) { // 0.001 allows for tiny rounding differences
            throw ValidationException::withMessages([
                'allocated_amount' => 'Only ₱' . number_format(max(0, $available - (float) ($budget?->allocated_amount ?? 0)), 2)
                    . " of the {$year} fund is left to allocate.",
            ]);
        }
    }

    /**
     * Creates a budget (fund source) for an assistance type and year, or adds
     * money to it if one already exists (a "top-up").
     *
     * Called by the "Add allocation" form on Budget Tracking (POST /mswdo/budget).
     * Officer only. Returns: redirect back to Budget Tracking for that year.
     */
    public function store(Request $request)
    {
        $this->ensureOfficer();

        // assistance_type must be one of the four types; amount at least ₱1;
        // fiscal_year within the allowed range.
        $data = $request->validate([
            'assistance_type'  => 'required|in:Medical,Burial,Educational,Livelihood',
            'allocated_amount' => 'required|numeric|min:1',
            'fiscal_year'      => 'required|integer|min:' . self::FIRST_YEAR . '|max:' . self::LAST_YEAR,
        ]);

        // Only one budget per type per year: find it, or start a new one.
        $budget = Budget::firstOrNew([
            'assistance_type' => $data['assistance_type'],
            'fiscal_year' => $data['fiscal_year'],
        ]);
        $toppedUp = $budget->exists; // true = adding to an existing budget, false = brand new
        // The budget's total after saving: old amount + entered amount, or just the entered amount.
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
                  . $budget->assistance_type . ' ' . $budget->fiscal_year . ' fund source, now ₱'
                  . number_format((float) $budget->allocated_amount, 2) . '.'
                : 'Created the ' . $budget->assistance_type . ' ' . $budget->fiscal_year
                  . ' fund source at ₱' . number_format((float) $budget->allocated_amount, 2) . '.');
    }

    /**
     * The releases behind a row, for the "View releases" modal. Returned as
     * JSON so the table can stay a single page load — opening the breakdown is
     * a look-up, not a navigation, and the clerk keeps their place in the list.
     *
     * Called by JavaScript when "View releases" is clicked (GET /mswdo/budget/{budget}).
     * Returns: JSON with the budget's totals and its list of releases.
     */
    public function show(Budget $budget)
    {
        // Adds the total released so total_released / remaining_balance need no extra query.
        $budget->loadSum('disbursements', 'amount_released');

        // Every release from this budget, newest first, turned into simple
        // rows (beneficiary name, amount, date, who released it) for the modal.
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
            'balance'         => number_format($budget->remaining_balance, 2), // allocated minus released
            'releases'        => $releases,
        ]);
    }

    /**
     * Sets the allocation to an exact figure, unlike store() which tops up.
     * Correcting a mistyped allocation is a different intent from adding
     * funds, so it gets its own action rather than a flag on the same one.
     *
     * Called by the "Edit" form on a Budget Tracking row (PUT /mswdo/budget/{budget}).
     * Officer only. Returns: redirect back to Budget Tracking for that year.
     */
    public function update(Request $request, Budget $budget)
    {
        $this->ensureOfficer();

        // The new exact amount; may be 0 but not negative.
        $data = $request->validate([
            'allocated_amount' => 'required|numeric|min:0',
        ]);

        // Money already paid out of this budget.
        $released = $budget->total_released;

        // Allowing this would report a negative balance and make the
        // utilisation bar exceed 100% — the figures would say the office
        // released money it never had.
        if ((float) $data['allocated_amount'] < $released) {
            throw ValidationException::withMessages([
                'allocated_amount' => 'The fund source cannot be lower than the ₱'
                    . number_format($released, 2) . ' already released from it.',
            ]);
        }

        $previous = (float) $budget->allocated_amount; // old amount, for the audit log and message

        // Lowering an allocation hands money back to the fund and is always
        // allowed; raising it has to fit in what the fund has left.
        if ((float) $data['allocated_amount'] > $previous) {
            $this->ensureWithinFund($budget->fiscal_year, (float) $data['allocated_amount'], $budget);
        }

        $budget->update(['allocated_amount' => $data['allocated_amount']]);

        AuditLog::record(
            "Updated {$budget->assistance_type} {$budget->fiscal_year} fund source from "
            . number_format($previous, 2) . ' to ' . number_format((float) $budget->allocated_amount, 2),
            'Budget',
            $budget->id
        );

        return redirect()->route('mswdo.budget.index', ['year' => $budget->fiscal_year])
            ->with('success', 'The ' . $budget->assistance_type . ' ' . $budget->fiscal_year
                . ' fund source is now ₱' . number_format((float) $budget->allocated_amount, 2)
                . ' (was ₱' . number_format($previous, 2) . ').');
    }

    /**
     * Deletes a budget (fund source), but only if nothing has been released from it.
     *
     * Called by the "Delete" button on a Budget Tracking row (DELETE /mswdo/budget/{budget}).
     * Officer only. Returns: redirect back with an error, or to Budget Tracking with a success message.
     */
    public function destroy(Budget $budget)
    {
        $this->ensureOfficer();

        // A budget that has paid out money must stay, so the release records keep their source.
        if ($budget->disbursements()->exists()) {
            return back()->with('error', 'A budget with released funds cannot be deleted.');
        }

        AuditLog::record("Deleted budget #{$budget->id}", 'Budget', $budget->id);
        // Saved before deleting so they can still be used in the redirect and message.
        $type = $budget->assistance_type;
        $year = $budget->fiscal_year;
        $budget->delete();

        return redirect()->route('mswdo.budget.index', ['year' => $year])
            ->with('success', "The {$type} {$year} fund source has been deleted.");
    }

    /**
     * Add a named portion to a budget — "College", "TUPAD", "School Supplies".
     *
     * Earmarking only: releases are still recorded against the parent budget,
     * so this changes what the money is set aside for, never what has been
     * paid out.
     *
     * Called by the sub-allocation form on Budget Tracking
     * (POST /mswdo/budget/{budget}/sub-allocations). Officer only.
     * Returns: redirect back with an error, or to Budget Tracking with a success message.
     */
    public function storeSubAllocation(Request $request, Budget $budget)
    {
        $this->ensureOfficer();

        // label: the portion's name (up to 100 characters); amount at least ₱1.
        $data = $request->validate([
            'label'            => 'required|string|max:100',
            'allocated_amount' => 'required|numeric|min:1',
        ]);

        $label = trim($data['label']); // name without leading/trailing spaces

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
        $remaining = $budget->unallocated; // budget amount not yet split into named portions
        if ((float) $data['allocated_amount'] > $remaining) {
            return back()->with('error', 'Only ₱' . number_format($remaining, 2)
                . ' of the ' . $budget->assistance_type . ' fund source is still unassigned.');
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

    /**
     * Removes a named portion (sub-allocation) from a budget. The money goes
     * back to the budget's unassigned amount.
     *
     * Called by the remove button next to a sub-allocation
     * (DELETE /mswdo/budget/{budget}/sub-allocations/{subAllocation}). Officer only.
     * Returns: redirect to Budget Tracking with a success message.
     */
    public function destroySubAllocation(Budget $budget, BudgetSubAllocation $subAllocation)
    {
        $this->ensureOfficer();

        // Guards against a hand-typed URL pairing a budget with another
        // budget's sub-allocation.
        abort_unless($subAllocation->budget_id === $budget->id, 404);

        $label = $subAllocation->label; // kept for the audit log and message after deleting
        $subAllocation->delete();

        AuditLog::record(
            "Removed sub-allocation “{$label}” from {$budget->assistance_type} {$budget->fiscal_year}",
            'Budget',
            $budget->id
        );

        return redirect()->route('mswdo.budget.index', ['year' => $budget->fiscal_year])
            ->with('success', "Removed “{$label}” from {$budget->assistance_type}.");
    }

    /**
     * Stops the action with "403 Forbidden" unless the logged-in user is the
     * MSWDO Officer. Only the Officer may change budget figures.
     */
    private function ensureOfficer(): void
    {
        abort_unless(auth()->user()?->role === 'mswdo_officer', 403);
    }
}
