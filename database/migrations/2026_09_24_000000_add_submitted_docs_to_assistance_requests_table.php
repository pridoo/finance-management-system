<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The requirements the beneficiary physically handed in, as ticked on the
     * request form. The checklist was on the form before but nothing stored
     * it, so there was no record of what was submitted.
     *
     * Nullable so requests filed before this column existed read as "not
     * recorded" rather than "nothing submitted".
     */
    public function up(): void
    {
        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->json('submitted_docs')->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->dropColumn('submitted_docs');
        });
    }
};
