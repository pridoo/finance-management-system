@extends('mswdo.layouts.app')
@section('title', 'Budget Tracking')
@section('page-title', 'Budget and Release Tracking')

{{-- The fiscal year sits beside the page title: every figure on the page
     belongs to it, so it is the first thing read and can be changed from the
     top rather than from inside the allocation table. --}}
@section('page-actions')
    <form method="GET" action="{{ route('mswdo.budget.index') }}"
          class="panel flex items-center gap-3 px-4 py-2.5">
        @if ($type)
            <input type="hidden" name="type" value="{{ $type }}">
        @endif
        <label for="budget-year" class="form-label mb-0! whitespace-nowrap">Fiscal Year</label>
        {{-- A dropdown rather than a free number field: the years that can hold
             records are a known list, and typing 2019 into a spinner only ever
             produced an empty table. --}}
        <select id="budget-year" name="year" onchange="this.form.submit()"
                class="form-control w-32 py-1.5! text-lg! font-bold text-teal-800">
            @foreach ($years as $option)
                <option value="{{ $option }}" @selected((int) $year === $option)>{{ $option }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="btn-soft">Go</button></noscript>
    </form>
@endsection

@section('content')

@php
    $isOfficer = auth()->user()->role === 'mswdo_officer';

    // Figures for whatever the page is narrowed to (one type, or all).
    $allocated = $budgets->sum('allocated_amount');
    $released  = $budgets->sum('total_released');
    $balance   = $budgets->sum('remaining_balance');

    // The year's fund, and how it has been drawn down. Only allocations
    // and releases ever move these, and both take money out — the fund's
    // remaining figures count down to zero, never up.
    $fundAmount       = $fund ? (float) $fund->amount : null;
    $yearAllocated    = (float) $allBudgets->sum('allocated_amount');
    $yearReleased     = (float) $allBudgets->sum('total_released');
    $fundUnallocated  = $fund ? max(0, $fundAmount - $yearAllocated) : 0;
    $fundUnreleased   = $fund ? max(0, $fundAmount - $yearReleased) : max(0, $yearAllocated - $yearReleased);
    $fundBase         = $fundAmount ?: $yearAllocated;
    $releasedPct      = $fundBase > 0 ? min(100, ($yearReleased / $fundBase) * 100) : 0;
    $allocatedOpenPct = $fundBase > 0 ? min(100 - $releasedPct, (($yearAllocated - $yearReleased) / $fundBase) * 100) : 0;
    $yearOver         = $year < now()->year;

    $stats = $type
        ? [
            ['label' => $type . ' Allocated', 'value' => '₱ ' . number_format($allocated, 2), 'tone' => 'text-gray-900',  'rule' => 'from-teal-500 to-teal-600'],
            ['label' => $type . ' Released',  'value' => '₱ ' . number_format($released, 2),  'tone' => 'text-green-700', 'rule' => 'from-green-500 to-green-600'],
            ['label' => $type . ' Balance',   'value' => '₱ ' . number_format($balance, 2),   'tone' => 'text-teal-800',  'rule' => 'from-yellow-400 to-yellow-500'],
            ['label' => 'Fiscal Year',        'value' => $year,                               'tone' => 'text-gray-900',  'rule' => 'from-amber-400 to-amber-500'],
        ]
        : [
            ['label' => 'Annual Fund',          'value' => $fund ? '₱ ' . number_format($fundAmount, 2) : 'Not set', 'tone' => $fund ? 'text-gray-900' : 'text-gray-400', 'rule' => 'from-teal-500 to-teal-600'],
            ['label' => 'Allocated to Types',   'value' => '₱ ' . number_format($yearAllocated, 2),  'tone' => 'text-gray-900',  'rule' => 'from-teal-400 to-teal-500'],
            ['label' => 'Total Released',       'value' => '₱ ' . number_format($yearReleased, 2),   'tone' => 'text-green-700', 'rule' => 'from-green-500 to-green-600'],
            ['label' => 'Remaining to Release', 'value' => '₱ ' . number_format($fundUnreleased, 2), 'tone' => 'text-teal-800',  'rule' => 'from-yellow-400 to-yellow-500'],
        ];
@endphp

{{-- Narrow the page to one assistance type. Every figure below — the boxes,
     the table and the releases list — follows the choice. --}}
<nav class="mb-5 flex flex-wrap items-center gap-2" aria-label="Filter by assistance type">
    <span class="mr-1 text-xs font-bold uppercase tracking-wide text-gray-500">Show</span>
    @foreach (array_merge([null], $types) as $option)
        @php $active = $type === $option; @endphp
        <a href="{{ route('mswdo.budget.index', array_filter(['year' => $year, 'type' => $option])) }}"
           @if($active) aria-current="page" @endif
           class="rounded-full border px-4 py-1.5 text-sm font-semibold transition
                  {{ $active ? 'border-teal-700 bg-teal-700 text-white' : 'border-[#ece7dc] bg-white text-gray-700 hover:border-teal-300 hover:bg-teal-50' }}">
            {{ $option ?? 'All types' }}
        </a>
    @endforeach
</nav>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
    @foreach ($stats as $stat)
        <div class="panel relative p-5">
            <span class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $stat['rule'] }}" aria-hidden="true"></span>
            <p class="text-xs text-gray-600 font-semibold uppercase tracking-wide mb-1.5">{{ $stat['label'] }}</p>
            <p class="text-2xl font-bold tabular-nums {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
        </div>
    @endforeach
