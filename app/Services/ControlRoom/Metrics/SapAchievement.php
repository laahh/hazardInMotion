<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Metrics;

/**
 * plan-OCR.md T2.1 — "% SAP". Target per personil per shift: 1 hazard +
 * 1 inspeksi + 1 observasi (observasi ATAU oak, keduanya mengisi slot yang
 * sama — lihat config('control-room.sap_sources')).
 *
 * CATATAN: bonus di atas 100% (laporan tambahan + baris coaching) belum
 * diimplementasikan — besarannya masih open question (plan-OCR.md
 * Lampiran D #20, "coaching menambah berapa persen ke %SAP?"). percentage()
 * di bawah cuma menghitung basis 0-100% dari 3 komponen wajib.
 */
final class SapAchievement
{
    /**
     * @param  array<string, int>  $componentCounts  key: hazard/inspeksi/observasi/oak
     */
    public function percentage(array $componentCounts): float
    {
        $targetComponents = config('control-room.sap_target_components', ['hazard', 'inspeksi', 'observasi']);

        if ($targetComponents === []) {
            return 0.0;
        }

        $slots = [
            'hazard' => (int) ($componentCounts['hazard'] ?? 0),
            'inspeksi' => (int) ($componentCounts['inspeksi'] ?? 0),
            'observasi' => (int) ($componentCounts['observasi'] ?? 0) + (int) ($componentCounts['oak'] ?? 0),
        ];

        $fulfilled = 0;
        foreach ($targetComponents as $component) {
            if (($slots[$component] ?? $componentCounts[$component] ?? 0) >= 1) {
                $fulfilled++;
            }
        }

        return round(($fulfilled / count($targetComponents)) * 100, 2);
    }
}
