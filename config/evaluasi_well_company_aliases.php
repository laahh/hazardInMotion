<?php

declare(strict_types=1);

/**
 * Mapping alias nama perusahaan BeWell → label kanonik.
 * Key = nama tampilan; value = daftar alias mentah (exact, case-insensitive).
 */
return [
    'PT Berau Coal' => [
        'PT Berau Coal',
        'PT. Berau Coal',
        'PT.Berau Coal',
        'Berau Coal',
        'PT Berau Coal Energy',
        'PT. Berau Coal Energy',
        'PT Berau Coal Energi',
        'PT. Berau Coal Energi',
        'PT Berau Coal Enegry', // typo umum di data
        'Berau Coal Energy',
        'Berau Coal Energi',
    ],
];
