<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'last_name', 'first_name', 'middle_name', 'suffix',
        'date_of_birth', 'sex', 'civil_status', 'address',
        'barangay', 'contact_number',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    /**
     * Normalise names on the way in, not just on the way out.
     *
     * The form title-cases as the clerk types, but that is a convenience the
     * browser can skip — a paste, an autofill, or a request that never went
     * through the form at all. Doing it here means what is stored is always
     * consistent, so searching and sorting behave.
     */
    public function setFirstNameAttribute($value): void
    {
        $this->attributes['first_name'] = $this->formatNamePart($value);
    }

    public function setLastNameAttribute($value): void
    {
        $this->attributes['last_name'] = $this->formatNamePart($value);
    }

    public function setMiddleNameAttribute($value): void
    {
        $this->attributes['middle_name'] = $this->formatNamePart($value) ?: null;
    }

    /** Store contact numbers as 11 bare digits, however they were typed. */
    public function setContactNumberAttribute($value): void
    {
        $digits = preg_replace('/\D/', '', (string) $value);
        // "+639171234567" and "639171234567" are the same subscriber as
        // "09171234567"; keep one shape in the column.
        if (str_starts_with($digits, '639') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }
        $this->attributes['contact_number'] = $digits === '' ? null : $digits;
    }

    /**
     * Title-case a name part for display.
     *
     * Records are encoded inconsistently — some all lowercase, some shouted in
     * caps — so the stored casing is normalised here rather than trusted. Uses
     * mb_convert_case so names with accents (Peña, Ordoñez) are not mangled.
     */
    protected function formatNamePart(?string $value): string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? '' : mb_convert_case($trimmed, MB_CASE_TITLE, 'UTF-8');
    }

    public function getFullNameAttribute(): string
    {
        $first  = $this->formatNamePart($this->first_name);
        $last   = $this->formatNamePart($this->last_name);
        $middle = $this->middle_name
            ? ' ' . mb_strtoupper(mb_substr(trim($this->middle_name), 0, 1, 'UTF-8'), 'UTF-8') . '.'
            : '';
        $suffix = $this->suffix ? ' ' . $this->formatNamePart($this->suffix) : '';

        return trim($first . $middle . ' ' . $last . $suffix);
    }

    /** "Dela Cruz, Juan T." — for lists and reports that sort by surname. */
    public function getFormalNameAttribute(): string
    {
        $middle = $this->middle_name
            ? ' ' . mb_strtoupper(mb_substr(trim($this->middle_name), 0, 1, 'UTF-8'), 'UTF-8') . '.'
            : '';
        $suffix = $this->suffix ? ' ' . $this->formatNamePart($this->suffix) : '';

        return trim($this->formatNamePart($this->last_name) . ', ' . $this->formatNamePart($this->first_name) . $middle . $suffix);
    }

    public function assistanceRequests()
    {
        return $this->hasMany(AssistanceRequest::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
