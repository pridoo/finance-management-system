<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Roles become data rather than a hardcoded enum -----------------
        // Custom roles cannot exist while the column only accepts three fixed
        // values, so role is widened to a string keyed against this table.
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label', 100);
            $table->json('permissions')->nullable();
            // The three shipped roles underpin authorisation checks in code and
            // must not be renamed or deleted from the UI.
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        // SQLite (the test database) has no MODIFY; the previous migration
        // already left role there as a plain string, so there is nothing to do.
        $mysql = DB::getDriverName() !== 'sqlite';

        if ($mysql) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'mswdo_staff'");
        }

        DB::table('roles')->insert([
            [
                'key' => 'admin', 'label' => 'Administrator', 'is_system' => true,
                'permissions' => json_encode([]),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'key' => 'mswdo_officer', 'label' => 'MSWDO Officer', 'is_system' => true,
                'permissions' => json_encode(['dashboard', 'beneficiaries', 'requests', 'approvals', 'budget', 'reports']),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'key' => 'mswdo_staff', 'label' => 'MSWDO Staff', 'is_system' => true,
                'permissions' => json_encode(['dashboard', 'beneficiaries', 'requests', 'reports']),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        // ---- Name split into parts ------------------------------------------
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('id');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
        });

        // Backfill from the single name column so existing accounts keep a
        // sensible display name. The last word is taken as the surname, which
        // is right for "MSWDO Officer" and "System Administrator" alike.
        foreach (DB::table('users')->get() as $row) {
            $parts = preg_split('/\s+/', trim((string) $row->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if (count($parts) >= 2) {
                $last  = array_pop($parts);
                $first = array_shift($parts);
                $middle = $parts ? implode(' ', $parts) : null;
            } else {
                $first  = $parts[0] ?? 'User';
                $middle = null;
                $last   = '';
            }

            DB::table('users')->where('id', $row->id)->update([
                'first_name'  => $first,
                'middle_name' => $middle,
                'last_name'   => $last,
            ]);
        }

        // Raw statement rather than ->change(): this project does not install
        // doctrine/dbal, which Laravel 10 requires for column modification.
        // Left nullable on SQLite: the model always sets it, so the tests lose
        // nothing by the constraint being absent.
        if ($mysql) {
            DB::statement("ALTER TABLE users MODIFY COLUMN first_name VARCHAR(100) NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
        });

        // Custom roles have no enum slot to return to; fold them into staff.
        DB::table('users')
            ->whereNotIn('role', ['admin', 'mswdo_officer', 'mswdo_staff'])
            ->update(['role' => 'mswdo_staff']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                "ALTER TABLE users MODIFY COLUMN role
                 ENUM('admin', 'mswdo_officer', 'mswdo_staff') NOT NULL DEFAULT 'mswdo_staff'"
            );
        }

        Schema::dropIfExists('roles');
    }
};
