<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssistanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'assistance_type', 'amount_requested',
        'purpose', 'status', 'encoded_by',
    ];

    protected $casts = [
        'amount_requested' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function encoder()
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    public function approval()
    {
        return $this->hasOne(Approval::class, 'request_id');
    }

    public function disbursement()
    {
        return $this->hasOne(Disbursement::class, 'request_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'request_id');
    }
}
