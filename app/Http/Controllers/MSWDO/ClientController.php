<?php

namespace App\Http\Controllers\MSWDO;

use App\Http\Controllers\Controller;
use App\Models\AssistanceRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\FamilyMember;
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

        $search = trim((string) $request->search);

        $clients = Client::withCount('assistanceRequests')
            // Matches the beneficiary themselves — every word typed has to
            // appear in their name or barangay, so "Juan Dela Cruz" finds Juan
            // Dela Cruz — or anyone listed in their Family Composition, which
            // finds the Family Head of the person searched for.
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where(function ($own) use ($search) {
                    foreach (preg_split('/\s+/', $search) as $word) {
                        $own->where(fn ($w) => $w
                            ->where('last_name', 'like', "%{$word}%")
                            ->orWhere('first_name', 'like', "%{$word}%")
                            ->orWhere('middle_name', 'like', "%{$word}%")
                            ->orWhere('barangay', 'like', "%{$word}%"));
                    }
                })
                ->orWhereHas('familyMembers', fn ($m) => $m->where('name', 'like', "%{$search}%"))
            ))
            // Only the members that matched, for the "Family Head of …" line.
            ->when($search !== '', fn ($query) => $query->with([
                'familyMembers' => fn ($m) => $m->where('name', 'like', "%{$search}%"),
            ]))
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
            'contact_number'         => ['required', 'digits:11', 'regex:/^09\d{9}$/'],
            'category'               => 'nullable|string|max:60',
            'client_relationship'    => 'nullable|string|max:50',
            'family_head_id'         => 'nullable|integer|exists:clients,id',

            // A. Source of information / referring party — blank when the
            // client came in personally.
            'referrer_name'                   => 'nullable|string|max:150',
            'referrer_civil_status'           => 'nullable|string|max:50',
            'referrer_sex'                    => 'nullable|in:Male,Female',
            'referrer_date_of_birth'          => 'nullable|date|before_or_equal:today',
            'referrer_birth_place'            => 'nullable|string|max:150',
            'referrer_religion'               => 'nullable|string|max:60',
            'referrer_educational_attainment' => 'nullable|string|max:80',
            'referrer_occupation'             => 'nullable|string|max:100',
            'referrer_address'                => 'nullable|string|max:255',
            'referrer_income'                 => 'nullable|numeric|min:0|max:9999999999',
            'referrer_contact_number'         => ['nullable', 'digits:11', 'regex:/^09\d{9}$/'],
            'referrer_relationship'           => 'nullable|string|max:50',

            // B. Family composition. Rows left without a name are dropped.
            'family'                          => 'nullable|array|max:30',
            'family.*.name'                   => 'nullable|string|max:150',
            'family.*.age'                    => 'nullable|integer|min:0|max:130',
            'family.*.sex'                    => 'nullable|in:Male,Female',
            'family.*.civil_status'           => 'nullable|string|max:50',
            'family.*.birth_date'             => 'nullable|date|before_or_equal:today',
            'family.*.relationship'           => 'nullable|string|max:50',
            'family.*.educational_attainment' => 'nullable|string|max:80',
            'family.*.occupation'             => 'nullable|string|max:100',
            'family.*.monthly_income'         => 'nullable|numeric|min:0|max:9999999999',

            // C. Additional income
            'employment_nature'      => 'nullable|in:' . implode(',', Client::EMPLOYMENT_NATURES),
            'housing_ownership'      => 'nullable|string|max:30',
            'housing_structure'      => 'nullable|string|max:30',
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
            $row = $this->zeroIncomeIfNone($row);
            $client->familyMembers()->create(array_merge(
                array_map(fn ($v) => $v === '' ? null : $v, $row),
                ['name' => mb_convert_case(trim($row['name']), MB_CASE_TITLE, 'UTF-8')]
            ));
        }
    }

    /** Unemployed and Student earn nothing: income is stored as 0 whatever was sent. */
    private function zeroIncomeIfNone(array $data): array
    {
        if (in_array($data['occupation'] ?? null, Client::NO_INCOME_OCCUPATIONS, true)) {
            $data['monthly_income'] = 0;
        }
        if (in_array($data['referrer_occupation'] ?? null, Client::NO_INCOME_OCCUPATIONS, true)) {
            $data['referrer_income'] = 0;
        }

        return $data;
    }

    /**
     * Find a Family Head for the intake form, by name or client ID. Returns
     * what the household shares, so the form can fill it in.
     */
    public function lookup(Request $request)
    {
        $q = trim((string) $request->get('q'));
        if (mb_strlen($q) < 2 && ! ctype_digit($q)) {
            return response()->json([]);
        }

        $clients = Client::with('familyMembers')
            ->where(function ($query) use ($q) {
                if (ctype_digit($q)) {
                    $query->orWhere('id', (int) $q);
                }
                $query->orWhere(function ($name) use ($q) {
                    foreach (preg_split('/\s+/', $q) as $word) {
                        $name->where(fn ($w) => $w->where('first_name', 'like', "%{$word}%")
                            ->orWhere('last_name', 'like', "%{$word}%")
                            ->orWhere('middle_name', 'like', "%{$word}%"));
                    }
                });
            })
            ->orderBy('last_name')
            ->limit(10)
            ->get();

        return response()->json($clients->map(fn (Client $c) => [
            'id'                     => $c->id,
            'name'                   => $c->full_name,
            'barangay'               => $c->barangay,
            'purok'                  => $c->purok,
            'sex'                    => $c->sex,
            'civil_status'           => $c->civil_status,
            'birth_date'             => $c->date_of_birth?->format('Y-m-d'),
            'educational_attainment' => $c->educational_attainment,
            'occupation'             => $c->occupation,
            'monthly_income'         => $c->monthly_income,
            'employment_nature'      => $c->employment_nature,
            'housing_ownership'      => $c->housing_ownership,
            'housing_structure'      => $c->housing_structure,
            'water_source'           => $c->water_source,
            'toilet'                 => $c->toilet,
            'light'                  => $c->light,
            'members'                => $c->familyMembers->map(fn ($m) => [
                'name'                   => $m->name,
                'birth_date'             => $m->birth_date?->format('Y-m-d'),
                'age'                    => $m->current_age,
                'sex'                    => $m->sex,
                'civil_status'           => $m->civil_status,
                'relationship'           => $m->relationship,
                'educational_attainment' => $m->educational_attainment,
                'occupation'             => $m->occupation,
                'monthly_income'         => $m->monthly_income,
            ])->values(),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->zeroIncomeIfNone($request->validate($this->rules()));
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

        // Family ledger: every request filed by anyone in this household — the
        // Family Head and every client linked to them — so the assistance the
        // family has received can be read in one place.
        $householdIds = $client->householdIds();
        $ledger = AssistanceRequest::with(['client', 'disbursement'])
            ->whereIn('client_id', $householdIds)
            ->latest()
            ->get();
        $ledgerByType = $ledger->where('status', '!=', 'Rejected')
            ->groupBy('assistance_type')
            ->map(fn ($rows) => [
                'count'    => $rows->count(),
                'released' => (float) $rows->sum(fn ($r) => $r->disbursement->amount_released ?? 0),
                'last'     => $rows->max('created_at'),
            ]);
        $household = Client::whereIn('id', $householdIds)->orderBy('id')->get();
        $client->loadMissing('familyHead');

        return view('mswdo.beneficiaries.show', compact(
            'client', 'requests', 'byCategory', 'stage', 'stageCounts',
            'ledger', 'ledgerByType', 'household'
        ));
    }

    /**
     * The household around a beneficiary, for the request form: who is in
     * their Family Composition, and what assistance anyone in that household
     * has already received — so the same family is not helped twice for the
     * same need without the clerk noticing.
     *
     * Household members are typed names, not linked records, so a member is
     * matched to a registered beneficiary by name. That is a "possible" match:
     * namesakes and spelling differences both happen, which is why the form
     * only warns and leaves the decision to the clerk.
     */
    public function household(Client $client)
    {
        $client->load('familyMembers');

        $members = $client->familyMembers->map(fn ($m) => [
            'name'         => $m->name,
            'relationship' => $m->relationship,
            'age'          => $m->current_age,
            'matches'      => $this->registeredNamed($m->name, $client->id)
                ->map(fn (Client $c) => $this->historyOf($c))
                ->filter(fn ($c) => count($c['requests']) > 0)
                ->values(),
        ]);

        // The other direction: this beneficiary written into someone else's
        // Family Composition, e.g. a son filing on his own after his father did.
        $listedIn = FamilyMember::with('client')
            ->where('client_id', '!=', $client->id)
            ->where('name', 'like', '%' . trim($client->first_name) . '%')
            ->where('name', 'like', '%' . trim($client->last_name) . '%')
            ->get()
            ->filter(fn ($m) => $m->client)
            ->map(fn ($m) => array_merge($this->historyOf($m->client), [
                'relationship' => $m->relationship,
            ]))
            ->values();

        // Registered clients linked to the same Family Head — an exact match,
        // unlike the name matching above. Their history counts towards the
        // household's 3-month rule.
        $linked = Client::whereIn('id', array_diff($client->householdIds(), [$client->id]))
            ->get()
            ->map(fn (Client $c) => $this->historyOf($c))
            ->values();

        return response()->json([
            'members'   => $members,
            'listed_in' => $listedIn,
            'linked'    => $linked,
        ]);
    }

    /** Registered beneficiaries whose first and last name both appear in $name. */
    private function registeredNamed(string $name, int $exceptId)
    {
        $lower = mb_strtolower($name, 'UTF-8');
        $first = strtok($lower, ' ');

        return Client::where('id', '!=', $exceptId)
            ->where('first_name', 'like', '%' . $first . '%')
            ->get()
            ->filter(fn (Client $c) => str_contains($lower, mb_strtolower(trim($c->first_name), 'UTF-8'))
                && str_contains($lower, mb_strtolower(trim($c->last_name), 'UTF-8')));
    }

    private function historyOf(Client $c): array
    {
        return [
            'name'     => $c->full_name,
            'barangay' => $c->barangay,
            'url'      => route('mswdo.beneficiaries.show', $c),
            'requests' => $c->assistanceRequests()->latest()->limit(5)->get()->map(fn ($r) => [
                'type'   => $r->assistance_type,
                'status' => $r->status,
                'amount' => number_format((float) $r->amount_requested, 2),
                'filed'  => $r->created_at?->format('M d, Y'),
            ])->all(),
        ];
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
        $data = $this->zeroIncomeIfNone($request->validate($this->rules()));
        $family = $data['family'] ?? [];
        unset($data['family']);

        // A client cannot be their own Family Head.
        if ((int) ($data['family_head_id'] ?? 0) === $client->id) {
            $data['family_head_id'] = null;
        }

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
