@extends('mswdo.layouts.app')
@section('title', 'Approvals')
@section('page-title', 'Approval Status Recording')

@section('content')

@php
    // How many rows on this page still have a decision outstanding. This is
    // what the page is for, so it is stated rather than left to be counted.
    $awaiting = $requests->getCollection()
        ->reject(fn ($r) => in_array($r->status, ['Rejected', 'Released'], true))
        ->count();
@endphp

<div class="panel">

    <div class="flex flex-wrap items-center gap-2 px-5 pt-4">
        <h2 class="panel-title">Requests</h2>
        <span class="pill bg-teal-50 text-teal-800">
            {{ number_format($requests->total()) }}
            {{ $requests->total() === 1 ? 'record' : 'records' }}
        </span>
        @if($awaiting > 0)
            <span class="pill bg-amber-50 text-amber-800">{{ $awaiting }} awaiting a decision</span>
        @endif
    </div>

    {{-- Status pill tabs --}}
    <div class="toolbar">
        <div class="flex flex-wrap gap-1.5">
            @php
                $statuses = ['', 'Pending', 'Under Review', 'Approved', 'Rejected', 'For Release', 'Released'];
                $labels   = ['' => 'All', 'Pending' => 'Pending', 'Under Review' => 'Under Review',
                             'Approved' => 'Approved', 'Rejected' => 'Rejected',
                             'For Release' => 'For Release', 'Released' => 'Released'];
                $current  = request('status', '');
            @endphp
            @foreach ($statuses as $s)
                {{-- Same treatment as the Requests tabs, so the two lists are
                     filtered the same way and look it. --}}
                <a href="{{ route('mswdo.approvals.index', $s ? ['status' => $s] : []) }}"
                   @if($current === $s) aria-current="true" @endif
                   class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs transition-all duration-150
                          {{ $current === $s
                              ? 'bg-teal-700 text-white font-bold shadow-sm ring-2 ring-yellow-300 ring-offset-1'
                              : 'bg-gray-100 text-gray-700 font-semibold hover:bg-teal-50 hover:text-teal-800' }}">
                    {{ $labels[$s] }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3 text-left">#</th>
                    <th class="px-5 py-3 text-left">Beneficiary</th>
                    <th class="px-5 py-3 text-left">Source of Fund</th>
                    <th class="px-5 py-3 text-left">Amount Req.</th>
                    <th class="px-5 py-3 text-left">Current Status</th>
                    <th class="px-5 py-3 text-left">Date Filed</th>
                    <th class="px-5 py-3 text-left">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($requests as $req)
                    <tr class="hover:bg-gray-50 transition-colors">
                        {{-- Continues across pages rather than restarting at 1,
                             so "the third one down on page 2" stays unambiguous
                             when two people are talking about the same list. --}}
                        <td class="px-5 py-3.5 text-gray-400 text-xs tabular-nums">{{ $requests->firstItem() + $loop->index }}</td>
                        <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $req->client->full_name }}</td>
                        <td class="px-5 py-3.5">
                            <span class="pill bg-gray-100 text-gray-700 font-semibold">{{ $req->assistance_type }}</span>
                        </td>
                        <td class="px-5 py-3.5 font-semibold text-gray-900 tabular-nums">₱ {{ number_format($req->amount_requested, 2) }}</td>
                        <td class="px-5 py-3.5">
                            @php
                                $c = match($req->status) {
                                    'Approved'    => 'bg-green-100 text-green-800 ring-green-300',
                                    'Rejected'    => 'bg-red-100 text-red-800 ring-red-300',
                                    'Released'    => 'bg-teal-100 text-teal-800 ring-teal-300',
                                    'For Release' => 'bg-lime-100 text-lime-800 ring-lime-300',
                                    'Pending'     => 'bg-yellow-100 text-yellow-800 ring-yellow-300',

                                    'Under Review'=> 'bg-amber-100 text-amber-800 ring-amber-300',
                                    default       => 'bg-gray-100 text-gray-700 ring-gray-300',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold ring-1 ring-inset {{ $c }}">
                                {{ $req->status }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-gray-500 whitespace-nowrap">{{ $req->created_at->format('M d, Y') }}</td>
                        <td class="px-5 py-3.5">
                            @php
                                $rowBudgets = $budgetsByType[$req->assistance_type] ?? collect();
                                $isFinal    = in_array($req->status, ['Rejected', 'Released'], true);
                                // Staff can release but not decide: a request
                                // still waiting for a decision has no action
                                // for them.
                                $awaitingDecision = in_array($req->status, ['Pending', 'Under Review'], true)
                                    && auth()->user()->role !== 'mswdo_officer';
                            @endphp
                            <div class="flex flex-wrap items-center gap-2 whitespace-nowrap">
                            {{-- Always available, including on rejected and
                                 released rows: the reason behind a decision is
                                 exactly what someone opens a closed request
                                 for, and "No action needed" alone hid it. --}}
                            <button type="button"
                                    data-approval-view="{{ route('mswdo.approvals.show', $req) }}"
                                    class="btn-soft-plain">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                View
                            </button>

                            @if($isFinal)
                                {{-- Nothing left to record: a rejected or
                                     released request is the end of the line. --}}
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-500">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Closed
                                </span>
                            @elseif($awaitingDecision)
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800"
                                      title="Only the MSWDO Officer can approve or reject a request.">
                                    Awaiting Officer's decision
                                </span>
                            @else
                                <button type="button"
                                        data-approval-row
                                        data-status="{{ $req->status }}"
                                        data-id="{{ $req->id }}"
                                        data-name="{{ $req->client->full_name }}"
                                        data-type="{{ $req->assistance_type }}"
                                        data-max="{{ number_format((float) $req->amount_requested, 2, '.', '') }}"
                                        data-store-url="{{ route('mswdo.approvals.store') }}"
                                        data-update-url="{{ route('mswdo.approvals.update', $req) }}"
                                        data-approved-budget="{{ $req->approval?->budget_id }}"
                                        data-budgets="{{ json_encode($rowBudgets->map(fn ($b) => [
                                            'id'      => $b->id,
                                            'label'   => $b->assistance_type . ' ' . $b->fiscal_year
                                                         . ' — ₱ ' . number_format($b->remaining_balance, 2) . ' remaining',
                                        ])->values()) }}"
                                        {{-- Solid rather than soft: recording a
                                             decision is what this page is for,
                                             and it should be the most visible
                                             control in the row. --}}
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-teal-700 px-3 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Update Status
                                </button>
                            @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                @if($current)
                                    <p>No requests with the status “{{ $current }}”.</p>
                                    <a href="{{ route('mswdo.approvals.index') }}" class="btn-soft mt-1">Show all requests</a>
                                @else
                                    <p>Nothing waiting for a decision.</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- The count shows even on a single page: "25 of 25" is what tells the
         officer there may be more behind a filter. --}}
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


