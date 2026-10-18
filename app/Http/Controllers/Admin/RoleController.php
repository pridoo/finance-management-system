<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        // The administrator role is hidden from everyone but an administrator.
        $roles = Role::withCount('users')
            ->when(! auth()->user()->isAdmin(), fn ($q) => $q->where('key', '!=', 'admin'))
            ->orderBy('label')
            ->get();

        return view('admin.roles.index', [
            'roles'   => $roles,
            'modules' => User::MODULES,
        ]);
    }

    public function create()
    {
        return view('admin.roles.create', [
            'role'    => new Role(['permissions' => []]),
            'modules' => User::MODULES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $role = Role::create([
            'key'         => Role::makeKey($data['label']),
            'label'       => $data['label'],
            'permissions' => $this->permissions($request),
            'is_system'   => false,
        ]);

        AuditLog::record("Created role: {$role->label}", 'Users', $role->id);

        return redirect()->route('admin.roles.index')
            ->with('success', "Role {$role->label} created.");
    }

    public function edit(Role $role)
    {
        $this->authorizeRole($role);

        return view('admin.roles.edit', [
            'role'    => $role,
            'modules' => User::MODULES,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $this->authorizeRole($role);

        $data = $this->validated($request, $role);

        // System roles are referenced by key in middleware and controllers, so
        // their label may be corrected but their permissions stay fixed —
        // editing them here would silently rewrite authorisation everywhere.
        if ($role->is_system) {
            $role->update(['label' => $data['label']]);
        } else {
            $role->update([
                'label'       => $data['label'],
                'permissions' => $this->permissions($request),
            ]);
        }

        AuditLog::record("Updated role: {$role->label}", 'Users', $role->id);

        return redirect()->route('admin.roles.index')
            ->with('success', "Role {$role->label} updated.");
    }

    public function destroy(Role $role)
    {
        $this->authorizeRole($role);

        if ($role->is_system) {
            return back()->withErrors(['role' => 'Built-in roles cannot be deleted.']);
        }

        // Users would be left pointing at a role that no longer exists, which
        // silently drops them to the bare ROLE_DEFAULTS fallback.
        $inUse = User::where('role', $role->key)->count();

        if ($inUse > 0) {
            return back()->withErrors([
                'role' => "{$role->label} is assigned to {$inUse} user(s). Reassign them before deleting.",
            ]);
        }

        $label = $role->label;
        $role->delete();

        AuditLog::record("Deleted role: {$label}", 'Users');

        return redirect()->route('admin.roles.index')
            ->with('success', "Role {$label} deleted.");
    }

    /**
     * The admin role itself is only editable by an administrator. Its
     * permissions are fixed as a system role either way, but its label feeds
     * the account form, and letting an officer rename it there is needless
     * reach into the role that outranks them.
     */
    private function authorizeRole(Role $role): void
    {
        abort_if(! auth()->user()->isAdmin() && $role->key === 'admin', 404);

        abort_unless(
            auth()->user()->canAssignRole($role->key),
            403,
            'The administrator role can only be changed by an administrator.'
        );
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'label' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'label')->ignore($role?->id),
            ],
            'permissions'   => 'array',
            'permissions.*' => ['string', Rule::in(array_keys(User::MODULES))],
        ]);
    }

    private function permissions(Request $request): array
    {
        return array_values(array_intersect(
            $request->input('permissions', []),
            array_keys(User::MODULES)
        ));
    }
}
