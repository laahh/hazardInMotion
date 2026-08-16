<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

/**
 * Hitung baseline personal (mean & std) dari riwayat skor Fatigue Test.
 * Diekstrak jadi satu tempat supaya angka yang dipakai di skor risiko
 * (PraOperasiRiskScoreService) dan yang ditampilkan di grafik tren
 * (PraOperasiOperatorProfileReader) selalu konsisten.
 */
final class PraOperasiBaselineCalculator
{
    private const MIN_HISTORY = 5;

    private const STD_FLOOR = 0.5;

    /**
     * @param  list<array{date:string, score:int}>  $history
     * @return array{mean:float, std:float, n:int}|null  null jika riwayat kurang dari MIN_HISTORY
     */
    public static function compute(array $history): ?array
    {
        if (count($history) < self::MIN_HISTORY) {
            return null;
        }

        $scores = array_map(static fn (array $h): int => $h['score'], $history);
        $n = count($scores);
        $mean = array_sum($scores) / $n;
        $variance = array_sum(array_map(static fn (int $s): float => ($s - $mean) ** 2, $scores)) / $n;
        $std = max(self::STD_FLOOR, sqrt($variance));

        return ['mean' => round($mean, 2), 'std' => round($std, 2), 'n' => $n];
    }
}
