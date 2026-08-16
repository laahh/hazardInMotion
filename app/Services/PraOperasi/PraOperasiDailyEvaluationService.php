<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Models\PraOperasi\PraOperasiEvaluasiHarian;
use Illuminate\Support\Carbon;

/**
 * Fase 3 (Pasca Operasi) — hitung & simpan evaluasi harian per operator.
 * Dipanggil dari command terjadwal (EvaluateDayCommand), BUKAN dari request
 * HTTP — hasilnya "dibekukan" di tabel pra_operasi_evaluasi_harian supaya
 * dashboard besok tinggal baca, tidak menghitung ulang.
 *
 * Kategori evaluasi (sistem poin transparan, sama filosofinya dengan
 * PraOperasiRiskScoreService tapi berbasis KEJADIAN HARI ITU, bukan prediksi
 * ke depan):
 *   - Fatigue Test tier Merah, ATAU PVT tidak lulus     → minimal Perlu Pembinaan
 *   - Fatigue Test DAN PVT keduanya belum dilakukan     → langsung Kritis (gagal kontrol)
 *   - Ada >=1 alert dikonfirmasi nyata hari itu          → minimal Perlu Pembinaan
 *   - Ada >=2 alert dikonfirmasi nyata (berulang)        → Kritis
 *   - Skor hari ini <= -2 SD dari baseline pribadi        → minimal Perlu Pembinaan
 */
final class PraOperasiDailyEvaluationService
{
    private const REPEAT_ALERT_THRESHOLD = 2;

    private const ZSCORE_THRESHOLD = -2.0;

    public function __construct(
        private readonly PraOperasiCheckinReader $checkinReader,
        private readonly PraOperasiFatigueCheckReader $fatigueReader,
        private readonly PraOperasiDmsAlertReader $dmsAlertReader,
        private readonly PraOperasiPvtStatusReader $pvtReader,
    ) {}

    /**
     * Hitung & upsert evaluasi harian untuk semua operator yang checkin pada $date.
     *
     * @return array{processed:int, baik:int, perlu_pembinaan:int, kritis:int}
     */
    public function evaluateDate(string $date): array
    {
        $summary = ['processed' => 0, 'baik' => 0, 'perlu_pembinaan' => 0, 'kritis' => 0];

        if (! $this->checkinReader->isUp()) {
            return $summary;
        }

        $checkins = $this->checkinReader->operatorCheckinsForDate($date);
        if ($checkins === []) {
            return $summary;
        }

        $sids = array_map(static fn (array $r): string => $r['kode_sid'], $checkins);

        $fatigueBySid = $this->fatigueReader->statusForSidsOnDate($sids, $date);
        $alertBySid = $this->dmsAlertReader->dailyAlertBreakdownForSids($sids, $date);
        $historyBySid = $this->fatigueReader->scoreHistoryForSids($sids, $date);

        $checkinAtBySid = [];
        foreach ($checkins as $row) {
            $checkinAtBySid[mb_strtoupper($row['kode_sid'])] = $row['checked_in_at'];
        }
        $pvtBySid = $this->pvtReader->statusForCheckins($checkinAtBySid, $date);

        foreach ($checkins as $row) {
            $upper = mb_strtoupper($row['kode_sid']);
            $fatigue = $fatigueBySid[$upper] ?? null;
            $alert = $alertBySid[$upper] ?? ['nyata' => 0, 'palsu' => 0, 'belum' => 0];
            $pvt = $pvtBySid[$upper] ?? ['status' => 'belum'];

            $history = array_values(array_filter(
                $historyBySid[$upper] ?? [],
                static fn (array $h): bool => $h['date'] !== $date
            ));
            $baseline = PraOperasiBaselineCalculator::compute($history);
            $zscore = null;
            if ($baseline !== null && $fatigue !== null && $fatigue['kesiapan_score'] !== null) {
                $zscore = round(($fatigue['kesiapan_score'] - $baseline['mean']) / $baseline['std'], 2);
            }

            [$kategori, $alasan] = $this->classify($fatigue, $pvt['status'], $alert, $zscore);

            PraOperasiEvaluasiHarian::updateOrCreate(
                ['kode_sid' => $row['kode_sid'], 'tanggal' => $date],
                [
                    'nama' => $row['nama'] !== '' ? $row['nama'] : null,
                    'perusahaan' => $row['perusahaan'] !== '' ? $row['perusahaan'] : null,
                    'shift' => $fatigue['shift'] ?? null,
                    'hari_ke' => $fatigue['hari_ke'] ?? null,
                    'fatigue_score' => $fatigue['kesiapan_score'] ?? null,
                    'fatigue_tier' => $fatigue['tier'] ?? null,
                    'pvt_status' => $pvt['status'],
                    'alert_nyata_count' => $alert['nyata'],
                    'alert_palsu_count' => $alert['palsu'],
                    'alert_belum_count' => $alert['belum'],
                    'durasi_kerja_menit' => $this->durationMinutes($row['checked_in_at'], $row['checked_out_at'] ?? null),
                    'baseline_zscore' => $zscore,
                    'kategori_evaluasi' => $kategori,
                    'alasan' => $alasan,
                ]
            );

            $summary['processed']++;
            $summary[$kategori]++;
        }

        return $summary;
    }

