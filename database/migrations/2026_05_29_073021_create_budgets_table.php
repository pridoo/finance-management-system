<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->enum('assistance_type', ['Medical', 'Burial', 'Educational', 'Emergency', 'Livelihood']);
            $table->decimal('allocated_amount', 10, 2);
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['assistance_type', 'fiscal_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
