<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Profil lengkap SATU operator (on-demand — dipanggil saat baris watchlist
 * diklik, bukan untuk semua orang sekaligus) untuk panel detail: timeline
 * alert + status intervensi, riwayat penyakit kritis + follow-up, dan riwayat
 * skor Fatigue Test untuk grafik tren personal.
 */
final class PraOperasiOperatorProfileReader
{
    private const TIMELINE_LIMIT = 30;

    /** @var list<string> */
    private const FATIGUE_ALERT_NAMES = ['Menutup Mata', 'Menguap', 'Menunduk'];

    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $connectionSource,
        private readonly PraOperasiCriticalIllnessReader $criticalIllnessReader,
        private readonly PraOperasiFatigueCheckReader $fatigueCheckReader,
        private readonly PraOperasiDmsAlertReader $dmsAlertReader,
    ) {}

    public function isUp(): bool
    {
        return $this->connectionSource->isUp();
    }

    /**
     * @return array{
     *     kode_sid:string,
     *     alertTimeline: list<array{date:string, name:string, status:string}>,
     *     alertSummary: array{nyata:int, palsu:int, belum:int, total:int, trend:string},
     *     criticalIllness: array{has_critical_illness:bool, confirmed_date:string|null, followed_up:bool},
     *     fatigueHistory: list<array{date:string, score:int}>,
     *     baseline: array{mean:float,std:float,n:int}|null
     * }
     */
    public function profile(string $kodeSid, string $untilDate, int $days = 30): array
    {
        $empty = [
            'kode_sid' => $kodeSid,
            'alertTimeline' => [],
            'alertSummary' => ['nyata' => 0, 'palsu' => 0, 'belum' => 0, 'total' => 0, 'trend' => 'stabil'],
            'criticalIllness' => ['has_critical_illness' => false, 'confirmed_date' => null, 'followed_up' => false],
            'fatigueHistory' => [],
            'baseline' => null,
        ];

        if (! $this->isUp()) {
            return $empty;
        }

        try {
            $timeline = $this->alertTimeline($kodeSid, $untilDate, $days);
            $summary = $this->summarizeTimeline($timeline);
            $stats = $this->dmsAlertReader->confirmedAlertStatsForSids([$kodeSid], $untilDate, $days);
            $summary['trend'] = $stats[mb_strtoupper($kodeSid)]['trend'] ?? 'stabil';

            $illness = $this->criticalIllnessReader->statusForSids([$kodeSid], $untilDate);
            $illnessStatus = $illness[mb_strtoupper($kodeSid)] ?? $empty['criticalIllness'];

            $history = $this->fatigueCheckReader->scoreHistoryForSids([$kodeSid], $untilDate, $days);
            $fatigueHistory = $history[mb_strtoupper($kodeSid)] ?? [];

            $baselineHistory = array_values(array_filter($fatigueHistory, static fn (array $h): bool => $h['date'] !== $untilDate));
            $baseline = PraOperasiBaselineCalculator::compute($baselineHistory);

            return [
                'kode_sid' => $kodeSid,
                'alertTimeline' => $timeline,
                'alertSummary' => $summary,
                'criticalIllness' => $illnessStatus,
                'fatigueHistory' => $fatigueHistory,
                'baseline' => $baseline,
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return list<array{date:string, name:string, status:string}>
     */
    private function alertTimeline(string $kodeSid, string $untilDate, int $days): array
    {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return [];
        }

        $tz = (string) config('app.timezone');
        $end = Carbon::parse($untilDate, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');
        $start = Carbon::parse($untilDate, $tz)->startOfDay()->subDays($days)->format('Y-m-d H:i:s');
        $namePlaceholders = implode(',', array_fill(0, count(self::FATIGUE_ALERT_NAMES), '?'));

        $sql = '
            SELECT waktu_deteksi, nama_pelanggaran, l1_model_status, sudah_direview_l1
            FROM bcsid.mv_dms_alert
            WHERE UPPER(TRIM(kode_sid)) = ?
              AND nama_pelanggaran IN ('.$namePlaceholders.')
              AND waktu_deteksi >= ? AND waktu_deteksi < ?
            ORDER BY waktu_deteksi DESC
            LIMIT ?
        ';

        try {
            $rows = DB::connection($connection)->select(
                $sql,
                array_merge([mb_strtoupper($kodeSid)], self::FATIGUE_ALERT_NAMES, [$start, $end, self::TIMELINE_LIMIT])
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $reviewed = (bool) ($row->sudah_direview_l1 ?? false);
            $status = ! $reviewed ? 'belum' : (((bool) $row->l1_model_status) ? 'nyata' : 'palsu');

            $waktu = $row->waktu_deteksi ?? null;
            $tanggal = $waktu instanceof \DateTimeInterface
                ? Carbon::instance($waktu)->timezone($tz)->format('Y-m-d H:i')
                : (string) $waktu;

            $out[] = [
                'date' => $tanggal,
                'name' => trim((string) ($row->nama_pelanggaran ?? '')),
                'status' => $status,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{date:string, name:string, status:string}>  $timeline
     * @return array{nyata:int, palsu:int, belum:int, total:int, trend:string}
     */
    private function summarizeTimeline(array $timeline): array
    {
        $summary = ['nyata' => 0, 'palsu' => 0, 'belum' => 0, 'total' => count($timeline), 'trend' => 'stabil'];
        foreach ($timeline as $item) {
            $summary[$item['status']]++;
        }

        return $summary;
    }
}
