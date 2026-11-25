<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row of section B (Family Composition) of the Family Intake Data Form. */
class FamilyMember extends Model
{
    protected $fillable = [
        'client_id', 'name', 'age', 'sex', 'civil_status', 'birth_date',
        'relationship', 'educational_attainment', 'occupation_income',
        'occupation', 'monthly_income',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'age' => 'integer',
        'monthly_income' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /** The age as of today when a birth date is on file, else the age typed in. */
    public function getCurrentAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : $this->age;
    }
}
