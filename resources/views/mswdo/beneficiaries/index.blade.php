@extends('mswdo.layouts.app')
@section('title', 'Beneficiaries')
@section('page-title', 'Beneficiary Records')

@section('content')

{{-- The primary action sits beside the page title, which the layout renders. --}}
@section('page-actions')
    <a href="{{ route('mswdo.beneficiaries.create') }}" class="btn-primary shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Add Client
    </a>
@endsection

<div class="panel">

    {{-- Record count. It was previously only discoverable by scrolling to the
         paginator at the very bottom of the table. --}}
    <div class="flex flex-wrap items-center gap-2 px-5 pt-4">
        <h2 class="panel-title">Registered Beneficiaries</h2>
        <span class="pill bg-teal-50 text-teal-800">
            {{ number_format($clients->total()) }}
            {{ $clients->total() === 1 ? 'record' : 'records' }}
        </span>
        @if(request()->filled('search') || request()->filled('barangay'))
            <span class="pill bg-yellow-50 text-yellow-800">filtered</span>
        @endif
    </div>

    {{-- Search and filter --}}
    <div class="toolbar">
        {{-- The search box is the primary control and should dominate: it was
             being squeezed to a stub while the barangay filter — a short,
             fixed list — stretched across most of the bar. flex-1 on the input
             and a fixed width on the select puts the emphasis back. --}}
        <form method="GET" action="{{ route('mswdo.beneficiaries.index') }}" class="flex flex-col sm:flex-row gap-2 flex-1 min-w-0">
            {{-- The magnifier sits inside the field rather than only on the
                 button, so the box is recognisable as a search box before it
                 is read. --}}
            <div class="relative flex-1 min-w-0">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search by beneficiary or family member name..."
                       class="form-control w-full pl-10 py-2.5">
            </div>
            <select name="barangay" class="form-control w-full sm:w-44 shrink-0 py-2.5" aria-label="Filter by barangay">
                <option value="">All barangays</option>
                @foreach($barangays as $barangay)
                    <option value="{{ $barangay }}" @selected(request('barangay') === $barangay)>{{ $barangay }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary shrink-0 py-2.5">Search</button>
            @if(request()->filled('search') || request()->filled('barangay'))
                <a href="{{ route('mswdo.beneficiaries.index') }}" class="btn-secondary shrink-0 py-2.5">Clear</a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-base">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-600 uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3.5 text-left">#</th>
                    <th class="px-5 py-3 text-left">Full Name</th>
                    <th class="px-5 py-3 text-left">Barangay</th>
                    <th class="px-5 py-3 text-left">Contact</th>
                    <th class="px-5 py-3 text-left">Assistance History</th>
                    <th class="px-5 py-3 text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($clients as $client)
                    @php
                        $count = $client->assistance_requests_count ?? $client->assistanceRequests()->count();
                        // Initials for the row avatar, same treatment as the
                        // signed-in user in the sidebar.
                        $initials = collect(preg_split('/\s+/', trim($client->full_name)))
                            ->filter()->take(2)
                            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                            ->implode('');
                    @endphp
                    <tr class="hover:bg-teal-50/50 transition-colors">
                        <td class="px-5 py-4 text-gray-500 text-sm tabular-nums">{{ $clients->firstItem() + $loop->index }}</td>
                        <td class="px-5 py-4">
                            {{-- Name and barangay travel together: the avatar
                                 gives the row a fixed anchor to scan down, and
                                 the two lines make each record easier to pick
                                 out than a wall of same-weight text. --}}
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-teal-100 text-xs font-extrabold text-teal-800" aria-hidden="true">{{ $initials }}</span>
                                <span class="min-w-0">
                                    <span class="block font-semibold text-gray-900">{{ $client->full_name }}</span>
                                    {{-- Matched through the household rather than
                                         the client's own name: say who matched, so
                                         the clerk sees this is the Family Head of
                                         the person searched for. --}}
                                    @if ($client->relationLoaded('familyMembers') && $client->familyMembers->isNotEmpty())
                                        <span class="mt-0.5 block text-xs font-medium text-amber-800">
                                            Family Head of
                                            {{ $client->familyMembers->map(fn ($m) => $m->name . ($m->relationship ? ' (' . $m->relationship . ')' : ''))->join(', ') }}
                                        </span>
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-gray-700">{{ $client->barangay }}</td>
                        <td class="px-5 py-4 text-gray-700 tabular-nums">{{ $client->contact_number ?? '—' }}</td>
                        <td class="px-5 py-4">
                            {{-- A beneficiary with no history reads grey rather
                                 than green: "0 requests" is not an achievement
                                 and should not look like one. --}}
                            <span class="pill {{ $count > 0 ? 'bg-teal-50 text-teal-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $count }} {{ $count === 1 ? 'request' : 'requests' }}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            {{-- Icon *and* word. A bare icon is a guessing game
                                 for anyone who has not used this before, and the
                                 old text links were 12px — too small a target to
                                 hit reliably. These are full buttons. --}}
                            <div class="flex items-center gap-2">
                                <a href="{{ route('mswdo.beneficiaries.show', $client) }}" title="View profile" class="btn-soft">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    View
                                </a>
                                <a href="{{ route('mswdo.beneficiaries.edit', $client) }}" title="Edit beneficiary" class="btn-soft-plain">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Edit
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                @if(request('search') || request('barangay'))
                                    <p>No beneficiaries match this search.</p>
                                    <a href="{{ route('mswdo.beneficiaries.index') }}" class="btn-soft mt-1">Clear filters</a>
                                @else
                                    <p>No beneficiaries registered yet.</p>
                                    <a href="{{ route('mswdo.beneficiaries.create') }}" class="btn-primary mt-1">Add the first beneficiary</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($clients->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $clients->links() }}
        </div>
    @endif
</div>

@endsection
