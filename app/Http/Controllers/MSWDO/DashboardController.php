<?php

namespace App\Http\Controllers\MSWDO;

use App\Http\Controllers\Controller;
use App\Models\AssistanceRequest;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Disbursement;
use Carbon\Carbon;
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

        // Utilisation window. Defaults to the whole fiscal year; a clerk asking
        // "how much of the budget did September to December use?" narrows it
        // here. Kept inside the selected year so the range and the year picker
        // can never describe two different periods.
        [$from, $to] = $this->utilizationRange($request, $year);
        $isFullYear  = $from->isSameDay(Carbon::create($year, 1, 1))
            && $to->isSameDay(Carbon::create($year, 12, 31)->endOfDay());

        // Recent requests are filtered to one month when asked; 'all' keeps the
        // whole year, which is what the panel showed before.
        $month = $request->get('month', 'all');
        if ($month !== 'all' && (! ctype_digit((string) $month) || (int) $month < 1 || (int) $month > 12)) {
            $month = 'all';
        }

        $totalClients   = Client::count();
        $activeRequests = AssistanceRequest::whereIn('status', ['Pending', 'Under Review', 'Approved', 'For Release'])
            ->count();

        // For the current year this is the month to date; for a past year the
        // month has no meaning, so the whole year is the useful figure.
        $releasedTotal = Disbursement::whereYear('released_at', $year)
            ->when($isCurrentYear, fn ($q) => $q->whereMonth('released_at', now()->month))
            ->sum('amount_released');

        // Releases inside the chosen window, per budget. Summed separately from
        // the budget's own lifetime total because the bars and the pie describe
        // the window, while Budget Remaining describes the year.
        $releasedInRange = Disbursement::query()
            ->whereBetween('released_at', [$from, $to])
            ->selectRaw('budget_id, SUM(amount_released) AS total')
            ->groupBy('budget_id')
            ->pluck('total', 'budget_id');

        $budgets = Budget::withSum('disbursements', 'amount_released')
            ->where('fiscal_year', $year)
            ->orderBy('assistance_type')
            ->get();

        $budgetRemaining = $budgets->sum(fn ($b) => $b->remaining_balance);

        $recentRequests = AssistanceRequest::with('client')
            ->whereYear('created_at', $year)
            ->when($month !== 'all', fn ($q) => $q->whereMonth('created_at', (int) $month))
            ->latest()
            ->take(6)
            ->get();

        $budgetUtilization = $budgets->map(function ($budget) use ($releasedInRange) {
            $allocated = (float) $budget->allocated_amount;
            $released  = (float) ($releasedInRange[$budget->id] ?? 0);
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

        // The pie answers "where did the money actually go?", so it is built
        // from money released in the window, not from what was allocated. Types
        // with no release in the window are dropped: a zero slice draws nothing
        // but still takes a legend row.
        $releasedInWindow = $budgetUtilization->sum('released');
        $budgetPie = $budgetUtilization
            ->filter(fn ($b) => $b['released'] > 0)
            ->values()
            ->map(fn ($b) => [
                'type'    => $b['type'],
                'value'   => $b['released'],
                'share'   => $releasedInWindow > 0 ? ($b['released'] / $releasedInWindow) * 100 : 0,
            ]);

        // Pre-computed here so the Blade file stays markup: each slice needs the
        // running offset of everything before it.
        $offset = 0.0;
        $budgetPie = $budgetPie->map(function ($slice) use (&$offset) {
            $slice['offset'] = $offset;
            $offset += $slice['share'];

            return $slice;
        });

        $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [
            $m => Carbon::create($year, $m, 1)->format('F'),
        ]);

        return view('mswdo.dashboard.index', compact(
            'totalClients', 'activeRequests', 'releasedTotal', 'isCurrentYear',
            'budgetRemaining', 'recentRequests', 'budgetUtilization', 'year', 'years',
            'budgetPie', 'releasedInWindow', 'from', 'to', 'isFullYear', 'month', 'months'
        ));
    }

    /**
     * The date window the utilisation panel describes.
     *
     * Both ends are clamped into the selected fiscal year: a range that spilled
     * outside it would report releases the year picker says are not being
     * shown. An inverted range (to before from) is swapped rather than rejected,
     * since the intent is obvious and an error page here helps nobody.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function utilizationRange(Request $request, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd   = Carbon::create($year, 12, 31)->endOfDay();

        $from = $this->parseDate($request->get('from')) ?? $yearStart;
        $to   = $this->parseDate($request->get('to')) ?? $yearEnd;

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            $from->startOfDay()->max($yearStart),
            $to->endOfDay()->min($yearEnd),
        ];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            // A hand-edited ?from= should fall back to the default window
            // rather than break the page.
            return null;
        }
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
