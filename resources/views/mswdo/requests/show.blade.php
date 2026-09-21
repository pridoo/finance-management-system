@extends('mswdo.layouts.app')
@section('title', 'Request #' . $req->id)
@section('page-title', 'Assistance Request Details')

@section('breadcrumb')
    <a href="{{ route('mswdo.dashboard') }}">Home</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="{{ route('mswdo.requests.index') }}">Assistance Requests</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current">#{{ $req->id }}</span>
@endsection

@section('content')

@php
    $statusStyles = match($req->status) {
        'Approved'     => ['badge' => 'bg-green-100 text-green-800 border-green-300',   'bar' => 'bg-green-500'],
        'Rejected'     => ['badge' => 'bg-red-100 text-red-800 border-red-300',         'bar' => 'bg-red-500'],
        'Released'     => ['badge' => 'bg-teal-100 text-teal-800 border-teal-300',      'bar' => 'bg-teal-600'],
        'For Release'  => ['badge' => 'bg-lime-100 text-lime-800 border-lime-300',      'bar' => 'bg-lime-500'],
        'Pending'      => ['badge' => 'bg-yellow-100 text-yellow-800 border-yellow-300','bar' => 'bg-yellow-400'],

        'Under Review' => ['badge' => 'bg-amber-100 text-amber-800 border-amber-300',   'bar' => 'bg-amber-500'],
        default        => ['badge' => 'bg-gray-100 text-gray-700 border-gray-300',      'bar' => 'bg-gray-400'],
    };
    $canEdit    = in_array($req->status, ['Pending', 'Under Review'], true);
    $isOfficer  = auth()->user()->role === 'mswdo_officer';
@endphp

{{-- The actions sit beside the page title, where the eye lands first.
     Previously they were buried in a row at the very bottom, so returning to
     the list meant scrolling past everything else to find it. --}}
@section('page-actions')
    <div class="flex flex-wrap items-center gap-2">
        {{-- The same details modal the two listings use, so the decision, its
             reason and the release record read identically wherever they are
             opened from. --}}
        <button type="button"
                data-approval-view="{{ route('mswdo.approvals.show', $req) }}"
                class="btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            View Details
        </button>

        @if($canEdit)
            <button type="button" data-request-edit class="btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Request
            </button>
        @endif

        @if($isOfficer && $req->status !== 'Released' && $req->status !== 'Rejected')
            <button type="button" data-approval-update class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Update Approval Status
            </button>
        @endif
    </div>
@endsection

