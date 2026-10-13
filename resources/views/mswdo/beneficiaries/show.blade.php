@extends('mswdo.layouts.app')
@section('title', $client->full_name)
@section('page-title', 'Beneficiary Profile')

@section('breadcrumb')
    <a href="{{ route('mswdo.dashboard') }}">Home</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="{{ route('mswdo.beneficiaries.index') }}">Beneficiaries</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current">{{ $client->full_name }}</span>
@endsection

@section('page-actions')
    <div class="flex flex-wrap gap-2">
    <a href="{{ route('mswdo.beneficiaries.intake-pdf', $client) }}" target="_blank" rel="noopener" class="btn-soft shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        Print intake form
    </a>
    <a href="{{ route('mswdo.beneficiaries.index') }}" class="btn-secondary shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to list
    </a>
    </div>
@endsection

@section('content')

{{-- 2:3 rather than 1:2. The profile holds twelve fields and was squeezed into
     a third of the width while the history table — usually near-empty — kept
     two thirds. Widening the left column is what lets the fields sit in pairs
     instead of one long stack. --}}
<div class="grid grid-cols-1 lg:grid-cols-5 gap-5">

    {{-- Profile card --}}
    <div class="lg:col-span-2 panel">

        <div class="panel-head">
            <h3 class="panel-title">Profile</h3>
            {{-- Icon plus label, not an icon alone: an unlabelled pencil is a
                 guess for anyone who does not already know this software. --}}
            <a href="{{ route('mswdo.beneficiaries.edit', $client) }}" class="btn-soft">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
            </a>
        </div>

        <div class="px-6 py-5">
            <div class="flex items-center gap-4 pb-5 border-b border-gray-100">
                <div class="w-16 h-16 shrink-0 bg-gradient-to-br from-teal-600 to-teal-700 text-white rounded-2xl flex items-center justify-center text-2xl font-extrabold shadow-sm">
                    {{ strtoupper(substr(trim($client->first_name), 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <h2 class="text-xl font-bold text-gray-900 leading-tight">{{ $client->full_name }}</h2>
                    <p class="text-sm text-gray-600 mt-1 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $client->barangay }}
                    </p>
                </div>
            </div>

            {{-- Grouped into sections rather than one long list of twelve rows.
                 Unbroken, every field carried equal weight and the eye had no
                 landmarks, so finding the contact number meant reading all of
                 them. Two columns keep the pairs (first/middle, dates) beside
                 each other instead of stacked a screen apart. --}}
            @php
                $sections = [
                    'Personal Details' => [
                        'First Name'    => $client->first_name,
                        'Middle Name'   => $client->middle_name ?: '—',
                        'Last Name'     => $client->last_name,
                        'Suffix'        => $client->suffix ?: '—',
                        'Date of Birth' => $client->date_of_birth?->format('F d, Y'),
                        'Age'           => $client->date_of_birth?->age,
                        'Sex'           => $client->sex,
                        'Civil Status'  => $client->civil_status,
                        'Religion'      => $client->religion ?: '—',
                        'Birth Place'   => $client->birth_place ?: '—',
                        'Educational Attainment' => $client->educational_attainment ?: '—',
                        'Occupation'    => $client->occupation ?: '—',
                        'Income'        => $client->monthly_income !== null ? '₱ ' . number_format((float) $client->monthly_income, 2) . ' / month' : '—',
                        'Category'      => $client->category ?: '—',
                    ],
                    'Contact & Location' => [
                        'CP #'     => $client->contact_number ?: '—',
                        'Purok'    => $client->purok ?: '—',
                        'Barangay' => $client->barangay,
                        'Address'  => $client->address,
                    ],
                    'Record History' => [
                        'Intake Date'   => $client->intake_date?->format('F d, Y') ?? '—',
                        'Registered On' => $client->created_at?->format('F d, Y, g:i A'),
                        'Last Updated'  => $client->updated_at?->format('F d, Y, g:i A'),
                    ],
                ];
                // Long free text needs the full width; a wrapped address in a
                // half-column reads as a broken paragraph.
                $fullWidth = ['Address', 'Educational Attainment', 'Category'];
            @endphp

            @foreach ($sections as $heading => $fields)
                <section class="mt-6 first:mt-5">
                    <h4 class="text-[11px] font-bold uppercase tracking-[0.14em] text-teal-700">{{ $heading }}</h4>
                    <div class="mt-1 h-px bg-gray-100"></div>

                    {{-- The row gap has to clear the value AND the next label
                         under it. At the old spacing a value sat closer to the
                         label below than to its own, so "Test1 / LAST NAME"
                         read as one field. Each cell also gets a tinted panel
                         so the pairing is obvious even when scanning fast. --}}
                    <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
                        @foreach ($fields as $label => $value)
                            <div class="rounded-xl bg-gray-50/70 px-4 py-3 {{ in_array($label, $fullWidth) ? 'sm:col-span-2' : '' }}">
                                <dt class="text-[11px] font-bold uppercase tracking-[0.08em] text-gray-500">{{ $label }}</dt>
                                <dd class="mt-1 text-base font-medium leading-snug text-gray-900 wrap-break-word">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach

            <a href="{{ route('mswdo.requests.create', ['client_id' => $client->id]) }}"
               class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-3.5 text-base font-bold text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                New Assistance Request
            </a>
        </div>
    </div>

    {{-- Assistance history --}}
    <div class="lg:col-span-3 panel">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between gap-3">
            <h3 class="text-base font-bold text-gray-900">Assistance History</h3>
            @if ($client->assistanceRequests->isNotEmpty())
                <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-700">
                    {{ $client->assistanceRequests->count() }}
                    {{ Str::plural('record', $client->assistanceRequests->count()) }}
                </span>
            @endif
        </div>

        @if ($byCategory->isNotEmpty())
            {{-- By category. The table below lists every case in date order;
                 this says what the household has drawn and for what, which is
                 the question asked when a new request comes in. --}}
            <div class="border-b border-gray-100 bg-[#fbf9f4] px-6 py-4">
                <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-500">By Assistance Type</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($byCategory as $type => $totals)
                        <div class="rounded-xl border border-[#ece7dc] bg-white px-3.5 py-3">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-sm font-bold text-gray-900">{{ $type }}</span>
                                <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-bold text-gray-700 tabular-nums">
                                    {{ $totals['count'] }}
                                </span>
                            </div>
                            <p class="mt-1.5 text-sm font-bold tabular-nums text-teal-800">
                                ₱ {{ number_format($totals['released'], 2) }}
                                <span class="text-xs font-semibold text-gray-500">released</span>
                            </p>
                            <p class="text-xs tabular-nums text-gray-600">
                                of ₱ {{ number_format($totals['requested'], 2) }} requested
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($client->assistanceRequests->isNotEmpty())
            {{-- Pending vs released: the two states the office is actually
                 asked about. Counts sit on the tabs so an empty one is obvious
                 before it is clicked. --}}
            <div class="flex flex-wrap gap-1.5 border-b border-gray-100 px-6 py-3">
                @php
                    $stages = [
                        'all'      => 'All',
                        'pending'  => 'Pending',
                        'released' => 'Released',
                    ];
                @endphp
                @foreach ($stages as $key => $label)
                    <a href="{{ route('mswdo.beneficiaries.show', [$client, 'stage' => $key]) }}"
                       @if($stage === $key) aria-current="true" @endif
                       class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs transition-all duration-150
                              {{ $stage === $key
                                  ? 'bg-teal-700 text-white font-bold shadow-sm ring-2 ring-yellow-300 ring-offset-1'
                                  : 'bg-gray-100 text-gray-700 font-semibold hover:bg-teal-50 hover:text-teal-800' }}">
                        {{ $label }}
                        <span class="tabular-nums {{ $stage === $key ? 'text-teal-100' : 'text-gray-500' }}">
                            {{ $stageCounts[$key] }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="px-5 py-3.5 text-left">Date</th>
                        <th class="px-5 py-3.5 text-left">Type</th>
                        <th class="px-5 py-3.5 text-right">Requested</th>
                        <th class="px-5 py-3.5 text-right">Released</th>
                        <th class="px-5 py-3.5 text-left">Status</th>
                        <th class="px-5 py-3.5 text-right">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-base">
                    @forelse ($requests as $req)
                        <tr class="transition-colors hover:bg-teal-50/50">
                            <td class="px-5 py-4 text-gray-600 whitespace-nowrap">{{ $req->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-4 font-semibold text-gray-900">{{ $req->assistance_type }}</td>
                            {{-- Amounts right-aligned and tabular so the decimal
                                 points line up down the column. --}}
                            <td class="px-5 py-4 text-right text-gray-900 tabular-nums whitespace-nowrap">
                                ₱ {{ number_format($req->amount_requested, 2) }}
                            </td>
                            <td class="px-5 py-4 text-right text-gray-900 tabular-nums whitespace-nowrap">
                                {{ $req->disbursement ? '₱ ' . number_format($req->disbursement->amount_released, 2) : '—' }}
                            </td>
                            <td class="px-5 py-4">
                                @php
                                    $c = match($req->status) {
                                        'Approved'    => 'bg-green-100 text-green-800 border-green-300',
                                        'Rejected'    => 'bg-red-100 text-red-800 border-red-300',
                                        'Released'    => 'bg-teal-100 text-teal-800 border-teal-300',
                                        'For Release' => 'bg-lime-100 text-lime-800 border-lime-300',
                                        'Pending'     => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                        'Under Review'=> 'bg-amber-100 text-amber-800 border-amber-300',
                                        default       => 'bg-gray-100 text-gray-700 border-gray-300',
                                    };
                                @endphp
                                <span class="inline-block rounded-full border px-3 py-1 text-sm font-semibold whitespace-nowrap {{ $c }}">
                                    {{ $req->status }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                {{-- The row shows the outcome; this shows how
                                     the case got there — every step, its exact
                                     time, who recorded it and their remarks. --}}
                                <button type="button"
                                        data-approval-view="{{ route('mswdo.approvals.show', $req) }}"
                                        title="View full case history"
                                        aria-label="View full case history for this {{ $req->assistance_type }} request"
                                        class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white p-2 text-gray-600 transition hover:bg-teal-50 hover:text-teal-700 hover:border-teal-200 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-16 text-center">
                                <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                @if ($stage === 'all')
                                    <p class="mt-3 text-base font-semibold text-gray-700">No assistance records yet</p>
                                    <p class="mt-1 text-sm text-gray-500">Assistance requests for this beneficiary will appear here.</p>
                                @else
                                    {{-- The records exist, just not in this
                                         stage — saying so stops the tab reading
                                         as "this beneficiary has nothing". --}}
                                    <p class="mt-3 text-base font-semibold text-gray-700">
                                        No {{ $stage }} records
                                    </p>
                                    <a href="{{ route('mswdo.beneficiaries.show', $client) }}" class="btn-soft mt-3">
                                        Show all records
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- The rest of the Family Intake Data Form, full width: the household table
     needs the room, and the case notes read better as paragraphs. --}}
<div class="panel mt-5" data-intake-details>
    <div class="panel-head">
        <h3 class="panel-title">Family Intake Details</h3>
        <a href="{{ route('mswdo.beneficiaries.edit', $client) }}" class="btn-soft">Edit</a>
    </div>

    <div class="px-6 py-5">
        <h4 class="intake-heading"><span>B</span> Family Composition</h4>
        @if ($client->familyMembers->isNotEmpty())
            <div class="overflow-x-auto rounded-xl border border-[#ece7dc]">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="px-4 py-2.5 text-left">Name</th>
                            <th class="px-4 py-2.5 text-left">Age</th>
                            <th class="px-4 py-2.5 text-left">Gender</th>
                            <th class="px-4 py-2.5 text-left">Civil Status</th>
                            <th class="px-4 py-2.5 text-left">Birth-date</th>
                            <th class="px-4 py-2.5 text-left">Relationship</th>
                            <th class="px-4 py-2.5 text-left">Educational Attainment</th>
                            <th class="px-4 py-2.5 text-left">Occupation / Income</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($client->familyMembers as $member)
                            <tr>
                                <td class="px-4 py-2.5 font-semibold text-gray-900">{{ $member->name }}</td>
                                <td class="px-4 py-2.5 tabular-nums">{{ $member->current_age ?? '—' }}</td>
                                <td class="px-4 py-2.5">{{ $member->sex ?? '—' }}</td>
                                <td class="px-4 py-2.5">{{ $member->civil_status ?? '—' }}</td>
                                <td class="px-4 py-2.5 whitespace-nowrap">{{ $member->birth_date?->format('M d, Y') ?? '—' }}</td>
                                <td class="px-4 py-2.5">{{ $member->relationship ?? '—' }}</td>
                                <td class="px-4 py-2.5">{{ $member->educational_attainment ?? '—' }}</td>
                                <td class="px-4 py-2.5">{{ $member->occupation_income ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600">No household members recorded yet.</p>
        @endif

        <h4 class="intake-heading mt-8"><span>C</span> Additional Income</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach ([
                'Nature of Employment' => $client->employment_nature,
                'Housing'              => $client->housing,
                'Potable Water'        => $client->water_source,
                'Toilet'               => $client->toilet,
                'Light'                => $client->light,
            ] as $label => $value)
                <div class="rounded-xl bg-gray-50/70 px-4 py-3">
                    <dt class="text-[11px] font-bold uppercase tracking-[0.08em] text-gray-500">{{ $label }}</dt>
                    <dd class="mt-1 text-base font-medium leading-snug text-gray-900">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-x-8">
            @foreach ([
                ['D', 'Problem Presented',      $client->problem_presented],
                ['E', 'Initial Assessment',     $client->initial_assessment],
                ['F', 'Recommendation',         $client->recommendation],
                ['G', 'Plan of Action / Taken', $client->plan_of_action],
            ] as [$letter, $label, $value])
                <section class="mb-6">
                    <h4 class="intake-heading"><span>{{ $letter }}</span> {{ $label }}</h4>
                    <p class="whitespace-pre-line text-base text-gray-900">{{ $value ?: '—' }}</p>
                </section>
            @endforeach
        </div>
    </div>
</div>

@include('mswdo.partials.request-detail-modal')

@endsection
