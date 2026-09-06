@php
    $isSelf = $user->exists && $user->is(auth()->user());

    // On edit, whatever the user actually holds; on create, the selected
    // role's own set is pre-ticked so the common case needs no fiddling.
    $checked = old('permissions', $user->exists
        ? $user->allowedModules()
        : ($roles->firstWhere('key', $user->role)?->permissions ?? []));

    // Permissions per role, so picking a role can retick the boxes live.
    $rolePerms = $roles->mapWithKeys(fn ($r) => [$r->key => $r->permissions ?? []]);
@endphp

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    {{-- ===== Account details ===== --}}
    <div class="panel p-5">
        <h3 class="font-semibold text-gray-900 mb-4">Account details</h3>

        <div class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="form-label">First name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}"
                           class="form-control" required maxlength="100">
                    @error('first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">
                        Middle name <span class="font-normal text-gray-500">— or initial</span>
                    </label>
                    <input type="text" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}"
                           class="form-control" maxlength="100">
                    @error('middle_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="form-label">Last name <span class="text-red-500">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}"
                       class="form-control" required maxlength="100">
                <p class="mt-1 text-xs text-gray-500">
                    Displayed as <span class="font-medium">Juan T. Dela Cruz</span> — the middle name shows as an initial.
                </p>
                @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}"
                       class="form-control" required maxlength="150">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">
                    Password
                    @if($user->exists)
                        <span class="font-normal text-gray-500">— leave blank to keep current</span>
                    @else
                        <span class="text-red-500">*</span>
                    @endif
                </label>
                <input type="password" name="password" class="form-control"
                       autocomplete="new-password" {{ $user->exists ? '' : 'required' }}>
                <p class="mt-1 text-xs text-gray-500">Minimum 8 characters.</p>
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Confirm password</label>
                <input type="password" name="password_confirmation" class="form-control"
                       autocomplete="new-password" {{ $user->exists ? '' : 'required' }}>
            </div>

            <div>
                <label class="form-label">Role <span class="text-red-500">*</span></label>
                <select name="role" class="form-control" data-role-select {{ $isSelf ? 'disabled' : '' }} required>
                    @foreach($roles as $r)
                        <option value="{{ $r->key }}" @selected(old('role', $user->role) === $r->key)>
                            {{ $r->label }}{{ $r->is_system ? '' : ' (custom)' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    <a href="{{ route('admin.roles.index') }}" class="text-teal-600 hover:underline">Manage roles</a>
                    to add your own.
                </p>
                @if($isSelf)
                    {{-- Disabled inputs are not submitted, so the value is
                         resent here; the controller ignores it either way. --}}
                    <input type="hidden" name="role" value="{{ $user->role }}">
                    <p class="mt-1 text-xs text-gray-500">You cannot change your own role.</p>
                @endif
                @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- ===== Access and permissions ===== --}}
    <div class="panel p-5">
        <h3 class="font-semibold text-gray-900">Access &amp; permissions</h3>
        <p class="text-xs text-gray-500 mt-0.5 mb-4">
            Choose which sections this user can open, and whether they may change anything.
        </p>

        {{-- Account status --}}
        <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
            <input type="checkbox" name="is_active" value="1" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-teal-600"
                   @checked(old('is_active', $user->exists ? $user->is_active : true))
                   {{ $isSelf ? 'disabled' : '' }}>
            <span class="min-w-0">
                <span class="block text-sm font-medium text-gray-900">Account is active</span>
                <span class="block text-xs text-gray-500">
                    Untick to disable sign-in. The account and its history are kept.
                </span>
            </span>
        </label>
        @if($isSelf)
            <input type="hidden" name="is_active" value="1">
        @endif

        {{-- View-only --}}
        <label class="mt-3 flex items-start gap-3 rounded-xl border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
            <input type="checkbox" name="view_only" value="1" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-amber-600"
                   @checked(old('view_only', $user->view_only))
                   {{ $isSelf ? 'disabled' : '' }}>
            <span class="min-w-0">
                <span class="block text-sm font-medium text-gray-900">View only</span>
                <span class="block text-xs text-gray-500">
                    Can open the sections below but cannot add, edit or delete anything.
                </span>
            </span>
        </label>
        @if($isSelf)
            <input type="hidden" name="view_only" value="0">
        @endif

        {{-- Module checkboxes. Hidden for administrators, who manage accounts
             rather than welfare records and hold no MSWDO modules at all. --}}
        <div data-modules class="mt-4 {{ old('role', $user->role) === 'admin' ? 'hidden' : '' }}">
            <p class="form-label">Sections this user can access</p>
            <div class="mt-1.5 space-y-1.5">
                @foreach($modules as $key => $label)
                    <label class="flex items-center gap-2.5 rounded-lg border border-gray-200 px-3 py-2 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" name="permissions[]" value="{{ $key }}"
                               class="h-4 w-4 rounded border-gray-300 text-teal-600"
                               @checked(in_array($key, (array) $checked, true))>
                        <span class="text-sm text-gray-800">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('permissions.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div data-admin-note class="mt-4 rounded-xl border border-purple-200 bg-purple-50 p-3 {{ old('role', $user->role) === 'admin' ? '' : 'hidden' }}">
            <p class="text-sm font-medium text-purple-900">Administrator</p>
            <p class="mt-0.5 text-xs text-purple-800">
                Manages user accounts only. Has no access to beneficiaries, requests, approvals or budget.
            </p>
        </div>
    </div>
</div>

<div class="mt-5 flex items-center gap-2">
    <button type="submit" class="btn-primary px-6 py-2.5">
        {{ $user->exists ? 'Save changes' : 'Create user' }}
    </button>
    <a href="{{ route('admin.users.index') }}" class="btn-secondary px-5 py-2.5">Cancel</a>
</div>

<script>
    (function () {
        var select = document.querySelector('[data-role-select]');
        var modules = document.querySelector('[data-modules]');
        var note = document.querySelector('[data-admin-note]');
        if (!select || !modules || !note) return;

        var ROLE_PERMS = @json($rolePerms);

        select.addEventListener('change', function () {
            // Administrators hold no MSWDO modules, so the checkbox list is
            // swapped for an explanatory note.
            var isAdmin = select.value === 'admin';
            modules.classList.toggle('hidden', isAdmin);
            note.classList.toggle('hidden', !isAdmin);
            if (isAdmin) return;

            // Retick to the newly chosen role's own permissions. The admin can
            // still tweak from there — the boxes are the source of truth on
            // submit, not the role.
            var allowed = ROLE_PERMS[select.value] || [];
            modules.querySelectorAll('input[name="permissions[]"]').forEach(function (box) {
                box.checked = allowed.indexOf(box.value) !== -1;
            });
        });
    })();
</script>
