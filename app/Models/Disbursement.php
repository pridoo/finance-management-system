<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Disbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id', 'budget_id', 'amount_released',
        'released_by', 'released_at', 'remarks',
    ];

    protected $casts = [
        'released_at' => 'datetime',
        'amount_released' => 'decimal:2',
    ];

    public function request()
    {
        return $this->belongsTo(AssistanceRequest::class, 'request_id');
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function releasedBy()
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
