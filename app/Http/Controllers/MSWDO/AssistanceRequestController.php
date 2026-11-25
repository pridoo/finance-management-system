<?php

namespace App\Http\Controllers\MSWDO;

use App\Http\Controllers\Controller;
use App\Models\AssistanceRequest;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Client;
use App\Support\Requirements;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manages Assistance Requests — the applications for help (Medical, Burial,
 * Educational or Livelihood) filed for a registered beneficiary.
 *
 * A request starts as "Pending", then moves through Approvals (approve /
 * reject) and Disbursement (release of money), which are handled by other
 * controllers. This controller lists, files, shows, edits and deletes the
 * requests, and enforces the office rules before a request is saved:
 *  - all mandatory documents must be submitted,
 *  - only one open request per beneficiary per assistance type,
 *  - the same assistance only once every 3 months per household.
 *
 * Routes (see routes/web.php): Route::resource('requests') under /mswdo,
 * named "mswdo.requests.*" → index, create, store, show, edit, update,
 * destroy. Pages are in resources/views/mswdo/requests/.
 *
 * Access: the user must be logged in, active, and have the "requests"
 * module permission.
 */
class AssistanceRequestController extends Controller
{
    /**
     * Assistance Requests list page (GET /mswdo/requests).
     *
     * Shows all requests, newest first, 25 per page, with filters by status
     * and by the beneficiary's barangay.
     *
     * Returns: the view mswdo.requests.index.
     */
    public function index(Request $request)
    {
        // Offered from the official list plus anything already on file, so a
        // barangay captured under an older spelling can still be filtered on
        // rather than becoming unreachable.
        // $barangays: the choices for the barangay filter dropdown.
        $barangays = \App\Support\Barangay::withExisting(
            Client::query()->whereNotNull('barangay')->distinct()->orderBy('barangay')->pluck('barangay')
        );

        // $requests: the page of requests to show, each with its client loaded.
        $requests = AssistanceRequest::with('client')
            // Status filter (e.g. Pending, Approved, Released), if one is chosen.
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            // Blank means every barangay, which is the default view.
            ->when($request->filled('barangay'), fn ($q) => $q->whereHas(
                'client',
                fn ($c) => $c->where('barangay', $request->string('barangay')->toString())
            ))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('mswdo.requests.index', compact('requests', 'barangays'));
    }

    /**
     * "New Assistance Request" page (GET /mswdo/requests/create).
     *
     * Can be opened with ?client_id=… (e.g. from a beneficiary's profile) so
     * that beneficiary is already selected.
     *
     * Returns: the view mswdo.requests.create.
     */
    public function create(Request $request)
    {
        // $clients: all beneficiaries, for the beneficiary picker.
        $clients = Client::orderBy('last_name')->get();
        // $selectedClient: the beneficiary to pre-select, or null.
        $selectedClient = $request->client_id ? Client::find($request->client_id) : null;
        // $requirements: the document checklist for every assistance type
        // (from App\Support\Requirements), shown as checkboxes on the form.
        $requirements = Requirements::all();
        return view('mswdo.requests.create', compact('clients', 'selectedClient', 'requirements'));
    }

