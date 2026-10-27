<?php

namespace App\Http\Controllers\MSWDO;

use App\Http\Controllers\Controller;
use App\Models\AssistanceRequest;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Disbursement;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * MSWDO Dashboard: the summary page shown after logging in.
 *
 * Shows the stat boxes (total beneficiaries, active requests, amount released,
 * budget remaining), the budget utilisation bars and pie chart, the per-fund
 * breakdown table, recent requests, and the daily requests-processed chart.
 * It only reads data; nothing is changed here.
 *
 * Page:  resources/views/mswdo/dashboard/index.blade.php
 * Route: GET /mswdo/dashboard -> index()  (mswdo.dashboard, needs the 'dashboard' module permission)
 *
 * Filters read from the URL: ?year=, ?from= / ?to= (utilisation date range), ?month=.
 */
class DashboardController extends Controller
{
    /**
     * Builds every figure on the dashboard for the chosen year.
     *
     * Called when the user opens the Dashboard, or changes the year, date
     * range or month filter. Returns: the view mswdo.dashboard.index.
     */
    public function index(Request $request)
    {
        // Typed in, from the first fiscal year the system covers - the same rule
        // as Budget Tracking. Anything outside that falls back to this year.
        $year = (int) $request->get('year', now()->year); // the fiscal year being viewed
        if ($year < BudgetController::FIRST_YEAR || $year > BudgetController::LAST_YEAR) {
            $year = max(BudgetController::FIRST_YEAR, (int) now()->year);
        }
        $firstYear = BudgetController::FIRST_YEAR; // lowest year the year picker allows

        $isCurrentYear = $year === (int) now()->year; // true when viewing this calendar year

        // Utilisation window. Defaults to the whole fiscal year; a clerk asking
        // "how much of the budget did September to December use?" narrows it
        // here. Kept inside the selected year so the range and the year picker
        // can never describe two different periods.
        [$from, $to] = $this->utilizationRange($request, $year);
        // True when no narrower range was chosen (Jan 1 to Dec 31), so the page can say "whole year".
        $isFullYear  = $from->isSameDay(Carbon::create($year, 1, 1))
            && $to->isSameDay(Carbon::create($year, 12, 31)->endOfDay());

        // Recent requests are filtered to one month when asked; 'all' keeps the
        // whole year, which is what the panel showed before.
        $month = $request->get('month', 'all'); // 'all' or a month number 1-12
        if ($month !== 'all' && (! ctype_digit((string) $month) || (int) $month < 1 || (int) $month > 12)) {
            $month = 'all';
        }

        // Stat box: number of registered beneficiaries (all years).
        $totalClients   = Client::count();
        // Stat box: requests still in progress (not yet Released or Rejected).
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
        // Result: [budget_id => total released in the window].
        $releasedInRange = Disbursement::query()
            ->whereBetween('released_at', [$from, $to])
            ->selectRaw('budget_id, SUM(amount_released) AS total')
            ->groupBy('budget_id')
            ->pluck('total', 'budget_id');

        // The year's budgets (fund sources), each with its total released.
        $budgets = Budget::withSum('disbursements', 'amount_released')
            ->where('fiscal_year', $year)
            ->orderBy('assistance_type')
            ->get();

        // Stat box: money still unspent across all of the year's budgets.
        $budgetRemaining = $budgets->sum(fn ($b) => $b->remaining_balance);

        // The "Recent requests" panel: the 6 newest requests of the year (or of the chosen month).
        $recentRequests = AssistanceRequest::with('client')
            ->whereYear('created_at', $year)
            ->when($month !== 'all', fn ($q) => $q->whereMonth('created_at', (int) $month))
            ->latest()
            ->take(6)
            ->get();

        // One row per budget for the utilisation bars: allocated, released in
        // the window, the percent used, and the bar width to draw.
        $budgetUtilization = $budgets->map(function ($budget) use ($releasedInRange) {
            $allocated = (float) $budget->allocated_amount;
            $released  = (float) ($releasedInRange[$budget->id] ?? 0);
            $exact     = $allocated > 0 ? ($released / $allocated) * 100 : 0; // unrounded percent used

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
        $releasedInWindow = $budgetUtilization->sum('released'); // total released in the window, all types
        // Pie slices: type, amount released, and 'share' = its percent of the pie.
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
        $offset = 0.0; // where the next slice starts, in percent of the circle
        $budgetPie = $budgetPie->map(function ($slice) use (&$offset) {
            $slice['offset'] = $offset;
            $offset += $slice['share'];

            return $slice;
        });

        // Month choices for the month filter: [1 => 'January', ..., 12 => 'December'].
        $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [
            $m => Carbon::create($year, $m, 1)->format('F'),
        ]);

        // Breakdown per funding source for the year: how many recipients each
        // one has paid, how much it released, and what is left. Each row links
        // to that funding source on Budget Tracking.
        // Joins releases to their requests to count DISTINCT clients (one person
        // paid twice counts once) and total releases, keyed by budget_id.
        $recipientsPerBudget = Disbursement::query()
            ->join('assistance_requests', 'assistance_requests.id', '=', 'disbursements.request_id')
            ->whereIn('disbursements.budget_id', $budgets->pluck('id'))
            ->selectRaw('disbursements.budget_id, COUNT(DISTINCT assistance_requests.client_id) AS recipients, COUNT(*) AS releases')
            ->groupBy('disbursements.budget_id')
            ->get()
            ->keyBy('budget_id');

        // Rows of the fund breakdown table (one per budget).
        $fundBreakdown = $budgets->map(fn ($b) => [
            'type'       => $b->assistance_type,
            'allocated'  => (float) $b->allocated_amount,
            'released'   => $b->total_released,
            'remaining'  => $b->remaining_balance,
            'recipients' => (int) ($recipientsPerBudget[$b->id]->recipients ?? 0),
            'releases'   => (int) ($recipientsPerBudget[$b->id]->releases ?? 0),
            'url'        => route('mswdo.budget.index', ['year' => $year, 'type' => $b->assistance_type]),
        ]);

        // Daily count of requests processed — filed, decided (approved or
        // rejected) and released — with a running total of decisions for the
        // month. Shows the chosen month, otherwise the current one.
        // For a past year with no month chosen, January is shown.
        $dailyMonth = $month !== 'all' ? (int) $month : ($isCurrentYear ? now()->month : 1);
        $dayStart   = Carbon::create($year, $dailyMonth, 1)->startOfDay(); // first day of that month
        $dayEnd     = $dayStart->copy()->endOfMonth();                     // last day of that month
        if ($dayEnd->isFuture()) {
            $dayEnd = now()->endOfDay(); // stop at today; future days have nothing yet
        }

        // Small reusable function: counts rows per day for a given date column
        // between $dayStart and $dayEnd. Returns ['2026-10-01' => 3, ...].
        $perDay = fn ($query, string $column) => $query
            ->whereBetween($column, [$dayStart, $dayEnd])
            ->selectRaw("DATE({$column}) AS day, COUNT(*) AS total")
            ->groupByRaw("DATE({$column})")
            ->pluck('total', 'day');

        $filedPerDay    = $perDay(AssistanceRequest::query(), 'created_at');      // requests filed each day
        $decidedPerDay  = $perDay(\App\Models\Approval::query(), 'decided_at');   // approvals/rejections each day
        $releasedPerDay = $perDay(Disbursement::query(), 'released_at');          // releases each day

        // One row per day of the month for the chart/table; days with no
        // activity get 0. 'cumulative' is the running total of decisions.
        $dailyProcessed = collect();
        $running = 0;
        for ($day = $dayStart->copy(); $dayStart->lte($dayEnd) && $day->lte($dayEnd); $day->addDay()) {
            $key = $day->toDateString();
            $decided = (int) ($decidedPerDay[$key] ?? 0);
            $running += $decided;
            $dailyProcessed->push([
                'date'       => $day->copy(),
                'filed'      => (int) ($filedPerDay[$key] ?? 0),
                'decided'    => $decided,
                'released'   => (int) ($releasedPerDay[$key] ?? 0),
                'cumulative' => $running,
            ]);
        }
        $dailyMonthName = $dayStart->format('F Y'); // heading for the daily chart, e.g. "October 2026"

        // compact() passes each listed variable to the view under the same name.
        return view('mswdo.dashboard.index', compact(
            'totalClients', 'activeRequests', 'releasedTotal', 'isCurrentYear',
            'budgetRemaining', 'recentRequests', 'budgetUtilization', 'year', 'firstYear',
            'budgetPie', 'releasedInWindow', 'from', 'to', 'isFullYear', 'month', 'months',
            'fundBreakdown', 'dailyProcessed', 'dailyMonthName'
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
     * Helper for index(); reads ?from= and ?to= from the URL.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function utilizationRange(Request $request, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay(); // Jan 1, 00:00
        $yearEnd   = Carbon::create($year, 12, 31)->endOfDay(); // Dec 31, 23:59:59

        // Use the typed dates, or the whole year when missing/invalid.
        $from = $this->parseDate($request->get('from')) ?? $yearStart;
        $to   = $this->parseDate($request->get('to')) ?? $yearEnd;

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        // max()/min() keep both dates inside the selected year.
        return [
            $from->startOfDay()->max($yearStart),
            $to->endOfDay()->min($yearEnd),
        ];
    }

    /**
     * Turns a "YYYY-MM-DD" string from the URL into a date.
     * Returns null when the value is empty or not a valid date.
     */
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
}
