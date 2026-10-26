@extends('mswdo.layouts.app')
@section('title', 'Edit Request #' . $req->id)
@section('page-title', 'Edit Assistance Request')

@section('breadcrumb')
    <a href="{{ route('mswdo.dashboard') }}">Home</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="{{ route('mswdo.requests.index') }}">Assistance Requests</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="{{ route('mswdo.requests.show', $req) }}">#{{ $req->id }}</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current">Edit</span>
@endsection

@section('content')
<div class="max-w-2xl">
    <div class="panel p-6">
        <div class="mb-5 pb-4 border-b border-gray-100">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Request #{{ $req->id }}</p>
            <h3 class="mt-1 font-semibold text-gray-800">{{ $req->client->full_name }}</h3>
            <p class="text-sm text-gray-500">{{ $req->client->barangay }}</p>
        </div>

        <form method="POST" action="{{ route('mswdo.requests.update', $req) }}"
              data-swal-confirm
              data-swal-title="Save these changes?"
              data-swal-text="Request #{{ $req->id }} for <b>{{ $req->client->full_name }}</b> will be updated.">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Type of Assistance</label>
                    <select name="assistance_type" required class="form-control">
                        @foreach (['Medical','Burial','Educational','Livelihood'] as $type)
                            <option value="{{ $type }}" @selected(old('assistance_type', $req->assistance_type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('assistance_type')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">Amount Requested (₱)</label>
                    <input type="number" name="amount_requested" value="{{ old('amount_requested', $req->amount_requested) }}"
                           min="1" step="0.01" required class="form-control">
                    @error('amount_requested')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Purpose / Remarks</label>
                    <textarea name="purpose" rows="4" required class="form-control">{{ old('purpose', $req->purpose) }}</textarea>
                    @error('purpose')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                <a href="{{ route('mswdo.requests.show', $req) }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