    /**
     * Saves a new assistance request (POST /mswdo/requests).
     *
     * Called when the New Assistance Request form is submitted. Checks the
     * documents, the one-open-request rule and the 3-month household rule,
     * then saves the request as "Pending".
     *
     * Returns: a redirect to the Approvals page, or back to the form with an
     * error / duplicate warning.
     */
    public function store(Request $request)
    {
        // $data: the validated form fields.
        $data = $request->validate([
            // client_id must belong to a registered beneficiary.
            'client_id'        => 'required|exists:clients,id',
            'assistance_type'  => 'required|in:Medical,Burial,Educational,Livelihood',
            'amount_requested' => 'required|numeric|min:1',
            'purpose'          => 'required|string',
            // submitted_docs: the ticked document checkboxes.
            'submitted_docs'   => 'nullable|array',
            // Only documents on the chosen type's checklist, so a Burial
            // request cannot be stored as having an enrollment form.
            'submitted_docs.*' => ['string', Rule::in(Requirements::labels((string) $request->input('assistance_type')))],
            'is_referred'      => 'nullable|boolean',
            'is_scholar'       => 'nullable|boolean',
        ]);
        // Remove repeated documents and re-number the list.
        $data['submitted_docs'] = array_values(array_unique($data['submitted_docs'] ?? []));
        // is_referred: the client was referred by another office/party
        // (this changes which documents are mandatory).
        $data['is_referred'] = $request->boolean('is_referred');
        // Scholar status only means something for educational assistance.
        $data['is_scholar'] = $data['assistance_type'] === 'Educational' && $request->boolean('is_scholar');

        // Every mandatory document has to be in hand before a request is
        // filed; optional ones ("if applicable") never block it. The form
        // keeps Submit disabled until they are ticked; this is the same rule
        // for a request that skipped the form.
        // $missing: mandatory documents that were not ticked.
        $missing = array_diff(
            Requirements::mandatory($data['assistance_type'], $data['is_referred'], $data['is_scholar']),
            $data['submitted_docs']
        );
        if ($missing) {
            throw ValidationException::withMessages([
                'submitted_docs' => 'All required documents must be submitted first. Still missing: '
                    . implode(', ', $missing) . '.',
            ]);
        }

        // A hard block, with no "file anyway". The office's rule is one open
        // request per beneficiary per type: the existing one has to be
        // released or rejected first, and a rejection stands rather than being
        // worked around by filing the same case again.
        // $open: the existing unfinished request, if there is one. Its details
        // are flashed as 'duplicate_request' so the form can show them.
        if ($open = $this->findOpenRequest($data)) {
            return back()->withInput()->with('duplicate_request', [
                'name'   => $open->client?->full_name ?? 'This beneficiary',
                'type'   => $open->assistance_type,
                'status' => $open->status,
                'amount' => number_format((float) $open->amount_requested, 2),
                'filed'  => $open->created_at?->format('M d, Y'),
                'url'    => route('mswdo.requests.show', $open),
            ]);
        }

        // One assistance of a kind per household every three months. The
        // household is the client plus everyone linked to the same Family
        // Head, so a spouse or child cannot file for the same need a week
        // later. Members only typed into the Family Composition are matched by
        // name, which is not reliable enough to block on — the form warns
        // about those instead.
        // $recent: the household's latest same-type request within 3 months.
        if ($recent = $this->findRecentInHousehold($data)) {
            // $who: "This client" or the name of the household member who received it.
            $who = (int) $recent->client_id === (int) $data['client_id']
                ? 'This client'
                : ($recent->client?->full_name ?? 'A member of this household');

            throw ValidationException::withMessages([
                'assistance_type' => "{$who} already received {$recent->assistance_type} assistance on "
                    . $recent->created_at->format('M d, Y') . " (request #{$recent->id}). "
                    . 'The same assistance can be given to a household only once every 3 months — next eligible on '
                    . $recent->created_at->copy()->addMonths(3)->format('M d, Y') . '.',
            ]);
        }

        // Record who encoded the request (the logged-in user) and start it as Pending.
        $data['encoded_by'] = auth()->id();
        $data['status']     = 'Pending';

        // $req: the newly saved request.
        $req = AssistanceRequest::create($data);
        AuditLog::record("Created assistance request #{$req->id}", 'Requests', $req->id);

        // Straight to Approvals: filing a request is the first half of a job
        // whose next step is a decision, and the officer was previously left
        // on a read-only detail page having to navigate there themselves.
        $req->loadMissing('client');
        // $name: the beneficiary's name for the success message.
        $name = $req->client?->full_name ?? "request #{$req->id}";

        return redirect()->route('mswdo.approvals.index')
            ->with('success', "Request #{$req->id} for <b>{$name}</b> has been submitted and is now "
                . 'awaiting a decision. It appears below as <b>Pending</b>.');
    }

    /**
     * Request details page (GET /mswdo/requests/{assistanceRequest}).
     *
     * Shows the request, its beneficiary, who encoded it, the approval
     * decision and the disbursement (release) if any. The approve/reject
     * modal also lives on this page.
     *
     * Returns: the view mswdo.requests.show.
     */
    public function show(AssistanceRequest $assistanceRequest)
    {
        // Load everything the page shows: client, encoder, approval (with who
        // recorded it and which budget it was charged to), and disbursement
        // (with who released it).
        $assistanceRequest->load(['client', 'encoder', 'approval.recorder', 'approval.budget', 'disbursement.releasedBy']);

        // The approval step now happens in a modal on this page rather than on
        // a separate screen, so the budget options it needs have to be here.
        // $budgets: this year's budgets for the same assistance type.
        $budgets = Budget::where('assistance_type', $assistanceRequest->assistance_type)
            ->where('fiscal_year', now()->year)
            ->get();

        // 'req' = the request; 'requirements' = the document checklist for its type.
        return view('mswdo.requests.show', [
            'req'          => $assistanceRequest,
            'budgets'      => $budgets,
            'requirements' => Requirements::labels($assistanceRequest->assistance_type),
        ]);
    }

