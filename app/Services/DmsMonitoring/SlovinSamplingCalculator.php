<?php

declare(strict_types=1);

namespace App\Services\DmsMonitoring;

/**
 * Rumus Slovin: n = N / (1 + N·e²) — ukuran sampel minimum supaya hasil audit
 * sampel bisa dianggap mewakili seluruh populasi, dengan margin of error e.
 * Transparan (rumus statistik biasa), bukan model apa pun.
 */
final class SlovinSamplingCalculator
{
    public const DEFAULT_MARGIN_OF_ERROR = 0.05;

    /**
     * @param  int  $population  N — total populasi (mis. jumlah alert yang di-dismiss L1 dalam periode)
     * @param  float  $marginOfError  e — margin of error, mis. 0.05 = 5% (confidence level ~95%)
     * @return int  n — ukuran sampel minimum yang disarankan, dibulatkan ke atas
     */
    public static function sampleSize(int $population, float $marginOfError = self::DEFAULT_MARGIN_OF_ERROR): int
    {
        if ($population <= 0) {
            return 0;
        }
        if ($marginOfError <= 0) {
            return $population;
        }

        $n = $population / (1 + $population * ($marginOfError ** 2));

        return (int) min($population, max(1, ceil($n)));
    }
}
