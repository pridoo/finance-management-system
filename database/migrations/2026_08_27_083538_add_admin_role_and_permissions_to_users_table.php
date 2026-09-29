<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // role is an enum, so a new value has to be added by redefining the
        // column. Doctrine/DBAL cannot modify enums, hence the raw statement.
        if (DB::getDriverName() === 'sqlite') {
            // The test database. SQLite has no MODIFY, and its enum is a CHECK
            // constraint that would refuse 'admin', so the column is rebuilt as
            // the plain string the next migration widens it to anyway.
            DB::statement("ALTER TABLE users ADD COLUMN role_new VARCHAR(50) NOT NULL DEFAULT 'mswdo_staff'");
            DB::statement('UPDATE users SET role_new = role');
            DB::statement('ALTER TABLE users DROP COLUMN role');
            DB::statement('ALTER TABLE users RENAME COLUMN role_new TO role');
        } else {
            DB::statement(
                "ALTER TABLE users MODIFY COLUMN role
                 ENUM('admin', 'mswdo_officer', 'mswdo_staff') NOT NULL DEFAULT 'mswdo_staff'"
            );
        }

        Schema::table('users', function (Blueprint $table) {
            // Disabled accounts keep their history and audit trail; only the
            // ability to sign in is withdrawn. Deleting the row instead would
            // orphan every audit entry that user ever produced.
            $table->boolean('is_active')->default(true)->after('role');

            // Per-user overrides on top of the role default. Null means "use
            // whatever the role grants", so existing users need no backfill.
            $table->json('permissions')->nullable()->after('is_active');
            $table->boolean('view_only')->default(false)->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'permissions', 'view_only']);
        });

        // Any admin left behind would violate the narrowed enum, so fold them
        // back into the officer role before restoring it.
        DB::table('users')->where('role', 'admin')->update(['role' => 'mswdo_officer']);

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role
             ENUM('mswdo_officer', 'mswdo_staff') NOT NULL DEFAULT 'mswdo_staff'"
        );
    }
};
