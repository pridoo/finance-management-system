@extends('mswdo.layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard Overview')

@section('content')

{{-- Fiscal year picker, beside the page title. Mirrors Budget Tracking so a
     past year's allocations and releases can be reviewed rather than only the
     current one. --}}
@section('page-actions')
    <form method="GET" action="{{ route('mswdo.dashboard') }}" class="flex items-center gap-2">
        {{-- The month filter survives a year change; the date range does not,
             since its days belong to the year being left behind. --}}
        <input type="hidden" name="month" value="{{ $month }}">
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
            <h3 class="panel-title">
                Recent Assistance Requests
                ({{ $month === 'all' ? $year : $months[(int) $month] . ' ' . $year }})
            </h3>
            <div class="flex items-center gap-2">
                {{-- Month narrows the list within the selected year. Submitting
                     on change keeps it to one action, and the year and range are
                     carried through so this filter never resets the others. --}}
                <form method="GET" action="{{ route('mswdo.dashboard') }}" class="flex items-center gap-2">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="from" value="{{ $from->format('Y-m-d') }}">
                    <input type="hidden" name="to" value="{{ $to->format('Y-m-d') }}">
                    <label for="dash-month" class="sr-only">Month</label>
                    <select id="dash-month" name="month" onchange="this.form.submit()"
                            class="rounded-xl border border-[#ece7dc] bg-white px-3 py-1.5 text-xs font-bold text-gray-900 transition hover:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                        <option value="all" @selected($month === 'all')>All months</option>
                        @foreach ($months as $number => $name)
                            <option value="{{ $number }}" @selected((string) $month === (string) $number)>{{ $name }}</option>
                        @endforeach
                    </select>
                </form>
                {{-- Was 12px plain text. Now a real target with an arrow, which is
                     both easier to hit and easier to recognise as a link. --}}
                <a href="{{ route('mswdo.requests.index') }}" class="btn-soft shrink-0">
                    View all
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
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
                                    <p>No assistance requests filed in {{ $month === 'all' ? $year : $months[(int) $month] . ' ' . $year }}.</p>
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
        <h3 class="panel-title is-accent mb-1">Budget Utilization</h3>
        {{-- The window has to be stated: "60% utilised" means something
             different over four months than over the whole year. --}}
        <p class="mb-3 text-xs font-semibold text-gray-600">
            {{ $isFullYear
                ? 'Full year ' . $year
                : $from->format('M j, Y') . ' — ' . $to->format('M j, Y') }}
        </p>

        {{-- From/to picker. Both ends are clamped into the selected fiscal year
             by the controller, so this can never describe a period the year
             picker says is not being shown. --}}
        <form method="GET" action="{{ route('mswdo.dashboard') }}" class="mb-4 rounded-xl border border-[#ece7dc] bg-[#fbf9f4] p-3">
            <input type="hidden" name="year" value="{{ $year }}">
            <input type="hidden" name="month" value="{{ $month }}">
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-0 flex-1">
                    <label for="util-from" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">From</label>
                    <input type="date" id="util-from" name="from" value="{{ $from->format('Y-m-d') }}"
                           min="{{ $year }}-01-01" max="{{ $year }}-12-31"
                           class="w-full rounded-lg border border-[#ece7dc] bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                </div>
                <div class="min-w-0 flex-1">
                    <label for="util-to" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">To</label>
                    <input type="date" id="util-to" name="to" value="{{ $to->format('Y-m-d') }}"
                           min="{{ $year }}-01-01" max="{{ $year }}-12-31"
                           class="w-full rounded-lg border border-[#ece7dc] bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                </div>
                <button type="submit" class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-1">
                    Apply
                </button>
                @unless ($isFullYear)
                    <a href="{{ route('mswdo.dashboard', ['year' => $year, 'month' => $month]) }}"
                       class="rounded-lg px-2 py-1.5 text-xs font-bold text-gray-600 underline underline-offset-2 transition hover:text-gray-900">
                        Reset
                    </a>
                @endunless
            </div>
        </form>
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
            // The same walk as the bars, as hex, so a type keeps one colour
            // whichever of the two it appears in.
            $sliceColors = ['#0d9488', '#2dd4bf', '#84cc16', '#facc15', '#f59e0b'];
        @endphp

        {{-- Share of money actually released, by assistance type. The bars below
             answer "how much of each allocation is used"; this answers "where
             did the spending go", which is the question a pie can actually
             carry. --}}
        @if ($budgetPie->isNotEmpty())
            <div class="mb-5 flex items-center gap-4">
                {{-- A donut drawn with stroke-dasharray: no chart library, and it
                     stays sharp at any size. r=15.915 makes the circumference
                     exactly 100, so a slice's percentage is its dash length. --}}
                <svg viewBox="0 0 42 42" class="h-28 w-28 shrink-0 -rotate-90" role="img"
                     aria-label="Released budget by assistance type">
                    <circle cx="21" cy="21" r="15.915" fill="none" stroke="#f1efe9" stroke-width="6"></circle>
                    @foreach ($budgetPie as $i => $slice)
                        <circle cx="21" cy="21" r="15.915" fill="none"
                                stroke="{{ $sliceColors[$i % count($sliceColors)] }}" stroke-width="6"
                                stroke-dasharray="{{ round($slice['share'], 2) }} {{ round(100 - $slice['share'], 2) }}"
                                stroke-dashoffset="{{ round(100 - $slice['offset'], 2) }}">
                            <title>{{ $slice['type'] }}: ₱{{ number_format($slice['value'], 2) }} ({{ round($slice['share']) }}%)</title>
                        </circle>
                    @endforeach
                </svg>

                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Total Released</p>
                    <p class="mb-2 text-lg font-bold tabular-nums text-gray-900">₱ {{ number_format($releasedInWindow, 2) }}</p>
                    <ul class="space-y-1">
                        @foreach ($budgetPie as $i => $slice)
                            <li class="flex items-center gap-2 text-xs">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full"
                                      style="background: {{ $sliceColors[$i % count($sliceColors)] }}"></span>
                                <span class="min-w-0 flex-1 truncate font-medium text-gray-700">{{ $slice['type'] }}</span>
                                <span class="shrink-0 font-bold tabular-nums text-gray-900">{{ round($slice['share']) }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
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

        @if ($budgetUtilization->isNotEmpty() && $releasedInWindow <= 0)
            {{-- Allocations exist but nothing was released in this window, which
                 is a real answer rather than a blank panel. --}}
            <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-600">
                No releases recorded in this period.
            </p>
        @endif
    </div>

</div>

@endsection
