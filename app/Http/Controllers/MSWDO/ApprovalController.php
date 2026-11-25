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

/**
 * Approvals module: deciding on assistance requests and releasing the funds.
 *
 * A request moves through these statuses:
 *   Pending / Under Review -> Approved or Rejected      (store(), Officer only)
 *   Approved -> For Release -> Released                  (update(), Officer or Staff)
 * Releasing creates a Disbursement record, which deducts the money from the
 * chosen budget (fund source).
 *
 * Pages: resources/views/mswdo/approvals/index.blade.php (the list, with the
 * decide / release modals) and resources/views/mswdo/approvals/show.blade.php.
 *
 * Routes (resource 'approvals', under /mswdo, need the 'approvals' module permission):
 *  GET  /mswdo/approvals              index()   mswdo.approvals.index
 *  GET  /mswdo/approvals/{approval}   show()    mswdo.approvals.show   (page or JSON)
 *  POST /mswdo/approvals              store()   mswdo.approvals.store  (approve / reject)
 *  PUT  /mswdo/approvals/{approval}   update()  mswdo.approvals.update (for release / released)
 *
 * Note: the {approval} route parameter is an AssistanceRequest, not an Approval row.
 */
class ApprovalController extends Controller
{
    /**
     * Shows the Approvals list: every assistance request, newest first.
     *
     * Called when the user opens Approvals from the sidebar, or filters by
     * status (?status=Pending). Returns: the view mswdo.approvals.index.
     */
    public function index(Request $request)
    {
        // The requests in the table (25 per page) with their client and
        // approval record. Filtered by status only when ?status= is given.
        $requests = AssistanceRequest::with(['client', 'approval'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()->paginate(25)->withQueryString();

        // The release step is now a modal on this page, so each row needs the
        // budgets it could draw from. Fetched once and grouped by type rather
        // than queried per row, which would be a query per request on the page.
        // Result: ['Medical' => [budgets...], 'Burial' => [...], ...] for this year,
        // each budget with its total released so the remaining balance can be shown.
        $budgetsByType = Budget::withSum('disbursements', 'amount_released')
            ->where('fiscal_year', now()->year)
            ->get()
            ->groupBy('assistance_type');

        return view('mswdo.approvals.index', compact('requests', 'budgetsByType'));
    }

    /**
     * Shows one assistance request with its decision and release details.
     *
     * Called by the "View" button. When opened by JavaScript (the details
     * modal) it returns JSON; when opened as a normal page it returns the
     * view mswdo.approvals.show.
     *
     * $approval here is the AssistanceRequest (named after the route parameter).
     */
    public function show(Request $request, AssistanceRequest $approval)
    {
        // Load everything the details need in one go: the client, who encoded
        // the request, the decision (and who recorded it / which budget), and
        // the release (and who released it).
        $approval->load(['client', 'encoder', 'approval.recorder', 'approval.budget', 'disbursement.releasedBy']);

        // The index pages open the full picture in a modal rather than
        // navigating away, so the same record is served as JSON on request.
        if ($request->expectsJson()) {
            return response()->json($this->detailPayload($approval));
        }

        // This year's budgets for the same assistance type, for the funding source choice on the page.
        $budgets = Budget::where('assistance_type', $approval->assistance_type)
            ->where('fiscal_year', now()->year)->get();

        return view('mswdo.approvals.show', ['req' => $approval, 'budgets' => $budgets]);
    }

    /**
     * Everything the details modal shows: the request itself, the approval
     * decision and its reason, and the release record when there is one.
     *
     * Helper for show(). Returns: a plain array that becomes the JSON response.
     * 'approval' and 'release' are null when that step has not happened yet.
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
                'remarks'     => $req->approval->decision === 'Approved'
                    ? 'Funding source: ' . ($req->approval->budget
                        ? $req->approval->budget->assistance_type . ' FY ' . $req->approval->budget->fiscal_year
                        : 'not recorded')
                    : ($req->approval->remarks ?: 'No reason recorded.'),
                'funding_source' => $req->approval->budget
                    ? $req->approval->budget->assistance_type . ' FY ' . $req->approval->budget->fiscal_year
                    : null,
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
     *
     * Helper for detailPayload(). Returns: a list of events, each with a label,
     * date, time, who did it, a note, and a 'tone' (neutral / good / bad /
     * pending) that the modal uses to colour the step.
     */
    private function timelineFor(AssistanceRequest $req): array
    {
        // Step 1 always exists: the request was filed.
        $events = [[
            'label'  => 'Request filed',
            'at'     => $req->created_at?->format('M d, Y'),
            'time'   => $req->created_at?->format('h:i A'),
            'by'     => $req->encoder?->name ?? '—',
            'note'   => $req->purpose,
            'tone'   => 'neutral',
        ]];

        // Step 2: the Officer's decision, if one has been made.
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

        // Step 3: the release of funds, if it has happened.
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

    /**
     * Records the Officer's decision (Approved or Rejected) on a request.
     *
     * Called by the Approve / Reject modal on the Approvals list
     * (POST /mswdo/approvals). Officer only.
     * Returns: redirect back to the list with a success message.
     */
    public function store(Request $request)
    {
        $this->ensureOfficer();

        // request_id: must be an existing assistance request.
        // decision: only "Approved" or "Rejected".
        // remarks: the reason, used when rejecting.
        $data = $request->validate([
            'request_id' => 'required|exists:assistance_requests,id',
            'decision'   => 'required|in:Approved,Rejected',
            // An approval names the funding source the money will come from;
            // a rejection carries the reason instead.
            'budget_id'  => 'required_if:decision,Approved|nullable|exists:budgets,id',
            'remarks'    => 'nullable|string',
        ], [
            'budget_id.required_if' => 'Select the funding source before approving.',
        ]);

        // Done in a database transaction: either every change below is saved, or none is.
        $assistanceRequest = DB::transaction(function () use ($data) {
            // lockForUpdate() locks the row so two users cannot decide on the same request at once.
            $assistanceRequest = AssistanceRequest::lockForUpdate()->findOrFail($data['request_id']);

            // Only requests still waiting for a decision can be decided.
            if (! in_array($assistanceRequest->status, ['Pending', 'Under Review'], true)) {
                throw ValidationException::withMessages([
                    'decision' => 'A final decision has already been recorded for this request.',
                ]);
            }

            $approved = $data['decision'] === 'Approved'; // true = approving, false = rejecting
            if ($approved) {
                // The chosen funding source must match the request's assistance type and be this year's.
                $budget = Budget::findOrFail($data['budget_id']);
                if ($budget->assistance_type !== $assistanceRequest->assistance_type || $budget->fiscal_year !== now()->year) {
                    throw ValidationException::withMessages([
                        'budget_id' => 'Select a current funding source for ' . $assistanceRequest->assistance_type . '.',
                    ]);
                }
            }

            // One Approval row per request: create it, or overwrite it if it exists.
            // Approved keeps the budget and no remarks; Rejected keeps the remarks and no budget.
            Approval::updateOrCreate(
                ['request_id' => $data['request_id']],
                [
                    'recorded_by' => auth()->id(),
                    'decision'    => $data['decision'],
                    'budget_id'   => $approved ? $data['budget_id'] : null,
                    'remarks'     => $approved ? null : ($data['remarks'] ?? null),
                    'decided_at'  => now(),
                ]
            );

            // Move the request itself to its new status.
            $assistanceRequest->update([
                'status' => $data['decision'] === 'Approved' ? 'Approved' : 'Rejected',
            ]);

            AuditLog::record("Recorded {$data['decision']} for request #{$data['request_id']}", 'Approvals', $data['request_id']);

            return $assistanceRequest;
        });

        $assistanceRequest->loadMissing('client');
        // The beneficiary's name for the success message (falls back to the request number).
        $name = $assistanceRequest->client?->full_name ?? "request #{$assistanceRequest->id}";

        // Back to the listing the decision was made from, now that the modal
        // means the officer never left it in the first place.
        return back()->with('success', $data['decision'] === 'Approved'
            ? "Request #{$assistanceRequest->id} for {$name} has been <b>approved</b>."
            : "Request #{$assistanceRequest->id} for {$name} has been <b>rejected</b>.");
    }

    /**
     * Moves an approved request through the release steps:
     *  - status "For Release": queues an Approved request for payout;
     *  - status "Released": records the payout (creates a Disbursement) and
     *    deducts it from the chosen budget.
     *
     * Called by the release modal on the Approvals list (PUT /mswdo/approvals/{approval}).
     * Officer or Staff. $approval is the AssistanceRequest.
     * Returns: redirect back to the list with a success message.
     */
    public function update(Request $request, AssistanceRequest $approval)
    {
        $this->ensureCanRelease();

        // status: the step being done ("For Release" or "Released").
        // amount_released: required only when releasing; cannot be negative.
        // remarks: optional note saved with the release.
        $data = $request->validate([
            'status'        => 'required|in:For Release,Released',
            'amount_released'=> 'required_if:status,Released|nullable|numeric|min:0',
            // Defaults to the funding source chosen at approval.
            'budget_id'     => 'nullable|exists:budgets,id',
            'remarks'       => 'nullable|string',
        ]);

        // All-or-nothing: the disbursement and status change are saved together.
        DB::transaction(function () use ($approval, $data) {
            // Re-read the request with a row lock so two users cannot release it twice.
            $approval = AssistanceRequest::lockForUpdate()->findOrFail($approval->id);

            if ($data['status'] === 'For Release') {
                if ($approval->status !== 'Approved') {
                    throw ValidationException::withMessages(['status' => 'Only approved requests can be marked for release.']);
                }

                $approval->update(['status' => 'For Release']);
            } else {
                // Releasing: the request must be "For Release" and not already paid.
                if ($approval->status !== 'For Release' || $approval->disbursement()->exists()) {
                    throw ValidationException::withMessages(['status' => 'This request is not eligible for release.']);
                }

                // The budget to pay from: the one picked now, otherwise the one picked at approval.
                $budgetId = $data['budget_id'] ?? $approval->approval?->budget_id;
                if (! $budgetId) {
                    throw ValidationException::withMessages(['budget_id' => 'Select the funding source the funds come from.']);
                }

                // Lock the budget too, so two releases cannot overspend it at the same time.
                $budget = Budget::lockForUpdate()->findOrFail($budgetId);
                $amount = (float) $data['amount_released']; // the amount being paid out

                if ($budget->assistance_type !== $approval->assistance_type || $budget->fiscal_year !== now()->year) {
                    throw ValidationException::withMessages(['budget_id' => 'Select a current funding source for the same assistance.']);
                }

                if ($amount > (float) $approval->amount_requested) {
                    throw ValidationException::withMessages(['amount_released' => 'Released amount cannot exceed the requested amount.']);
                }

                // remaining_balance = budget's allocated amount minus everything already released from it.
                if ($amount > $budget->remaining_balance) {
                    throw ValidationException::withMessages(['amount_released' => 'The selected budget has insufficient balance.']);
                }

                // The release record. Budget balances are computed from these
                // rows, so creating it is what deducts the money from the budget.
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
        // The beneficiary's name for the success message.
        $name = $approval->client?->full_name ?? "request #{$approval->id}";

        return back()->with('success', $data['status'] === 'Released'
            ? '₱' . number_format((float) $data['amount_released'], 2)
              . " has been released to {$name} and deducted from the selected budget."
            : "Request #{$approval->id} for {$name} is now queued <b>for release</b>.");
    }

    /** Approving or rejecting is the MSWDO Officer's call alone. */
    private function ensureOfficer(): void
    {
        abort_unless(auth()->user()?->role === 'mswdo_officer', 403);
    }

    /**
     * Releasing — marking an approved request for release and recording the
     * release — is shared by the Officer and the MSWDO Staff. The Staff can
     * carry out a decision but never make one.
     */
    private function ensureCanRelease(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['mswdo_officer', 'mswdo_staff'], true), 403);
    }
}