    /**
     * "Edit Request" page (GET /mswdo/requests/{assistanceRequest}/edit).
     *
     * Returns: the view mswdo.requests.edit with the request ('req') and the
     * list of beneficiaries ('clients').
     */
    public function edit(AssistanceRequest $assistanceRequest)
    {
        $clients = Client::orderBy('last_name')->get();
        return view('mswdo.requests.edit', ['req' => $assistanceRequest, 'clients' => $clients]);
    }

    /**
     * Saves changes to a request (PUT/PATCH /mswdo/requests/{assistanceRequest}).
     *
     * Only requests still Pending or Under Review can be edited; once approved
     * or released they are locked. Only the type, amount and purpose can change.
     *
     * Returns: a redirect to the request details page, or back with an error.
     */
    public function update(Request $request, AssistanceRequest $assistanceRequest)
    {
        if (! in_array($assistanceRequest->status, ['Pending', 'Under Review'], true)) {
            return back()->with('error', 'Only pending requests can be edited.');
        }

        // $data: the validated fields that are allowed to change.
        $data = $request->validate([
            'assistance_type'  => 'required|in:Medical,Burial,Educational,Livelihood',
            'amount_requested' => 'required|numeric|min:1',
            'purpose'          => 'required|string',
        ]);

        // A change of type keeps only the submitted documents that are still
        // on the new type's checklist; the rest no longer mean anything.
        if ($data['assistance_type'] !== $assistanceRequest->assistance_type && $assistanceRequest->submitted_docs !== null) {
            $data['submitted_docs'] = array_values(array_intersect(
                $assistanceRequest->submitted_docs,
                Requirements::labels($data['assistance_type'])
            ));
        }

        $assistanceRequest->update($data);
        AuditLog::record("Updated request #{$assistanceRequest->id}", 'Requests', $assistanceRequest->id);

        return redirect()->route('mswdo.requests.show', $assistanceRequest)
            ->with('success', 'Request updated.');
    }

    /**
     * Deletes a request (DELETE /mswdo/requests/{assistanceRequest}).
     *
     * Only Pending or Rejected requests can be deleted; approved or released
     * ones are kept because money/budget is tied to them.
     *
     * Returns: a redirect to the requests list, or back with an error.
     */
    public function destroy(AssistanceRequest $assistanceRequest)
    {
        if (! in_array($assistanceRequest->status, ['Pending', 'Rejected'], true)) {
            return back()->with('error', 'Approved or released requests cannot be deleted.');
        }

        AuditLog::record("Deleted request #{$assistanceRequest->id}", 'Requests', $assistanceRequest->id);
        $assistanceRequest->delete();
        return redirect()->route('mswdo.requests.index')->with('success', 'Request deleted.');
    }

    /**
     * A non-rejected request of the same type, filed in the last 3 months by
     * anyone in this client's household.
     */
    private function findRecentInHousehold(array $data): ?AssistanceRequest
    {
        // $client: the beneficiary the new request is for.
        $client = Client::find($data['client_id']);
        if (! $client) {
            return null;
        }

        // Search requests of every household member (Family Head + linked
        // clients) for the same type, not rejected, filed in the last 3 months.
        return AssistanceRequest::with('client')
            ->whereIn('client_id', $client->householdIds())
            ->where('assistance_type', $data['assistance_type'])
            ->where('status', '!=', 'Rejected')
            ->where('created_at', '>=', now()->subMonths(3))
            ->latest()
            ->first();
    }

    /**
     * An unfinished request of the same type for the same beneficiary.
     *
     * "Unfinished" covers every status before the case is closed, not just the
     * literal Pending: a request sitting at Approved or For Release is just as
     * much a reason to stop and look before filing another. Rejected and
     * Released are settled, so they do not warn.
     *
     * Returns null when there is none.
     */
    private function findOpenRequest(array $data): ?AssistanceRequest
    {
        return AssistanceRequest::with('client')
            ->where('client_id', $data['client_id'])
            ->where('assistance_type', $data['assistance_type'])
            ->whereIn('status', ['Pending', 'Under Review', 'Approved', 'For Release'])
            ->latest()
            ->first();
    }
}
