<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manages user roles (Roles page in the admin area).
 *
 * A role is a named set of module permissions (Dashboard, Beneficiaries,
 * Assistance Requests, Approvals, Budget Tracking, Reports) that can be given
 * to user accounts. Built-in "system" roles (admin, mswdo_officer,
 * mswdo_staff) cannot be deleted and their permissions are fixed; custom
 * roles can be freely created, edited and deleted.
 *
 * Routes (Route::resource('roles') in routes/web.php, prefix /admin):
 *  - GET    /admin/roles             -> index()   (admin.roles.index)
 *  - GET    /admin/roles/create      -> create()  (admin.roles.create)
 *  - POST   /admin/roles             -> store()   (admin.roles.store)
 *  - GET    /admin/roles/{role}/edit -> edit()    (admin.roles.edit)
 *  - PUT    /admin/roles/{role}      -> update()  (admin.roles.update)
 *  - DELETE /admin/roles/{role}      -> destroy() (admin.roles.destroy)
 *
 * Only signed-in, active, non-view-only administrators and MSWDO officers can
 * reach these pages ('auth', 'active', 'admin', 'notviewonly' middleware).
 */
class RoleController extends Controller
{
    /**
     * Show the list of roles.
     *
     * Called when the Roles page is opened (GET /admin/roles).
     * Returns the admin.roles.index view.
     */
    public function index()
    {
        // The administrator role is hidden from everyone but an administrator.
        // withCount('users') adds a users_count field to each role = how many
        // accounts currently have that role.
        $roles = Role::withCount('users')
            ->when(! auth()->user()->isAdmin(), fn ($q) => $q->where('key', '!=', 'admin'))
            ->orderBy('label')
            ->get();

        return view('admin.roles.index', [
            'roles'   => $roles,          // roles to list in the table
            'modules' => User::MODULES,   // module key => display name, used to show each role's permissions
        ]);
    }

    /**
     * Show the "Add Role" form.
     *
     * Called when the user clicks the button to create a new role
     * (GET /admin/roles/create). Returns the admin.roles.create view.
     */
    public function create()
    {
        return view('admin.roles.create', [
            'role'    => new Role(['permissions' => []]), // empty role so the form starts blank (no modules ticked)
            'modules' => User::MODULES,                   // list of module checkboxes to show
        ]);
    }

    /**
     * Save a new custom role.
     *
     * Called when the "Add Role" form is submitted (POST /admin/roles).
     * Returns a redirect to the Roles page with a success message.
     */
    public function store(Request $request)
    {
        // $data = the form input after passing the validation rules below.
        $data = $this->validated($request);

        $role = Role::create([
            'key'         => Role::makeKey($data['label']), // unique code made from the name, e.g. "Budget Clerk" -> budget_clerk
            'label'       => $data['label'],                // display name of the role
            'permissions' => $this->permissions($request),  // the ticked modules
            'is_system'   => false,                         // roles made here are custom, so they can be edited/deleted later
        ]);

        // Keep a record of who did this in the audit log.
        AuditLog::record("Created role: {$role->label}", 'Users', $role->id);

        return redirect()->route('admin.roles.index')
            ->with('success', "Role {$role->label} created.");
    }

    /**
     * Show the "Edit Role" form for one role.
     *
     * Called when the user clicks Edit on a role (GET /admin/roles/{role}/edit).
     * Returns the admin.roles.edit view.
     */
    public function edit(Role $role)
    {
        $this->authorizeRole($role);

        return view('admin.roles.edit', [
            'role'    => $role,           // the role being edited (fills in the form)
            'modules' => User::MODULES,   // list of module checkboxes to show
        ]);
    }

    /**
     * Save changes to an existing role.
     *
     * Called when the "Edit Role" form is submitted (PUT /admin/roles/{role}).
     * Returns a redirect to the Roles page with a success message.
     */
    public function update(Request $request, Role $role)
    {
        $this->authorizeRole($role);

        // Passing $role lets the "unique label" rule ignore this role's own name.
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

    /**
     * Delete a custom role.
     *
     * Called when the user clicks Delete on a role (DELETE /admin/roles/{role}).
     * Built-in roles and roles still assigned to users are refused.
     * Returns a redirect back (with an error) or to the Roles page (with success).
     */
    public function destroy(Role $role)
    {
        $this->authorizeRole($role);

        if ($role->is_system) {
            return back()->withErrors(['role' => 'Built-in roles cannot be deleted.']);
        }

        // Users would be left pointing at a role that no longer exists, which
        // silently drops them to the bare ROLE_DEFAULTS fallback.
        // $inUse = number of user accounts that still have this role.
        $inUse = User::where('role', $role->key)->count();

        if ($inUse > 0) {
            return back()->withErrors([
                'role' => "{$role->label} is assigned to {$inUse} user(s). Reassign them before deleting.",
            ]);
        }

        // Save the name first, because after delete() we still need it for the
        // audit log and the success message.
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
     *
     * Used by edit(), update() and destroy(). Returns nothing; it stops the
     * request with a 404 or 403 error page if the user is not allowed.
     */
    private function authorizeRole(Role $role): void
    {
        // 404 (not found) so a non-admin cannot even confirm the admin role exists.
        abort_if(! auth()->user()->isAdmin() && $role->key === 'admin', 404);

        abort_unless(
            auth()->user()->canAssignRole($role->key),
            403,
            'The administrator role can only be changed by an administrator.'
        );
    }

    /**
     * Validation rules for the Add/Edit Role form.
     *
     * Used by store() and update(). Returns the validated input as an array.
     * If validation fails, Laravel automatically goes back to the form and
     * shows the error messages.
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            // Role name: required, max 100 characters, and no two roles may share
            // a name (the current role is ignored when editing).
            'label' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'label')->ignore($role?->id),
            ],
            // Ticked modules: must be a list, and each item must be one of the
            // keys in User::MODULES (stops fake module names being submitted).
            'permissions'   => 'array',
            'permissions.*' => ['string', Rule::in(array_keys(User::MODULES))],
        ]);
    }

    /**
     * Get the list of ticked module checkboxes from the form.
     *
     * Keeps only real module keys (from User::MODULES) and re-numbers the
     * array. Returns e.g. ['dashboard', 'requests']. An empty form gives [].
     */
    private function permissions(Request $request): array
    {
        return array_values(array_intersect(
            $request->input('permissions', []),
            array_keys(User::MODULES)
        ));
    }
}
