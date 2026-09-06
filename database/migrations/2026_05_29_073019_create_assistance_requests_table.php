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
        Schema::create('assistance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->enum('assistance_type', ['Medical', 'Burial', 'Educational', 'Emergency', 'Livelihood']);
            $table->decimal('amount_requested', 10, 2);
            $table->text('purpose');
            $table->enum('status', ['Pending', 'Under Review', 'Approved', 'Rejected', 'For Release', 'Released'])->default('Pending');
            $table->foreignId('encoded_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistance_requests');
    }
};
