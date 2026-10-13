<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The beneficiary record now follows the office's paper Family Intake Data
     * Form: the client's own details, the household (family composition), the
     * employment and housing condition, and the social worker's notes.
     *
     * Every new column is nullable so the records captured before this change
     * stay valid; the form simply shows those fields empty until filled in.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->date('intake_date')->nullable()->after('id');
            $table->string('religion', 60)->nullable()->after('civil_status');
            $table->string('birth_place', 150)->nullable()->after('religion');
            $table->string('educational_attainment', 80)->nullable()->after('birth_place');
            $table->string('occupation', 100)->nullable()->after('educational_attainment');
            $table->decimal('monthly_income', 12, 2)->nullable()->after('occupation');
            $table->string('purok', 30)->nullable()->after('monthly_income');
            $table->string('category', 60)->nullable()->after('contact_number');

            // C. Additional income
            $table->string('employment_nature', 20)->nullable();
            $table->string('housing', 255)->nullable();
            $table->string('water_source', 150)->nullable();
            $table->string('toilet', 150)->nullable();
            $table->string('light', 150)->nullable();

            // D–G. The social worker's case notes
            $table->text('problem_presented')->nullable();
            $table->text('initial_assessment')->nullable();
            $table->text('recommendation')->nullable();
            $table->text('plan_of_action')->nullable();
        });

        // B. Family composition — one row per household member.
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('sex', 10)->nullable();
            $table->string('civil_status', 50)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('relationship', 50)->nullable();
            $table->string('educational_attainment', 80)->nullable();
            $table->string('occupation_income', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_members');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'intake_date', 'religion', 'birth_place', 'educational_attainment',
                'occupation', 'monthly_income', 'purok', 'category',
                'employment_nature', 'housing', 'water_source', 'toilet', 'light',
                'problem_presented', 'initial_assessment', 'recommendation', 'plan_of_action',
            ]);
        });
    }
};
