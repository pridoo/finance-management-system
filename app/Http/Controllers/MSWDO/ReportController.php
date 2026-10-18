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

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $report = null;

        if ($request->filled('type')) {
            $this->validateFilters($request);
            $report = $this->buildReport($request);
        }

        $budgetSummary = Budget::withSum('disbursements', 'amount_released')
            ->where('fiscal_year', now()->year)
            ->orderBy('assistance_type')
            ->get();

        $recentAudit = AuditLog::with('user')
            ->latest('performed_at')
            ->limit(8)
            ->get();

        return view('mswdo.reports.index', compact('report', 'budgetSummary', 'recentAudit'));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->validateFilters($request);
        $report = $this->buildReport($request);
        $filename = $request->string('type')->slug().'_report_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $report['columns']);

            foreach ($report['rows'] as $row) {
                fputcsv($file, $row);
            }

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

    public function exportPdf(Request $request): Response
    {
        $this->validateFilters($request);
        $report = $this->buildReport($request);
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
            $canvas->get_width() - 110,
            $canvas->get_height() - 28,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans'),
            8,
            [0.4, 0.4, 0.4],
        );

        return $pdf->download($filename);
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'type' => 'required|in:recipient,budget,audit',
            'assistance_type' => 'nullable|in:Medical,Burial,Educational,Livelihood',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
    }

    private function buildReport(Request $request): array
    {
        return match ($request->string('type')->toString()) {
            'budget' => $this->budgetReport($request),
            'audit' => $this->auditReport($request),
            default => $this->recipientReport($request),
        };
    }

    private function recipientReport(Request $request): array
    {
        $records = AssistanceRequest::with(['client', 'disbursement'])
            ->when($request->assistance_type, fn ($query, $type) => $query->where('assistance_type', $type))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->where('status', 'Released')
            ->latest()
            ->get();

        $summaryRows = [];
        $totals = ['count' => 0, 'requested' => 0.0, 'released' => 0.0];
        foreach ($this->reportTypes($request) as $type) {
            $ofType    = $records->where('assistance_type', $type);
            $requested = (float) $ofType->sum('amount_requested');
            $released  = (float) $ofType->sum(fn ($r) => $r->disbursement?->amount_released ?? 0);

            $summaryRows[] = [$type, $ofType->count(), number_format($requested, 2), number_format($released, 2)];
            $totals['count']     += $ofType->count();
            $totals['requested'] += $requested;
            $totals['released']  += $released;
        }

        return [
            'title' => 'Recipient Monitoring',
            'columns' => ['Beneficiary', 'Barangay', 'Fund Source', 'Requested', 'Released', 'Date Filed', 'Date Released'],
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

    private function budgetReport(Request $request): array
    {
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
                $percent = (float) $budget->allocated_amount > 0
                    ? round(($budget->total_released / (float) $budget->allocated_amount) * 100, 1)
                    : 0;

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
        $row = function (string $label, $budgets) {
            $allocated = (float) $budgets->sum('allocated_amount');
            $released  = (float) $budgets->sum(fn (Budget $b) => $b->total_released);
            $percent   = $allocated > 0 ? round(($released / $allocated) * 100, 1) : 0;

            return [
                $label,
                number_format($allocated, 2),
                number_format($released, 2),
                number_format(max(0, $allocated - $released), 2),
                $percent.'%',
            ];
        };

        return [
            'title'   => 'Totals by Funding Source',
            'columns' => ['Fund Source', 'Allocated', 'Released', 'Remaining', 'Utilization'],
            'numeric' => [1, 2, 3, 4],
            'rows'    => array_map(
                fn ($type) => $row($type, $records->where('assistance_type', $type)),
                $this->reportTypes($request)
            ),
            'total'   => $row('Grand Total', $records),
        ];
    }

    private function auditReport(Request $request): array
    {
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
                $record->user?->name ?? 'System',
                $record->module,
                $record->action,
                $record->ip_address ?? '',
            ])->all(),
        ];
    }
}
