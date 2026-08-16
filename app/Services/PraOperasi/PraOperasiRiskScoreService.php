<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

/**
 * Skor risiko komposit per operator — sistem POIN TRANSPARAN (bukan black-box),
 * supaya level Merah/Kuning/Hijau selalu bisa dijelaskan alasannya ke siapa pun.
 *
 * Sinyal yang digabung:
 *   1. Kepatuhan kontrol hari ini (Fatigue Test & PVT sudah/belum)
 *   2. Hasil Fatigue Test hari ini (tier)
 *   3. Riwayat penyakit kritis tanpa follow-up Fatigue Test
 *   4. Frekuensi alert DMS terkonfirmasi nyata (ambang P90 = 177/30 hari,
 *      dari distribusi riil per 15 Agu 2026) + arah tren
 *   5. Penyimpangan skor Fatigue Test hari ini dari baseline personal (>2σ di bawah)
 */
final class PraOperasiRiskScoreService
{
    /** P90 dari distribusi jumlah alert terkonfirmasi nyata per orang / 30 hari (data riil). */
    private const ALERT_HIGH_THRESHOLD = 177;

    private const ZSCORE_THRESHOLD = -2.0;

    private const TIER_NAMES = [0 => 'hijau', 1 => 'kuning', 2 => 'merah'];

    /**
     * @param  array<string, mixed>  $row  baris watchlist (fatigue_done, fatigue_tier, fatigue_score, pvt_status)
     * @param  array{has_critical_illness:bool, confirmed_date:string|null, followed_up:bool}|null  $criticalIllness
     * @param  array{count:int, trend:string, ratio:float|null}|null  $alertStats
     * @param  list<array{date:string, score:int}>  $scoreHistory  riwayat skor (bisa termasuk tanggal acuan — akan disaring)
     * @return array{tier:string, reasons:list<string>, baseline: array{mean:float,std:float,n:int}|null, zscore: float|null}
     */
    public function score(array $row, ?array $criticalIllness, ?array $alertStats, array $scoreHistory, string $untilDate): array
    {
        $level = 0;
        $reasons = [];

        $fatigueDone = (bool) ($row['fatigue_done'] ?? false);
        $pvtStatus = (string) ($row['pvt_status'] ?? 'belum');
        $fatigueTier = $row['fatigue_tier'] ?? null;

        $missingFatigue = ! $fatigueDone;
        $missingPvt = $pvtStatus === 'belum';
        $missingCount = ($missingFatigue ? 1 : 0) + ($missingPvt ? 1 : 0);

        if ($missingCount === 2) {
            $level = max($level, 2);
            $reasons[] = 'Fatigue Test dan PVT keduanya belum dilakukan hari ini';
        } elseif ($missingCount === 1) {
            $level = max($level, 1);
            $reasons[] = $missingFatigue ? 'Fatigue Test belum dilakukan hari ini' : 'PVT belum dilakukan hari ini';
        }

        if ($fatigueTier === 'merah') {
            $level = max($level, 2);
            $reasons[] = 'Hasil Fatigue Test hari ini berada di tier Merah';
        }

        if (($criticalIllness['has_critical_illness'] ?? false) && ! ($criticalIllness['followed_up'] ?? false)) {
            $level = min(2, $level + 1);
            $reasons[] = 'Ada riwayat penyakit kritis terkonfirmasi ('.($criticalIllness['confirmed_date'] ?? '-').') tanpa Fatigue Test follow-up sejak saat itu';
        }

        if (($alertStats['count'] ?? 0) >= self::ALERT_HIGH_THRESHOLD && ($alertStats['trend'] ?? '') === 'meningkat') {
            $level = min(2, $level + 1);
            $reasons[] = sprintf(
                'Alert fatigue terkonfirmasi tinggi (%s alert/30 hari, di atas 90%% operator lain) dan tren meningkat',
                number_format((int) $alertStats['count'])
            );
        }

        $history = array_values(array_filter($scoreHistory, static fn (array $h): bool => $h['date'] !== $untilDate));
        $baseline = $this->computeBaseline($history);
        $zscore = null;

        if ($baseline !== null && $fatigueDone && $row['fatigue_score'] !== null) {
            $zscore = round((((int) $row['fatigue_score']) - $baseline['mean']) / $baseline['std'], 2);
            if ($zscore <= self::ZSCORE_THRESHOLD) {
                $level = min(2, $level + 1);
                $reasons[] = sprintf(
                    'Skor Fatigue Test hari ini (%d) jauh di bawah kebiasaan pribadi orang ini (rata-rata %.1f dari %d tes terakhir)',
                    (int) $row['fatigue_score'],
                    $baseline['mean'],
                    $baseline['n']
                );
            }
        }

        if ($reasons === []) {
            $reasons[] = 'Semua kontrol lengkap dan tidak ada sinyal risiko tambahan yang terdeteksi';
        }

        return [
            'tier' => self::TIER_NAMES[$level],
            'reasons' => $reasons,
            'baseline' => $baseline,
            'zscore' => $zscore,
        ];
    }

    /**
     * @param  list<array{date:string, score:int}>  $history
     * @return array{mean:float, std:float, n:int}|null
     */
    private function computeBaseline(array $history): ?array
    {
        return PraOperasiBaselineCalculator::compute($history);
    }
}
