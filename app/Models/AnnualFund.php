<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The year's whole assistance fund. The per-type budgets are carved out of it,
 * so the sum of a year's allocations can never exceed it.
 */
class AnnualFund extends Model
{
    protected $fillable = [
        'fiscal_year', 'amount', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fiscal_year' => 'integer',
    ];

    public static function forYear(int $year): ?self
    {
        return static::where('fiscal_year', $year)->first();
    }

    /** Sum of every assistance type's allocation for this fund's year. */
    public function allocatedTotal(?int $exceptBudgetId = null): float
    {
        return (float) Budget::where('fiscal_year', $this->fiscal_year)
            ->when($exceptBudgetId, fn ($q) => $q->whereKeyNot($exceptBudgetId))
            ->sum('allocated_amount');
    }

    /** What is still free to be allocated to an assistance type. */
    public function unallocated(): float
    {
        return max(0, (float) $this->amount - $this->allocatedTotal());
    }
}
