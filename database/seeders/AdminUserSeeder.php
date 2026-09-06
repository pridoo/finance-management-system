<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Order matters: the officer must vacate admin@sanagustin.gov.ph before
        // the administrator can claim it, or the admin row would overwrite the
        // officer's existing account instead of moving it.
        //
        // The officer previously used admin@sanagustin.gov.ph. Matching on the
        // old address migrates that row rather than leaving a duplicate.
        $officer = User::where('email', 'admin@sanagustin.gov.ph')
            ->where('role', 'mswdo_officer')
            ->first()
            ?? User::firstOrNew(['email' => 'mswdo@sanagustin.gov.ph']);

        $officer->fill([
            'email'       => 'mswdo@sanagustin.gov.ph',
            'first_name'  => 'MSWDO',
            'last_name'   => 'Officer',
            'role'        => 'mswdo_officer',
            'is_active'   => true,
            'view_only'   => false,
            'permissions' => User::ROLE_DEFAULTS['mswdo_officer'],
        ]);

        if (! $officer->exists) {
            $officer->password = Hash::make('mswdo2026');
        }

        $officer->save();

        // System administrator. Manages user accounts only — no beneficiary,
        // request or budget access, hence the empty permission list.
        User::updateOrCreate(
            ['email' => 'admin@sanagustin.gov.ph'],
            [
                'first_name'  => 'System',
                'last_name'   => 'Administrator',
                'password'    => Hash::make('12345678'),
                'role'        => 'admin',
                'is_active'   => true,
                'view_only'   => false,
                'permissions' => [],
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@sanagustin.gov.ph'],
            [
                'first_name'  => 'MSWDO',
                'last_name'   => 'Staff',
                'password'    => Hash::make('mswdo2026'),
                'role'        => 'mswdo_staff',
                'is_active'   => true,
                'view_only'   => false,
                'permissions' => User::ROLE_DEFAULTS['mswdo_staff'],
            ]
        );
    }
}
