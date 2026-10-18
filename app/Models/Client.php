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
        // Family Intake Data Form
        'intake_date', 'religion', 'birth_place', 'educational_attainment',
        'occupation', 'monthly_income', 'purok', 'category',
        'employment_nature', 'housing', 'water_source', 'toilet', 'light',
        'housing_ownership', 'housing_structure', 'family_head_id',
        // A. Source of information / referring party
        'referrer_name', 'referrer_civil_status', 'referrer_sex', 'referrer_date_of_birth',
        'referrer_birth_place', 'referrer_religion', 'referrer_educational_attainment',
        'referrer_occupation', 'referrer_address', 'referrer_income',
        'referrer_contact_number', 'referrer_relationship', 'client_relationship',
        'problem_presented', 'initial_assessment', 'recommendation', 'plan_of_action',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'intake_date' => 'date',
        'monthly_income' => 'decimal:2',
        'referrer_date_of_birth' => 'date',
        'referrer_income' => 'decimal:2',
    ];

    /** Whether section A (referring party) has anything in it. */
    public function getHasReferrerAttribute(): bool
    {
        return filled($this->referrer_name);
    }

    /** The options offered on the intake form; free text is still accepted. */
    public const CATEGORIES = [
        'FHONA - Family Head and Other Needy Adult',
        'YNSP - Youth in Need of Special Protection',
        'Senior Citizen', 'Person with Disability (PWD)', 'Solo Parent',
        '4Ps Beneficiary', 'Indigenous People (IP)', 'Indigent',
        'Out-of-School Youth', 'Women in Especially Difficult Circumstances',
        'Child in Need of Special Protection',
    ];

    /**
     * One occupation list for the client and every household member, so the
     * two can be compared and totalled the same way.
     */
    public const OCCUPATIONS = [
        'Unemployed', 'Student', 'Farmer', 'Fisherfolk', 'Laborer',
        'Construction Worker', 'Driver', 'Vendor', 'Housekeeper / Helper',
        'Self-employed', 'Private Employee', 'Government Employee', 'OFW',
        'Retired / Pensioner', 'Others',
    ];

    /** Occupations that by definition earn nothing; their income is fixed at 0. */
    public const NO_INCOME_OCCUPATIONS = ['Unemployed', 'Student'];

    public const CIVIL_STATUSES = ['Single', 'Married', 'Widowed', 'Separated', 'Annulled', 'Live-in'];

    public const RELATIONSHIPS = [
        'Spouse', 'Son', 'Daughter', 'Father', 'Mother', 'Brother', 'Sister',
        'Grandchild', 'Grandparent', 'Son-in-law', 'Daughter-in-law', 'Relative', 'Other',
    ];

    public const HOUSING_OWNERSHIP = ['Owned', 'Rented', 'Shared'];

    public const HOUSING_STRUCTURES = ['Concrete', 'Semi-concrete', 'Light materials'];

    public const WATER_SOURCES = ['Deep well', 'Public Water Facility', 'None'];

    public const TOILET_FACILITIES = ['Open Pit', 'Water Sealed', 'None'];

    public const EDUCATION_LEVELS = [
        'No Formal Education', 'Elementary Level', 'Elementary Graduate',
        'High School Level', 'High School Graduate', 'Senior High School Graduate',
        'Vocational', 'College Level', 'College Graduate', 'Post Graduate',
    ];

    public const EMPLOYMENT_NATURES = ['Casual', 'Permanent', 'Temporary', 'Unemployed'];

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

    public function familyMembers()
    {
        return $this->hasMany(FamilyMember::class)->orderBy('id');
    }

    /** The Family Head this client's household is recorded under, if any. */
    public function familyHead()
    {
        return $this->belongsTo(Client::class, 'family_head_id');
    }

    /** Clients registered as members of this client's household. */
    public function householdClients()
    {
        return $this->hasMany(Client::class, 'family_head_id');
    }

    /**
     * IDs of every registered client in this household: the Family Head and
     * everyone linked to them, this client included.
     *
     * @return int[]
     */
    public function householdIds(): array
    {
        $headId = $this->family_head_id ?: $this->id;

        return static::where('id', $headId)
            ->orWhere('family_head_id', $headId)
            ->pluck('id')
            ->push($this->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Client's own income plus every household member's. Unemployed and
     * student members count as zero whatever was typed.
     */
    public function getFamilyIncomeAttribute(): float
    {
        $own = in_array($this->occupation, self::NO_INCOME_OCCUPATIONS, true) ? 0 : (float) $this->monthly_income;

        return $own + (float) $this->familyMembers->sum(fn ($m) =>
            in_array($m->occupation, self::NO_INCOME_OCCUPATIONS, true) ? 0 : (float) $m->monthly_income);
    }

    /** "Purok 3, Calao, San Agustin, Isabela" — the address line as the form writes it. */
    public static function composeAddress(?string $purok, string $barangay): string
    {
        $purok = trim((string) $purok);

        return ($purok !== '' ? 'Purok ' . $purok . ', ' : '') . $barangay . ', San Agustin, Isabela';
    }
}
