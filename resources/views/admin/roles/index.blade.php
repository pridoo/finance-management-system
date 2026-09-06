@extends('mswdo.layouts.app')
@section('title', 'Roles')
@section('page-title', 'Roles & Permissions')

@section('breadcrumb')
    <a href="{{ route('admin.users.index') }}">User Management</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current">Roles</span>
@endsection

@section('page-actions')
    <a href="{{ route('admin.roles.create') }}" class="btn-primary whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Role
    </a>
@endsection

@section('content')

@if($errors->has('role'))
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3.5 text-sm text-red-800 shadow-sm" role="alert">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
        </svg>
        <p class="font-semibold">{{ $errors->first('role') }}</p>
    </div>
@endif

<div class="panel">

    <div class="panel-head">
        <div>
            <h3 class="panel-title">Roles</h3>
            <p class="mt-1 text-xs text-gray-600">
                A role sets the default sections its users can open. Individual users can still be adjusted.
            </p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-700 font-semibold uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3 text-left">Role</th>
                    <th class="px-5 py-3 text-left">Default access</th>
                    <th class="px-5 py-3 text-left">Users</th>
                    <th class="px-5 py-3 text-left">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($roles as $role)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <span class="font-medium text-gray-900">{{ $role->label }}</span>
                            @if($role->is_system)
                                <span class="ml-1.5 inline-flex whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-600">
                                    Built-in
                                </span>
                            @endif
                            <p class="text-xs text-gray-500 mt-0.5">{{ $role->key }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            @if($role->isAdminRole())
                                <span class="text-xs text-gray-600">Account management only</span>
                            @else
                                <div class="flex flex-wrap items-center gap-1">
                                    @forelse($role->permissions ?? [] as $m)
                                        <span class="inline-flex whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-700">
                                            {{ $modules[$m] ?? $m }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-red-600">No access</span>
                                    @endforelse
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-800 tabular-nums">{{ $role->users_count }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap items-center gap-2 whitespace-nowrap">
                                {{-- The administrator role outranks the MSWDO
                                     officer, so they may read it but not edit
                                     it. The controller refuses the same. --}}
                                @unless(auth()->user()->canAssignRole($role->key))
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500"
                                          title="Only an administrator can change the administrator role">
                                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        Admin only
                                    </span>
                                @else
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn-secondary px-3 py-1.5 text-xs">Edit</a>

                                {{-- Built-ins are referenced by key throughout
                                     the app, so only custom roles can go. --}}
                                @unless($role->is_system)
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="inline"
                                          data-swal-confirm="danger"
                                          data-swal-title="Delete {{ $role->label }}?"
                                          data-swal-text="This role will no longer be available when assigning users."
                                          data-swal-confirm-text="Yes, delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="px-3 py-1.5 text-xs font-medium rounded-lg border border-red-200 text-red-700 hover:bg-red-50 transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                @endunless
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