<div class="max-w-4xl space-y-5">

    {{-- Request info --}}
    <div class="panel">
        {{-- A colour bar keyed to the status: the state of a request is the
             first thing anyone opening this page wants to know. --}}
        <div class="h-1.5 {{ $statusStyles['bar'] }}"></div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Assistance Request</p>
                <h3 class="mt-0.5 text-xl font-bold text-gray-900">Request #{{ $req->id }}</h3>
            </div>
            <span class="inline-flex items-center rounded-full border px-4 py-1.5 text-sm font-bold {{ $statusStyles['badge'] }}">
                {{ $req->status }}
            </span>
        </div>

        <dl class="grid grid-cols-1 gap-x-8 gap-y-5 px-6 py-6 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Beneficiary</dt>
                <dd class="mt-1 text-base font-bold text-gray-900">{{ $req->client->full_name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Barangay</dt>
                <dd class="mt-1 text-base text-gray-800">{{ $req->client->barangay }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Assistance Type</dt>
                <dd class="mt-1">
                    <span class="inline-flex items-center rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-sm font-semibold text-teal-800">
                        {{ $req->assistance_type }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Amount Requested</dt>
                <dd class="mt-1 text-lg font-bold tabular-nums text-gray-900">₱ {{ number_format($req->amount_requested, 2) }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Purpose</dt>
                <dd class="mt-1 rounded-lg bg-gray-50 px-4 py-3 text-base text-gray-800">{{ $req->purpose }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Filed By</dt>
                <dd class="mt-1 text-base text-gray-800">{{ $req->encoder->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Date Filed</dt>
                <dd class="mt-1 text-base text-gray-800">{{ $req->created_at->format('M d, Y h:i A') }}</dd>
            </div>
        </dl>
    </div>

    {{-- Approval info --}}
    @if($req->approval)
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-700">Approval Record</h3>
        </div>
        <dl class="grid grid-cols-1 gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Decision</dt>
                <dd class="mt-1">
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-sm font-bold {{ $req->approval->decision === 'Approved' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200' }}">
                        {{ $req->approval->decision }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Decided At</dt>
                <dd class="mt-1 text-base text-gray-800">{{ $req->approval->decided_at?->format('M d, Y') ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Remarks</dt>
                <dd class="mt-1 text-base text-gray-800">{{ $req->approval->remarks ?? '—' }}</dd>
            </div>
        </dl>
    </div>
    @endif

    {{-- Disbursement info --}}
    @if($req->disbursement)
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-700">Release Record</h3>
        </div>
        <dl class="grid grid-cols-1 gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Amount Released</dt>
                <dd class="mt-1 text-lg font-bold tabular-nums text-green-700">₱ {{ number_format($req->disbursement->amount_released, 2) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Released At</dt>
                <dd class="mt-1 text-base text-gray-800">{{ $req->disbursement->released_at?->format('M d, Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Released By</dt>
                <dd class="mt-1 text-base text-gray-800">{{ $req->disbursement->releasedBy->name ?? '—' }}</dd>
            </div>
        </dl>
    </div>
    @endif

</div>

{{-- Hidden templates. Building the modal bodies in Blade rather than as
     JavaScript strings keeps escaping, currency formatting and the budget list
     server-side, where they are already solved. --}}
@if($canEdit)
<template id="tpl-edit-request">
    <div class="text-left">
        <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="edit-type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Type of Assistance</label>
                <select id="edit-type" class="swal2-input" style="margin:0;width:100%">
                    @foreach (['Medical','Burial','Educational','Livelihood'] as $type)
                        <option value="{{ $type }}" @selected($req->assistance_type === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="edit-amount" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Amount Requested (₱)</label>
                <input id="edit-amount" type="number" min="1" step="0.01"
                       value="{{ number_format((float) $req->amount_requested, 2, '.', '') }}"
                       class="swal2-input" style="margin:0;width:100%">
            </div>
        </div>
        <div>
            <label for="edit-purpose" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Purpose / Remarks</label>
            <textarea id="edit-purpose" rows="3" class="swal2-textarea" style="margin:0;width:100%">{{ $req->purpose }}</textarea>
        </div>
    </div>
</template>
@endif

@if($isOfficer)
<template id="tpl-approval-decision">
    <div class="text-left">
        <p class="mb-4 text-sm text-gray-600">
            Recording a decision closes request #{{ $req->id }} to further editing.
        </p>
        <div class="mb-4">
            <label for="ap-decision" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Decision <span class="text-red-500">*</span></label>
            <select id="ap-decision" class="swal2-input" style="margin:0;width:100%">
                <option value="">— Select decision —</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
            </select>
        </div>
        <div>
            <label for="ap-remarks" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Remarks</label>
            <textarea id="ap-remarks" rows="3" class="swal2-textarea" style="margin:0;width:100%"></textarea>
        </div>
    </div>
</template>

<template id="tpl-approval-release">
    <div class="text-left">
        {{-- Releasing draws down a budget and cannot be undone in the system,
             so the warning is stated before the fields, not after. --}}
        <p class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Funds will be recorded as released to <b>{{ $req->client->full_name }}</b> and deducted
            from the selected budget. This cannot be undone.
        </p>
        <div class="mb-4">
            <label for="rl-budget" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Budget Source <span class="text-red-500">*</span></label>
            <select id="rl-budget" class="swal2-input" style="margin:0;width:100%">
                <option value="">— Select budget —</option>
                @foreach ($budgets as $b)
                    <option value="{{ $b->id }}">{{ $b->assistance_type }} {{ $b->fiscal_year }} — ₱ {{ number_format($b->remaining_balance, 2) }} remaining</option>
                @endforeach
            </select>
            @if($budgets->isEmpty())
                <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                    No current {{ strtolower($req->assistance_type) }} budget is available.
                    Add a budget allocation before releasing funds.
                </p>
            @endif
        </div>
        <div class="mb-4">
            <label for="rl-amount" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Amount Released (₱) <span class="text-red-500">*</span></label>
            <input id="rl-amount" type="number" min="0.01" step="0.01"
                   max="{{ number_format((float) $req->amount_requested, 2, '.', '') }}"
                   value="{{ number_format((float) $req->amount_requested, 2, '.', '') }}"
                   class="swal2-input" style="margin:0;width:100%">
            <p class="mt-1 text-xs text-gray-500">Cannot exceed the requested ₱ {{ number_format($req->amount_requested, 2) }}.</p>
        </div>
        <div>
            <label for="rl-remarks" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Remarks</label>
            <textarea id="rl-remarks" rows="2" class="swal2-textarea" style="margin:0;width:100%"></textarea>
        </div>
    </div>
</template>
@endif

{{--
    type="module" so this runs after the deferred bundle has defined
    window.Swal. A plain inline script runs during parsing, before SweetAlert
    exists, and every button here would silently do nothing.
--}}
<script type="module">
(function () {
    var CSRF = '{{ csrf_token() }}';

    var modalClasses = {
        popup: 'swal-portal',
        title: 'swal-portal-title',
        htmlContainer: 'swal-portal-body',
    };

    var ready = function (message) {
        if (typeof Swal === 'undefined') {
            console.error('[requests] SweetAlert did not load — run `npm run build`.');
            window.alert(message);
            return false;
        }
        return true;
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

        add('_token', CSRF);
        Object.keys(fields).forEach(function (name) { add(name, fields[name]); });

        // The question has already been asked in the dialog above; without this
        // the global handler in app.js would stack a second, vaguer one.
        form.dataset.swalConfirmed = 'yes';
        document.body.appendChild(form);
        HTMLFormElement.prototype.submit.call(form);
    };

    var guard = function (trigger) {
        if (trigger.dataset.busy === 'yes') return false;
        trigger.dataset.busy = 'yes';
        return true;
    };

    // ---- Edit request ---------------------------------------------------
    document.addEventListener('click', async function (event) {
        var trigger = event.target.closest('[data-request-edit]');
        if (!trigger) return;
        if (!ready('The edit form is unavailable — please reload the page.')) return;
        if (!guard(trigger)) return;

        try {
            var result = await Swal.fire({
                title: 'Edit Request #{{ $req->id }}',
                html: bodyFrom('tpl-edit-request'),
                width: 620,
                showCancelButton: true,
                confirmButtonText: 'Save changes',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#15803d',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
                allowOutsideClick: false,
                focusConfirm: false,
                customClass: modalClasses,
                preConfirm: function () {
                    var amount  = parseFloat(document.getElementById('edit-amount').value);
                    var purpose = document.getElementById('edit-purpose').value.trim();

                    if (isNaN(amount) || amount < 1) {
                        Swal.showValidationMessage('Enter an amount of at least ₱1.00.');
                        return false;
                    }
                    if (!purpose) {
                        Swal.showValidationMessage('Purpose is required.');
                        return false;
                    }
                    return {
                        assistance_type: document.getElementById('edit-type').value,
                        amount_requested: amount,
                        purpose: purpose,
                    };
                },
            });

            if (!result.isConfirmed) return;

            post('{{ route('mswdo.requests.update', $req) }}', {
                _method: 'PUT',
                assistance_type: result.value.assistance_type,
                amount_requested: result.value.amount_requested,
                purpose: result.value.purpose,
            });
        } catch (error) {
            console.error('[requests] edit dialog failed:', error);
        } finally {
            delete trigger.dataset.busy;
        }
    });

    // ---- Update approval status -----------------------------------------
    document.addEventListener('click', async function (event) {
        var trigger = event.target.closest('[data-approval-update]');
        if (!trigger) return;
        if (!ready('The approval form is unavailable — please reload the page.')) return;
        if (!guard(trigger)) return;

        var status = @json($req->status);

        try {
            // Which question to ask depends on where the request already is in
            // the workflow, so the one button covers all three steps rather
            // than showing the clerk stages that do not apply yet.
            if (status === 'Pending' || status === 'Under Review') {
                var decision = await Swal.fire({
                    title: 'Record Approval Decision',
                    html: bodyFrom('tpl-approval-decision'),
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
                    preConfirm: function () {
                        var value = document.getElementById('ap-decision').value;
                        if (!value) {
                            Swal.showValidationMessage('Select Approved or Rejected.');
                            return false;
                        }
                        return { decision: value, remarks: document.getElementById('ap-remarks').value.trim() };
                    },
                });

                if (!decision.isConfirmed) return;

                post('{{ route('mswdo.approvals.store') }}', {
                    request_id: '{{ $req->id }}',
                    decision: decision.value.decision,
                    remarks: decision.value.remarks,
                });
                return;
            }

            if (status === 'Approved') {
                var queued = await Swal.fire({
                    icon: 'question',
                    title: 'Mark as for release?',
                    html: 'Request #{{ $req->id }} for <b>{{ $req->client->full_name }}</b> will be queued for fund release.',
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

                post('{{ route('mswdo.approvals.update', $req) }}', {
                    _method: 'PUT',
                    status: 'For Release',
                });
                return;
            }

            if (status === 'For Release') {
                var maxAmount = {{ (float) $req->amount_requested }};
                var hasBudget = {{ $budgets->isEmpty() ? 'false' : 'true' }};

                var release = await Swal.fire({
                    title: 'Record Fund Release',
                    html: bodyFrom('tpl-approval-release'),
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
                        if (!hasBudget) {
                            Swal.showValidationMessage('No budget is available for this assistance type.');
                            return false;
                        }
                        var budgetId = document.getElementById('rl-budget').value;
                        var amount   = parseFloat(document.getElementById('rl-amount').value);

                        if (!budgetId) {
                            Swal.showValidationMessage('Select the budget the funds come from.');
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

                post('{{ route('mswdo.approvals.update', $req) }}', {
                    _method: 'PUT',
                    status: 'Released',
                    budget_id: release.value.budget_id,
                    amount_released: release.value.amount_released,
                    remarks: release.value.remarks,
                });
            }
        } catch (error) {
            console.error('[requests] approval dialog failed:', error);
        } finally {
            delete trigger.dataset.busy;
        }
    });
})();
</script>

@include('mswdo.partials.request-detail-modal')

@endsection
