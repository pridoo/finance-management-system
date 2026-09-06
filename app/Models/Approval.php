<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id', 'recorded_by', 'decision', 'remarks', 'decided_at',
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
}
