<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Metrics;

/**
 * plan-OCR.md T2.1 — "Variasi Score".
 * variasi = COUNT DISTINCT kategori_temuan / COUNT total_temuan.
 * Basis kosong -> null (tampil "—"), bukan 0.
 */
final class FindingVariety
{
    /**
     * @param  list<string>  $categories  kategori temuan per baris (boleh duplikat)
     */
    public function score(array $categories): ?float
    {
        $total = count($categories);

        if ($total === 0) {
            return null;
        }

        $distinct = count(array_unique($categories));

        return round($distinct / $total, 2);
    }
}
