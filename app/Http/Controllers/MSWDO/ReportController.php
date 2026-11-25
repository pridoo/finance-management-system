<?php

namespace App\Http\Controllers\MSWDO;

use App\Http\Controllers\Controller;
use App\Models\AssistanceRequest;
use App\Models\AuditLog;
use App\Models\Budget;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports module of the MSWDO side.
 *
 * Generates three kinds of reports from the filters the user picks on the
 * Reports page (report type, assistance type, date range):
 *   - recipient : clients whose assistance was already Released
 *   - budget    : allocated vs. released vs. remaining budget per fund
 *   - audit     : the audit trail (who did what, and when)
 *
 * Routes:
 *   GET /reports            -> index()     show the page and an on-screen preview
 *   GET /reports/export     -> export()    download the report as CSV (Excel)
 *   GET /reports/export-pdf -> exportPdf() download the report as PDF
 *
 * All three share the same builder (buildReport), so the preview, the CSV
 * and the PDF always contain the same data.
 */
class ReportController extends Controller
{
    /**
     * Shows the Reports page. If a report type was chosen in the filter form,
     * it also builds that report so it can be previewed on the page.
     * Returns the mswdo.reports.index view.
     */
    public function index(Request $request)
    {
        // The generated report; stays null until the user picks a report type.
        $report = null;

        if ($request->filled('type')) {
            $this->validateFilters($request);
            $report = $this->buildReport($request);
        }

        // This year's budgets, each with the total amount already released
        // from it, shown as the budget summary cards on the page.
        $budgetSummary = Budget::withSum('disbursements', 'amount_released')
            ->where('fiscal_year', now()->year)
            ->orderBy('assistance_type')
            ->get();

        // The 8 most recent audit log entries (with the user who did them),
        // shown in the "recent activity" panel.
        $recentAudit = AuditLog::with('user')
            ->latest('performed_at')
            ->limit(8)
            ->get();

        return view('mswdo.reports.index', compact('report', 'budgetSummary', 'recentAudit'));
    }

    /**
     * Downloads the selected report as a CSV file (opens in Excel).
     * Called by the "Export CSV" button on the Reports page.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->validateFilters($request);
        $report = $this->buildReport($request);
        // File name like "recipient_report_20261026_143000.csv".
        $filename = $request->string('type')->slug().'_report_'.now()->format('Ymd_His').'.csv';

        // The file is written straight to the browser line by line instead of
        // being saved on the server first.
        return response()->streamDownload(function () use ($report) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM so Excel shows special characters (₱, ñ) correctly.
            fwrite($file, "\xEF\xBB\xBF");
            // Header row, then one row per record.
            fputcsv($file, $report['columns']);

            foreach ($report['rows'] as $row) {
                fputcsv($file, $row);
            }

            // If the report has a totals table, add it below the records
            // after a blank row.
            if (! empty($report['summary'])) {
                fputcsv($file, []);
                fputcsv($file, [$report['summary']['title']]);
                fputcsv($file, $report['summary']['columns']);
                foreach ($report['summary']['rows'] as $row) {
                    fputcsv($file, $row);
                }
                fputcsv($file, $report['summary']['total']);
            }

            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Downloads the selected report as a PDF using the mswdo.reports.pdf
     * template. Called by the "Export PDF" button on the Reports page.
     */
    public function exportPdf(Request $request): Response
    {
        $this->validateFilters($request);
        $report = $this->buildReport($request);
        // File name like "budget_report_20261026_143000.pdf".
        $filename = $request->string('type')->slug().'_report_'.now()->format('Ymd_His').'.pdf';

        // Always A4 portrait, matching the office's letterhead paper.
        $pdf = Pdf::loadView('mswdo.reports.pdf', [
            'report' => $report,
            'type' => $request->string('type')->toString(),
        ])->setPaper('a4', 'portrait');

        // Page numbers need the final page count, so they are stamped onto
        // the canvas after layout rather than written into the template.
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 110,   // x: near the right edge
            $canvas->get_height() - 28,   // y: near the bottom
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans'),
            8,                            // font size
            [0.4, 0.4, 0.4],              // gray text color (RGB)
        );

