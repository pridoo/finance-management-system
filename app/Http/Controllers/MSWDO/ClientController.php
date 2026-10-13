<?php

namespace App\Http\Controllers\MSWDO;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $barangays = Client::query()
            ->whereNotNull('barangay')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');

        $clients = Client::withCount('assistanceRequests')
            ->when($request->search, fn ($query, $search) => $query->where(fn ($q) =>
                $q->where('last_name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('barangay', 'like', "%{$search}%")
            ))
            ->when($request->filled('barangay'), fn ($query) =>
                $query->where('barangay', $request->string('barangay')->toString())
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('mswdo.beneficiaries.index', compact('clients', 'barangays'));
    }

    public function create()
    {
        return view('mswdo.beneficiaries.create');
    }

    /**
     * The fields of the Family Intake Data Form. Only the identifying details
     * the rest of the system depends on are required; the household, housing
     * and case-note sections are often completed over more than one visit.
     */
    private function rules(): array
    {
        return [
            'intake_date'            => 'nullable|date|before_or_equal:today',
            'last_name'              => 'required|string|max:100',
            'first_name'             => 'required|string|max:100',
            'middle_name'            => 'nullable|string|max:100',
            'suffix'                 => 'nullable|string|max:20',
            'date_of_birth'          => 'required|date|before_or_equal:today',
            'sex'                    => 'required|in:Male,Female',
            'civil_status'           => 'required|string|max:50',
            'religion'               => 'nullable|string|max:60',
            'birth_place'            => 'nullable|string|max:150',
            'educational_attainment' => 'nullable|string|max:80',
            'occupation'             => 'nullable|string|max:100',
            'monthly_income'         => 'nullable|numeric|min:0|max:9999999999',
            'purok'                  => 'nullable|string|max:30',
            'barangay'               => 'required|string|max:100',
            // Exactly 11 digits starting 09. The model's mutator has already
            // rewritten +63/63 prefixes into this shape before storage.
            'contact_number'         => ['nullable', 'digits:11', 'regex:/^09\d{9}$/'],
            'category'               => 'nullable|string|max:60',

            // B. Family composition. Rows left without a name are dropped.
            'family'                          => 'nullable|array|max:30',
            'family.*.name'                   => 'nullable|string|max:150',
            'family.*.age'                    => 'nullable|integer|min:0|max:130',
            'family.*.sex'                    => 'nullable|in:Male,Female',
            'family.*.civil_status'           => 'nullable|string|max:50',
            'family.*.birth_date'             => 'nullable|date|before_or_equal:today',
            'family.*.relationship'           => 'nullable|string|max:50',
            'family.*.educational_attainment' => 'nullable|string|max:80',
            'family.*.occupation_income'      => 'nullable|string|max:100',

            // C. Additional income
            'employment_nature'      => 'nullable|in:' . implode(',', Client::EMPLOYMENT_NATURES),
            'housing'                => 'nullable|string|max:255',
            'water_source'           => 'nullable|string|max:150',
            'toilet'                 => 'nullable|string|max:150',
            'light'                  => 'nullable|string|max:150',

            // D–G
            'problem_presented'      => 'nullable|string|max:5000',
            'initial_assessment'     => 'nullable|string|max:5000',
            'recommendation'         => 'nullable|string|max:5000',
            'plan_of_action'         => 'nullable|string|max:5000',
        ];
    }

    /** Replace the household rows with what the form sent. */
    private function syncFamily(Client $client, array $rows): void
    {
        $client->familyMembers()->delete();

        foreach ($rows as $row) {
            if (trim((string) ($row['name'] ?? '')) === '') {
                continue;
            }
            $client->familyMembers()->create(array_merge(
                array_map(fn ($v) => $v === '' ? null : $v, $row),
                ['name' => mb_convert_case(trim($row['name']), MB_CASE_TITLE, 'UTF-8')]
            ));
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $family = $data['family'] ?? [];
        unset($data['family']);
        $data['address'] = Client::composeAddress($data['purok'] ?? null, $data['barangay']);

        // The warning is advisory: the clerk confirms once and the same submit
        // goes through. Blocking outright would strand genuine namesakes, which
        // are common enough in a single municipality.
        if (($duplicate = $this->findDuplicate($data)) && !$request->boolean('allow_duplicate_registration')) {
            return back()->withInput()->with('duplicate_beneficiary', [
                'name' => $duplicate->full_name,
                'url' => route('mswdo.beneficiaries.show', $duplicate),
                'existing_barangay' => $duplicate->barangay,
                'requested_barangay' => $data['barangay'],
                'different_barangay' => strcasecmp(trim($duplicate->barangay), trim($data['barangay'])) !== 0,
                'birth_date' => $duplicate->date_of_birth?->format('F d, Y'),
            ]);
        }

        $client = DB::transaction(function () use ($data, $family) {
            $client = Client::create($data);
            $this->syncFamily($client, $family);

            return $client;
        });
        AuditLog::record("Created client: {$client->full_name}", 'Beneficiaries', $client->id);

        return redirect()->route('mswdo.beneficiaries.show', $client)
            ->with('success', 'Beneficiary registered successfully.');
    }

    public function show(Request $request, Client $client)
    {
        $client->load(['assistanceRequests.approval', 'assistanceRequests.disbursement', 'familyMembers']);

        $history = $client->assistanceRequests;

        // Totals per assistance type, so the profile answers "what has this
        // household drawn, and for what?" without the clerk adding the column
        // up by eye. Built from the whole history, not the filtered view, so
        // the summary does not move when the filter does.
        $byCategory = $history
            ->groupBy('assistance_type')
            ->map(fn ($rows) => [
                'count'     => $rows->count(),
                'requested' => (float) $rows->sum('amount_requested'),
                'released'  => (float) $rows->sum(fn ($r) => $r->disbursement->amount_released ?? 0),
            ])
            ->sortByDesc('released');

        // 'pending' is everything still moving through the office; 'released'
        // is money actually paid out. Those are the two questions asked of this
        // table, so they are the two filters offered.
        $stage = $request->get('stage', 'all');
        if (! in_array($stage, ['all', 'pending', 'released'], true)) {
            $stage = 'all';
        }

        $pendingStatuses = ['Pending', 'Under Review', 'Approved', 'For Release'];

        $requests = match ($stage) {
            'pending'  => $history->whereIn('status', $pendingStatuses),
            'released' => $history->where('status', 'Released'),
            default    => $history,
        };

        $stageCounts = [
            'all'      => $history->count(),
            'pending'  => $history->whereIn('status', $pendingStatuses)->count(),
            'released' => $history->where('status', 'Released')->count(),
        ];

        return view('mswdo.beneficiaries.show', compact(
            'client', 'requests', 'byCategory', 'stage', 'stageCounts'
        ));
    }

    public function edit(Client $client)
    {
        $client->load('familyMembers');

        return view('mswdo.beneficiaries.edit', compact('client'));
    }

    /**
     * The completed Family Intake Data Form as a PDF, laid out like the paper
     * original so it can be printed and signed by the client and the social
     * worker.
     */
    public function intakePdf(Client $client)
    {
        $client->load('familyMembers');

        return Pdf::loadView('mswdo.beneficiaries.intake-pdf', compact('client'))
            ->setPaper('a4', 'portrait')
            ->stream('family-intake_' . str($client->last_name)->slug() . '_' . $client->id . '.pdf');
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate($this->rules());
        $family = $data['family'] ?? [];
        unset($data['family']);

        if ($duplicate = $this->findDuplicate($data, $client)) {
            return back()->withInput()->with('duplicate_beneficiary', [
                'name' => $duplicate->full_name,
                'url' => route('mswdo.beneficiaries.show', $duplicate),
            ]);
        }

        // Records from before the intake form carry a free-text address and
        // no purok. That address is kept until a purok is entered or the
        // barangay changes, so saving an old record never wipes it.
        if (filled($data['purok'] ?? null) || $data['barangay'] !== $client->barangay || blank($client->address)) {
            $data['address'] = Client::composeAddress($data['purok'] ?? null, $data['barangay']);
        }

        DB::transaction(function () use ($client, $data, $family) {
            $client->update($data);
            $this->syncFamily($client, $family);
        });
        AuditLog::record("Updated client: {$client->full_name}", 'Beneficiaries', $client->id);

        return redirect()->route('mswdo.beneficiaries.show', $client)
            ->with('success', 'Beneficiary updated successfully.');
    }

    public function destroy(Client $client)
    {
        if ($client->assistanceRequests()->exists()) {
            return back()->with('error', 'This beneficiary has assistance records and cannot be deleted.');
        }

        AuditLog::record("Deleted client: {$client->full_name}", 'Beneficiaries', $client->id);
        $client->delete();
        return redirect()->route('mswdo.beneficiaries.index')
            ->with('success', 'Beneficiary deleted.');
    }

    /**
     * A possible duplicate is anyone sharing a first and last name.
     *
     * The birth date used to be part of the match, which made the check almost
     * useless in practice: a clerk re-registering someone rarely has the same
     * date to hand — they mistype it, or the person gives a different answer —
     * and any difference let a second record through silently. Name alone
     * over-matches on common names, which is why this only raises a warning the
     * clerk can dismiss rather than blocking the save.
     *
     * Same-barangay matches are returned first: those are the likely genuine
     * duplicates, while a same name in another barangay is more often a
     * different person.
     */
    private function findDuplicate(array $data, ?Client $except = null): ?Client
    {
        $firstName = mb_strtolower(trim($data['first_name']), 'UTF-8');
        $lastName = mb_strtolower(trim($data['last_name']), 'UTF-8');
        $barangay = mb_strtolower(trim($data['barangay']), 'UTF-8');

        $matches = Client::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [$firstName])
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [$lastName])
            ->get();

        return $matches->first(fn (Client $client) =>
            mb_strtolower(trim($client->barangay), 'UTF-8') === $barangay
        ) ?? $matches->first();
    }
}