    /**
     * @param  array{kesiapan_score:int|null, tier:string|null}|null  $fatigue
     * @param  array{nyata:int, palsu:int, belum:int}  $alert
     * @return array{0:string, 1:list<string>}
     */
    private function classify(?array $fatigue, string $pvtStatus, array $alert, ?float $zscore): array
    {
        $level = 0; // 0=baik,1=perlu_pembinaan,2=kritis
        $reasons = [];

        $fatigueDone = $fatigue !== null;
        $pvtDone = $pvtStatus !== 'belum';

        if (! $fatigueDone && ! $pvtDone) {
            $level = 2;
            $reasons[] = 'Fatigue Test dan PVT keduanya tidak pernah dilakukan hari ini';
        }

        if ($fatigue !== null && $fatigue['tier'] === 'merah') {
            $level = max($level, 1);
            $reasons[] = 'Hasil Fatigue Test hari ini tier Merah';
        }
        if ($pvtStatus === 'tidak_lulus') {
            $level = max($level, 1);
            $reasons[] = 'Hasil PVT hari ini tidak lulus';
        }

        if ($alert['nyata'] >= self::REPEAT_ALERT_THRESHOLD) {
            $level = 2;
            $reasons[] = sprintf('Alert fatigue dikonfirmasi nyata berulang (%dx) selama shift', $alert['nyata']);
        } elseif ($alert['nyata'] >= 1) {
            $level = max($level, 1);
            $reasons[] = 'Ada alert fatigue dikonfirmasi nyata selama shift';
        }

        if ($zscore !== null && $zscore <= self::ZSCORE_THRESHOLD) {
            $level = max($level, 1);
            $reasons[] = sprintf('Skor Fatigue Test hari ini jauh di bawah baseline pribadi (z=%.2f)', $zscore);
        }

        if ($reasons === []) {
            $reasons[] = 'Kontrol lengkap, tidak ada alert nyata, sesuai kebiasaan pribadi';
        }

        $kategori = match ($level) {
            2 => PraOperasiEvaluasiHarian::KATEGORI_KRITIS,
            1 => PraOperasiEvaluasiHarian::KATEGORI_PERLU_PEMBINAAN,
            default => PraOperasiEvaluasiHarian::KATEGORI_BAIK,
        };

        return [$kategori, $reasons];
    }

    private function durationMinutes(string $checkedInAt, ?string $checkedOutAt): ?int
    {
        if ($checkedOutAt === null || $checkedOutAt === '') {
            return null;
        }

        try {
            $in = Carbon::parse($checkedInAt);
            $out = Carbon::parse($checkedOutAt);
            $minutes = $out->diffInMinutes($in, false);

            return $minutes > 0 ? $minutes : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
