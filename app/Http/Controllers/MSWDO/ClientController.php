<?php

namespace App\Http\Controllers\MSWDO;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $data = $request->validate([
            'last_name'      => 'required|string|max:100',
            'first_name'     => 'required|string|max:100',
            'middle_name'    => 'nullable|string|max:100',
            'suffix'         => 'nullable|string|max:20',
            'date_of_birth'  => 'required|date|before_or_equal:today',
            'sex'            => 'required|in:Male,Female',
            'civil_status'   => 'required|string|max:50',
            'address'        => 'required|string',
            'barangay'       => 'required|string|max:100',
            // Exactly 11 digits starting 09. The model's mutator has already
            // rewritten +63/63 prefixes into this shape before storage.
            'contact_number' => ['nullable', 'digits:11', 'regex:/^09\d{9}$/'],
        ]);

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

        $client = Client::create($data);
        AuditLog::record("Created client: {$client->full_name}", 'Beneficiaries', $client->id);

        return redirect()->route('mswdo.beneficiaries.show', $client)
            ->with('success', 'Beneficiary registered successfully.');
    }

    public function show(Client $client)
    {
        $client->load(['assistanceRequests.approval', 'assistanceRequests.disbursement']);
        return view('mswdo.beneficiaries.show', compact('client'));
    }

    public function edit(Client $client)
    {
        return view('mswdo.beneficiaries.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'last_name'      => 'required|string|max:100',
            'first_name'     => 'required|string|max:100',
            'middle_name'    => 'nullable|string|max:100',
            'suffix'         => 'nullable|string|max:20',
            'date_of_birth'  => 'required|date|before_or_equal:today',
            'sex'            => 'required|in:Male,Female',
            'civil_status'   => 'required|string|max:50',
            'address'        => 'required|string',
            'barangay'       => 'required|string|max:100',
            // Exactly 11 digits starting 09. The model's mutator has already
            // rewritten +63/63 prefixes into this shape before storage.
            'contact_number' => ['nullable', 'digits:11', 'regex:/^09\d{9}$/'],
        ]);

        if ($duplicate = $this->findDuplicate($data, $client)) {
            return back()->withInput()->with('duplicate_beneficiary', [
                'name' => $duplicate->full_name,
                'url' => route('mswdo.beneficiaries.show', $duplicate),
            ]);
        }

        $client->update($data);
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
