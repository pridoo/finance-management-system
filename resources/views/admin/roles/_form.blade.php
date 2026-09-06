@php
    $checked = old('permissions', $role->permissions ?? []);
    $locked  = $role->exists && $role->is_system;
@endphp

<div class="max-w-2xl panel p-5">

    <div>
        <label class="form-label">Role name <span class="text-red-500">*</span></label>
        <input type="text" name="label" value="{{ old('label', $role->label) }}"
               class="form-control" required maxlength="100" placeholder="e.g. Budget Clerk">
        @error('label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    @if($locked)
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3">
            <p class="text-sm font-medium text-amber-900">Built-in role</p>
            <p class="mt-0.5 text-xs text-amber-800">
                The name can be corrected, but the permissions are fixed — this role is referenced
                throughout the system, and changing its access here would alter it everywhere at once.
                To grant different access, create a new role or adjust the individual user.
            </p>
        </div>
    @endif

    <div class="mt-4">
        <p class="form-label">Default sections for this role</p>
        <p class="text-xs text-gray-500 mb-1.5">
            Applied when a user is assigned this role. Each user can still be adjusted afterwards.
        </p>
        <div class="space-y-1.5">
            @foreach($modules as $key => $label)
                <label class="flex items-center gap-2.5 rounded-lg border border-gray-200 px-3 py-2
                              {{ $locked ? 'opacity-60' : 'cursor-pointer hover:bg-gray-50' }}">
                    <input type="checkbox" name="permissions[]" value="{{ $key }}"
                           class="h-4 w-4 rounded border-gray-300 text-teal-600"
                           @checked(in_array($key, (array) $checked, true))
                           {{ $locked ? 'disabled' : '' }}>
                    <span class="text-sm text-gray-800">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('permissions.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="mt-5 flex items-center gap-2">
        <button type="submit" class="btn-primary px-6 py-2.5">
            {{ $role->exists ? 'Save changes' : 'Create role' }}
        </button>
        <a href="{{ route('admin.roles.index') }}" class="btn-secondary px-5 py-2.5">Cancel</a>
    </div>
</div>
