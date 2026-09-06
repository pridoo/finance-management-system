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
    <a href="{{ route('mswdo.beneficiaries.index') }}" class="btn-secondary shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to list
    </a>
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
                        'Sex'           => $client->sex,
                        'Civil Status'  => $client->civil_status,
                    ],
                    'Contact & Location' => [
                        'Contact'  => $client->contact_number ?: '—',
                        'Barangay' => $client->barangay,
                        'Address'  => $client->address,
                    ],
                    'Record History' => [
                        'Registered On' => $client->created_at?->format('F d, Y, g:i A'),
                        'Last Updated'  => $client->updated_at?->format('F d, Y, g:i A'),
                    ],
                ];
                // Long free text needs the full width; a wrapped address in a
                // half-column reads as a broken paragraph.
                $fullWidth = ['Address'];
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
                    @forelse ($client->assistanceRequests as $req)
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
                                <p class="mt-3 text-base font-semibold text-gray-700">No assistance records yet</p>
                                <p class="mt-1 text-sm text-gray-500">Assistance requests for this beneficiary will appear here.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('mswdo.partials.request-detail-modal')

@endsection
