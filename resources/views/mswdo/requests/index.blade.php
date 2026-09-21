@extends('mswdo.layouts.app')
@section('title', 'Assistance Requests')
@section('page-title', 'Assistance Requests')

@section('content')

@section('page-actions')
    <a href="{{ route('mswdo.requests.create') }}" class="btn-primary shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Request
    </a>
@endsection

<div class="panel">

    <div class="flex flex-wrap items-center gap-2 px-5 pt-4">
        <h2 class="panel-title">Requests</h2>
        <span class="pill bg-teal-50 text-teal-800">
            {{ number_format($requests->total()) }}
            {{ $requests->total() === 1 ? 'record' : 'records' }}
        </span>
    </div>

    {{-- Status tabs --}}
    <div class="toolbar">
        <div class="flex flex-col gap-2 w-full">

            {{-- Status pill tabs --}}
            <div class="flex flex-wrap gap-1.5">
                @php
                    $statuses = ['', 'Pending', 'Under Review', 'Approved', 'Rejected', 'For Release', 'Released'];
                    $labels   = ['' => 'All', 'Pending' => 'Pending', 'Under Review' => 'Under Review',
                                 'Approved' => 'Approved', 'Rejected' => 'Rejected',
                                 'For Release' => 'For Release', 'Released' => 'Released'];
                    $current  = request('status', '');
                    $currentBarangay = request('barangay', '');
                @endphp
                @foreach ($statuses as $s)
                    {{-- The selected filter is marked by weight and a ring as
                         well as by fill, so it stays obvious which subset of
                         the table is on screen. The barangay is carried along,
                         so changing status keeps the place you were looking at. --}}
                    <a href="{{ route('mswdo.requests.index', array_filter([
                            'status'   => $s,
                            'barangay' => $currentBarangay,
                       ])) }}"
                       @if($current === $s) aria-current="true" @endif
                       class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs transition-all duration-150
                              {{ $current === $s
                                  ? 'bg-teal-700 text-white font-bold shadow-sm ring-2 ring-yellow-300 ring-offset-1'
                                  : 'bg-gray-100 text-gray-700 font-semibold hover:bg-teal-50 hover:text-teal-800' }}">
                        {{ $labels[$s] }}
                    </a>
                @endforeach
            </div>

            {{-- Barangay. "All barangays" is the default, so the page opens on
                 the whole municipality and narrows only when asked. --}}
            <form method="GET" action="{{ route('mswdo.requests.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="status" value="{{ $current }}">
                <label for="req-barangay" class="text-xs font-bold uppercase tracking-wide text-gray-500 whitespace-nowrap">
                    Barangay
                </label>
                <select id="req-barangay" name="barangay" onchange="this.form.submit()"
                        class="rounded-xl border border-[#ece7dc] bg-white px-3 py-1.5 text-xs font-bold text-gray-900 transition hover:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $b)
                        <option value="{{ $b }}" @selected($currentBarangay === $b)>{{ $b }}</option>
                    @endforeach
                </select>
                @if ($currentBarangay)
                    <a href="{{ route('mswdo.requests.index', array_filter(['status' => $current])) }}"
                       class="text-xs font-bold text-gray-600 underline underline-offset-2 transition hover:text-gray-900">
                        Clear
                    </a>
                @endif
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3 text-left">#</th>
                    <th class="px-5 py-3 text-left">Beneficiary</th>
                    <th class="px-5 py-3 text-left">Type</th>
                    <th class="px-5 py-3 text-left">Amount</th>
                    <th class="px-5 py-3 text-left">Status</th>
                    <th class="px-5 py-3 text-left">Date</th>
                    <th class="px-5 py-3 text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($requests as $req)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5 text-gray-400 text-xs tabular-nums">{{ $requests->firstItem() + $loop->index }}</td>
                        <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $req->client->full_name }}</td>
                        <td class="px-5 py-3.5">
                            {{-- The assistance type is a category, not prose;
                                 as a quiet chip it stops competing with the
                                 name and the amount either side of it. --}}
                            <span class="pill bg-gray-100 text-gray-700 font-semibold">{{ $req->assistance_type }}</span>
                        </td>
                        {{-- The amount is one of the two figures this table
                             exists for, so it is set in the same weight as the
                             name rather than in muted grey. --}}
                        <td class="px-5 py-3.5 font-semibold text-gray-900 tabular-nums">₱ {{ number_format($req->amount_requested, 2) }}</td>
                        <td class="px-5 py-3.5">
                            @php
                                // Matches the dashboard exactly: same status, same badge,
                                // wherever it appears in the portal.
                                $badge = match($req->status) {
                                    'Approved'     => 'bg-green-100 text-green-800 ring-green-300',
                                    'Rejected'     => 'bg-red-100 text-red-800 ring-red-300',
                                    'Released'     => 'bg-teal-100 text-teal-800 ring-teal-300',
                                    'For Release'  => 'bg-lime-100 text-lime-800 ring-lime-300',
                                    'Pending'      => 'bg-yellow-100 text-yellow-800 ring-yellow-300',
                                    'Under Review' => 'bg-amber-100 text-amber-800 ring-amber-300',
                                    default        => 'bg-gray-100 text-gray-700 ring-gray-300',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold ring-1 ring-inset {{ $badge }}">
                                {{ $req->status }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap">{{ $req->created_at->format('M d, Y') }}</td>
                        <td class="px-5 py-3.5">
                            {{-- Matches the Beneficiaries table: icon and word
                                 in a real button. The old 12px text link was
                                 too small a target to hit reliably. --}}
                            {{-- One control, not two. The modal carries a link
                                 through to the full page, so a separate "Open"
                                 button was the same destination twice. --}}
                            <button type="button"
                                    data-approval-view="{{ route('mswdo.approvals.show', $req) }}"
                                    data-page-url="{{ route('mswdo.requests.show', $req) }}"
                                    title="View request details"
                                    class="btn-soft">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                View
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                @if($current || $currentBarangay)
                                    {{-- Naming both filters matters: with only
                                         one mentioned, the other looks like it
                                         is not being applied. --}}
                                    <p>
                                        No requests
                                        @if($current) with the status “{{ $current }}” @endif
                                        @if($current && $currentBarangay) in @endif
                                        @if($currentBarangay) from {{ $currentBarangay }} @endif
                                    </p>
                                    <a href="{{ route('mswdo.requests.index') }}" class="btn-soft mt-1">Show all requests</a>
                                @else
                                    <p>No assistance requests filed yet.</p>
                                    <a href="{{ route('mswdo.requests.create') }}" class="btn-primary mt-1">File the first request</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- The count shows even on a single page: "25 of 25" is what tells the
         clerk there may be more behind a filter. --}}
    @if($requests->total() > 0)
        <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-gray-500">
                Showing <span class="font-semibold text-gray-700">{{ $requests->firstItem() }}</span>–<span
                    class="font-semibold text-gray-700">{{ $requests->lastItem() }}</span>
                of <span class="font-semibold text-gray-700">{{ $requests->total() }}</span> requests
            </p>
            @if($requests->hasPages())
                <div>{{ $requests->links() }}</div>
            @endif
        </div>
    @endif
</div>

@include('mswdo.partials.request-detail-modal')

@endsection
