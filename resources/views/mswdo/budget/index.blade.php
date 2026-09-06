@extends('mswdo.layouts.app')
@section('title', 'Budget Tracking')
@section('page-title', 'Budget and Release Tracking')

@section('content')

{{-- 4 stat boxes. Each carries a coloured rule on its top edge, matching the
     dashboard, so the two pages read as one system. --}}
@php
    $allocated = $budgets->sum('allocated_amount');
    $released  = $budgets->sum('total_released');
    $balance   = $budgets->sum('remaining_balance');
    // Share of the year's allocation already paid out — the one number that
    // says whether the fund is on track, and it was not shown anywhere.
    $usedPct   = $allocated > 0 ? min(100, round(($released / $allocated) * 100)) : 0;

    $stats = [
        ['label' => 'Total Allocated',   'value' => '₱ ' . number_format($allocated, 2), 'tone' => 'text-gray-900',  'rule' => 'from-teal-500 to-teal-600'],
        ['label' => 'Total Released',    'value' => '₱ ' . number_format($released, 2),  'tone' => 'text-green-700', 'rule' => 'from-green-500 to-green-600'],
        ['label' => 'Remaining Balance', 'value' => '₱ ' . number_format($balance, 2),   'tone' => $balance < 0 ? 'text-red-700' : 'text-teal-800', 'rule' => 'from-yellow-400 to-yellow-500'],
        ['label' => 'Fiscal Year',       'value' => $year,                               'tone' => 'text-gray-900',  'rule' => 'from-amber-400 to-amber-500'],
    ];
@endphp

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
    @foreach ($stats as $stat)
        <div class="panel relative p-5">
            <span class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $stat['rule'] }}" aria-hidden="true"></span>
            <p class="text-xs text-gray-600 font-semibold uppercase tracking-wide mb-1.5">{{ $stat['label'] }}</p>
            <p class="text-2xl font-bold tabular-nums {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
        </div>
    @endforeach
</div>

{{-- Overall utilisation. The per-type bars in the table below answer "which
     fund", but not "how much of the year's money is gone" — which is the
     question asked first. --}}
@if($allocated > 0)
    <div class="panel mb-5 p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
            <p class="text-sm font-bold text-gray-800">Overall utilisation for {{ $year }}</p>
            <p class="text-sm font-bold tabular-nums {{ $usedPct >= 90 ? 'text-red-700' : 'text-teal-800' }}">
                {{ $usedPct }}% released
            </p>
        </div>
        <div class="h-2.5 overflow-hidden rounded-full bg-gray-100">
            <div class="h-2.5 rounded-full transition-all {{ $usedPct >= 90 ? 'bg-red-500' : 'bg-gradient-to-r from-teal-600 to-yellow-400' }}"
                 style="width: {{ max($usedPct, 1) }}%"></div>
        </div>
        <p class="mt-2 text-xs text-gray-600 tabular-nums">
            ₱ {{ number_format($released, 2) }} released of ₱ {{ number_format($allocated, 2) }} allocated
        </p>
    </div>
@endif

{{-- The table now runs the full width. Squeezed into two thirds it could not
     fit its own action column, and the Delete button was cut off the edge. --}}
<div>

    {{-- Budget allocation table --}}
    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-title">Budget Allocation per Assistance Type</h3>
            <div class="flex flex-wrap items-center gap-3">
                <form method="GET" action="{{ route('mswdo.budget.index') }}" class="flex items-center gap-2">
                    <label for="budget-year" class="form-label mb-0 whitespace-nowrap">Fiscal Year</label>
                    {{-- A dropdown rather than a free number field: the years
                         that can hold records are a known list, and typing 2019
                         into a spinner only ever produced an empty table. --}}
                    <select id="budget-year" name="year" class="form-control w-28 py-1.5!" onchange="this.form.submit()">
                        @foreach ($years as $option)
                            <option value="{{ $option }}" @selected((int) $year === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </form>
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
                        <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $budget->assistance_type }}</td>
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
                                <p>No budget allocations recorded for {{ $year }}.</p>
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

</div>

{{-- The add form is now a modal rather than a permanent sidebar panel: it is
     used a handful of times a year, and it was taking a third of the page away
     from the table that gets read every day. --}}
@if(auth()->user()->role === 'mswdo_officer')
<template id="tpl-budget-add">
    <div class="text-left">
        {{-- Worth spelling out: saving against a type and year that already has
             an allocation tops it up, it does not replace the figure. --}}
        <p class="mb-4 rounded-lg bg-teal-50 px-4 py-3 text-sm text-teal-900">
            If an allocation already exists for this assistance type and fiscal year,
            this amount is <b>added</b> to it. To correct a figure instead, use Edit.
        </p>
        <div class="mb-4">
            <label for="add-type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">
                Assistance Type <span class="text-red-500">*</span>
            </label>
            <select id="add-type" class="swal2-input" style="margin:0;width:100%">
                <option value="">— Select type —</option>
                @foreach (['Medical','Burial','Educational','Emergency','Livelihood'] as $t)
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

    // ---- View releases --------------------------------------------------
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
