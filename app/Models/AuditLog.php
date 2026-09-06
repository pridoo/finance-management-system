<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'action', 'module', 'record_id', 'ip_address', 'performed_at',
    ];

    protected $casts = ['performed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, string $module, ?int $recordId = null): void
    {
        static::create([
            'user_id'      => auth()->id(),
            'action'       => $action,
            'module'       => $module,
            'record_id'    => $recordId,
            'ip_address'   => request()->ip(),
            'performed_at' => now(),
        ]);
    }
}
