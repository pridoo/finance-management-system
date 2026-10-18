@extends('mswdo.layouts.app')
@section('title', 'Update Approval')
@section('page-title', 'Update Approval Status')

@section('breadcrumb')
    <a href="{{ route('mswdo.dashboard') }}">Home</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="{{ route('mswdo.approvals.index') }}">Approvals</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current">Request #{{ $req->id }}</span>
@endsection

@section('content')

<div class="max-w-2xl space-y-4">

    {{-- Request summary --}}
    <div class="panel p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700">Request #{{ $req->id }}</h3>
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
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold ring-1 ring-inset {{ $c }}">{{ $req->status }}</span>
        </div>
        <dl class="grid grid-cols-2 gap-2 text-sm">
            <div><dt class="text-xs text-gray-400">Beneficiary</dt><dd class="font-medium text-gray-800">{{ $req->client->full_name }}</dd></div>
            <div><dt class="text-xs text-gray-400">Source of Fund</dt><dd class="text-gray-700">{{ $req->assistance_type }}</dd></div>
            <div><dt class="text-xs text-gray-400">Amount Req.</dt><dd class="font-semibold text-gray-800">₱ {{ number_format($req->amount_requested, 2) }}</dd></div>
            <div><dt class="text-xs text-gray-400">Date Filed</dt><dd class="text-gray-600">{{ $req->created_at->format('M d, Y') }}</dd></div>
        </dl>
    </div>

    {{-- Step 1: Record approval decision --}}
    @if(in_array($req->status, ['Pending', 'Under Review']))
    <div class="panel p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Record Approval Decision</h3>
        {{-- A decision closes the request to further editing, so it is worth a
             deliberate second step rather than a single click. --}}
        <form method="POST" action="{{ route('mswdo.approvals.store') }}"
              data-swal-confirm
              data-swal-title="Record this decision?"
              data-swal-text="The decision for request #{{ $req->id }} (<b>{{ $req->client->full_name }}</b>) will be saved and the request can no longer be edited."
              data-swal-confirm-text="Yes, record it">
            @csrf
            <input type="hidden" name="request_id" value="{{ $req->id }}">
            <div class="space-y-3">
                <div>
                    <label class="form-label">Decision <span class="text-red-500">*</span></label>
                    <select name="decision" required class="form-control">
                        <option value="">— Select Decision —</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" rows="2" class="form-control"></textarea>
                </div>
                <button type="submit"
                        class="btn-primary w-full">
                    Save Decision
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Step 2: Set For Release / Record Release --}}
    @if($req->status === 'Approved')
    <div class="panel p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Mark as For Release</h3>
        <form method="POST" action="{{ route('mswdo.approvals.update', $req) }}"
              data-swal-confirm
              data-swal-title="Mark as for release?"
              data-swal-text="Request #{{ $req->id }} for <b>{{ $req->client->full_name }}</b> will be queued for fund release."
              data-swal-confirm-text="Yes, mark it">
            @csrf
            @method('PUT')
            <input type="hidden" name="status" value="For Release">
            <button type="submit"
                    class="btn-primary">
                Mark as For Release
            </button>
        </form>
    </div>
    @endif

    @if($req->status === 'For Release')
    <div class="panel p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Record Fund Release</h3>
        {{-- Releasing draws down the budget and cannot be undone in the system,
             so this one names the beneficiary and warns plainly. --}}
        <form method="POST" action="{{ route('mswdo.approvals.update', $req) }}"
              data-swal-confirm
              data-swal-title="Record this fund release?"
              data-swal-text="Funds will be recorded as released to <b>{{ $req->client->full_name }}</b> and deducted from the selected budget. This cannot be undone."
              data-swal-confirm-text="Yes, record the release">
            @csrf
            @method('PUT')
            <input type="hidden" name="status" value="Released">
            <div class="space-y-3">
                <div>
                    <label class="form-label">Budget Source <span class="text-red-500">*</span></label>
                    <select name="budget_id" required class="form-control">
                        <option value="">— Select Budget —</option>
                        @foreach ($budgets as $b)
                            <option value="{{ $b->id }}">{{ $b->assistance_type }} {{ $b->fiscal_year }} — ₱ {{ number_format($b->remaining_balance, 2) }} remaining</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Amount Released (₱) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount_released" value="{{ old('amount_released', $req->amount_requested) }}" min="0.01" max="{{ $req->amount_requested }}" step="0.01" required
                           class="form-control">
                </div>
                <div>
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" rows="2" class="form-control"></textarea>
                </div>
                @if($budgets->isEmpty())
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">No current {{ strtolower($req->assistance_type) }} budget is available. Add a budget allocation before releasing funds.</p>
                @endif
                <button type="submit"
                        @disabled($budgets->isEmpty())
                        class="btn-primary w-full">
                    Record Release
                </button>
            </div>
        </form>
    </div>
    @endif

    <a href="{{ route('mswdo.approvals.index') }}" class="inline-block text-sm text-gray-500 hover:text-gray-700 font-medium px-1">
        ← Back to Approvals
    </a>
</div>

@endsection
