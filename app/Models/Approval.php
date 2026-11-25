<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id', 'recorded_by', 'decision', 'budget_id', 'remarks', 'decided_at',
    ];

    protected $casts = ['decided_at' => 'datetime'];

    public function request()
    {
        return $this->belongsTo(AssistanceRequest::class, 'request_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** The funding source chosen when the request was approved. */
    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }
}
