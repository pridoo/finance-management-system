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

        return [
            'title' => 'Recipient Monitoring',
            'columns' => ['Beneficiary', 'Barangay', 'Type', 'Requested', 'Released', 'Date Filed', 'Date Released'],
            'rows' => $records->map(fn ($record) => [
                $record->client->full_name,
                $record->client->barangay,
                $record->assistance_type,
                number_format((float) $record->amount_requested, 2),
                number_format((float) ($record->disbursement?->amount_released ?? 0), 2),
                $record->created_at->format('Y-m-d'),
                $record->disbursement?->released_at?->format('Y-m-d') ?? '',
            ])->all(),
        ];
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
            'columns' => ['Fiscal Year', 'Assistance Type', 'Allocated', 'Released', 'Remaining', 'Utilization'],
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
