<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fields asked for in the third round of revisions.
 *
 * Additive only — every column is nullable or defaulted, so records captured
 * before this run keep working and simply show the new fields empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Housing split into two dropdowns: who owns it, and what it is built of.
            $table->string('housing_ownership', 30)->nullable()->after('employment_nature');
            $table->string('housing_structure', 30)->nullable()->after('housing_ownership');

            // A client who belongs to an existing household points at its
            // Family Head, so the family's assistance can be read as one ledger.
            $table->foreignId('family_head_id')->nullable()->after('id')
                ->constrained('clients')->nullOnDelete();
        });

        // Occupation and monthly income were one free-text cell; now two fields.
        Schema::table('family_members', function (Blueprint $table) {
            $table->string('occupation', 100)->nullable()->after('educational_attainment');
            $table->decimal('monthly_income', 12, 2)->nullable()->after('occupation');
        });

        // Carry the old combined text over as the occupation, so nothing typed
        // before is lost. The legacy column stays, unused, for rollback.
        DB::table('family_members')->whereNotNull('occupation_income')
            ->update(['occupation' => DB::raw('occupation_income')]);

        Schema::table('assistance_requests', function (Blueprint $table) {
            // A referred client does not need a personal letter; a scholar
            // applying for educational assistance must submit a COG.
            $table->boolean('is_referred')->default(false)->after('submitted_docs');
            $table->boolean('is_scholar')->default(false)->after('is_referred');
        });

        // The funding source is chosen when the request is approved, before
        // anything is released from it.
        Schema::table('approvals', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('decision')
                ->constrained('budgets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_id');
        });
        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->dropColumn(['is_referred', 'is_scholar']);
        });
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn(['occupation', 'monthly_income']);
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('family_head_id');
            $table->dropColumn(['housing_ownership', 'housing_structure']);
        });
    }
};
