@extends('mswdo.layouts.app')
@section('title', 'Reports')
@section('page-title', 'Reports & Analytics')

@section('content')

{{-- Horizontal filter bar --}}
<div data-print-hide class="panel mb-4">
    <form method="GET" action="{{ route('mswdo.reports.index') }}" class="toolbar border-b-0">
        <div class="min-w-40 flex-1">
            <label class="form-label">Report Type</label>
            <select name="type" class="form-control">
                <option value="">Select report ▾</option>
                <option value="recipient" @selected(request('type') === 'recipient')>Recipient Monitoring</option>
                <option value="budget"    @selected(request('type') === 'budget')>Budget Utilization</option>
                <option value="audit"     @selected(request('type') === 'audit')>Audit Trail Log</option>
            </select>
        </div>
        <div class="min-w-32">
            <label class="form-label">Date From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
        </div>
        <div class="min-w-32">
            <label class="form-label">Date To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
        </div>
        <div class="min-w-36 flex-1">
            <label class="form-label">Assistance Type</label>
            <select name="assistance_type" class="form-control">
                <option value="">All types ▾</option>
                @foreach (['Medical','Burial','Educational','Livelihood'] as $t)
                    <option value="{{ $t }}" @selected(request('assistance_type') === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="shrink-0 pb-0.5">
            <button type="submit" class="btn-primary px-6 py-2.5 whitespace-nowrap">Generate</button>
        </div>
    </form>
</div>

{{-- Two-column layout: left = report, right = chart + audit log --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

    {{-- ===== LEFT: Main report panel ===== --}}
    {{-- min-w-0 is required: a grid item defaults to min-width:auto, so the
         wide report table stretched this column past its track and the last
         columns were clipped off instead of scrolling. --}}
    <div data-print-area class="xl:col-span-2 min-w-0 panel flex flex-col">

        {{-- Report header --}}
        <div class="panel-head">
            <div>
                <p class="text-[11px] uppercase tracking-wider text-teal-700 font-bold">Report Results</p>
                <h3 class="font-bold text-gray-900 mt-0.5 text-base">
                    {{ $report ? $report['title'] : 'Recipient Monitoring Report' }}
                </h3>
                @if($report)
                    <p class="text-xs text-gray-500 mt-0.5">{{ count($report['rows']) }} {{ count($report['rows']) === 1 ? 'record' : 'records' }} found</p>

                    {{-- Print-only provenance. On screen the active filters are
                         visible in the bar above, but that bar is hidden when
                         printing, so a printed sheet would otherwise carry no
                         record of which range or type it covers. --}}
                    <p data-print-only class="hidden text-xs text-gray-700 mt-1">
                        MSWDO San Agustin ·
                        {{ request('assistance_type') ?: 'All assistance types' }} ·
                        @if(request('date_from') || request('date_to'))
                            {{ request('date_from') ?: 'start' }} to {{ request('date_to') ?: 'present' }}
                        @else
                            All dates
                        @endif
                        · Generated {{ now()->format('M d, Y g:i A') }}
                    </p>
                @endif
            </div>
            @if($report)
                <div data-print-hide class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('mswdo.reports.export-pdf', request()->query()) }}"
                       class="btn-secondary px-3.5 py-2 text-sm inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Export PDF
                    </a>
                    <a href="{{ route('mswdo.reports.export', request()->query()) }}"
                       class="btn-secondary px-3.5 py-2 text-sm inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Export CSV
                    </a>
                </div>
            @endif
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto min-w-0 flex-1">
            @if($report)
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-700 font-semibold uppercase tracking-wide">
                        <tr>
                            @foreach($report['columns'] as $col)
                                <th class="px-5 py-3 text-left whitespace-nowrap">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($report['rows'] as $row)
                            <tr class="hover:bg-gray-50 transition-colors">
                                @foreach($row as $ci => $value)
                                @php
                                    $rt = request('type', 'recipient');
                                    $cls = 'text-gray-800';
                                    if ($rt === 'recipient' && $ci === 4) $cls = 'text-green-700 font-semibold tabular-nums';
                                    elseif ($rt === 'recipient' && $ci === 3) $cls = 'text-gray-800 tabular-nums';
                                    elseif ($rt === 'budget' && $ci === 5) {
                                        $p = (float) rtrim($value, '%');
                                        $cls = $p >= 90 ? 'text-red-600 font-semibold tabular-nums' : 'text-teal-700 font-medium tabular-nums';
                                    } elseif ($rt === 'budget' && in_array($ci, [2,3,4])) $cls = 'text-gray-800 tabular-nums';
                                @endphp
                                    <td class="px-5 py-3.5 whitespace-nowrap {{ $cls }}">{{ $value }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($report['columns']) }}" class="px-5 py-12 text-center text-gray-500 text-sm">
                                    No records match these filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- Footer total for recipient report --}}
                @if(request('type') === 'recipient' && count($report['rows']) > 0)
                @php
                    $totalRel = collect($report['rows'])->sum(fn($r) => (float) str_replace(',', '', $r[4] ?? '0'));
                @endphp
                    <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 flex justify-between text-xs text-gray-700">
                        <span>{{ count($report['rows']) }} released {{ count($report['rows']) === 1 ? 'request' : 'requests' }}</span>
                        <span class="font-semibold text-green-700 tabular-nums">Total Released: ₱ {{ number_format($totalRel, 2) }}</span>
                    </div>
                @endif

                {{-- One line per assistance type plus the grand total, so the
                     Medical / Burial / Educational / Livelihood figures do not
                     have to be added up from the rows above by hand. --}}
                @if(! empty($report['summary']))
                    @php $summary = $report['summary']; @endphp
                    <div class="border-t border-gray-200 px-5 pt-5 pb-2">
                        <h4 class="panel-title">{{ $summary['title'] }}</h4>
                    </div>
                    <table class="w-full text-sm" data-report-summary>
                        <thead class="bg-gray-50 text-xs text-gray-700 font-semibold uppercase tracking-wide">
                            <tr>
                                @foreach($summary['columns'] as $ci => $col)
                                    <th class="px-5 py-3 whitespace-nowrap {{ in_array($ci, $summary['numeric']) ? 'text-right' : 'text-left' }}">{{ $col }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($summary['rows'] as $row)
                                <tr>
                                    @foreach($row as $ci => $value)
                                        <td class="px-5 py-3 whitespace-nowrap {{ in_array($ci, $summary['numeric']) ? 'text-right tabular-nums text-gray-800' : 'font-semibold text-gray-900' }}">{{ $value }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            <tr class="bg-teal-50">
                                @foreach($summary['total'] as $ci => $value)
                                    <td class="px-5 py-3 whitespace-nowrap font-bold text-teal-900 {{ in_array($ci, $summary['numeric']) ? 'text-right tabular-nums' : '' }}">{{ $value }}</td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                @endif

            @else
                {{-- Empty state --}}
                <div class="flex flex-col items-center justify-center px-6 py-20 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-teal-50 ring-1 ring-teal-100 flex items-center justify-center mb-4">
                        <svg class="w-7 h-7 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <p class="font-bold text-gray-800">Select a report type and click <span class="text-teal-700">Generate</span></p>
                    <p class="mt-1 text-sm text-gray-600 max-w-xs">
                        Choose from Recipient Monitoring, Budget Utilization, or Audit Trail Log.
                    </p>
                    {{-- One-click shortcuts. The filter bar above can do the
                         same job, but from a standing start these are the
                         faster route, and each carries the icon its report
                         uses elsewhere in the portal. --}}
                    @php
                        $shortcuts = [
                            ['type' => 'recipient', 'label' => 'Recipient Monitoring', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                            ['type' => 'budget',    'label' => 'Budget Utilization',   'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                            ['type' => 'audit',     'label' => 'Audit Trail Log',      'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        ];
                    @endphp
                    <div class="mt-5 flex flex-wrap justify-center gap-2">
                        @foreach ($shortcuts as $shortcut)
                            <a href="{{ route('mswdo.reports.index', ['type' => $shortcut['type']]) }}" class="btn-soft">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $shortcut['icon'] }}"/>
                                </svg>
                                {{ $shortcut['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ===== RIGHT: Budget chart + Audit log ===== --}}
    <div data-print-hide class="space-y-4">

        {{-- Budget utilization summary --}}
        <div class="panel p-5">
            <h4 class="panel-title">Budget utilization summary</h4>
            <p class="text-xs text-gray-600 mt-1.5 mb-4">
                Share of each {{ now()->year }} budget already released.
            </p>

            @if($budgetSummary->isNotEmpty())
                {{-- Horizontal bars, one row per assistance type. Each bar is
                     scaled to its OWN allocation rather than to the largest
                     budget in the set, so a 50M Medical line no longer flattens
                     every other row into an unreadable stub. The question this
                     panel answers is "how much of this budget is spent", and
                     that is what the fill width now shows. --}}
                <div class="space-y-3">
                    @foreach($budgetSummary as $b)
                    @php
                        $allocated = (float) ($b->allocated_amount ?? 0);
                        $released  = (float) ($b->total_released ?? 0);
                        $pct       = $allocated > 0 ? min(100, ($released / $allocated) * 100) : 0;
                        $barColor  = $pct >= 90 ? 'bg-red-500' : ($pct >= 70 ? 'bg-yellow-400' : 'bg-teal-600');
                        $pctColor  = $pct >= 90 ? 'text-red-700' : ($pct >= 70 ? 'text-amber-700' : 'text-teal-800');
                    @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-2 mb-1">
                                <span class="text-xs font-medium text-gray-700">{{ $b->assistance_type }}</span>
                                {{-- A real but tiny release (₱2k against ₱9M)
                                     rounds to "0.0%", which reads as untouched.
                                     Show it as "<0.1%" so spent and unspent are
                                     never displayed the same way. --}}
                                <span class="text-xs font-semibold tabular-nums {{ $pctColor }}">
                                    {{ $pct > 0 && $pct < 0.1 ? '<0.1' : number_format($pct, 1) }}%
                                </span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-gray-100 overflow-hidden">
                                {{-- A tiny non-zero release would round to a 0px
                                     sliver, so anything above zero keeps a 2%
                                     minimum width and stays visible. --}}
                                <div class="h-full rounded-full {{ $barColor }}"
                                     style="width: {{ $pct > 0 ? max(2, $pct) : 0 }}%;"></div>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-600 tabular-nums">
                                ₱ {{ number_format($released, 2) }} of ₱ {{ number_format($allocated, 2) }}
                            </p>
                        </div>
                    @endforeach
                </div>

                {{-- The legend describes the fill colour, which is what the bars
                     actually encode. The previous legend claimed grey/teal meant
                     Allocated/Released while the bars were in fact painted a
                     different colour per assistance type. --}}
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 mt-4 pt-3 border-t border-gray-100 text-[11px] font-semibold text-gray-600">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-sm bg-teal-600 inline-block"></span> Below 70%
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-sm bg-yellow-400 inline-block"></span> 70–89%
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-sm bg-red-500 inline-block"></span> 90% and up
                    </span>
                </div>
            @else
                <div class="flex items-center justify-center h-24 text-sm text-gray-500">
                    No budget data for {{ now()->year }}.
                </div>
            @endif
        </div>

        {{-- Audit trail log --}}
        <div class="panel p-5">
            <h4 class="panel-title is-accent mb-3">Audit trail log</h4>

            @if($recentAudit->isNotEmpty())
                {{-- Drawn as a timeline: a continuous rule down the left with a
                     dot per entry. The old loose rows read as an unordered pile,
                     when the whole point of an audit trail is its sequence. --}}
                <ol class="relative space-y-3 border-l border-teal-100 pl-4">
                    @foreach($recentAudit as $log)
                        <li class="relative text-xs">
                            <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full border-2 border-white bg-teal-500 shadow-sm" aria-hidden="true"></span>
                            <p class="font-semibold leading-snug text-gray-800">
                                {{ $log->action }}
                                <span class="font-medium text-gray-500">— {{ $log->module }}</span>
                            </p>
                            <p class="mt-0.5 text-gray-600">
                                {{ $log->user?->name ?? 'System' }}
                                · {{ $log->performed_at?->diffForHumans() ?? '' }}
                            </p>
                        </li>
                    @endforeach
                </ol>
                <a href="{{ route('mswdo.reports.index', ['type' => 'audit']) }}" class="btn-soft mt-4 w-full justify-center">
                    View full audit log
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @else
                <p class="py-4 text-center text-sm text-gray-500">No activity recorded yet.</p>
            @endif
        </div>

    </div>
</div>

@endsection
