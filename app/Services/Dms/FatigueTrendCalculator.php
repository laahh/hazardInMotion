<?php

declare(strict_types=1);

namespace App\Services\Dms;

use App\Services\PraOperasi\PraOperasiBaselineCalculator;
use Illuminate\Support\Carbon;

/**
 * Statistik personal + proyeksi kapan kemungkinan operator "fatigue" (melewati
 * ambang 2 sigma dari baseline pribadinya) berdasarkan tren alert DMS 14 hari
 * terakhir — dipakai oleh FatigueBaselineService (/dms/fatigue-baseline-static).
 *
 * Pola transparan (bukan model black-box), selaras dengan filosofi seluruh
 * modul Pra Operasi: baseline = mean & std jumlah alert per hari (window lebih
 * lama, TIDAK termasuk 14 hari terakhir supaya baseline tidak ikut tercemar
 * tren yang sedang terjadi), lalu proyeksi linear sederhana dari 14 hari
 * terakhir untuk memperkirakan kapan garis tren memotong ambang.
 *
 * Prediksi ini SENGAJA hanya proyeksi tren linear, bukan forecast tervalidasi —
 * pesan yang dikembalikan selalu eksplisit soal ini supaya tidak disalahartikan
 * sebagai kepastian.
 */
final class FatigueTrendCalculator
{
    public const TREND_DAYS = 14;

    public const THRESHOLD_SIGMA = 2.0;

    public const PREDICTION_HORIZON_DAYS = 30;

    private const EWMA_ALPHA = 0.2;

    private const SLOPE_EPSILON = 0.01;

    /**
     * @param  array<string, int>  $dailyCounts  tanggal (Y-m-d) => jumlah alert hari itu,
     *                                            urut kronologis ASC, TANPA lubang tanggal (gap
     *                                            harus sudah diisi 0 oleh pemanggil)
     * @return array{
     *     mean: float|null, std: float|null, n: int,
     *     hist: list<float>, ewma: list<float>, dates: list<string>,
     *     rate: float, z: float|null, slope: float,
     *     threshold: float|null,
     *     prediction: array{status:string, days:int|null, date:string|null, message:string},
     *     riskBucket: string, tier: int
     * }
     */
    public static function compute(array $dailyCounts, string $today): array
    {
        $dates = array_keys($dailyCounts);
        $values = array_map(static fn ($v): float => (float) $v, array_values($dailyCounts));
        $total = count($values);

        $trendDays = min(self::TREND_DAYS, $total);
        $baselineValues = array_slice($values, 0, $total - $trendDays);
        $baselineDates = array_slice($dates, 0, $total - $trendDays);

        $baselineHistory = [];
        foreach ($baselineDates as $i => $d) {
            $baselineHistory[] = ['date' => $d, 'score' => (int) round($baselineValues[$i])];
        }
        $baseline = PraOperasiBaselineCalculator::compute($baselineHistory);

        $ewma = self::ewma($values, self::EWMA_ALPHA);
        $lastRaw = $values === [] ? 0.0 : end($values);
        $lastEwma = $ewma === [] ? 0.0 : end($ewma);

        // Slope dihitung dari EWMA (bukan raw) di window tren — supaya satu
        // hari lonjakan ekstrem tidak mendominasi arah tren yang diproyeksikan.
        $trendEwma = array_slice($ewma, -$trendDays);
        $slope = self::linearSlope($trendEwma);

        $z = $baseline !== null ? round(($lastRaw - $baseline['mean']) / $baseline['std'], 2) : null;
        $zEwma = $baseline !== null ? round(($lastEwma - $baseline['mean']) / $baseline['std'], 2) : null;
        $threshold = $baseline !== null ? round($baseline['mean'] + self::THRESHOLD_SIGMA * $baseline['std'], 2) : null;

        // Titik jangkar proyeksi = EWMA saat ini (bukan regresi ulang) supaya
        // "sudah di atas ambang sekarang" selalu konsisten dengan angka EWMA
        // yang juga ditampilkan ke pengguna, bukan angka tersembunyi lain.
        $prediction = self::predict($baseline, $threshold, $lastEwma, $slope, $zEwma, $today);
        $riskBucket = self::riskBucket($z, $prediction);

        return [
            'mean' => $baseline['mean'] ?? null,
            'std' => $baseline['std'] ?? null,
            'n' => $baseline['n'] ?? 0,
            'hist' => $values,
            'ewma' => $ewma,
            'dates' => $dates,
            'rate' => $lastRaw,
            'z' => $z,
            'slope' => round($slope, 3),
            'threshold' => $threshold,
            'prediction' => $prediction,
            'riskBucket' => $riskBucket,
            'tier' => self::tierForBucket($riskBucket),
        ];
    }

