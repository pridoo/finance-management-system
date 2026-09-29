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

class AssistanceRequestController extends Controller
{
    public function index(Request $request)
    {
        // Offered from the official list plus anything already on file, so a
        // barangay captured under an older spelling can still be filtered on
        // rather than becoming unreachable.
        $barangays = \App\Support\Barangay::withExisting(
            Client::query()->whereNotNull('barangay')->distinct()->orderBy('barangay')->pluck('barangay')
        );

        $requests = AssistanceRequest::with('client')
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

    public function create(Request $request)
    {
        $clients = Client::orderBy('last_name')->get();
        $selectedClient = $request->client_id ? Client::find($request->client_id) : null;
        $requirements = Requirements::all();
        return view('mswdo.requests.create', compact('clients', 'selectedClient', 'requirements'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'        => 'required|exists:clients,id',
            'assistance_type'  => 'required|in:Medical,Burial,Educational,Livelihood',
            'amount_requested' => 'required|numeric|min:1',
            'purpose'          => 'required|string',
            'submitted_docs'   => 'nullable|array',
            // Only documents on the chosen type's checklist, so a Burial
            // request cannot be stored as having an enrollment form.
            'submitted_docs.*' => ['string', Rule::in(Requirements::labels((string) $request->input('assistance_type')))],
        ]);
        $data['submitted_docs'] = array_values(array_unique($data['submitted_docs'] ?? []));

        // Advisory, not a block: the clerk confirms once and the same submit
        // goes through. There are legitimate reasons to file a second request
        // of the same type — a separate hospital admission, a second child in
        // school — so refusing outright would be wrong. What is worth stopping
        // is the accidental re-filing of a request that is already in the
        // queue, which is otherwise only noticed at approval time.
        if (($open = $this->findOpenRequest($data)) && ! $request->boolean('allow_duplicate_request')) {
            return back()->withInput()->with('duplicate_request', [
                'name'   => $open->client?->full_name ?? 'This beneficiary',
                'type'   => $open->assistance_type,
                'status' => $open->status,
                'amount' => number_format((float) $open->amount_requested, 2),
                'filed'  => $open->created_at?->format('M d, Y'),
                'url'    => route('mswdo.requests.show', $open),
            ]);
        }

        $data['encoded_by'] = auth()->id();
        $data['status']     = 'Pending';

        $req = AssistanceRequest::create($data);
        AuditLog::record("Created assistance request #{$req->id}", 'Requests', $req->id);

        // Straight to Approvals: filing a request is the first half of a job
        // whose next step is a decision, and the officer was previously left
        // on a read-only detail page having to navigate there themselves.
        $req->loadMissing('client');
        $name = $req->client?->full_name ?? "request #{$req->id}";

        return redirect()->route('mswdo.approvals.index')
            ->with('success', "Request #{$req->id} for <b>{$name}</b> has been submitted and is now "
                . 'awaiting a decision. It appears below as <b>Pending</b>.');
    }

    public function show(AssistanceRequest $assistanceRequest)
    {
        $assistanceRequest->load(['client', 'encoder', 'approval.recorder', 'disbursement.releasedBy']);

        // The approval step now happens in a modal on this page rather than on
        // a separate screen, so the budget options it needs have to be here.
        $budgets = Budget::where('assistance_type', $assistanceRequest->assistance_type)
            ->where('fiscal_year', now()->year)
            ->get();

        return view('mswdo.requests.show', [
            'req'          => $assistanceRequest,
            'budgets'      => $budgets,
            'requirements' => Requirements::labels($assistanceRequest->assistance_type),
        ]);
    }

    public function edit(AssistanceRequest $assistanceRequest)
    {
        $clients = Client::orderBy('last_name')->get();
        return view('mswdo.requests.edit', ['req' => $assistanceRequest, 'clients' => $clients]);
    }

    public function update(Request $request, AssistanceRequest $assistanceRequest)
    {
        if (! in_array($assistanceRequest->status, ['Pending', 'Under Review'], true)) {
            return back()->with('error', 'Only pending requests can be edited.');
        }

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
     * An unfinished request of the same type for the same beneficiary.
     *
     * "Unfinished" covers every status before the case is closed, not just the
     * literal Pending: a request sitting at Approved or For Release is just as
     * much a reason to stop and look before filing another. Rejected and
     * Released are settled, so they do not warn.
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
