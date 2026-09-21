<?php

namespace App\Support;

/**
 * The barangays of San Agustin, Isabela.
 *
 * Kept here rather than in a Blade file so the registration form, the
 * beneficiary filter and the request filter all offer the same list: a
 * barangay that exists in one dropdown but not another silently splits the
 * same village into two groups in the reports.
 */
class Barangay
{
    /** @var string[] */
    private const ALL = [
        'Bautista',
        'Calaocan',
        'Dabubu Grande',
        'Dabubu Pequeño',
        'Dappig',
        'Laoag',
        'Mapalad',
        'Masaya Centro (Poblacion)',
        'Masaya Norte',
        'Masaya Sur',
        'Nemmatan',
        'Palacian',
        'Panang',
        'Quimalabasa Norte',
        'Quimalabasa Sur',
        'Rang-ay',
        'Salay',
        'San Antonio',
        'Santo Niño',
        'Santos',
        'Sinaoangan Norte',
        'Sinaoangan Sur',
        'Virgoneza',
    ];

    /** @return string[] */
    public static function all(): array
    {
        return self::ALL;
    }

    /**
     * The official list, plus any barangay already stored on a beneficiary.
     *
     * Records captured before this list was corrected may carry a spelling
     * that is no longer offered. Dropping those from the filter would hide
     * those beneficiaries entirely, so they stay selectable until the data is
     * cleaned up.
     *
     * @param  iterable<string>  $existing
     * @return string[]
     */
    public static function withExisting(iterable $existing): array
    {
        $merged = array_unique(array_filter(array_merge(
            self::ALL,
            is_array($existing) ? $existing : iterator_to_array($existing)
        )));

        sort($merged, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($merged);
    }
}
