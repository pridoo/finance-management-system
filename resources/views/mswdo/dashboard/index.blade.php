@extends('mswdo.layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard Overview')

@section('content')

{{-- Fiscal year picker, beside the page title. Mirrors Budget Tracking so a
     past year's allocations and releases can be reviewed rather than only the
     current one. --}}
@section('page-actions')
    <form method="GET" action="{{ route('mswdo.dashboard') }}" class="flex items-center gap-2">
        <label for="dash-year" class="text-xs font-bold uppercase tracking-wide text-gray-500 whitespace-nowrap">Fiscal Year</label>
        <select id="dash-year" name="year" onchange="this.form.submit()"
                class="rounded-xl border border-[#ece7dc] bg-white px-3 py-2 text-sm font-bold text-gray-900 transition hover:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
            @foreach ($years as $option)
                <option value="{{ $option }}" @selected($year === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </form>
@endsection

@unless($isCurrentYear)
    {{-- Saying so plainly stops a past year's figures being read as today's,
         which is the whole risk of a year picker. --}}
    <div class="mb-5 flex items-center gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
        <svg class="w-5 h-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        You are viewing the past fiscal year {{ $year }} — these are not current figures.
    </div>
@endunless

{{-- Stat cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

    @php
        $cards = [
            ['label' => 'Total Beneficiaries', 'value' => number_format($totalClients),                          'color' => 'teal',   'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['label' => 'Active Requests',     'value' => number_format($activeRequests),                        'color' => 'amber',  'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            // "This month" only means something for the current year; on a past
            // year the month is arbitrary, so the whole year is shown instead.
            ['label' => $isCurrentYear ? 'Released This Month' : 'Released in ' . $year, 'value' => '₱ ' . number_format($releasedTotal, 2),          'color' => 'green',  'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Budget Remaining',    'value' => '₱ ' . number_format($budgetRemaining, 2),            'color' => 'blue',   'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ];
        // Each card carries a coloured rule along its top edge as well as a
        // tinted icon, so the four are told apart by position and shape rather
        // than by hue alone. Text tones are the dark steps throughout: these
        // are the figures the page exists to show, and they have to stay
        // readable on a washed-out monitor.
        $colorMap = [
            'teal'  => ['bg' => 'bg-teal-50',   'icon' => 'text-teal-700',   'value' => 'text-teal-800',   'rule' => 'from-teal-500 to-teal-600'],
            'amber' => ['bg' => 'bg-amber-50',  'icon' => 'text-amber-700',  'value' => 'text-amber-800',  'rule' => 'from-amber-400 to-amber-500'],
            'green' => ['bg' => 'bg-green-50',  'icon' => 'text-green-700',  'value' => 'text-green-800',  'rule' => 'from-green-500 to-green-600'],
            'blue'  => ['bg' => 'bg-blue-50',   'icon' => 'text-blue-700',   'value' => 'text-blue-800',   'rule' => 'from-yellow-400 to-yellow-500'],
        ];
    @endphp

    @foreach ($cards as $card)
        @php $c = $colorMap[$card['color']]; @endphp
        <div class="panel group relative p-5 flex items-center gap-4 transition-shadow duration-200 hover:shadow-lg">
            <span class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $c['rule'] }}" aria-hidden="true"></span>
            <div class="w-12 h-12 rounded-xl {{ $c['bg'] }} flex items-center justify-center shrink-0 transition-transform duration-200 group-hover:scale-105">
                <svg class="w-6 h-6 {{ $c['icon'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">{{ $card['label'] }}</p>
                <p class="mt-0.5 text-2xl font-bold tabular-nums {{ $c['value'] }}">{{ $card['value'] }}</p>
            </div>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

    {{-- Recent Requests --}}
    <div class="lg:col-span-3 panel">
        <div class="panel-head">
            {{-- The list is filtered to the selected year, so the heading has
                 to say which — otherwise a past year's rows read as current. --}}
            <h3 class="panel-title">Recent Assistance Requests ({{ $year }})</h3>
            {{-- Was 12px plain text. Now a real target with an arrow, which is
                 both easier to hit and easier to recognise as a link. --}}
            <a href="{{ route('mswdo.requests.index') }}" class="btn-soft shrink-0">
                View all
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Beneficiary</th>
                        <th class="px-5 py-3 text-left">Type</th>
                        <th class="px-5 py-3 text-left">Amount</th>
                        <th class="px-5 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recentRequests as $req)
                        <tr class="hover:bg-gray-50 transition-colors">
                            {{-- Darkened from gray-600/800: these are the
                                 figures the page exists to show, and at this
                                 size the muted greys were the first thing to
                                 go illegible on an older monitor. --}}
                            <td class="px-5 py-3 font-semibold text-gray-900">{{ $req->client->full_name }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $req->assistance_type }}</td>
                            <td class="px-5 py-3 font-semibold text-gray-900 tabular-nums">₱ {{ number_format($req->amount_requested, 2) }}</td>
                            <td class="px-5 py-3">
                                @php
                                    // A ring as well as a fill. Flat pastel pills blur together at
                                    // a glance down a status column; the outline gives each badge a
                                    // defined edge, and the darker text keeps every one legible.
                                    $statusColor = match($req->status) {
                                        'Approved'    => 'bg-green-100 text-green-800 ring-green-300',
                                        'Rejected'    => 'bg-red-100 text-red-800 ring-red-300',
                                        'Released'    => 'bg-teal-100 text-teal-800 ring-teal-300',
                                        'For Release' => 'bg-lime-100 text-lime-800 ring-lime-300',
                                        'Pending'     => 'bg-yellow-100 text-yellow-800 ring-yellow-300',
                                        'Under Review'=> 'bg-amber-100 text-amber-800 ring-amber-300',
                                        default       => 'bg-gray-100 text-gray-700 ring-gray-300',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold ring-1 ring-inset {{ $statusColor }}">
                                    {{ $req->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <p>No assistance requests filed in {{ $year }}.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Budget Utilization --}}
    <div class="lg:col-span-2 panel p-5">
        <h3 class="panel-title is-accent mb-4">Budget Utilization ({{ $year }})</h3>
        @php
            // Walked through green into yellow rather than five unrelated hues,
            // so the set reads as one chart. Each step is distinguishable from
            // its neighbour in lightness as well as hue, which is what keeps
            // them apart for a reader who does not separate green from yellow.
            $barColors = [
                'bg-teal-600',
                'bg-teal-400',
                'bg-lime-500',
                'bg-yellow-400',
                'bg-amber-500',
            ];
        @endphp
        @forelse ($budgetUtilization as $i => $b)
            <div class="mb-4">
                <div class="flex justify-between text-xs mb-1.5">
                    <span class="font-medium text-gray-700">{{ $b['type'] }}</span>
                    {{-- Darker than the old grey-500: at 11px on white these
                         were the hardest numbers on the page to read. --}}
                    <span class="font-semibold text-gray-700 tabular-nums">{{ $b['percent'] }}%</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    {{-- 'bar' rather than 'percent': a ₱2,000 draw against ₱9M
                         is 0.02%, which rounds to a bar of zero width and reads
                         as "nothing released" when money has in fact moved. --}}
                    <div class="h-2 {{ $barColors[$i % count($barColors)] }} rounded-full transition-all"
                         style="width: {{ $b['bar'] }}%"></div>
                </div>
                <p class="text-xs text-gray-600 mt-1.5 tabular-nums">
                    <span class="font-semibold text-gray-800">₱ {{ number_format($b['released'], 2) }}</span>
                    <span class="text-gray-400">/</span>
                    ₱ {{ number_format($b['allocated'], 2) }}
                </p>
            </div>
        @empty
            <p class="text-sm text-gray-500 text-center py-6">No budget records for {{ $year }}.</p>
        @endforelse
    </div>

</div>

@endsection
