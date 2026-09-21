<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A named portion of a budget — "College", "TUPAD", "School Supplies".
 *
 * Splits the parent allocation for reporting. Releases are still recorded
 * against the budget itself, so a sub-allocation says what the money was set
 * aside for, not what has been paid out of it.
 */
class BudgetSubAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id', 'label', 'allocated_amount', 'created_by',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
