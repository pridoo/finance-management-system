<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sub-allocations: the named portions a budget is split into.
     *
     * The Educational fund, for instance, may be earmarked as college
     * assistance, TUPAD and school supplies. The office needs to see how much
     * of the parent allocation each of those accounts for, which the single
     * allocated_amount column on budgets cannot express.
     *
     * Deliberately additive: a budget with no sub-allocations behaves exactly
     * as it did before, so existing records keep working untouched.
     */
    public function up(): void
    {
        Schema::create('budget_sub_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('label', 100);
            $table->decimal('allocated_amount', 10, 2);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // One label per budget: two rows both called "College" would split
            // the same earmark in every total that groups by name.
            $table->unique(['budget_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_sub_allocations');
    }
};
