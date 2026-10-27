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

/**
 * Manages user accounts (Users page in the admin area).
 *
 * Lets an administrator or the MSWDO officer list, search, create, edit,
 * enable/disable and delete the login accounts of the system, and choose
 * each account's role and module permissions. Officers can never see or
 * touch administrator accounts, and cannot give anyone the admin role.
 *
 * Routes (routes/web.php, prefix /admin):
 *  - GET    /admin/users               -> index()   (admin.users.index)
 *  - GET    /admin/users/create        -> create()  (admin.users.create)
 *  - POST   /admin/users               -> store()   (admin.users.store)
 *  - GET    /admin/users/{user}/edit   -> edit()    (admin.users.edit)
 *  - PUT    /admin/users/{user}        -> update()  (admin.users.update)
 *  - PATCH  /admin/users/{user}/toggle -> toggle()  (admin.users.toggle)
 *  - DELETE /admin/users/{user}        -> destroy() (admin.users.destroy)
 *
 * Only signed-in, active, non-view-only administrators and MSWDO officers can
 * reach these pages ('auth', 'active', 'admin', 'notviewonly' middleware).
 */
class UserController extends Controller
{
    /**
     * Show the list of user accounts, with search and filters.
     *
     * Called when the Users page is opened (GET /admin/users), including when
     * the search box or the role/status filters are used.
     * Returns the admin.users.index view.
     */
    public function index(Request $request)
    {
        // $users = one page (10 rows) of accounts matching the search/filters.
        $users = User::query()
            // Administrator accounts are hidden from everyone but an
            // administrator. They still exist and can still sign in; an
            // officer simply never sees or manages them.
            ->when(! auth()->user()->isAdmin(), fn ($query) => $query->where('role', '!=', 'admin'))
            // Search box: match the text against name, first/last name or email.
            ->when($request->search, fn ($query, $search) => $query->where(fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
            ))
            // Role filter dropdown: show only users with the chosen role.
            ->when($request->filled('role'), fn ($query) =>
                $query->where('role', $request->string('role')->toString())
            )
            // Status filter dropdown: "active" = enabled accounts, anything else = disabled.
            ->when($request->filled('status'), fn ($query) =>
                $query->where('is_active', $request->string('status')->toString() === 'active')
            )
            // Built-ins first in rank order, then any custom role alphabetically.
            // Same order as MySQL's FIELD(...) DESC, written as CASE so it also
            // runs on SQLite (the test database).
            ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'mswdo_officer' THEN 2 WHEN 'mswdo_staff' THEN 3 ELSE 0 END DESC")
            ->orderBy('role')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(10)
            // Keep the search/filter values in the page links (page 2, 3, ...).
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            // 'roles' = options for the role filter dropdown (admin role hidden from officers).
            'roles' => Role::query()
                ->when(! auth()->user()->isAdmin(), fn ($q) => $q->where('key', '!=', 'admin'))
                ->orderBy('label')->get(),
        ]);
    }

    /**
     * Show the "Add User" form.
     *
     * Called when the user clicks the button to add a new account
     * (GET /admin/users/create). Returns the admin.users.create view.
     */
    public function create()
    {
        // An officer cannot create administrators, so the form must not open
        // pre-set to a role they are not allowed to submit.
        // $roles = only the roles the current user is allowed to give out.
        $roles = auth()->user()->assignableRoles(Role::orderBy('label')->get());

        return view('admin.users.create', [
            'user'    => new User(['role' => 'mswdo_staff', 'is_active' => true]), // blank account; defaults to Staff and active
            'modules' => User::MODULES,                                            // module checkboxes (key => display name)
            'roles'   => $roles,                                                   // options for the role dropdown
        ]);
    }

    /**
     * Save a new user account.
     *
     * Called when the "Add User" form is submitted (POST /admin/users).
     * Returns a redirect to the Users page with a success message.
     */
    public function store(Request $request)
    {
        // $data = the form input after passing the rules in validated() below.
        $data = $this->validated($request);

        // Stop the request if the chosen role is not allowed (e.g. officer picking admin).
        $this->authorizeRole($data['role']);

        $user = User::create([
            'first_name'  => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name'   => $data['last_name'],
            'email'       => $data['email'],
            'password'    => Hash::make($data['password']),        // stored encrypted (hashed), never as plain text
            'role'        => $data['role'],
            'is_active'   => $request->boolean('is_active'),       // can sign in (ticked) or disabled
            'view_only'   => $request->boolean('view_only'),       // can look at pages but not change anything
            'permissions' => $this->permissionsFor($request, $data['role']), // which modules this user may open
        ]);

        // Keep a record of who did this in the audit log.
        AuditLog::record("Created user: {$user->name} ({$user->email})", 'Users', $user->id);

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} created.");
    }

    /**
     * Show the "Edit User" form for one account.
     *
     * Called when the user clicks Edit on an account
     * (GET /admin/users/{user}/edit). Returns the admin.users.edit view.
     */
    public function edit(User $user)
    {
        $this->authorizeTarget($user);

        // An officer editing a non-admin must still not be offered the admin
        // role in the dropdown. The current role is kept in the list even if
        // unassignable, so the select always has the row's own value.
        $me    = auth()->user(); // the person currently signed in
        $roles = $me->assignableRoles(Role::orderBy('label')->get()); // roles they may give out

        if (! $roles->contains('key', $user->role)) {
            // $existing = the Role record for the account's current role.
            $existing = Role::allKeyed()->get($user->role);

            if ($existing) {
                $roles = $roles->push($existing)->sortBy('label')->values();
            }
        }

        return view('admin.users.edit', [
            'user'    => $user,          // the account being edited (fills in the form)
            'modules' => User::MODULES,  // module checkboxes (key => display name)
            'roles'   => $roles,         // options for the role dropdown
        ]);
    }

    /**
     * Save changes to an existing user account.
     *
     * Called when the "Edit User" form is submitted (PUT /admin/users/{user}).
     * Returns a redirect to the Users page with a success message.
     */
    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($user);

        // Passing $user makes the password optional and lets the "unique email"
        // rule ignore this account's own email.
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

        // fill() sets the new values on the model; they are saved by save() below.
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
     *
     * Called when the Enable/Disable button is clicked on an account
     * (PATCH /admin/users/{user}/toggle). Flips is_active on or off and
     * returns a redirect back to the same page with a success message.
     */
    public function toggle(User $user)
    {
        $this->authorizeTarget($user);

        abort_if($user->is(auth()->user()), 403, 'You cannot disable your own account.');

        $user->update(['is_active' => ! $user->is_active]);

        // $state = "Enabled" or "Disabled", used in the audit log and message.
        $state = $user->is_active ? 'Enabled' : 'Disabled';
        AuditLog::record("{$state} user: {$user->name} ({$user->email})", 'Users', $user->id);

        return back()->with('success', "{$user->name} has been ".strtolower($state).'.');
    }

    /**
     * Permanently delete a user account.
     *
     * Called when the Delete button is clicked on an account
     * (DELETE /admin/users/{user}). You cannot delete yourself or the last
     * active administrator. Returns a redirect back (with an error) or to the
     * Users page (with a success message).
     */
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

        // Save the name and email first, because after delete() we still need
        // them for the audit log and the success message.
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
     *
     * Used by edit(), update(), toggle() and destroy(). Returns nothing; it
     * stops the request with a 404 or 403 error page if not allowed.
     */
    private function authorizeTarget(User $user): void
    {
        // 404 rather than 403 for a hidden administrator account, so a guessed
        // URL does not even confirm the account exists.
        abort_if(! auth()->user()->isAdmin() && $user->isAdmin(), 404);

        abort_unless(
            auth()->user()->canManage($user),
            403,
            'Administrator accounts can only be managed by an administrator.'
        );
    }

    /** The officer may hand out any role except admin. */
    private function authorizeRole(string $role): void
    {
        // Used by store() and update(). Stops the request with a 403 error if
        // the signed-in user is not allowed to give out $role.
        abort_unless(
            auth()->user()->canAssignRole($role),
            403,
            'You are not allowed to assign the administrator role.'
        );
    }

    /**
     * Validation rules for the Add/Edit User form.
     *
     * Used by store() ($user is null) and update() ($user is the account being
     * edited). Returns the validated input as an array. If validation fails,
     * Laravel automatically goes back to the form and shows the errors.
     */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'first_name'  => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100', // optional
            'last_name'   => 'required|string|max:100',
            // Must be a valid email and not already used by another account
            // (the account being edited is ignored).
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            // Required when creating, optional when editing.
            // 'confirmed' = must match the "Confirm Password" field (password_confirmation).
            'password' => [
                $user ? 'nullable' : 'required',
                'confirmed',
                Password::min(8),
            ],
            // Roles are data now, so the allowed set comes from the table.
            'role'          => ['required', Rule::exists('roles', 'key')],
            // Ticked modules: a list where each item must be a key of User::MODULES.
            'permissions'   => 'array',
            'permissions.*' => ['string', Rule::in(array_keys(User::MODULES))],
        ], [
            // Custom error message for an invalid module.
            'permissions.*.in' => 'One of the selected modules is not valid.',
        ]);
    }

    /**
     * Admins hold no MSWDO modules, so their list is always empty. For everyone
     * else an unticked form means no access rather than falling back to the
     * role default, which would silently re-grant what the admin just removed.
     *
     * Used by store() and update(). Returns the list of module keys to save,
     * e.g. ['dashboard', 'beneficiaries', 'requests'].
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
