@extends('mswdo.layouts.app')
@section('title', 'Add Beneficiary')
@section('page-title', 'Add Beneficiary')

@section('breadcrumb')
    <a href="{{ route('mswdo.dashboard') }}">Home</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="{{ route('mswdo.beneficiaries.index') }}">Beneficiaries</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current">Add</span>
@endsection

@section('content')

<div class="max-w-3xl mx-auto">

    <div class="panel">

        {{-- A green banner header rather than centred grey text: it names the
             task, and gives the form a defined top edge. --}}
        <div class="flex items-start gap-4 border-b border-gray-100 bg-gradient-to-r from-teal-800 to-teal-700 px-6 py-5">
            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-yellow-400/20 ring-1 ring-yellow-300/40">
                <svg class="h-6 w-6 text-yellow-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-white">Add Beneficiary Profile</h2>
                <p class="mt-0.5 text-sm text-teal-100">
                    Register a new beneficiary by filling in their personal information below.
                </p>
            </div>
        </div>

        <div class="p-6">
            <h3 class="flex items-center gap-2.5 text-sm font-bold text-gray-800 mb-5 pb-4 border-b border-gray-100">
                <span class="grid h-7 w-7 place-items-center rounded-full bg-teal-700 text-xs font-bold leading-none text-white">1</span>
                Personal Information
                <span class="ml-auto text-xs font-semibold text-gray-500">
                    <span class="text-red-500">*</span> required
                </span>
            </h3>

            <form method="POST" action="{{ route('mswdo.beneficiaries.store') }}"
                  data-swal-confirm
                  data-swal-title="Register this beneficiary?"
                  data-swal-text="Please check the spelling of the name and barangay before saving."
                  data-swal-confirm-text="Yes, register">
                @csrf
                @include('mswdo.beneficiaries._form')

                <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                    <a href="{{ route('mswdo.beneficiaries.index') }}" class="btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Beneficiary
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
