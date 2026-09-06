<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'label', 'permissions', 'is_system'];

    protected $casts = [
        'permissions' => 'array',
        'is_system'   => 'boolean',
    ];

    /** Per-request cache; cleared on write so saves are never stale. */
    protected static ?\Illuminate\Support\Collection $keyed = null;

    public function users()
    {
        return $this->hasMany(User::class, 'role', 'key');
    }

    /** All roles keyed by their `key`, loaded once per request. */
    public static function allKeyed(): \Illuminate\Support\Collection
    {
        return static::$keyed ??= static::orderBy('label')->get()->keyBy('key');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::$keyed = null);
        static::deleted(fn () => static::$keyed = null);
    }

    /**
     * 'admin' carries hardcoded meaning throughout the app — it is the only
     * role that reaches user management — so a custom role can never claim it.
     */
    public function isAdminRole(): bool
    {
        return $this->key === 'admin';
    }

    /** Turn a label into a stable, unique key: "Budget Clerk" -> budget_clerk. */
    public static function makeKey(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'role';
        $key  = $base;
        $i    = 2;

        while (static::where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }

        return $key;
    }
}