</div>

{{-- The year's fund and how far it has been drawn down. One bar, three parts:
     released, allocated but not yet released, and not yet allocated. The goal
     is for the green to reach the end — every peso of the fund released. --}}
@unless($type)
    <div class="panel mb-5 p-5" data-annual-fund>
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
            <div>
                <p class="text-sm font-bold text-gray-800">{{ $year }} Annual Fund</p>
                <p class="mt-0.5 text-xs text-gray-600">
                    Allocations per assistance type are taken out of this fund, and releases are taken out of the allocations.
                </p>
            </div>
            @if($isOfficer)
                <button type="button" data-fund-set
                        data-year="{{ $year }}"
                        data-amount="{{ $fund ? number_format($fundAmount, 2, '.', '') : '' }}"
                        data-allocated="{{ number_format($yearAllocated, 2, '.', '') }}"
                        class="{{ $fund ? 'btn-soft' : 'btn-primary' }} shrink-0">
                    {{ $fund ? 'Edit fund' : 'Set annual fund' }}
                </button>
            @endif
        </div>

        @if($fund || $yearAllocated > 0)
            <div class="flex h-3 overflow-hidden rounded-full bg-gray-100" role="img"
                 aria-label="{{ round($releasedPct) }}% of the fund released">
                <div class="h-3 bg-green-600" style="width: {{ $releasedPct }}%"></div>
                <div class="h-3 bg-teal-300" style="width: {{ max(0, $allocatedOpenPct) }}%"></div>
            </div>
            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs font-semibold text-gray-700 tabular-nums">
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-green-600"></span>Released ₱ {{ number_format($yearReleased, 2) }}</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-teal-300"></span>Allocated, not released ₱ {{ number_format(max(0, $yearAllocated - $yearReleased), 2) }}</span>
                @if($fund)
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-gray-200 ring-1 ring-gray-300"></span>Not yet allocated ₱ {{ number_format($fundUnallocated, 2) }}</span>
                @endif
            </div>
        @endif

        @if(! $fund)
            <p class="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
                No annual fund has been set for {{ $year }}. Set it first; allocations per assistance type are then drawn from it and can no longer push the year's total past it.
            </p>
        @elseif($fundUnreleased > 0)
            <p class="mt-3 rounded-lg {{ $yearOver ? 'bg-red-50 text-red-900' : 'bg-amber-50 text-amber-900' }} px-4 py-3 text-sm">
                @if($yearOver)
                    <b>₱ {{ number_format($fundUnreleased, 2) }}</b> of the {{ $year }} fund was never released.
                @else
                    <b>₱ {{ number_format($fundUnreleased, 2) }}</b> of the {{ $year }} fund still has to be released before the year ends.
                @endif
            </p>
        @else
            <p class="mt-3 rounded-lg bg-teal-50 px-4 py-3 text-sm font-semibold text-teal-900">
                The whole {{ $year }} fund has been released.
            </p>
        @endif
    </div>
@endunless

{{-- The table now runs the full width. Squeezed into two thirds it could not
     fit its own action column, and the Delete button was cut off the edge. --}}
