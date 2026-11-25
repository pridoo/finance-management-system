<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MSWDO Staff now handle releases, which happen on the Approvals page, so the
 * staff role — and every existing staff account with its own permission list —
 * gains the "approvals" module. The decision itself stays Officer-only; that
 * is enforced in the controller, not by this module.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setApprovals(true);
    }

    public function down(): void
    {
        $this->setApprovals(false);
    }

    private function setApprovals(bool $grant): void
    {
        $role = DB::table('roles')->where('key', 'mswdo_staff')->first();
        if ($role) {
            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode($this->apply(json_decode($role->permissions ?? '[]', true) ?: [], $grant)),
            ]);
        }

        DB::table('users')->where('role', 'mswdo_staff')->whereNotNull('permissions')->get()
            ->each(function ($user) use ($grant) {
                DB::table('users')->where('id', $user->id)->update([
                    'permissions' => json_encode($this->apply(json_decode($user->permissions, true) ?: [], $grant)),
                ]);
            });
    }

    private function apply(array $modules, bool $grant): array
    {
        $modules = array_values(array_diff($modules, ['approvals']));
        if ($grant) {
            $modules[] = 'approvals';
        }

        return $modules;
    }
};
