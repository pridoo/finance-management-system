@extends('mswdo.layouts.app')
@section('title', 'Edit Beneficiary')
@section('page-title', 'Edit Beneficiary')

@section('breadcrumb')
    <a href="{{ route('mswdo.dashboard') }}">Home</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="{{ route('mswdo.beneficiaries.index') }}">Beneficiaries</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="{{ route('mswdo.beneficiaries.show', $client) }}">{{ $client->full_name }}</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current">Edit</span>
@endsection

@section('content')

<div class="max-w-3xl mx-auto">

    <div class="panel">

        <div class="flex items-start gap-4 border-b border-gray-100 bg-gradient-to-r from-teal-800 to-teal-700 px-6 py-5">
            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-yellow-400/20 ring-1 ring-yellow-300/40">
                <svg class="h-5 w-5 text-yellow-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-yellow-300">Editing beneficiary</p>
                <h2 class="mt-0.5 truncate text-lg font-bold text-white">{{ $client->full_name }}</h2>
            </div>
        </div>

        <div class="p-6">
            <form method="POST" action="{{ route('mswdo.beneficiaries.update', $client) }}"
                  data-swal-confirm
                  data-swal-name="{{ $client->full_name }}"
                  data-swal-title="Save these changes?">
                @csrf
                @method('PUT')
                @include('mswdo.beneficiaries._form')

                <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                    <a href="{{ route('mswdo.beneficiaries.show', $client) }}" class="btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Update Beneficiary
                    </button>
                </div>
            </form>
        </div>

        @if(!$client->assistanceRequests()->exists())
            {{-- Deletion sits in its own marked-off section rather than loose at
                 the foot of the form, where it was one stray click away from
                 the Update button. It only appears at all while the record has
                 no assistance history. --}}
            <div class="border-t border-red-100 bg-red-50/60 px-6 py-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-red-900">Delete this beneficiary</p>
                        <p class="mt-0.5 text-sm text-red-800">
                            Permanent, and available only because this record has no assistance requests.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('mswdo.beneficiaries.destroy', $client) }}"
                          data-swal-confirm="danger"
                          data-swal-name="{{ $client->full_name }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-danger shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete Beneficiary
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

@endsection