<div>

    {{-- Budget allocation table --}}
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-title">Budget Allocation per Assistance Type</h3>
            <div class="flex flex-wrap items-center gap-3">
                @if(auth()->user()->role === 'mswdo_officer')
                    <button type="button" data-budget-add class="btn-primary shrink-0 py-2.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Allocation
                    </button>
                @endif
            </div>
        </div>
        {{-- Scrolls rather than clips. Without this the action column was cut
             off at the card's edge on narrower screens and Delete became
             unreachable. --}}
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3 text-left">Type</th>
                    <th class="px-5 py-3 text-left">Allocated (₱)</th>
                    <th class="px-5 py-3 text-left">Released (₱)</th>
                    <th class="px-5 py-3 text-left">Balance (₱)</th>
                    <th class="px-5 py-3 text-left">Utilization</th>
                    <th class="px-5 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($budgets as $budget)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('mswdo.budget.index', ['year' => $year, 'type' => $budget->assistance_type]) }}"
                               class="font-semibold text-gray-900 hover:text-teal-700 hover:underline"
                               title="Show only {{ $budget->assistance_type }}">{{ $budget->assistance_type }}</a>
                            {{-- How the fund is split internally, as one quiet
                                 line under the name. The detail lives in the
                                 Split dialog: five budgets each with their own
                                 expanded panel buried the table this page is
                                 actually for. --}}
                            @if ($budget->subAllocations->isNotEmpty())
                                <p class="mt-0.5 truncate text-xs text-gray-500" title="{{ $budget->subAllocations->pluck('label')->join(', ') }}">
                                    {{ $budget->subAllocations->count() }}
                                    {{ Str::plural('part', $budget->subAllocations->count()) }}:
                                    {{ Str::limit($budget->subAllocations->pluck('label')->join(', '), 34) }}
                                </p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-800 tabular-nums">{{ number_format($budget->allocated_amount, 2) }}</td>
                        <td class="px-5 py-3.5 font-semibold text-green-700 tabular-nums">{{ number_format($budget->total_released, 2) }}</td>
                        <td class="px-5 py-3.5 font-semibold tabular-nums {{ $budget->remaining_balance < 0 ? 'text-red-700' : 'text-gray-800' }}">
                            {{ number_format($budget->remaining_balance, 2) }}
                        </td>
                        <td class="px-5 py-3.5 w-40">
                            @php $pct = $budget->allocated_amount > 0 ? min(100, round(($budget->total_released / $budget->allocated_amount) * 100)) : 0; @endphp
                            <div class="flex items-center gap-2">
                                {{-- Thicker than the old 1.5px hairline, which
                                     was hard to read at a glance and vanished
                                     entirely at low percentages. --}}
                                <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-2 rounded-full transition-all {{ $pct >= 90 ? 'bg-red-500' : ($pct >= 70 ? 'bg-yellow-400' : 'bg-teal-600') }}"
                                         style="width:{{ $pct }}%"></div>
                                </div>
                                <span class="w-9 shrink-0 text-xs font-bold tabular-nums {{ $pct >= 90 ? 'text-red-700' : 'text-gray-700' }}">{{ $pct }}%</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            {{-- Available to every role: reading where the money
                                 went is not an officer-only concern, and it is
                                 the question this table most often raises. --}}
                            <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                <button type="button"
                                        data-budget-view="{{ route('mswdo.budget.show', $budget) }}"
                                        class="btn-soft-plain">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Releases
                                </button>

                                {{-- Reading the split is not officer-only, same
                                     reasoning as Releases above. --}}
                                <button type="button"
                                        data-budget-split="{{ $budget->id }}"
                                        class="btn-soft-plain">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                    Split
                                    @if ($budget->subAllocations->isNotEmpty())
                                        <span class="ml-1 rounded-full bg-gray-200 px-1.5 text-[10px] font-bold tabular-nums text-gray-700">
                                            {{ $budget->subAllocations->count() }}
                                        </span>
                                    @endif
                                </button>

                                @if(auth()->user()->role === 'mswdo_officer')
                                    <button type="button"
                                            data-budget-edit
                                            data-action="{{ route('mswdo.budget.update', $budget) }}"
                                            data-type="{{ $budget->assistance_type }}"
                                            data-year="{{ $budget->fiscal_year }}"
                                            data-allocated="{{ number_format((float) $budget->allocated_amount, 2, '.', '') }}"
                                            data-released="{{ number_format($budget->total_released, 2, '.', '') }}"
                                            class="btn-soft">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Edit
                                    </button>

                                    @if($budget->total_released <= 0)
                                        <form method="POST" action="{{ route('mswdo.budget.destroy', $budget) }}"
                                              data-swal-confirm="danger"
                                              data-swal-name="the {{ $budget->assistance_type }} {{ $budget->fiscal_year }} allocation">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-soft-danger"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Delete</button>
                                        </form>
                                    @else
                                        {{-- Deleting would orphan the release
                                             records that reference it. --}}
                                        <span class="inline-flex items-center rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-400"
                                              title="This allocation has released funds and can no longer be deleted.">In use</span>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>

                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p>No {{ $type ? $type . ' ' : '' }}budget allocation recorded for {{ $year }}.</p>
                                @if(auth()->user()->role === 'mswdo_officer')
                                    <button type="button" data-budget-add class="btn-primary mt-1">Add the first allocation</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- Every release paid out of the year's funds — or, with a type chosen
         above, only that type's. This is what the filter is for: picking
         Medical shows Medical releases and nothing else. --}}
    <div class="panel mt-5" data-budget-releases>
        <div class="panel-head">
            <h3 class="panel-title is-accent">{{ $type ? $type . ' releases' : 'All releases' }} — FY {{ $year }}</h3>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-700">
                {{ $releases->total() }} {{ Str::plural('release', $releases->total()) }}
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Date Released</th>
                        <th class="px-5 py-3 text-left">Beneficiary</th>
                        <th class="px-5 py-3 text-left">Type</th>
                        <th class="px-5 py-3 text-right">Amount (₱)</th>
                        <th class="px-5 py-3 text-left">Released By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($releases as $release)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 whitespace-nowrap text-gray-700">{{ $release->released_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @if($release->request)
                                    <a href="{{ route('mswdo.requests.show', $release->request) }}" class="font-semibold text-gray-900 hover:text-teal-700 hover:underline">
                                        {{ $release->request->client?->full_name ?? '—' }}
                                    </a>
                                    <span class="block text-xs text-gray-500">Request #{{ $release->request_id }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-700">{{ $release->budget?->assistance_type }}</td>
                            <td class="px-5 py-3 text-right font-semibold tabular-nums text-green-700">{{ number_format((float) $release->amount_released, 2) }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $release->releasedBy?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500">
                                No {{ $type ? $type . ' ' : '' }}funds released in {{ $year }} yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($releases->hasPages())
            <div class="border-t border-gray-100 px-5 py-3">{{ $releases->links() }}</div>
        @endif
    </div>

</div>

{{-- One split panel per budget, kept out of the table and opened on demand.
     Rendered as templates rather than fetched, since the data is already on
     the page and a split is read far more often than it is edited. --}}
@foreach ($budgets as $budget)
    <template id="tpl-budget-split-{{ $budget->id }}">
        <div class="text-left">
            @php
                $subTotal = $budget->sub_allocated_total;
                $subPct   = $budget->allocated_amount > 0
                    ? min(100, ($subTotal / $budget->allocated_amount) * 100)
                    : 0;
            @endphp

            {{-- The whole against its parts, so "how much is still unassigned"
                 is answered before the list is read. --}}
            <div class="mb-4 rounded-xl border border-[#ece7dc] bg-[#fbf9f4] px-4 py-3">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Allocation</span>
                    <span class="text-sm font-bold tabular-nums text-gray-900">
                        ₱ {{ number_format($budget->allocated_amount, 2) }}
                    </span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-200">
                    <div class="h-2 rounded-full bg-teal-600" style="width: {{ $subPct }}%"></div>
                </div>
                <div class="mt-2 flex items-baseline justify-between gap-3 text-xs">
                    <span class="font-semibold text-teal-800 tabular-nums">
                        ₱ {{ number_format($subTotal, 2) }} assigned
                    </span>
                    <span class="font-semibold text-gray-600 tabular-nums">
                        ₱ {{ number_format($budget->unallocated, 2) }} unassigned
                    </span>
                </div>
            </div>

            @if ($budget->subAllocations->isNotEmpty())
                <ul class="mb-4 divide-y divide-gray-100 overflow-hidden rounded-xl border border-[#ece7dc]">
                    @foreach ($budget->subAllocations as $sub)
                        <li class="flex items-center justify-between gap-3 bg-white px-4 py-2.5">
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900">{{ $sub->label }}</span>
                            <span class="shrink-0 text-sm font-bold tabular-nums text-teal-800">
                                ₱ {{ number_format($sub->allocated_amount, 2) }}
                            </span>
                            @if(auth()->user()->role === 'mswdo_officer')
                                <form method="POST"
                                      action="{{ route('mswdo.budget.sub-allocations.destroy', [$budget, $sub]) }}"
                                      data-swal-confirm="danger"
                                      data-swal-name="the “{{ $sub->label }}” sub-allocation"
                                      class="shrink-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" aria-label="Remove {{ $sub->label }}"
                                            class="rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mb-4 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    Not split yet — the whole allocation sits under {{ $budget->assistance_type }}.
                </p>
            @endif

            @if(auth()->user()->role === 'mswdo_officer')
                @if ($budget->unallocated > 0)
                    <form method="POST" action="{{ route('mswdo.budget.sub-allocations.store', $budget) }}"
                          class="rounded-xl border border-[#ece7dc] bg-white p-4">
                        @csrf
                        <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-500">Add a part</p>
                        <div class="mb-3">
                            <label for="sub-label-{{ $budget->id }}" class="mb-1.5 block text-xs font-semibold text-gray-600">
                                Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="sub-label-{{ $budget->id }}" name="label" required maxlength="100"
                                   placeholder="e.g. College"
                                   class="w-full rounded-lg border border-[#ece7dc] bg-white px-3 py-2 text-sm font-semibold text-gray-900 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div class="mb-4">
                            <label for="sub-amount-{{ $budget->id }}" class="mb-1.5 block text-xs font-semibold text-gray-600">
                                Amount (₱) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="sub-amount-{{ $budget->id }}" name="allocated_amount" required
                                   min="1" step="0.01" max="{{ number_format($budget->unallocated, 2, '.', '') }}"
                                   placeholder="0.00"
                                   class="w-full rounded-lg border border-[#ece7dc] bg-white px-3 py-2 text-sm font-semibold tabular-nums text-gray-900 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <p class="mt-1.5 text-xs text-gray-500">
                                Up to ₱ {{ number_format($budget->unallocated, 2) }} is still unassigned.
                            </p>
                        </div>
                        <button type="submit" class="btn-primary w-full justify-center">Add part</button>
                    </form>
                @else
                    <p class="rounded-xl bg-teal-50 px-4 py-3 text-sm font-semibold text-teal-900">
                        Fully assigned — remove a part to free up funds.
                    </p>
                @endif
            @endif
        </div>
    </template>
@endforeach

{{-- The add form is now a modal rather than a permanent sidebar panel: it is
     used a handful of times a year, and it was taking a third of the page away
     from the table that gets read every day. --}}
@if(auth()->user()->role === 'mswdo_officer')
<template id="tpl-budget-add">
    <div class="text-left">
        {{-- Worth spelling out: saving against a type and year that already has
             an allocation tops it up, it does not replace the figure. --}}
        {{-- The amount comes out of the year's fund, so say how much is left. --}}
        @if($fund)
            <p class="mb-4 rounded-lg bg-teal-50 px-4 py-3 text-sm text-teal-900">
                This amount is <b>taken out of</b> the {{ $year }} fund.
                <b>₱ {{ number_format($fundUnallocated, 2) }}</b> is still free to allocate.
                If the type already has an allocation, the amount is added to it.
            </p>
        @else
            <p class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Set the annual fund for the year first — allocations are drawn from it.
            </p>
        @endif
        <div class="mb-4">
            <label for="add-type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">
                Assistance Type <span class="text-red-500">*</span>
            </label>
            <select id="add-type" class="swal2-input" style="margin:0;width:100%">
                <option value="">— Select type —</option>
                @foreach (['Medical','Burial','Educational','Livelihood'] as $t)
                    <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-4">
            <label for="add-amount" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">
                Allocated Amount (₱) <span class="text-red-500">*</span>
            </label>
            <input id="add-amount" type="number" min="1" step="0.01" placeholder="0.00"
                   class="swal2-input" style="margin:0;width:100%">
        </div>
        <div>
            <label for="add-year" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">
                Fiscal Year <span class="text-red-500">*</span>
            </label>
            <select id="add-year" class="swal2-input" style="margin:0;width:100%">
                @foreach ($years as $option)
                    <option value="{{ $option }}" @selected((int) $year === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
    </div>
</template>
@endif


{{--
    Both dialogs are built on the same SweetAlert instance the rest of the
    portal uses, so they inherit the branded styling and the "no dismiss by
    clicking the backdrop" rule already established in resources/js/app.js.

    type="module" for the same reason as the swal partial: a plain inline
    script runs while the page is still parsing, before the deferred bundle has
    defined window.Swal, and the buttons would quietly do nothing.
--}}
<script type="module">
(function () {
    var peso = function (value) { return '₱ ' + value; };

    var escapeHtml = function (value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    };

    var unavailable = function (message) {
        // Never dead-end the click. If SweetAlert is missing the clerk still
        // gets told what happened rather than pressing a button that is inert.
        if (typeof Swal === 'undefined') {
            window.alert(message);
            return true;
        }
        return false;
    };

    // Returns markup as a string, not a DocumentFragment. SweetAlert's `html`
    // option stringifies anything that is not an Element, so handing it a
    // fragment rendered the literal text "[object DocumentFragment]" instead
    // of the form. Reading innerHTML each time also keeps the template pristine
    // for the next open.
    var bodyFrom = function (id) {
        var template = document.getElementById(id);
        return template ? template.innerHTML : '';
    };

    var post = function (action, fields) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = action;

        var add = function (name, value) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };

        add('_token', '{{ csrf_token() }}');
        Object.keys(fields).forEach(function (name) { add(name, fields[name]); });

        // Already confirmed in the dialog; without this the global handler in
        // app.js would ask a second, vaguer question on top.
        form.dataset.swalConfirmed = 'yes';
        document.body.appendChild(form);
        HTMLFormElement.prototype.submit.call(form);
    };

    // ---- Add allocation ---------------------------------------------------
    document.addEventListener('click', async function (event) {
        var trigger = event.target.closest('[data-budget-add]');
        if (!trigger) return;

        if (unavailable('The add form is unavailable — please reload the page.')) return;
        if (trigger.dataset.busy === 'yes') return;
        trigger.dataset.busy = 'yes';

        try {
            var result = await Swal.fire({
                title: 'Add Budget Allocation',
                html: bodyFrom('tpl-budget-add'),
                width: 560,
                showCancelButton: true,
                confirmButtonText: 'Save allocation',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#15803d',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
                allowOutsideClick: false,
                focusConfirm: false,
                customClass: { popup: 'swal-portal', title: 'swal-portal-title', htmlContainer: 'swal-portal-body' },
                preConfirm: function () {
                    var type   = document.getElementById('add-type').value;
                    var amount = parseFloat(document.getElementById('add-amount').value);

                    if (!type) {
                        Swal.showValidationMessage('Select an assistance type.');
                        return false;
                    }
                    if (isNaN(amount) || amount < 1) {
                        Swal.showValidationMessage('Enter an amount of at least ₱1.00.');
                        return false;
                    }
                    return {
                        assistance_type: type,
                        allocated_amount: amount,
                        fiscal_year: document.getElementById('add-year').value,
                    };
                },
            });

            if (!result.isConfirmed) return;

            post('{{ route('mswdo.budget.store') }}', result.value);
        } catch (error) {
            console.error('[budget] add dialog failed:', error);
        } finally {
            delete trigger.dataset.busy;
        }
    });

    // ---- Annual fund ------------------------------------------------------
    document.addEventListener('click', async function (event) {
        var trigger = event.target.closest('[data-fund-set]');
        if (!trigger) return;

        if (unavailable('The fund dialog is unavailable — please reload the page.')) return;
        if (trigger.dataset.busy === 'yes') return;
        trigger.dataset.busy = 'yes';

        var info = trigger.dataset;
        var allocated = parseFloat(info.allocated) || 0;

        try {
            var result = await Swal.fire({
                title: escapeHtml(info.year) + ' Annual Fund',
                html:
                    '<p class="mb-3 text-left text-sm text-gray-600">The whole assistance fund for FY ' +
                    escapeHtml(info.year) + '. Allocations per assistance type are taken out of it.</p>' +
                    (allocated > 0
                        ? '<p class="mb-3 text-left text-xs text-gray-500">Already allocated: <b>' +
                          peso(allocated.toFixed(2)) + '</b> — the fund cannot be lower than this.</p>'
                        : '') +
                    '<input id="swal-fund" type="number" step="0.01" min="' + Math.max(1, allocated) +
                    '" value="' + escapeHtml(info.amount) +
                    '" class="swal2-input" style="margin:0;width:100%" placeholder="e.g. 3000000">',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Save fund',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#15803d',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
                allowOutsideClick: false,
                customClass: { popup: 'swal-portal', title: 'swal-portal-title', htmlContainer: 'swal-portal-body' },
                didOpen: function () { document.getElementById('swal-fund').focus(); },
                preConfirm: function () {
                    var value = parseFloat(document.getElementById('swal-fund').value);
                    if (isNaN(value) || value < 1) {
                        Swal.showValidationMessage('Enter an amount of at least ₱1.00.');
                        return false;
                    }
                    if (value < allocated) {
                        Swal.showValidationMessage('Cannot be lower than the ' + peso(allocated.toFixed(2)) + ' already allocated.');
                        return false;
                    }
                    return value;
                },
            });

            if (!result.isConfirmed) return;

            post('{{ route('mswdo.budget.fund.store') }}', { fiscal_year: info.year, amount: result.value });
        } catch (error) {
            console.error('[budget] fund dialog failed:', error);
        } finally {
            delete trigger.dataset.busy;
        }
    });

    // ---- View releases --------------------------------------------------
    // Sub-allocations. The panel is already on the page as a template, so this
    // only has to show it — the forms inside post normally, which keeps the
    // delete confirmation and CSRF handling identical to the rest of the page.
    document.addEventListener('click', async function (event) {
        var trigger = event.target.closest('[data-budget-split]');
        if (!trigger) return;

        if (unavailable('The split panel is unavailable — please reload the page.')) return;
        if (trigger.dataset.busy === 'yes') return;
        trigger.dataset.busy = 'yes';

        try {
            var row  = trigger.closest('tr');
            var type = row ? (row.querySelector('td') || {}).textContent : '';

            await Swal.fire({
                title: 'Sub-allocations' + (type ? ' — ' + type.trim().split('\n')[0] : ''),
                html: bodyFrom('tpl-budget-split-' + trigger.dataset.budgetSplit),
                width: 560,
                showConfirmButton: false,
                showCloseButton: true,
                allowOutsideClick: true,
                customClass: { popup: 'swal-portal', title: 'swal-portal-title', htmlContainer: 'swal-portal-body' },
            });
        } finally {
            trigger.dataset.busy = 'no';
        }
    });

    document.addEventListener('click', async function (event) {
        var trigger = event.target.closest('[data-budget-view]');
        if (!trigger) return;

        if (unavailable('Release details are unavailable — please reload the page.')) return;

        // Same repeat-click guard the portal uses elsewhere: a second click
        // while the dialog is open closes the first one, which reads as the
        // button having done nothing.
        if (trigger.dataset.busy === 'yes') return;
        trigger.dataset.busy = 'yes';

        try {
            Swal.fire({
                title: 'Loading releases…',
                allowOutsideClick: false,
                didOpen: function () { Swal.showLoading(); },
            });

            var response = await fetch(trigger.dataset.budgetView, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            var data = await response.json();

            var summary =
                '<div class="grid grid-cols-3 gap-3 mb-4 text-left">' +
                '<div class="rounded-lg bg-gray-50 p-3"><p class="text-[11px] uppercase tracking-wide text-gray-500">Allocated</p>' +
                '<p class="text-sm font-bold text-gray-800">' + peso(escapeHtml(data.allocated)) + '</p></div>' +
                '<div class="rounded-lg bg-gray-50 p-3"><p class="text-[11px] uppercase tracking-wide text-gray-500">Released</p>' +
                '<p class="text-sm font-bold text-green-600">' + peso(escapeHtml(data.released)) + '</p></div>' +
                '<div class="rounded-lg bg-gray-50 p-3"><p class="text-[11px] uppercase tracking-wide text-gray-500">Balance</p>' +
                '<p class="text-sm font-bold text-teal-600">' + peso(escapeHtml(data.balance)) + '</p></div>' +
                '</div>';

            var body;
            if (!data.releases.length) {
                body = '<p class="py-6 text-sm text-gray-500">No funds have been released from this allocation yet.</p>';
            } else {
                body =
                    '<div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200">' +
                    '<table class="w-full text-left text-sm"><thead class="sticky top-0 bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">' +
                    '<tr><th class="px-3 py-2">Beneficiary</th><th class="px-3 py-2">Amount</th>' +
                    '<th class="px-3 py-2">Released</th><th class="px-3 py-2">By</th></tr></thead>' +
                    '<tbody class="divide-y divide-gray-100">' +
                    data.releases.map(function (release) {
                        return '<tr>' +
                            '<td class="px-3 py-2 font-medium text-gray-800">' + escapeHtml(release.beneficiary) +
                            '<span class="block text-[11px] font-normal text-gray-400">Request #' + escapeHtml(release.request_id) + '</span></td>' +
                            '<td class="px-3 py-2 tabular-nums text-green-600">' + peso(escapeHtml(release.amount)) + '</td>' +
                            '<td class="px-3 py-2 text-gray-600">' + escapeHtml(release.released_at) + '</td>' +
                            '<td class="px-3 py-2 text-gray-600">' + escapeHtml(release.released_by) + '</td>' +
                            '</tr>';
                    }).join('') +
                    '</tbody></table></div>';
            }

            Swal.fire({
                title: escapeHtml(data.assistance_type) + ' — FY ' + escapeHtml(data.fiscal_year),
                html: summary + body,
                width: 640,
                confirmButtonText: 'Close',
                confirmButtonColor: '#15803d',
                allowOutsideClick: false,
                customClass: { popup: 'swal-portal', title: 'swal-portal-title', htmlContainer: 'swal-portal-body' },
            });
        } catch (error) {
            console.error('[budget] could not load releases:', error);
            Swal.fire({
                icon: 'error',
                title: 'Could not load releases',
                text: 'Please check your connection and try again.',
                confirmButtonColor: '#15803d',
            });
        } finally {
            delete trigger.dataset.busy;
        }
    });

    // ---- Edit allocation ------------------------------------------------
    document.addEventListener('click', async function (event) {
        var trigger = event.target.closest('[data-budget-edit]');
        if (!trigger) return;

        if (unavailable('The edit dialog is unavailable — please reload the page.')) return;
        if (trigger.dataset.busy === 'yes') return;
        trigger.dataset.busy = 'yes';

        var info = trigger.dataset;
        var released = parseFloat(info.released) || 0;

        try {
            var result = await Swal.fire({
                title: 'Edit ' + escapeHtml(info.type) + ' allocation',
                // Unlike the Add form, which tops up, this sets the figure
                // outright. Spelling that out prevents the amount being
                // doubled by someone expecting the other behaviour.
                html:
                    '<p class="mb-3 text-left text-sm text-gray-600">This <b>replaces</b> the allocation for ' +
                    escapeHtml(info.type) + ' FY ' + escapeHtml(info.year) +
                    '. To add funds instead, use the Add Budget Allocation form.</p>' +
                    '<p class="mb-3 text-left text-xs text-gray-500">Already released: <b>' +
                    peso(released.toFixed(2)) + '</b> — the new amount cannot be lower than this.</p>' +
                    '<input id="swal-allocation" type="number" step="0.01" min="' + released +
                    '" value="' + escapeHtml(info.allocated) +
                    '" class="swal2-input" style="margin:0;width:100%" placeholder="0.00">',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Save changes',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#15803d',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
                allowOutsideClick: false,
                customClass: { popup: 'swal-portal', title: 'swal-portal-title', htmlContainer: 'swal-portal-body' },
                didOpen: function () {
                    var field = document.getElementById('swal-allocation');
                    field.focus();
                    field.select();
                },
                preConfirm: function () {
                    var value = parseFloat(document.getElementById('swal-allocation').value);
                    if (isNaN(value) || value < 0) {
                        Swal.showValidationMessage('Enter a valid amount.');
                        return false;
                    }
                    // Mirrors the server rule so the clerk is corrected here
                    // rather than after a round trip. The controller still
                    // enforces it — this check is only a convenience.
                    if (value < released) {
                        Swal.showValidationMessage('Cannot be lower than the ' + peso(released.toFixed(2)) + ' already released.');
                        return false;
                    }
                    return value;
                },
            });

            if (!result.isConfirmed) return;

            post(info.action, { _method: 'PUT', allocated_amount: result.value });
        } catch (error) {
            console.error('[budget] edit dialog failed:', error);
        } finally {
            delete trigger.dataset.busy;
        }
    });
})();
</script>

@endsection
