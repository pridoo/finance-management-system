<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section A of the revised Family Intake Data Form: the source of information
 * or referring party — the person who brought the client in or gave the
 * details. All nullable: a client who comes in personally leaves it blank.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'referrer_name', 'referrer_civil_status', 'referrer_sex', 'referrer_date_of_birth',
        'referrer_birth_place', 'referrer_religion', 'referrer_educational_attainment',
        'referrer_occupation', 'referrer_address', 'referrer_income',
        'referrer_contact_number', 'referrer_relationship',
    ];

    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('referrer_name', 150)->nullable();
            $table->string('referrer_civil_status', 50)->nullable();
            $table->string('referrer_sex', 10)->nullable();
            $table->date('referrer_date_of_birth')->nullable();
            $table->string('referrer_birth_place', 150)->nullable();
            $table->string('referrer_religion', 60)->nullable();
            $table->string('referrer_educational_attainment', 80)->nullable();
            $table->string('referrer_occupation', 100)->nullable();
            $table->string('referrer_address', 255)->nullable();
            $table->decimal('referrer_income', 12, 2)->nullable();
            $table->string('referrer_contact_number', 15)->nullable();
            $table->string('referrer_relationship', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
