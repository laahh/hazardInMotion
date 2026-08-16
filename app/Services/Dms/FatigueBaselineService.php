<?php

declare(strict_types=1);

namespace App\Services\Dms;

use App\Services\PraOperasi\PraOperasiDmsAlertReader;
use App\Services\PraOperasi\PraOperasiFatigueCheckReader;
use App\Services\PraOperasi\PraOperasiPvtStatusReader;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Orkestrator /dms/fatigue-baseline-static: watchlist operator dengan pola
 * alert fatigue DMS paling menonjol, baseline personal (mean & std harian),
 * dan proyeksi kapan kemungkinan mencapai ambang risiko (lihat
 * FatigueTrendCalculator). Data REAL dari bcsid.mv_dms_alert — bukan lagi
 * mockup.
 */
final class FatigueBaselineService
{
    private const LOOKBACK_DAYS = 60;

    private const MIN_ALERT_DAYS = 5;

    /** Batas pengaman query, BUKAN batas tampilan — lihat DISPLAY_LIMIT. */
    private const CANDIDATE_SAFETY_CAP = 5000;

    /** Berapa banyak yang benar-benar ditampilkan di watchlist, dari yang PALING berisiko. */
    private const DISPLAY_LIMIT = 150;

    /** Window riwayat Fatigue Check & PVT yang ditampilkan di panel detail operator. */
    private const DETAIL_HISTORY_DAYS = 14;

    private const PVT_HISTORY_DAYS = 30;

    private const ALERT_TIMELINE_DAYS = 30;

    private const ALERT_TIMELINE_LIMIT = 50;

    public function __construct(
        private readonly FatigueBaselineDataReader $reader,
        private readonly PraOperasiFatigueCheckReader $fatigueCheckReader,
        private readonly PraOperasiPvtStatusReader $pvtReader,
        private readonly PraOperasiDmsAlertReader $dmsAlertReader,
    ) {}