{{--
    The status update happens here rather than on a separate page: the clerk
    works down this list, and losing their place and their filter for every
    single decision was the slowest part of the job.

    type="module" so it runs after the deferred bundle defines window.Swal.
--}}
<script type="module">
(function () {
    var CSRF = '{{ csrf_token() }}';

    var modalClasses = {
        popup: 'swal-portal',
        title: 'swal-portal-title',
        htmlContainer: 'swal-portal-body',
    };

    var escapeHtml = function (value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    };

    var field = function (id, label, control) {
        return '<div class="mb-4 text-left">' +
            '<label for="' + id + '" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">' +
            label + '</label>' + control + '</div>';
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

        add('_token', CSRF);
        Object.keys(fields).forEach(function (name) { add(name, fields[name]); });

        // Already confirmed in the dialog; without this the global handler in
        // app.js would ask a second, vaguer question on top.
        form.dataset.swalConfirmed = 'yes';
        document.body.appendChild(form);
        HTMLFormElement.prototype.submit.call(form);
    };

    document.addEventListener('click', async function (event) {
        var trigger = event.target.closest('[data-approval-row]');
        if (!trigger) return;

        if (typeof Swal === 'undefined') {
            console.error('[approvals] SweetAlert did not load — run `npm run build`.');
            window.alert('The status form is unavailable — please reload the page.');
            return;
        }

        // Repeat-click guard: a second click closes the dialog the first just
        // opened, which reads as the button doing nothing.
        if (trigger.dataset.busy === 'yes') return;
        trigger.dataset.busy = 'yes';

        var info = trigger.dataset;
        var name = escapeHtml(info.name);

        try {
            // Which question to ask depends on where the request already is in
            // the workflow, so one button covers all three steps.
            if (info.status === 'Pending' || info.status === 'Under Review') {
                var fundSources = [];
                try {
                    fundSources = JSON.parse(info.budgets || '[]');
                } catch (parseError) {
                    console.error('[approvals] could not read funding sources:', parseError);
                }
                var fundOptions = '<option value="">— Select funding source —</option>' +
                    fundSources.map(function (budget) {
                        return '<option value="' + escapeHtml(budget.id) + '">' + escapeHtml(budget.label) + '</option>';
                    }).join('');

                // Approving names the funding source the money will come from;
                // rejecting asks for the reason instead. Only the one that
                // applies to the chosen decision is shown.
                var decision = await Swal.fire({
                    title: 'Record Approval Decision',
                    html:
                        '<p class="mb-4 text-left text-sm text-gray-600">Request #' + escapeHtml(info.id) +
                        ' for <b>' + name + '</b>. Recording a decision closes it to further editing.</p>' +
                        field('ap-decision', 'Decision <span class="text-red-500">*</span>',
                            '<select id="ap-decision" class="swal2-input" style="margin:0;width:100%">' +
                            '<option value="">— Select decision —</option>' +
                            '<option value="Approved">Approved</option>' +
                            '<option value="Rejected">Rejected</option></select>') +
                        '<div id="ap-fund-wrap" style="display:none">' +
                        field('ap-fund', 'Funding Source <span class="text-red-500">*</span>',
                            '<select id="ap-fund" class="swal2-input" style="margin:0;width:100%">' + fundOptions + '</select>' +
                            (fundSources.length ? '' : '<p class="mt-1 text-left text-xs text-red-700">No current ' +
                                escapeHtml(info.type.toLowerCase()) + ' funding source. Add one in Budget Tracking first.</p>')) +
                        '</div>' +
                        '<div id="ap-reason-wrap" style="display:none">' +
                        field('ap-remarks', 'Reason for Rejection',
                            '<textarea id="ap-remarks" rows="3" class="swal2-textarea" style="margin:0;width:100%"></textarea>') +
                        '</div>',
                    width: 560,
                    showCancelButton: true,
                    confirmButtonText: 'Save decision',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#15803d',
                    cancelButtonColor: '#64748b',
                    reverseButtons: true,
                    allowOutsideClick: false,
                    focusConfirm: false,
                    customClass: modalClasses,
                    didOpen: function () {
                        var select = document.getElementById('ap-decision');
                        select.addEventListener('change', function () {
                            document.getElementById('ap-fund-wrap').style.display = select.value === 'Approved' ? '' : 'none';
                            document.getElementById('ap-reason-wrap').style.display = select.value === 'Rejected' ? '' : 'none';
                        });
                    },
                    preConfirm: function () {
                        var value = document.getElementById('ap-decision').value;
                        if (!value) {
                            Swal.showValidationMessage('Select Approved or Rejected.');
                            return false;
                        }
                        var fund = document.getElementById('ap-fund').value;
                        if (value === 'Approved' && !fund) {
                            Swal.showValidationMessage('Select the funding source before approving.');
                            return false;
                        }
                        return {
                            decision: value,
                            budget_id: value === 'Approved' ? fund : '',
                            remarks: value === 'Rejected' ? document.getElementById('ap-remarks').value.trim() : '',
                        };
                    },
                });

                if (!decision.isConfirmed) return;

                post(info.storeUrl, {
                    request_id: info.id,
                    decision: decision.value.decision,
                    budget_id: decision.value.budget_id,
                    remarks: decision.value.remarks,
                });
                return;
            }

            if (info.status === 'Approved') {
                var queued = await Swal.fire({
                    icon: 'question',
                    title: 'Mark as for release?',
                    html: 'Request #' + escapeHtml(info.id) + ' for <b>' + name +
                          '</b> will be queued for fund release.',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, mark it',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#15803d',
                    cancelButtonColor: '#64748b',
                    reverseButtons: true,
                    allowOutsideClick: false,
                    customClass: modalClasses,
                });

                if (!queued.isConfirmed) return;

                post(info.updateUrl, { _method: 'PUT', status: 'For Release' });
                return;
            }

            if (info.status === 'For Release') {
                var budgets = [];
                try {
                    budgets = JSON.parse(info.budgets || '[]');
                } catch (parseError) {
                    console.error('[approvals] could not read budget options:', parseError);
                }
                var maxAmount = parseFloat(info.max) || 0;

                // Starts on the funding source chosen at approval.
                var options = '<option value="">— Select funding source —</option>' +
                    budgets.map(function (budget) {
                        var picked = String(budget.id) === String(info.approvedBudget) ? ' selected' : '';
                        return '<option value="' + escapeHtml(budget.id) + '"' + picked + '>' + escapeHtml(budget.label) + '</option>';
                    }).join('');

                var warning = budgets.length
                    ? ''
                    : '<p class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-left text-xs text-red-700">' +
                      'No current ' + escapeHtml(info.type.toLowerCase()) + ' funding source is available. ' +
                      'Add a fund source before releasing funds.</p>';

                var release = await Swal.fire({
                    title: 'Record Fund Release',
                    html:
                        '<p class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-left text-sm text-amber-800">' +
                        'Funds will be recorded as released to <b>' + name + '</b> and deducted from the ' +
                        'selected funding source. This cannot be undone.</p>' +
                        warning +
                        field('rl-budget', 'Funding Source <span class="text-red-500">*</span>',
                            '<select id="rl-budget" class="swal2-input" style="margin:0;width:100%">' + options + '</select>') +
                        field('rl-amount', 'Amount Released (₱) <span class="text-red-500">*</span>',
                            '<input id="rl-amount" type="number" min="0.01" step="0.01" max="' + maxAmount +
                            '" value="' + maxAmount.toFixed(2) + '" class="swal2-input" style="margin:0;width:100%">' +
                            '<p class="mt-1 text-xs text-gray-500">Cannot exceed the requested ₱ ' +
                            maxAmount.toFixed(2) + '.</p>') +
                        field('rl-remarks', 'Remarks',
                            '<textarea id="rl-remarks" rows="2" class="swal2-textarea" style="margin:0;width:100%"></textarea>'),
                    width: 620,
                    showCancelButton: true,
                    confirmButtonText: 'Record release',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#64748b',
                    reverseButtons: true,
                    allowOutsideClick: false,
                    focusConfirm: false,
                    customClass: modalClasses,
                    preConfirm: function () {
                        if (!budgets.length) {
                            Swal.showValidationMessage('No funding source is available for this assistance.');
                            return false;
                        }
                        var budgetId = document.getElementById('rl-budget').value;
                        var amount   = parseFloat(document.getElementById('rl-amount').value);

                        if (!budgetId) {
                            Swal.showValidationMessage('Select the funding source the funds come from.');
                            return false;
                        }
                        if (isNaN(amount) || amount <= 0) {
                            Swal.showValidationMessage('Enter the amount released.');
                            return false;
                        }
                        // The controller enforces this too; catching it here
                        // saves a round trip that loses the typed remarks.
                        if (amount > maxAmount) {
                            Swal.showValidationMessage('Cannot exceed the requested ₱' + maxAmount.toFixed(2) + '.');
                            return false;
                        }
                        return {
                            budget_id: budgetId,
                            amount_released: amount,
                            remarks: document.getElementById('rl-remarks').value.trim(),
                        };
                    },
                });

                if (!release.isConfirmed) return;

                post(info.updateUrl, {
                    _method: 'PUT',
                    status: 'Released',
                    budget_id: release.value.budget_id,
                    amount_released: release.value.amount_released,
                    remarks: release.value.remarks,
                });
            }
        } catch (error) {
            console.error('[approvals] status dialog failed:', error);
        } finally {
            delete trigger.dataset.busy;
        }
    });
})();
</script>

@include('mswdo.partials.request-detail-modal')

@endsection
