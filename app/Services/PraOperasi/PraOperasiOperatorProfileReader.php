<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Models\PraOperasi\PraOperasiEvaluasiHarian;
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
        private readonly PraOperasiPvtStatusReader $pvtReader,
        private readonly PraOperasiCheckinReader $checkinReader,
        private readonly PraOperasiRosterShiftReader $rosterShiftReader,
    ) {}

    public function isUp(): bool
    {
        return $this->connectionSource->isUp();
    }

    /**
     * @return array{
     *     kode_sid:string,
     *     roster: array{hari_ke:int|null, shift:string|null, roster_code:string|null},
     *     alertTimeline: list<array{date:string, name:string, status:string}>,
     *     alertSummary: array{nyata:int, palsu:int, belum:int, total:int, trend:string},
     *     criticalIllness: array{has_critical_illness:bool, confirmed_date:string|null, followed_up:bool},
     *     fatigueHistory: list<array{date:string, score:int}>,
     *     baseline: array{mean:float,std:float,n:int}|null,
     *     evaluasiKemarin: array{kategori:string, alasan:list<string>}|null,
     *     evaluasiHistory: list<array{date:string, kategori:string}>,
     *     pvtHistory: list<array{date:string, status:string, mean_rt_ms:int|null, lapses:int|null, evaluation_label:string, tested_at:string}>,
     *     fatigueCheckHistory: list<array{
     *         date:string, kesiapan_score:int|null, tier:string|null, hasil_sobriety_test:string,
     *         kondisi_karyawan:string, tindakan_unfit:string, jumlah_jam_tidur:string, checked_at:string
     *     }>
     * }
     */
    public function profile(string $kodeSid, string $untilDate, int $days = 30): array
    {
        $empty = [
            'kode_sid' => $kodeSid,
            'roster' => ['hari_ke' => null, 'shift' => null, 'roster_code' => null],
            'alertTimeline' => [],
            'alertSummary' => ['nyata' => 0, 'palsu' => 0, 'belum' => 0, 'total' => 0, 'trend' => 'stabil'],
            'criticalIllness' => ['has_critical_illness' => false, 'confirmed_date' => null, 'followed_up' => false],
            'fatigueHistory' => [],
            'baseline' => null,
            'evaluasiKemarin' => null,
            'evaluasiHistory' => [],
            'pvtHistory' => [],
            'fatigueCheckHistory' => [],
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

            $todayStatus = $this->fatigueCheckReader->statusForSidsOnDate([$kodeSid], $untilDate);
            $today = $todayStatus[mb_strtoupper($kodeSid)] ?? null;
            $roster = ['hari_ke' => $today['hari_ke'] ?? null, 'shift' => $today['shift'] ?? null, 'roster_code' => null];

            // dms_roster (kode roster mis. "D1"/"N3") tidak ada di form Fit to Work
            // sama sekali, jadi selalu dicoba — bukan cuma fallback saat shift kosong.
            // Kalau form-nya juga belum diisi ("Belum Tes"), shift ikut jatuh ke
            // dms_roster/pola jam checkin (lihat PraOperasiRosterShiftReader).
            $checkinAt = $this->findCheckinAt($kodeSid, $untilDate);
            if ($checkinAt !== null) {
                $resolved = $this->rosterShiftReader->resolveForCheckins(
                    [mb_strtoupper($kodeSid) => $checkinAt],
                    $untilDate
                );
                $shiftInfo = $resolved[mb_strtoupper($kodeSid)] ?? null;
                if ($shiftInfo !== null) {
                    $roster['roster_code'] = $shiftInfo['roster_code'];
                    if ($roster['shift'] === null) {
                        $roster['shift'] = PraOperasiFatigueCheckReader::shiftLabel($shiftInfo['shift']);
                    }
                }
            }

            return [
                'kode_sid' => $kodeSid,
                'roster' => $roster,
                'alertTimeline' => $timeline,
                'alertSummary' => $summary,
                'criticalIllness' => $illnessStatus,
                'fatigueHistory' => $fatigueHistory,
                'baseline' => $baseline,
                'evaluasiKemarin' => $this->lookupEvaluasiKemarin($kodeSid, $untilDate),
                'evaluasiHistory' => $this->evaluasiHistory($kodeSid, $untilDate, 90),
                'pvtHistory' => $this->pvtReader->historyForSid($kodeSid, $untilDate, $days),
                'fatigueCheckHistory' => $this->fatigueCheckReader->detailHistoryForSid($kodeSid, $untilDate, 7),
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * Waktu checkin SID ini pada tanggal tsb, dari daftar checkin harian yang
     * sudah di-cache (PraOperasiCheckinReader) — tidak menambah query baru.
     */
    private function findCheckinAt(string $kodeSid, string $date): ?string
    {
        $upper = mb_strtoupper($kodeSid);
        foreach ($this->checkinReader->operatorCheckinsForDate($date) as $row) {
            if (mb_strtoupper($row['kode_sid']) === $upper) {
                return $row['checked_in_at'];
            }
        }

        return null;
    }

    /**
     * @return array{kategori:string, alasan:list<string>}|null
     */
    private function lookupEvaluasiKemarin(string $kodeSid, string $untilDate): ?array
    {
        try {
            $yesterday = Carbon::parse($untilDate)->subDay()->toDateString();
            $row = PraOperasiEvaluasiHarian::query()
                ->whereRaw('UPPER(kode_sid) = ?', [mb_strtoupper($kodeSid)])
                ->whereDate('tanggal', $yesterday)
                ->first(['kategori_evaluasi', 'alasan']);

            if ($row === null) {
                return null;
            }

            return [
                'kategori' => $row->kategori_evaluasi,
                'alasan' => is_array($row->alasan) ? array_values((array) $row->alasan) : [],
            ];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Riwayat kategori evaluasi harian (Fase 3) — bahan grafik kalender/heatmap.
     * Query ke tabel LOKAL (bukan hse_automation), jadi murah/cepat.
     *
     * @return list<array{date:string, kategori:string}>
     */
    private function evaluasiHistory(string $kodeSid, string $untilDate, int $days): array
    {
        try {
            $from = Carbon::parse($untilDate)->subDays($days)->toDateString();

            return PraOperasiEvaluasiHarian::query()
                ->whereRaw('UPPER(kode_sid) = ?', [mb_strtoupper($kodeSid)])
                ->whereBetween('tanggal', [$from, $untilDate])
                ->orderBy('tanggal')
                ->get(['tanggal', 'kategori_evaluasi'])
                ->map(static fn ($r): array => [
                    'date' => Carbon::parse($r->tanggal)->toDateString(),
                    'kategori' => $r->kategori_evaluasi,
                ])
                ->values()
                ->all();
        } catch (Throwable $e) {
            report($e);

            return [];
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
