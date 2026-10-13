<?php

namespace App\Support;

/**
 * The documents the MSWDO asks for, per type of assistance.
 *
 * Taken from the checklists handed out at the San Agustin MSWDO counter.
 * Kept here rather than in the request form so the sidebar list and the
 * "submitted" checkboxes cannot drift apart.
 */
class Requirements
{
    /**
     * Each entry is [label, note]; the note is shown smaller beside the label
     * and is null when there is nothing to add.
     *
     * @var array<string, array<int, array{0: string, 1: ?string}>>
     */
    private const BY_TYPE = [
        'Medical' => [
            ['Personal letter', 'Addressed to Hon. Raden A. Mondala, Municipal Mayor'],
            ['Barangay indigency', 'Of the patient, or of the guardian/parents if 18 and below'],
            ['Medical certification / abstract', null],
            ['Valid ID', null],
        ],
        'Burial' => [
            ['Personal letter', 'Addressed to Hon. Raden A. Mondala, Municipal Mayor'],
            ['Barangay indigency', null],
            ['Death certificate', null],
            ['Funeral contract', null],
            ['Valid ID', null],
        ],
        'Educational' => [
            ['Personal letter', null],
            ['Assessment form', null],
            ['CWA', null],
            ['Barangay indigency', null],
            ['School ID', null],
            ['Enrollment form', null],
            ["Voter's certificate", null],
        ],
        // No checklist has been issued for Livelihood yet, so it asks for the
        // documents every other type has in common.
        'Livelihood' => [
            ['Personal letter', 'Addressed to Hon. Raden A. Mondala, Municipal Mayor'],
            ['Barangay indigency', null],
            ['Valid ID', null],
        ],
    ];

    /** @return array<string, array<int, array{0: string, 1: ?string}>> */
    public static function all(): array
    {
        return self::BY_TYPE;
    }

    /** @return array<int, array{0: string, 1: ?string}> */
    public static function for(string $type): array
    {
        return self::BY_TYPE[$type] ?? [];
    }

    /** @return string[] */
    public static function labels(string $type): array
    {
        return array_column(self::for($type), 0);
    }
}
