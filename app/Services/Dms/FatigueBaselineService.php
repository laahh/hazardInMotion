<?php

declare(strict_types=1);

namespace App\Services\Dms;

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

    private const CANDIDATE_LIMIT = 40;

    public function __construct(
        private readonly FatigueBaselineDataReader $reader,
    ) {}

    /**
     * @return array{
     *     up: bool,
     *     dateLabel: string,
     *     operators: list<array<string, mixed>>,
     *     totalCandidates: int,
     *     shownCount: int,
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
            'params' => $params,
        ];

        if (! $this->reader->isUp()) {
            return $empty;
        }

        try {
            $sids = $this->reader->topFatigueSids($date, self::LOOKBACK_DAYS, self::MIN_ALERT_DAYS, self::CANDIDATE_LIMIT);
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

            return [
                'up' => true,
                'dateLabel' => $this->dateLabel($date),
                'operators' => $operators,
                'totalCandidates' => count($sids),
                'shownCount' => count($operators),
                'params' => $params,
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
