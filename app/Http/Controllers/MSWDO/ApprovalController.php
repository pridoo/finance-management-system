<?php

namespace App\Http\Controllers\MSWDO;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\AssistanceRequest;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Disbursement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $requests = AssistanceRequest::with('client')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()->paginate(25)->withQueryString();

        // The release step is now a modal on this page, so each row needs the
        // budgets it could draw from. Fetched once and grouped by type rather
        // than queried per row, which would be a query per request on the page.
        $budgetsByType = Budget::withSum('disbursements', 'amount_released')
            ->where('fiscal_year', now()->year)
            ->get()
            ->groupBy('assistance_type');

        return view('mswdo.approvals.index', compact('requests', 'budgetsByType'));
    }

    public function show(Request $request, AssistanceRequest $approval)
    {
        $approval->load(['client', 'encoder', 'approval.recorder', 'disbursement.releasedBy']);

        // The index pages open the full picture in a modal rather than
        // navigating away, so the same record is served as JSON on request.
        if ($request->expectsJson()) {
            return response()->json($this->detailPayload($approval));
        }

        $budgets = Budget::where('assistance_type', $approval->assistance_type)
            ->where('fiscal_year', now()->year)->get();

        return view('mswdo.approvals.show', ['req' => $approval, 'budgets' => $budgets]);
    }

    /**
     * Everything the details modal shows: the request itself, the approval
     * decision and its reason, and the release record when there is one.
     */
    private function detailPayload(AssistanceRequest $req): array
    {
        return [
            'id'          => $req->id,
            'status'      => $req->status,
            'beneficiary' => $req->client?->full_name ?? '—',
            'barangay'    => $req->client?->barangay ?? '—',
            'type'        => $req->assistance_type,
            'amount'      => number_format((float) $req->amount_requested, 2),
            'purpose'     => $req->purpose,
            'filed_by'    => $req->encoder?->name ?? '—',
            'filed_at'    => $req->created_at?->format('M d, Y h:i A'),
            'approval'    => $req->approval ? [
                'decision'    => $req->approval->decision,
                // The reason the decision went the way it did is the whole
                // point of opening this: surfaced, never left as a dash.
                'remarks'     => $req->approval->remarks ?: 'No reason recorded.',
                'decided_at'  => $req->approval->decided_at?->format('M d, Y h:i A'),
                'recorded_by' => $req->approval->recorder?->name ?? '—',
            ] : null,
            'release'     => $req->disbursement ? [
                'amount'      => number_format((float) $req->disbursement->amount_released, 2),
                'released_at' => $req->disbursement->released_at?->format('M d, Y h:i A'),
                'released_by' => $req->disbursement->releasedBy?->name ?? '—',
                'remarks'     => $req->disbursement->remarks ?: 'No remarks recorded.',
            ] : null,
            'timeline'    => $this->timelineFor($req),
        ];
    }

    /**
     * The case as a sequence of events: filed, decided, released.
     *
     * Built from the records themselves rather than the audit log, so it
     * always reflects the current state of the case and carries the remarks
     * attached to each step. A rejected request ends at its decision — there
     * is no release to show — and that absence is the point.
     */
    private function timelineFor(AssistanceRequest $req): array
    {
        $events = [[
            'label'  => 'Request filed',
            'at'     => $req->created_at?->format('M d, Y'),
            'time'   => $req->created_at?->format('h:i A'),
            'by'     => $req->encoder?->name ?? '—',
            'note'   => $req->purpose,
            'tone'   => 'neutral',
        ]];

        if ($req->approval) {
            $approved = $req->approval->decision === 'Approved';
            $events[] = [
                'label' => $approved ? 'Approved' : 'Rejected',
                'at'    => $req->approval->decided_at?->format('M d, Y'),
                'time'  => $req->approval->decided_at?->format('h:i A'),
                'by'    => $req->approval->recorder?->name ?? '—',
                'note'  => $req->approval->remarks ?: 'No reason recorded.',
                'tone'  => $approved ? 'good' : 'bad',
            ];
        }

        if ($req->disbursement) {
            $events[] = [
                'label' => 'Funds released — ₱' . number_format((float) $req->disbursement->amount_released, 2),
                'at'    => $req->disbursement->released_at?->format('M d, Y'),
                'time'  => $req->disbursement->released_at?->format('h:i A'),
                'by'    => $req->disbursement->releasedBy?->name ?? '—',
                'note'  => $req->disbursement->remarks ?: 'No remarks recorded.',
                'tone'  => 'good',
            ];
        }

        // Says plainly that the case is still moving, so a short timeline
        // does not read as missing information.
        if (! in_array($req->status, ['Rejected', 'Released'], true)) {
            $events[] = [
                'label' => 'Awaiting ' . ($req->status === 'For Release' ? 'fund release' : 'a decision'),
                'at'    => null,
                'time'  => null,
                'by'    => null,
                'note'  => 'This step has not happened yet.',
                'tone'  => 'pending',
            ];
        }

        return $events;
    }

    public function store(Request $request)
    {
        $this->ensureOfficer();

        $data = $request->validate([
            'request_id' => 'required|exists:assistance_requests,id',
            'decision'   => 'required|in:Approved,Rejected',
            'remarks'    => 'nullable|string',
        ]);

        $assistanceRequest = DB::transaction(function () use ($data) {
            $assistanceRequest = AssistanceRequest::lockForUpdate()->findOrFail($data['request_id']);

            if (! in_array($assistanceRequest->status, ['Pending', 'Under Review'], true)) {
                throw ValidationException::withMessages([
                    'decision' => 'A final decision has already been recorded for this request.',
                ]);
            }

            Approval::updateOrCreate(
                ['request_id' => $data['request_id']],
                [
                    'recorded_by' => auth()->id(),
                    'decision'    => $data['decision'],
                    'remarks'     => $data['remarks'] ?? null,
                    'decided_at'  => now(),
                ]
            );

            $assistanceRequest->update([
                'status' => $data['decision'] === 'Approved' ? 'Approved' : 'Rejected',
            ]);

            AuditLog::record("Recorded {$data['decision']} for request #{$data['request_id']}", 'Approvals', $data['request_id']);

            return $assistanceRequest;
        });

        $assistanceRequest->loadMissing('client');
        $name = $assistanceRequest->client?->full_name ?? "request #{$assistanceRequest->id}";

        // Back to the listing the decision was made from, now that the modal
        // means the officer never left it in the first place.
        return back()->with('success', $data['decision'] === 'Approved'
            ? "Request #{$assistanceRequest->id} for {$name} has been <b>approved</b>."
            : "Request #{$assistanceRequest->id} for {$name} has been <b>rejected</b>.");
    }

    public function update(Request $request, AssistanceRequest $approval)
    {
        $this->ensureOfficer();

        $data = $request->validate([
            'status'        => 'required|in:For Release,Released',
            'amount_released'=> 'required_if:status,Released|nullable|numeric|min:0',
            'budget_id'     => 'required_if:status,Released|nullable|exists:budgets,id',
            'remarks'       => 'nullable|string',
        ]);

        DB::transaction(function () use ($approval, $data) {
            $approval = AssistanceRequest::lockForUpdate()->findOrFail($approval->id);

            if ($data['status'] === 'For Release') {
                if ($approval->status !== 'Approved') {
                    throw ValidationException::withMessages(['status' => 'Only approved requests can be marked for release.']);
                }

                $approval->update(['status' => 'For Release']);
            } else {
                if ($approval->status !== 'For Release' || $approval->disbursement()->exists()) {
                    throw ValidationException::withMessages(['status' => 'This request is not eligible for release.']);
                }

                $budget = Budget::lockForUpdate()->findOrFail($data['budget_id']);
                $amount = (float) $data['amount_released'];

                if ($budget->assistance_type !== $approval->assistance_type || $budget->fiscal_year !== now()->year) {
                    throw ValidationException::withMessages(['budget_id' => 'Select a current budget for the same assistance type.']);
                }

                if ($amount > (float) $approval->amount_requested) {
                    throw ValidationException::withMessages(['amount_released' => 'Released amount cannot exceed the requested amount.']);
                }

                if ($amount > $budget->remaining_balance) {
                    throw ValidationException::withMessages(['amount_released' => 'The selected budget has insufficient balance.']);
                }

                Disbursement::create([
                    'request_id' => $approval->id,
                    'budget_id' => $budget->id,
                    'amount_released' => $amount,
                    'released_by' => auth()->id(),
                    'released_at' => now(),
                    'remarks' => $data['remarks'] ?? null,
                ]);
                $approval->update(['status' => 'Released']);
            }

            AuditLog::record("Status updated to {$data['status']} for request #{$approval->id}", 'Approvals', $approval->id);
        });

        $approval->loadMissing(['client', 'disbursement']);
        $name = $approval->client?->full_name ?? "request #{$approval->id}";

        return back()->with('success', $data['status'] === 'Released'
            ? '₱' . number_format((float) $data['amount_released'], 2)
              . " has been released to {$name} and deducted from the selected budget."
            : "Request #{$approval->id} for {$name} is now queued <b>for release</b>.");
    }

    private function ensureOfficer(): void
    {
        abort_unless(auth()->user()?->role === 'mswdo_officer', 403);
    }
}
