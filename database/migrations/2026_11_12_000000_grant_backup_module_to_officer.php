<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the new "backup" module to the MSWDO Officer role and to every officer
 * account that carries its own permission list. Other roles can be given it
 * from Roles & Permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setBackup(true);
    }

    public function down(): void
    {
        $this->setBackup(false);
    }

    private function setBackup(bool $grant): void
    {
        $role = DB::table('roles')->where('key', 'mswdo_officer')->first();
        if ($role) {
            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode($this->apply(json_decode($role->permissions ?? '[]', true) ?: [], $grant)),
            ]);
        }

        DB::table('users')->where('role', 'mswdo_officer')->whereNotNull('permissions')->get()
            ->each(function ($user) use ($grant) {
                DB::table('users')->where('id', $user->id)->update([
                    'permissions' => json_encode($this->apply(json_decode($user->permissions, true) ?: [], $grant)),
                ]);
            });
    }

    private function apply(array $modules, bool $grant): array
    {
        $modules = array_values(array_diff($modules, ['backup']));
        if ($grant) {
            $modules[] = 'backup';
        }

        return $modules;
    }
};
