<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Metrics;

use App\Services\ControlRoom\Reference\LocationReaderContract;

/**
 * plan-OCR.md T2.1 — "Coverage Score".
 * skor = (COUNT DISTINCT lokasi non-kritis x 1) + (COUNT DISTINCT lokasi kritis x 2).
 *
 * BLOCKED: LocationReader::isCritical() melempar exception sampai kolom flag
 * kritis di bcbeats ditemukan (plan-OCR.md Lampiran D #26) — calculate() di
 * bawah akan ikut melempar exception yang sama selama itu belum selesai.
 * Ini disengaja: jangan fabrikasi skor dari asumsi kritis/non-kritis yang
 * belum terverifikasi.
 */
final class CoverageScore
{
    public function __construct(
        private readonly LocationReaderContract $locationReader,
    ) {}

    /**
     * @param  list<array{lokasi: string, detail_lokasi: string}>  $distinctLocations  lokasi unik (sudah di-DISTINCT oleh pemanggil)
     */
    public function calculate(array $distinctLocations): int
    {
        $weights = config('control-room.coverage_weight', ['normal' => 1, 'critical' => 2]);
        $score = 0;

        foreach ($distinctLocations as $location) {
            $isCritical = $this->locationReader->isCritical($location['lokasi'], $location['detail_lokasi']);
            $score += $isCritical ? $weights['critical'] : $weights['normal'];
        }

        return $score;
    }
}
