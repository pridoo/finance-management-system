<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The whole assistance fund for a fiscal year — "₱3,000,000 for 2025".
     *
     * Allocations per assistance type are drawn out of this figure, so the
     * fund's unallocated balance only ever goes down as money is assigned and
     * released. Before this, each allocation stood on its own and adding one
     * simply grew the year's total with nothing to say it had gone over.
     */
    public function up(): void
    {
        Schema::create('annual_funds', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year')->unique();
            $table->decimal('amount', 14, 2);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_funds');
    }
};
