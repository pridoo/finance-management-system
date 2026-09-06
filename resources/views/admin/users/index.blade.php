@extends('mswdo.layouts.app')
@section('title', 'User Management')
@section('page-title', 'User Management')

@section('content')

@if($errors->has('user'))
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3.5 text-sm text-red-800 shadow-sm" role="alert">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
        </svg>
        <p class="font-semibold">{{ $errors->first('user') }}</p>
    </div>
@endif

{{-- The two navigation actions sit beside the page title rather than inside
     the filter row, where they read as though they were part of the search. --}}
@section('page-actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.roles.index') }}" class="btn-secondary whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            Roles
        </a>
        <a href="{{ route('admin.users.create') }}" class="btn-primary whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            New User
        </a>
    </div>
@endsection

<div class="panel">

    <div class="flex flex-wrap items-center gap-2 px-5 pt-4">
        <h2 class="panel-title">Accounts</h2>
        <span class="pill bg-teal-50 text-teal-800">
            {{ number_format($users->total()) }}
            {{ $users->total() === 1 ? 'account' : 'accounts' }}
        </span>
    </div>

    {{-- Filters --}}
    <div class="toolbar">
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-end gap-3 w-full">
            <div class="min-w-48 flex-1">
                <label for="user-search" class="form-label">Search</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input id="user-search" type="text" name="search" value="{{ request('search') }}"
                           placeholder="Name or email" class="form-control pl-10">
                </div>
            </div>
            <div class="min-w-36">
                <label for="user-role" class="form-label">Role</label>
                <select id="user-role" name="role" class="form-control">
                    <option value="">All roles</option>
                    @foreach($roles as $r)
                        <option value="{{ $r->key }}" @selected(request('role') === $r->key)>{{ $r->label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-32">
                <label for="user-status" class="form-label">Status</label>
                <select id="user-status" name="status" class="form-control">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="disabled" @selected(request('status') === 'disabled')>Disabled</option>
                </select>
            </div>
            <div class="shrink-0 flex items-center gap-2 pb-0.5">
                <button type="submit" class="btn-primary">Filter</button>
                @if(request()->filled('search') || request()->filled('role') || request()->filled('status'))
                    <a href="{{ route('admin.users.index') }}" class="btn-secondary">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-700 font-semibold uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3 text-left">Name</th>
                    <th class="px-5 py-3 text-left">Email</th>
                    <th class="px-5 py-3 text-left">Role</th>
                    <th class="px-5 py-3 text-left">Access</th>
                    <th class="px-5 py-3 text-left">Status</th>
                    <th class="px-5 py-3 text-left">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($users as $user)
                @php
                    $isSelf = $user->is(auth()->user());

                    // An MSWDO officer sees administrator rows but cannot act
                    // on them. The row still lists so they know the account
                    // exists; only the buttons are withheld. The controller
                    // refuses the same actions, so this is presentation.
                    $canManage = auth()->user()->canManage($user);

                    $roleLabel = $user->roleLabel();
                    // Custom roles share one neutral tone; the three built-ins
                    // keep the colours people already recognise.
                    $roleClass = match($user->role) {
                        'admin'         => 'bg-teal-800 text-white ring-teal-800',
                        'mswdo_officer' => 'bg-teal-100 text-teal-800 ring-teal-300',
                        'mswdo_staff'   => 'bg-yellow-100 text-yellow-800 ring-yellow-300',
                        default         => 'bg-slate-100 text-slate-800 ring-slate-300',
                    };
                @endphp
                    @php
                        $initials = collect(preg_split('/\s+/', trim($user->name)))
                            ->filter()->take(2)
                            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                            ->implode('');
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                {{-- A disabled account is greyed at the avatar
                                     too, so the row reads as switched off from
                                     its leading edge rather than only from the
                                     status column at the far right. --}}
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-extrabold
                                             {{ $user->is_active ? 'bg-teal-100 text-teal-800' : 'bg-gray-200 text-gray-500' }}"
                                      aria-hidden="true">{{ $initials }}</span>
                                <span class="font-semibold text-gray-900">
                                    {{ $user->name }}
                                    @if($isSelf)
                                        <span class="ml-1 text-xs font-medium text-teal-700">(you)</span>
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-gray-700">{{ $user->email }}</td>
                        {{-- whitespace-nowrap on both the cell and the pill:
                             without it "MSWDO Officer" broke across two lines
                             inside the badge and the rounded shape went with
                             it. Role names are short enough to always sit on
                             one line. --}}
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="inline-flex items-center whitespace-nowrap px-2.5 py-1 rounded-full text-xs font-bold ring-1 ring-inset {{ $roleClass }}">
                                {{ $roleLabel }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-700">
                            @if($user->isAdmin())
                                <span class="text-xs text-gray-500">Account management</span>
                            @else
                                <div class="flex flex-wrap items-center gap-1">
                                    @forelse($user->allowedModules() as $m)
                                        <span class="inline-flex whitespace-nowrap rounded-md bg-teal-50 px-2 py-0.5 text-[11px] font-semibold text-teal-800 ring-1 ring-inset ring-teal-200">
                                            {{ \App\Models\User::MODULES[$m] ?? $m }}
                                        </span>
                                    @empty
                                        <span class="inline-flex items-center gap-1 text-xs font-bold text-red-700">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                            </svg>
                                            No access
                                        </span>
                                    @endforelse
                                    @if($user->view_only)
                                        <span class="inline-flex whitespace-nowrap rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 ring-1 ring-inset ring-amber-300">
                                            View only
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            {{-- A dot as well as a word: the two states are then
                                 distinguishable at a glance down the column. --}}
                            <span class="pill {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $user->is_active ? 'bg-green-600' : 'bg-gray-500' }}" aria-hidden="true"></span>
                                {{ $user->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap items-center gap-2 whitespace-nowrap">
                                @unless($canManage)
                                    {{-- Officer looking at an administrator.
                                         Say why the row has no buttons, rather
                                         than leaving an unexplained blank. --}}
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500"
                                          title="Only an administrator can manage administrator accounts">
                                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        Admin only
                                    </span>
                                @else
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn-soft">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Edit
                                </a>

                                {{-- Disabling and deleting yourself would lock
                                     you out of the only area you can reach, so
                                     both are withheld on your own row. --}}
                                @unless($isSelf)
                                    <form method="POST" action="{{ route('admin.users.toggle', $user) }}" class="inline"
                                          data-swal-confirm
                                          data-swal-title="{{ $user->is_active ? 'Disable' : 'Enable' }} {{ $user->name }}?"
                                          data-swal-text="{{ $user->is_active
                                                ? 'They will be signed out and blocked from logging in until re-enabled.'
                                                : 'They will be able to sign in again.' }}"
                                          data-swal-confirm-text="Yes, {{ $user->is_active ? 'disable' : 'enable' }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-soft-plain">
                                            {{ $user->is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline"
                                          data-swal-confirm="danger"
                                          data-swal-title="Delete {{ $user->name }}?"
                                          data-swal-text="This permanently removes the account. Disabling is reversible — deleting is not."
                                          data-swal-confirm-text="Yes, delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-soft-danger">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            Delete
                                        </button>
                                    </form>
                                @endunless
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <p>No users match these filters.</p>
                                <a href="{{ route('admin.users.index') }}" class="btn-soft mt-1">Clear filters</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">
            {{ $users->links() }}
        </div>
    @endif
</div>

@endsection
