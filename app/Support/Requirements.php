<?php

namespace App\Support;

/**
 * The documents the MSWDO asks for, per type of assistance.
 *
 * Taken from the checklists handed out at the San Agustin MSWDO counter, as
 * revised by the office. Kept here rather than in the request form so the
 * sidebar list, the "submitted" checkboxes and the server check cannot drift
 * apart.
 */
class Requirements
{
    /** Must be submitted. */
    public const REQUIRED = 'required';

    /** "If applicable" — recorded when handed in, never blocks a request. */
    public const OPTIONAL = 'optional';

    /** Required, except for a client referred to the office. */
    public const UNLESS_REFERRED = 'unless_referred';

    /** Optional, except for a scholar applying for educational assistance. */
    public const IF_SCHOLAR = 'if_scholar';

    private const LETTER_NOTE = 'Addressed to Hon. Raden A. Mondala, Municipal Mayor';

    /**
     * Each entry is [label, note, rule]; the note is shown smaller beside the
     * label and is null when there is nothing to add.
     *
     * @var array<string, array<int, array{0: string, 1: ?string, 2: string}>>
     */
    private const BY_TYPE = [
        'Medical' => [
            ['Barangay indigency', 'Of the patient, or of the guardian/parents if 18 and below', self::REQUIRED],
            ['Medical certificate', 'Medical abstract or certificate of confinement', self::REQUIRED],
            ["Doctor's prescription", 'Treatment protocol / treatment plan', self::REQUIRED],
            ['Quotation or hospital bill', null, self::REQUIRED],
            ['Valid ID of client', null, self::REQUIRED],
            ['Valid ID of processor', 'The person processing on the client\'s behalf', self::REQUIRED],
            ['Personal letter', self::LETTER_NOTE . ' · not needed if referred', self::UNLESS_REFERRED],
        ],
        'Burial' => [
            ['Barangay indigency', null, self::REQUIRED],
            ['Death certificate', null, self::REQUIRED],
            ['Funeral contract', null, self::REQUIRED],
            ['Valid ID of processor', null, self::REQUIRED],
            ['Personal letter', 'If applicable', self::OPTIONAL],
        ],
        'Educational' => [
            ['Barangay indigency', null, self::REQUIRED],
            ['Enrollment form', null, self::REQUIRED],
            ['Student assessment form', null, self::OPTIONAL],
            ['Certificate of grades (COG)', 'Required for scholars', self::IF_SCHOLAR],
            ['School ID', null, self::OPTIONAL],
            ['CWA', null, self::OPTIONAL],
            ["Voter's certificate", null, self::OPTIONAL],
            ['Personal letter', 'If applicable', self::OPTIONAL],
        ],
        'Livelihood' => [
            ['Training certificate', 'If applicable', self::OPTIONAL],
            ['Personal letter', 'If applicable', self::OPTIONAL],
            ['Photo documentation of livelihood', 'If applicable', self::OPTIONAL],
        ],
    ];

    /** @return array<string, array<int, array{0: string, 1: ?string, 2: string}>> */
    public static function all(): array
    {
        return self::BY_TYPE;
    }

    /** @return array<int, array{0: string, 1: ?string, 2: string}> */
    public static function for(string $type): array
    {
        return self::BY_TYPE[$type] ?? [];
    }

    /** @return string[] */
    public static function labels(string $type): array
    {
        return array_column(self::for($type), 0);
    }

    /**
     * The documents that have to be in hand before this request can be filed.
     *
     * @return string[]
     */
    public static function mandatory(string $type, bool $referred = false, bool $scholar = false): array
    {
        return array_values(array_map(fn ($doc) => $doc[0], array_filter(
            self::for($type),
            fn ($doc) => match ($doc[2]) {
                self::REQUIRED        => true,
                self::UNLESS_REFERRED => ! $referred,
                self::IF_SCHOLAR      => $scholar,
                default               => false,
            }
        )));
    }

    /** The badge shown beside a document on the request form. */
    public static function ruleLabel(string $rule): string
    {
        return match ($rule) {
            self::REQUIRED        => 'Required',
            self::UNLESS_REFERRED => 'Required unless referred',
            self::IF_SCHOLAR      => 'Required for scholars',
            default               => 'Optional',
        };
    }
}