    /**
     * @return array{
     *     up: bool,
     *     dateLabel: string,
     *     operators: list<array<string, mixed>>,
     *     totalCandidates: int,
     *     shownCount: int,
     *     truncated: bool,
     *     params: array{lookbackDays:int, trendDays:int, thresholdSigma:float, alpha:float, minAlertDays:int}
     * }
     */
    public function dashboard(string $date): array
    {
        $params = [
            'lookbackDays' => self::LOOKBACK_DAYS,
            'trendDays' => FatigueTrendCalculator::TREND_DAYS,
            'thresholdSigma' => FatigueTrendCalculator::THRESHOLD_SIGMA,
            'alpha' => 0.2,
            'minAlertDays' => self::MIN_ALERT_DAYS,
        ];

        $empty = [
            'up' => false,
            'dateLabel' => $this->dateLabel($date),
            'operators' => [],
            'totalCandidates' => 0,
            'shownCount' => 0,
            'truncated' => false,
            'params' => $params,
        ];

        if (! $this->reader->isUp()) {
            return $empty;
        }

        try {
            $totalEligible = $this->reader->countEligibleSids($date, self::LOOKBACK_DAYS, self::MIN_ALERT_DAYS);
            $sids = $this->reader->topFatigueSids($date, self::LOOKBACK_DAYS, self::MIN_ALERT_DAYS, self::CANDIDATE_SAFETY_CAP);
            if ($sids === []) {
                return array_merge($empty, ['up' => true]);
            }

            $dailyCounts = $this->reader->dailyCountsForSids($sids, $date, self::LOOKBACK_DAYS);
            $meta = $this->reader->latestMetaForSids($sids);

            $dateRange = $this->denseDateRange($date, self::LOOKBACK_DAYS);

            $operators = [];
            foreach ($sids as $sid) {
                $countsForSid = $dailyCounts[$sid] ?? [];
                $dense = [];
                foreach ($dateRange as $d) {
                    $dense[$d] = $countsForSid[$d] ?? 0;
                }

                $stats = FatigueTrendCalculator::compute($dense, $date);
                $info = $meta[$sid] ?? ['nama' => '', 'unit' => '', 'site' => '', 'perusahaan' => ''];

                $operators[] = [
                    'id' => $sid,
                    'sid' => $sid,
                    'nama' => $info['nama'] !== '' ? $info['nama'] : $sid,
                    'unit' => $info['unit'] !== '' ? $info['unit'] : '-',
                    'site' => $info['site'] !== '' ? $info['site'] : '-',
                    'perusahaan' => $info['perusahaan'],
                    'mean' => $stats['mean'],
                    'std' => $stats['std'],
                    'n' => $stats['n'],
                    'rate' => $stats['rate'],
                    'z' => $stats['z'],
                    'slope' => $stats['slope'],
                    'threshold' => $stats['threshold'],
                    'hist' => $stats['hist'],
                    'ewma' => $stats['ewma'],
                    'dates' => $stats['dates'],
                    'tier' => $stats['tier'],
                    'riskBucket' => $stats['riskBucket'],
                    'prediction' => $stats['prediction'],
                ];
            }

            // Diurutkan berdasarkan risiko SEBENARNYA (bukan proksi volume alert
            // yang dipakai topFatigueSids cuma untuk membatasi query) — supaya
            // operator dengan tren tajam tapi volume total belum tinggi tetap
            // naik ke atas, bukan tenggelam di bawah operator volume tinggi
            // yang justru sudah stabil.
            usort($operators, static function (array $a, array $b): int {
                $order = ['extreme' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
                $oa = $order[$a['riskBucket']] ?? 2;
                $ob = $order[$b['riskBucket']] ?? 2;
                if ($oa !== $ob) {
                    return $oa <=> $ob;
                }
                $za = $a['z'] ?? -999.0;
                $zb = $b['z'] ?? -999.0;

                return $zb <=> $za;
            });

            $totalCandidates = max($totalEligible, count($operators));
            $displayed = array_slice($operators, 0, self::DISPLAY_LIMIT);

            return [
                'up' => true,
                'dateLabel' => $this->dateLabel($date),
                'operators' => $displayed,
                'totalCandidates' => $totalCandidates,
                'shownCount' => count($displayed),
                'truncated' => $totalCandidates > count($displayed),
                'params' => $params,
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * Detail on-demand SATU operator (dipanggil saat baris watchlist diklik,
     * bukan untuk semua operator sekaligus) — statistik Fatigue Check
     * (Fit to Work), status PVT, dan riwayat alert individual. Melengkapi
     * baseline/prediksi alert yang sudah dihitung di dashboard() dengan
     * konteks pemeriksaan pra-shift operator itu sendiri.
     *
     * @return array{
     *     fatigueCheckHistory: list<array{date:string, kesiapan_score:int|null, tier:string|null, hasil_sobriety_test:string, kondisi_karyawan:string, tindakan_unfit:string, jumlah_jam_tidur:string, checked_at:string}>,
     *     pvtHistory: list<array{date:string, status:string, mean_rt_ms:int|null, lapses:int|null, evaluation_label:string, tested_at:string}>,
     *     alertTimeline: list<array{date:string, name:string, status:string}>
     * }
     */
    public function operatorDetail(string $sid, string $date): array
    {
        $empty = ['fatigueCheckHistory' => [], 'pvtHistory' => [], 'alertTimeline' => []];

        try {
            return [
                'fatigueCheckHistory' => $this->fatigueCheckReader->detailHistoryForSid($sid, $date, self::DETAIL_HISTORY_DAYS),
                'pvtHistory' => $this->pvtReader->historyForSid($sid, $date, self::PVT_HISTORY_DAYS),
                'alertTimeline' => $this->dmsAlertReader->alertTimelineForSid($sid, $date, self::ALERT_TIMELINE_DAYS, self::ALERT_TIMELINE_LIMIT),
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return list<string>  tanggal Y-m-d kronologis ASC, dari (until-lookback) s.d. until (inklusif)
     */
    private function denseDateRange(string $untilDate, int $lookbackDays): array
    {
        $tz = (string) config('app.timezone');
        $end = Carbon::parse($untilDate, $tz)->startOfDay();
        $start = $end->copy()->subDays($lookbackDays - 1);

        $out = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $out[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $out;
    }

    private function dateLabel(string $date): string
    {
        try {
            return Carbon::parse($date, config('app.timezone'))->translatedFormat('d M Y');
        } catch (Throwable) {
            return $date;
        }
    }
}
