<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, fn ($query, $search) => $query->where(fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
            ))
            ->when($request->filled('role'), fn ($query) =>
                $query->where('role', $request->string('role')->toString())
            )
            ->when($request->filled('status'), fn ($query) =>
                $query->where('is_active', $request->string('status')->toString() === 'active')
            )
            // Built-ins first in rank order, then any custom role alphabetically.
            ->orderByRaw("FIELD(role, 'admin', 'mswdo_officer', 'mswdo_staff') DESC")
            ->orderBy('role')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('label')->get(),
        ]);
    }

    public function create()
    {
        // An officer cannot create administrators, so the form must not open
        // pre-set to a role they are not allowed to submit.
        $roles = auth()->user()->assignableRoles(Role::orderBy('label')->get());

        return view('admin.users.create', [
            'user'    => new User(['role' => 'mswdo_staff', 'is_active' => true]),
            'modules' => User::MODULES,
            'roles'   => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $this->authorizeRole($data['role']);

        $user = User::create([
            'first_name'  => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name'   => $data['last_name'],
            'email'       => $data['email'],
            'password'    => Hash::make($data['password']),
            'role'        => $data['role'],
            'is_active'   => $request->boolean('is_active'),
            'view_only'   => $request->boolean('view_only'),
            'permissions' => $this->permissionsFor($request, $data['role']),
        ]);

        AuditLog::record("Created user: {$user->name} ({$user->email})", 'Users', $user->id);

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} created.");
    }

    public function edit(User $user)
    {
        $this->authorizeTarget($user);

        // An officer editing a non-admin must still not be offered the admin
        // role in the dropdown. The current role is kept in the list even if
        // unassignable, so the select always has the row's own value.
        $me    = auth()->user();
        $roles = $me->assignableRoles(Role::orderBy('label')->get());

        if (! $roles->contains('key', $user->role)) {
            $existing = Role::allKeyed()->get($user->role);

            if ($existing) {
                $roles = $roles->push($existing)->sortBy('label')->values();
            }
        }

        return view('admin.users.edit', [
            'user'    => $user,
            'modules' => User::MODULES,
            'roles'   => $roles,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($user);

        $data = $this->validated($request, $user);

        // Changing a user's role is a separate permission from editing them:
        // an officer may edit staff but may not promote one to administrator.
        // Keeping the existing role is always allowed, so an officer editing a
        // record whose role they could not themselves grant still saves.
        if ($data['role'] !== $user->role) {
            $this->authorizeRole($data['role']);
        }

        // An admin who removes their own admin role, disables themselves, or
        // makes themselves view-only would lock the account area for everyone.
        if ($user->is(auth()->user())) {
            $data['role'] = $user->role;
            $request->merge(['is_active' => true, 'view_only' => false]);
        }

        $user->fill([
            'first_name'  => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name'   => $data['last_name'],
            'email'       => $data['email'],
            'role'        => $data['role'],
            'is_active'   => $request->boolean('is_active'),
            'view_only'   => $request->boolean('view_only'),
            'permissions' => $this->permissionsFor($request, $data['role']),
        ]);

        // Blank means "leave the current password alone" so the admin can edit
        // a profile without having to reissue credentials.
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        AuditLog::record("Updated user: {$user->name} ({$user->email})", 'Users', $user->id);

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} updated.");
    }

    /**
     * Disabling is the reversible alternative to deletion and is what the UI
     * offers first; it withdraws sign-in while keeping the audit trail intact.
     */
    public function toggle(User $user)
    {
        $this->authorizeTarget($user);

        abort_if($user->is(auth()->user()), 403, 'You cannot disable your own account.');

        $user->update(['is_active' => ! $user->is_active]);

        $state = $user->is_active ? 'Enabled' : 'Disabled';
        AuditLog::record("{$state} user: {$user->name} ({$user->email})", 'Users', $user->id);

        return back()->with('success', "{$user->name} has been ".strtolower($state).'.');
    }

    public function destroy(User $user)
    {
        $this->authorizeTarget($user);

        abort_if($user->is(auth()->user()), 403, 'You cannot delete your own account.');

        // The last admin must survive, otherwise nobody can administer accounts.
        if ($user->isAdmin() && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return back()->withErrors([
                'user' => 'Cannot delete the only active administrator.',
            ]);
        }

        $label = "{$user->name} ({$user->email})";
        $user->delete();

        AuditLog::record("Deleted user: {$label}", 'Users');

        return redirect()->route('admin.users.index')
            ->with('success', "User {$label} deleted.");
    }

    /**
     * Administrator accounts are off limits to the MSWDO officer. Editing one
     * would let an officer change its email and password and take it over, so
     * every route that reads or writes a specific account passes through here.
     */
    private function authorizeTarget(User $user): void
    {
        abort_unless(
            auth()->user()->canManage($user),
            403,
            'Administrator accounts can only be managed by an administrator.'
        );
    }

    /** The officer may hand out any role except admin. */
    private function authorizeRole(string $role): void
    {
        abort_unless(
            auth()->user()->canAssignRole($role),
            403,
            'You are not allowed to assign the administrator role.'
        );
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'first_name'  => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name'   => 'required|string|max:100',
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            // Required when creating, optional when editing.
            'password' => [
                $user ? 'nullable' : 'required',
                'confirmed',
                Password::min(8),
            ],
            // Roles are data now, so the allowed set comes from the table.
            'role'          => ['required', Rule::exists('roles', 'key')],
            'permissions'   => 'array',
            'permissions.*' => ['string', Rule::in(array_keys(User::MODULES))],
        ], [
            'permissions.*.in' => 'One of the selected modules is not valid.',
        ]);
    }

    /**
     * Admins hold no MSWDO modules, so their list is always empty. For everyone
     * else an unticked form means no access rather than falling back to the
     * role default, which would silently re-grant what the admin just removed.
     */
    private function permissionsFor(Request $request, string $role): array
    {
        if ($role === 'admin') {
            return [];
        }

        // The checkboxes are the source of truth: the form pre-ticks the role's
        // own set, so an unticked box is a deliberate removal, not an omission.

        return array_values(array_intersect(
            $request->input('permissions', []),
            array_keys(User::MODULES)
        ));
    }
}