    /**
     * @param  float  $currentEwma  EWMA saat ini — titik jangkar proyeksi, supaya "sudah di
     *                              atas ambang" selalu konsisten dengan angka EWMA yang juga
     *                              ditampilkan ke pengguna (bukan hasil regresi ulang yang
     *                              bisa menyimpang jauh saat window tren berisi lonjakan ekstrem)
     * @param  float|null  $zEwma  z-score dari EWMA saat ini (bukan raw hari ini)
     * @return array{status:string, days:int|null, date:string|null, message:string}
     */
    private static function predict(?array $baseline, ?float $threshold, float $currentEwma, float $slope, ?float $zEwma, string $today): array
    {
        if ($baseline === null || $threshold === null) {
            return [
                'status' => 'insufficient_data',
                'days' => null,
                'date' => null,
                'message' => 'Riwayat alert belum cukup (minimal 5 hari data) untuk menghitung baseline personal.',
            ];
        }

        if ($zEwma !== null && $zEwma >= self::THRESHOLD_SIGMA) {
            return [
                'status' => 'already_over',
                'days' => null,
                'date' => null,
                'message' => 'Sudah berada di atas ambang risiko (>2\u{03c3} dari baseline pribadi) saat ini.',
            ];
        }

        if ($slope <= self::SLOPE_EPSILON) {
            return [
                'status' => 'no_trend',
                'days' => null,
                'date' => null,
                'message' => 'Tidak ada tren memburuk terdeteksi pada '.self::TREND_DAYS.' hari terakhir — rate saat ini stabil/menurun dibanding baseline.',
            ];
        }

        $daysToThreshold = ($threshold - $currentEwma) / $slope;

        if ($daysToThreshold <= 0) {
            // Seharusnya tidak tercapai (sudah dicek zEwma di atas), tapi dijaga
            // untuk kasus pembulatan floating-point di batas ambang persis.
            return [
                'status' => 'already_over',
                'days' => null,
                'date' => null,
                'message' => 'Sudah berada di atas ambang risiko (>2\u{03c3} dari baseline pribadi) saat ini.',
            ];
        }

        $days = (int) ceil($daysToThreshold);

        if ($days > self::PREDICTION_HORIZON_DAYS) {
            return [
                'status' => 'no_imminent',
                'days' => null,
                'date' => null,
                'message' => 'Tren memburuk terdeteksi, tapi diproyeksikan lebih dari '.self::PREDICTION_HORIZON_DAYS.' hari lagi mencapai ambang risiko — belum mendesak.',
            ];
        }

        $date = Carbon::parse($today)->addDays($days)->toDateString();

        return [
            'status' => 'projected',
            'days' => $days,
            'date' => $date,
            'message' => 'Diperkirakan mencapai ambang risiko (>2\u{03c3} dari baseline pribadi) sekitar '.Carbon::parse($date)->translatedFormat('d M Y')." (~{$days} hari lagi) jika tren 14 hari terakhir berlanjut.",
        ];
    }

    /**
     * @param  array{status:string, days:int|null, date:string|null, message:string}  $prediction
     */
    private static function riskBucket(?float $z, array $prediction): string
    {
        if ($prediction['status'] === 'already_over' || ($z !== null && $z >= self::THRESHOLD_SIGMA)) {
            return 'extreme';
        }
        if ($prediction['status'] === 'projected' && $prediction['days'] !== null && $prediction['days'] <= 7) {
            return 'high';
        }
        if ($prediction['status'] === 'projected' && $prediction['days'] !== null && $prediction['days'] <= self::PREDICTION_HORIZON_DAYS) {
            return 'medium';
        }
        if ($z !== null && $z >= 1.0) {
            return 'medium';
        }

        return 'low';
    }

    private static function tierForBucket(string $bucket): int
    {
        return match ($bucket) {
            'extreme', 'high' => 2,
            'medium' => 1,
            default => 0,
        };
    }

    /**
     * @param  list<float>  $values
     * @return list<float>
     */
    private static function ewma(array $values, float $alpha): array
    {
        $out = [];
        $prev = null;
        foreach ($values as $i => $v) {
            $prev = $i === 0 ? $v : $alpha * $v + (1 - $alpha) * $prev;
            $out[] = round($prev, 3);
        }

        return $out;
    }

    /**
     * Kemiringan regresi linear sederhana (least squares) atas titik x=0..n-1.
     *
     * @param  list<float>  $values
     */
    private static function linearSlope(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }

        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumXX = 0.0;
        foreach ($values as $i => $y) {
            $sumX += $i;
            $sumY += $y;
            $sumXY += $i * $y;
            $sumXX += $i * $i;
        }

        $denominator = $n * $sumXX - $sumX * $sumX;
        if ($denominator === 0.0) {
            return 0.0;
        }

        return ($n * $sumXY - $sumX * $sumY) / $denominator;
    }
}
