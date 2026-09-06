<?php

namespace App\Http\Controllers\MSWDO;

use App\Http\Controllers\Controller;
use App\Models\AssistanceRequest;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Disbursement;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $years = $this->fiscalYears();
        $year  = (int) $request->get('year', now()->year);

        // A hand-edited ?year= would otherwise show an empty dashboard for a
        // year the dropdown never offers.
        if (! in_array($year, $years, true)) {
            $year = now()->year;
        }

        $isCurrentYear = $year === (int) now()->year;

        $totalClients   = Client::count();
        $activeRequests = AssistanceRequest::whereIn('status', ['Pending', 'Under Review', 'Approved', 'For Release'])
            ->count();

        // For the current year this is the month to date; for a past year the
        // month has no meaning, so the whole year is the useful figure.
        $releasedTotal = Disbursement::whereYear('released_at', $year)
            ->when($isCurrentYear, fn ($q) => $q->whereMonth('released_at', now()->month))
            ->sum('amount_released');

        $budgets = Budget::withSum('disbursements', 'amount_released')
            ->where('fiscal_year', $year)
            ->orderBy('assistance_type')
            ->get();

        $budgetRemaining = $budgets->sum(fn ($b) => $b->remaining_balance);

        $recentRequests = AssistanceRequest::with('client')
            ->whereYear('created_at', $year)
            ->latest()
            ->take(6)
            ->get();

        $budgetUtilization = $budgets->map(function ($budget) {
            $allocated = (float) $budget->allocated_amount;
            $released  = $budget->total_released;
            $exact     = $allocated > 0 ? ($released / $allocated) * 100 : 0;

            return [
                'type'      => $budget->assistance_type,
                'allocated' => $allocated,
                'released'  => $released,
                // Rounded for the label, but kept to two decimals below 1% so a
                // real release never reports a flat "0%" as though nothing had
                // been paid out at all.
                'percent'   => min(100, $exact >= 1 || $exact === 0.0
                    ? round($exact)
                    : round($exact, 2)),
                // The bar is given a visible sliver for any non-zero draw, so
                // "some money moved" is distinguishable at a glance from "none".
                'bar'       => $exact > 0 ? max(1.5, min(100, $exact)) : 0,
            ];
        });

        return view('mswdo.dashboard.index', compact(
            'totalClients', 'activeRequests', 'releasedTotal', 'isCurrentYear',
            'budgetRemaining', 'recentRequests', 'budgetUtilization', 'year', 'years'
        ));
    }

    /**
     * The fiscal years offered in the picker, newest first.
     *
     * Mirrors the Budget Tracking page: the current year is the ceiling, with
     * past years below it so previous allocations stay reviewable.
     */
    private function fiscalYears(): array
    {
        $current  = (int) now()->year;
        $earliest = (int) (Budget::min('fiscal_year') ?: $current);
        $start    = max(min($earliest, $current - 4), $current - 10);

        return array_reverse(range($start, $current));
    }
}
