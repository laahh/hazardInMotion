<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Metrics;

/**
 * plan-OCR.md T2.1 — "% TBC". Basis-agnostik dengan sengaja: menerima
 * count yang sudah dihitung pemanggil, karena apakah TBC dihitung per-baris
 * (join langsung ke laporan) atau agregat per personil/minggu masih
 * menunggu inventarisasi struktur Google Sheet (plan-OCR.md Lampiran D #23,
 * lihat juga T1.5). Class ini tidak perlu tahu bedanya — begitu sumbernya
 * jelas, pemanggil (Aggregation service) yang menghitung kedua angka ini.
 */
final class TbcValidity
{
    public function percentage(int $tbcValidatedCount, int $hazardInspeksiTotal): ?float
    {
        if ($hazardInspeksiTotal === 0) {
            return null;
        }

        return round(($tbcValidatedCount / $hazardInspeksiTotal) * 100, 2);
    }
}