        return $pdf->download($filename);
    }

    /**
     * Checks the filter inputs. If any is invalid, Laravel automatically
     * sends the user back to the form with error messages.
     */
    private function validateFilters(Request $request): void
    {
        $request->validate([
            // Which report to build; only these three are allowed.
            'type' => 'required|in:recipient,budget,audit',
            // Optional: limit the report to one type of assistance.
            'assistance_type' => 'nullable|in:Medical,Burial,Educational,Livelihood',
            // Optional date range; the end date cannot be before the start date.
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
    }

    /**
     * Picks which report to build based on the "type" filter.
     *
     * Every report is returned as an array with the same shape:
     *   'title'   => report title
     *   'columns' => table header labels
     *   'rows'    => table rows (already formatted for display)
     *   'summary' => (optional) totals table under the main table
     * That shared shape is what lets index(), export() and exportPdf() all
     * display any report the same way.
     */
    private function buildReport(Request $request): array
    {
        return match ($request->string('type')->toString()) {
            'budget' => $this->budgetReport($request),
            'audit' => $this->auditReport($request),
            default => $this->recipientReport($request),
        };
    }

    /**
     * Recipient Monitoring report: every assistance request that was already
     * Released, with how much was requested vs. actually released, plus
     * totals per funding source.
     */
    private function recipientReport(Request $request): array
    {
        // Released assistance requests, loaded together with the client and
        // the disbursement record. Each ->when() only applies its filter if
        // the user filled in that field.
        $records = AssistanceRequest::with(['client', 'disbursement'])
            ->when($request->assistance_type, fn ($query, $type) => $query->where('assistance_type', $type))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->where('status', 'Released')
            ->latest()
            ->get();

        // One row per assistance type for the "Totals by Funding Source" table.
        $summaryRows = [];
        // Running grand totals across all assistance types.
        $totals = ['count' => 0, 'requested' => 0.0, 'released' => 0.0];
        foreach ($this->reportTypes($request) as $type) {
            $ofType    = $records->where('assistance_type', $type);   // records of this type only
            $requested = (float) $ofType->sum('amount_requested');    // total amount asked for
            $released  = (float) $ofType->sum(fn ($r) => $r->disbursement?->amount_released ?? 0); // total actually given

            $summaryRows[] = [$type, $ofType->count(), number_format($requested, 2), number_format($released, 2)];
            $totals['count']     += $ofType->count();
            $totals['requested'] += $requested;
            $totals['released']  += $released;
        }

        return [
            'title' => 'Recipient Monitoring',
            'columns' => ['Beneficiary', 'Barangay', 'Fund Source', 'Requested', 'Released', 'Date Filed', 'Date Released'],
            // One table row per released request, in the same order as 'columns'.
            'rows' => $records->map(fn ($record) => [
                $record->client->full_name,
                $record->client->barangay,
                $record->assistance_type,
                number_format((float) $record->amount_requested, 2),
                number_format((float) ($record->disbursement?->amount_released ?? 0), 2),
                $record->created_at->format('Y-m-d'),
                $record->disbursement?->released_at?->format('Y-m-d') ?? '',
            ])->all(),
            'summary' => [
                'title'   => 'Totals by Funding Source',
                'columns' => ['Fund Source', 'Recipients', 'Requested', 'Released'],
                // Indexes of the columns that hold numbers (right-aligned in the view).
                'numeric' => [1, 2, 3],
                'rows'    => $summaryRows,
                'total'   => ['Grand Total', $totals['count'], number_format($totals['requested'], 2), number_format($totals['released'], 2)],
            ],
        ];
    }

    /**
     * The assistance types a report's totals are broken down by: the one the
     * report is filtered to, otherwise all four — listed even at zero, so a
     * type with nothing released reads as ₱0.00 rather than being missing.
     */
    private function reportTypes(Request $request): array
    {
        return $request->assistance_type
            ? [$request->assistance_type]
            : ['Medical', 'Burial', 'Educational', 'Livelihood'];
    }

    /**
     * Budget Utilization report: for each budget (fiscal year + fund source),
     * how much was allocated, how much was released, what is left, and the
     * percentage used.
     */
    private function budgetReport(Request $request): array
    {
        // Budgets with the sum of all their disbursements, filtered by the
        // optional assistance type and date range. Newest year first.
        $records = Budget::withSum('disbursements', 'amount_released')
            ->when($request->assistance_type, fn ($query, $type) => $query->where('assistance_type', $type))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->orderByDesc('fiscal_year')
            ->orderBy('assistance_type')
            ->get();

        return [
            'title' => 'Budget Utilization',
            'columns' => ['Fiscal Year', 'Fund Source', 'Allocated', 'Released', 'Remaining', 'Utilization'],
            'rows' => $records->map(function (Budget $budget) {
                // Utilization % = released / allocated × 100 (0 if nothing was allocated,
                // to avoid dividing by zero).
                $percent = (float) $budget->allocated_amount > 0
                    ? round(($budget->total_released / (float) $budget->allocated_amount) * 100, 1)
                    : 0;

                // total_released and remaining_balance are computed fields
                // defined in the Budget model.
                return [
                    $budget->fiscal_year,
                    $budget->assistance_type,
                    number_format((float) $budget->allocated_amount, 2),
                    number_format($budget->total_released, 2),
                    number_format($budget->remaining_balance, 2),
                    $percent.'%',
                ];
            })->all(),
            'summary' => $this->budgetTotals($records, $request),
        ];
    }

    /** Allocated / released / remaining per assistance type, plus the grand total. */
    private function budgetTotals($records, Request $request): array
    {
        // Small helper that turns a group of budgets into one totals row:
        // [label, allocated, released, remaining, utilization %].
        $row = function (string $label, $budgets) {
            $allocated = (float) $budgets->sum('allocated_amount');
            $released  = (float) $budgets->sum(fn (Budget $b) => $b->total_released);
            $percent   = $allocated > 0 ? round(($released / $allocated) * 100, 1) : 0;

            return [
                $label,
                number_format($allocated, 2),
                number_format($released, 2),
                number_format(max(0, $allocated - $released), 2),   // never shows a negative remaining
                $percent.'%',
            ];
        };

        return [
            'title'   => 'Totals by Funding Source',
            'columns' => ['Fund Source', 'Allocated', 'Released', 'Remaining', 'Utilization'],
            // Indexes of the columns that hold numbers (right-aligned in the view).
            'numeric' => [1, 2, 3, 4],
            // One totals row per assistance type...
            'rows'    => array_map(
                fn ($type) => $row($type, $records->where('assistance_type', $type)),
                $this->reportTypes($request)
            ),
            // ...and one grand total row for all budgets combined.
            'total'   => $row('Grand Total', $records),
        ];
    }

    /**
     * Audit Trail report: every recorded action in the system (who did it,
     * in which module, what they did, and from which IP address).
     * Only the date range filter applies here.
     */
    private function auditReport(Request $request): array
    {
        // Audit log entries with the user who performed them, newest first.
        $records = AuditLog::with('user')
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('performed_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('performed_at', '<=', $date))
            ->latest('performed_at')
            ->get();

        return [
            'title' => 'Audit Trail',
            'columns' => ['Date and Time', 'User', 'Module', 'Action', 'IP Address'],
            'rows' => $records->map(fn ($record) => [
                $record->performed_at?->format('Y-m-d H:i:s') ?? '',
                $record->user?->name ?? 'System',   // "System" if no user is attached (automatic actions)
                $record->module,
                $record->action,
                $record->ip_address ?? '',
            ])->all(),
        ];
    }
}
