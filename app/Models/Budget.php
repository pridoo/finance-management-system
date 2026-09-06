<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'assistance_type', 'allocated_amount', 'fiscal_year', 'created_by',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'fiscal_year' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function disbursements()
    {
        return $this->hasMany(Disbursement::class);
    }

    public function getTotalReleasedAttribute(): float
    {
        return (float) ($this->attributes['disbursements_sum_amount_released']
            ?? $this->disbursements()->sum('amount_released'));
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0, (float) $this->allocated_amount - $this->total_released);
    }
}
