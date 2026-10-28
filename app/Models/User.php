<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name', 'middle_name', 'last_name',
        'name', 'email', 'password', 'role', 'is_active', 'permissions', 'view_only',
    ];

    /**
     * Every module the admin can grant. Keys are stored in the permissions
     * column; labels drive the checkbox list on the user form.
     */
    public const MODULES = [
        'dashboard'     => 'Dashboard',
        'beneficiaries' => 'Beneficiaries',
        'requests'      => 'Assistance Requests',
        'approvals'     => 'Approvals',
        'budget'        => 'Budget Tracking',
        'reports'       => 'Reports',
    ];

    /**
     * Fallback access for the three shipped roles. Custom roles carry their own
     * permissions in the roles table; this constant covers the built-ins and
     * anything created before the roles table existed.
     */
    public const ROLE_DEFAULTS = [
        'mswdo_officer' => ['dashboard', 'beneficiaries', 'requests', 'approvals', 'budget', 'reports'],
        'mswdo_staff'   => ['dashboard', 'beneficiaries', 'requests', 'approvals', 'reports'],
        'admin'         => [],
    ];

    /**
     * Keep the single `name` column in step with the parts.
     *
     * Rebuilding it on save means every existing reader — audit logs, the
     * sidebar, report bylines — keeps working untouched, while the form edits
     * first/middle/last.
     */
    protected static function booted(): void
    {
        static::saving(function (self $user) {
            if ($user->isDirty(['first_name', 'middle_name', 'last_name'])) {
                $user->attributes['name'] = $user->buildDisplayName();
            }
        });
    }

    public function setFirstNameAttribute($value): void
    {
        $this->attributes['first_name'] = $this->formatNamePart($value);
    }

    public function setLastNameAttribute($value): void
    {
        $this->attributes['last_name'] = $this->formatNamePart($value) ?: null;
    }

    public function setMiddleNameAttribute($value): void
    {
        $this->attributes['middle_name'] = $this->formatNamePart($value) ?: null;
    }

    /**
     * Title-case a name part, matching how beneficiary names are normalised.
     * mb_convert_case keeps accented names (Peña, Ordoñez) intact.
     */
    protected function formatNamePart(?string $value): string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? '' : mb_convert_case($trimmed, MB_CASE_TITLE, 'UTF-8');
    }

    /** "Juan T. Dela Cruz" — the middle name shows as an initial. */
    public function buildDisplayName(): string
    {
        $middle = $this->middle_name
            ? ' '.mb_strtoupper(mb_substr(trim($this->middle_name), 0, 1, 'UTF-8'), 'UTF-8').'.'
            : '';

        return trim($this->first_name.$middle.' '.$this->last_name);
    }

    public function getFullNameAttribute(): string
    {
        return $this->buildDisplayName();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'permissions'       => 'array',
        'is_active'         => 'boolean',
        'view_only'         => 'boolean',
    ];

    /**
     * Profile picture placed by hand in public/images/avatars, named after the
     * part of the email before the @ (mswdo@sanagustin.gov.ph -> mswdo.jpg).
     * Null when there is no such file, so the layout falls back to initials.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        $name = strtolower(strstr($this->email, '@', true) ?: $this->email);

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            if (is_file(public_path("images/avatars/{$name}.{$ext}"))) {
                return asset("images/avatars/{$name}.{$ext}");
            }
        }

        return null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOfficer(): bool
    {
        return $this->role === 'mswdo_officer';
    }

    /**
     * Who may open the account-administration area at all.
     *
     * The MSWDO officer runs the office day to day, which includes issuing and
     * withdrawing staff logins, so they reach the same pages as the admin. What
     * they may do once inside is narrower — see canManage() and canAssignRole()
     * below — because an officer who could mint administrators would make the
     * two roles the same thing.
     */
    public function canAdministerAccounts(): bool
    {
        return $this->isAdmin() || $this->isOfficer();
    }

    /**
     * Whether this user may create, edit, disable or delete the given account.
     *
     * Administrator accounts are the boundary: only another administrator may
     * touch one. Without this an officer could edit the admin's email and
     * password and take the account over — a longer way round to the same
     * escalation canAssignRole() blocks directly.
     *
     * A null target means "a new account", which only canAssignRole() bounds.
     */
    public function canManage(?self $target): bool
    {
        if (! $this->canAdministerAccounts()) {
            return false;
        }

        if ($target === null || $this->isAdmin()) {
            return true;
        }

        // Officer: everyone except administrators.
        return ! $target->isAdmin();
    }

    /**
     * Whether this user may hand out the given role.
     *
     * The officer is barred from the admin role specifically. Any other role —
     * including custom ones — is theirs to assign, since none of them reach
     * beyond the MSWDO modules.
     */
    public function canAssignRole(string $role): bool
    {
        if (! $this->canAdministerAccounts()) {
            return false;
        }

        return $this->isAdmin() || $role !== 'admin';
    }

    /**
     * The roles this user may pick from on the account form. Filtering the list
     * is presentation; canAssignRole() is what actually stops a forged POST.
     */
    public function assignableRoles(Collection $roles): Collection
    {
        return $roles->filter(fn (Role $role) => $this->canAssignRole($role->key))->values();
    }

    /**
     * The modules this user may open. An explicit permission list always wins;
     * without one the role default applies, which keeps every pre-existing
     * account working exactly as it did before permissions were introduced.
     */
    public function allowedModules(): array
    {
        if ($this->isAdmin()) {
            return [];
        }

        if (is_array($this->permissions)) {
            return array_values(array_intersect($this->permissions, array_keys(self::MODULES)));
        }

        // No per-user list: fall back to whatever the role grants. Custom roles
        // keep their permissions in the roles table; the constant covers the
        // built-ins and any account predating that table.
        $role = $this->roleModel();

        if ($role && is_array($role->permissions)) {
            return array_values(array_intersect($role->permissions, array_keys(self::MODULES)));
        }

        return self::ROLE_DEFAULTS[$this->role] ?? [];
    }

    /**
     * Roles are a tiny, rarely-changing table read once per row in list views,
     * so the whole set is held for the request rather than queried per user.
     */
    public function roleModel(): ?Role
    {
        return Role::allKeyed()->get($this->role);
    }

    /** The role's human label, falling back to the raw key for unknown roles. */
    public function roleLabel(): string
    {
        return $this->roleModel()?->label
            ?? ucwords(str_replace('_', ' ', (string) $this->role));
    }

    /**
     * The page this user belongs on when they have not asked for anywhere in
     * particular — after signing in, or on hitting a guest-only page such as
     * /login while already authenticated.
     *
     * Lives on the model because both the login flow and the guest middleware
     * need the same answer, and it differs per user: the admin has no MSWDO
     * modules, and a restricted user may not hold the dashboard.
     */
    public function homeRoute(): string
    {
        if ($this->isAdmin()) {
            return route('admin.users.index');
        }

        if ($this->canAccess('dashboard')) {
            return route('mswdo.dashboard');
        }

        $routes = [
            'beneficiaries' => 'mswdo.beneficiaries.index',
            'requests'      => 'mswdo.requests.index',
            'approvals'     => 'mswdo.approvals.index',
            'budget'        => 'mswdo.budget.index',
            'reports'       => 'mswdo.reports.index',
        ];

        foreach ($this->allowedModules() as $module) {
            if (isset($routes[$module])) {
                return route($routes[$module]);
            }
        }

        // Permissions can be stripped to nothing; the dashboard then renders
        // its own empty state rather than leaving the user in a redirect loop
        // between here and the module guard.
        return route('mswdo.dashboard');
    }

    public function canAccess(string $module): bool
    {
        return in_array($module, $this->allowedModules(), true);
    }

    /**
     * View-only users may open their permitted modules but must not create,
     * edit or delete anything inside them.
     */
    public function canModify(): bool
    {
        return ! $this->view_only;
    }
}
