<?php

declare(strict_types=1);

namespace App\Services\DmsMonitoring;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Orkestrator /pra-operasi/dashboard — monitoring alert DMS L1/L2: total
 * alert, rasio per unit/orang, kuadran kategori, funnel layer (RFID -> alert
 * -> L1 -> L2 -> Post Event), orang yang belum pernah kena Post Event, sampling
 * QA false negative (Slovin), dan performa control room (proksi per site).
 */
final class DmsAlertMonitoringService
{
    private const DEFAULT_WINDOW_DAYS = 7;

    /** Window lebih panjang untuk "orang yang belum pernah post event" — 7 hari terlalu pendek untuk klaim "belum pernah". */
    private const NEVER_POST_EVENT_WINDOW_DAYS = 90;

    public function __construct(
        private readonly DmsAlertMonitoringDataReader $reader,
        private readonly DmsL1QaSamplingService $qaService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Request $request): array
    {
        $filters = $this->readFilters($request);
        $tz = (string) config('app.timezone');
        $this->reader->applyScope($filters['site'], $filters['perusahaan']);

        if (! $this->reader->isUp()) {
            return $this->emptyPayload($filters);
        }

        try {
            $start = Carbon::parse($filters['start'], $tz)->startOfDay()->format('Y-m-d H:i:s');
            $end = Carbon::parse($filters['end'], $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');

            $summary = $this->reader->alertSummary($start, $end);
            $byUnit = $this->reader->alertsByUnit($start, $end, 20);
            $byOperator = $this->reader->alertsByOperator($start, $end, 20);
            $quadrant = $this->reader->categoryQuadrant($start, $end);
            $unitsOperating = $this->reader->unitsOperatingNow(30);
            $postEvent = $this->reader->postEventSummary($start, $end);
            $turnaround = $this->reader->turnaroundBySite($start, $end);

            $funnel = $this->buildLayerFunnel($start, $end, $summary, $postEvent);
            $neverPostEvent = $this->buildNeverPostEvent($filters['end'], $tz);
            $slovin = $this->buildSlovinCoverage($summary, $postEvent);
            $qaSummary = $this->qaService->summaryForPeriod($filters['start'], $filters['end']);
            $qaPending = $this->qaService->pendingSamples($filters['start'], $filters['end']);
            $today = $this->buildTodaySnapshot($tz);
            $operatorsCheckedIn = $this->reader->countOperatorCheckinsInRange($start, $end);
            $unitsInPeriod = $this->reader->unitsOperatingInRange($start, $end);
            $filterOptions = $this->reader->filterOptions($start, $end);

            return [
                'up' => true,
                'filters' => $filters,
                'filterOptions' => $filterOptions,
                'dateLabel' => $this->dateRangeLabel($filters['start'], $filters['end']),
                'today' => $today,
                'kpis' => $this->buildKpis($summary, $unitsOperating, $operatorsCheckedIn, $unitsInPeriod),
                'summary' => $summary,
                'byUnit' => $byUnit,
                'byOperator' => $byOperator,
                'quadrant' => $quadrant,
                'unitsOperating' => $unitsOperating,
                'postEvent' => $postEvent,
                'turnaround' => $turnaround,
                'funnel' => $funnel,
                'neverPostEvent' => $neverPostEvent,
                'slovin' => $slovin,
                'qaSummary' => $qaSummary,
                'qaPending' => $qaPending,
            ];
        } catch (Throwable $e) {
            report($e);

            return $this->emptyPayload($filters);
        }
    }

    public function generateQaSample(string $periodStart, string $periodEnd): array
    {
        return $this->qaService->generateSampleForPeriod($periodStart, $periodEnd);
    }

    public function submitQaVerdict(int $sampleId, string $verdict, ?string $catatan, ?int $userId): bool
    {
        return $this->qaService->recordVerdict($sampleId, $verdict, $catatan, $userId);
    }

    /**
     * Funnel "layer mana yang gak jalan" — bandingkan populasi ORANG (bukan
     * baris) di tiap tahap: checkin RFID -> punya alert -> direview L1 ->
     * direview L2 -> Post Event. Drop-off paling besar menunjukkan layer mana
     * yang paling banyak "membocorkan" orang, dihitung dari data real, bukan
     * disimpulkan sekali dari satu window historis.
     *
     * @param  array{l1_reviewed:int, l2_reviewed:int}  $summary
     * @param  array{distinct_sids:list<string>}  $postEvent
     * @return list<array{label:string, count:int}>
     */
    private function buildLayerFunnel(string $start, string $end, array $summary, array $postEvent): array
    {
        $rfidSids = $this->reader->distinctCheckinSids($start, $end);
        $alertSids = $this->reader->distinctAlertSids($start, $end);

        return [
            ['label' => 'Checkin RFID', 'count' => count($rfidSids)],
            ['label' => 'Punya Alert DMS', 'count' => count($alertSids)],
            ['label' => 'Direview L1', 'count' => $summary['l1_reviewed'] ?? 0],
            ['label' => 'Direview L2', 'count' => $summary['l2_reviewed'] ?? 0],
            ['label' => 'Post Event', 'count' => count($postEvent['distinct_sids'] ?? [])],
        ];
    }

    /**
     * @return array{window_days:int, total_dengan_alert:int, total_belum_post_event:int, persentase:float}
     */
    private function buildNeverPostEvent(string $untilDate, string $tz): array
    {
        $end = Carbon::parse($untilDate, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');
        $start = Carbon::parse($untilDate, $tz)->startOfDay()->subDays(self::NEVER_POST_EVENT_WINDOW_DAYS)->format('Y-m-d H:i:s');

        $alertSids = $this->reader->distinctAlertSids($start, $end);
        $postEvent = $this->reader->postEventSummary($start, $end);
        $postEventSids = array_flip($postEvent['distinct_sids'] ?? []);

        $neverCount = 0;
        foreach ($alertSids as $sid) {
            if (! isset($postEventSids[$sid])) {
                $neverCount++;
            }
        }

        $totalAlertSids = count($alertSids);

        return [
            'window_days' => self::NEVER_POST_EVENT_WINDOW_DAYS,
            'total_dengan_alert' => $totalAlertSids,
            'total_belum_post_event' => $neverCount,
            'persentase' => $totalAlertSids > 0 ? round($neverCount / $totalAlertSids * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  array{total:int, l1_reviewed:int, l2_reviewed:int, l1_dismissed:int}  $summary
     * @param  array{total:int}  $postEvent
     * @return array{population:int, margin_of_error:float, target_sample_size:int, l1_reviewed:int, l2_reviewed:int, post_event:int}
     */
    private function buildSlovinCoverage(array $summary, array $postEvent): array
    {
        $population = $summary['total'] ?? 0;
        $marginOfError = SlovinSamplingCalculator::DEFAULT_MARGIN_OF_ERROR;
        $target = SlovinSamplingCalculator::sampleSize($population, $marginOfError);

        return [
            'population' => $population,
            'margin_of_error' => $marginOfError,
            'target_sample_size' => $target,
            'l1_reviewed' => $summary['l1_reviewed'] ?? 0,
            'l2_reviewed' => $summary['l2_reviewed'] ?? 0,
            'post_event' => $postEvent['total'] ?? 0,
        ];
    }

    /**
     * Enam kartu KPI WowDash — baris 1: check-in/alert/rasio per orang;
     * baris 2: unit beroperasi/total alert/rasio per unit.
     *
     * @param  array{total:int, l1_dismissed:int}  $summary
     * @return list<array{label:string, value:string, hint:string, icon:string, bg:string, gradient:string}>
     */
    private function buildKpis(array $summary, int $unitsOnline, int $operatorsCheckedIn, int $unitsInPeriod): array
    {
        $totalAlerts = (int) ($summary['total'] ?? 0);
        $ratioPerOperator = $operatorsCheckedIn > 0 ? round($totalAlerts / $operatorsCheckedIn, 2) : 0.0;
        $ratioPerUnit = $unitsInPeriod > 0 ? round($totalAlerts / $unitsInPeriod, 2) : 0.0;

        return [
            [
                'label' => 'Total Orang Checkin',
                'value' => number_format($operatorsCheckedIn),
                'hint' => 'jabatan Operator · periode filter',
                'icon' => 'mingcute:user-follow-fill',
                'bg' => 'bg-primary-600',
                'gradient' => 'bg-gradient-end-1',
            ],
            [
                'label' => 'Total Alert',
                'value' => number_format($totalAlerts),
                'hint' => 'periode filter',
                'icon' => 'solar:danger-triangle-bold',
                'bg' => 'bg-success-main',
                'gradient' => 'bg-gradient-end-2',
            ],
            [
                'label' => 'Rasio Alert / Orang',
                'value' => number_format($ratioPerOperator, 2),
                'hint' => 'alert ÷ orang check-in operator',
                'icon' => 'solar:chart-2-bold',
                'bg' => 'bg-yellow',
                'gradient' => 'bg-gradient-end-3',
            ],
            [
                'label' => 'Unit Beroperasi',
                'value' => number_format($unitsInPeriod),
                'hint' => 'periode filter'.($unitsOnline > 0 ? ' · '.$unitsOnline.' online 30 mnt' : ''),
                'icon' => 'solar:wheel-bold',
                'bg' => 'bg-purple',
                'gradient' => 'bg-gradient-end-4',
            ],
            [
                'label' => 'Total Alert',
                'value' => number_format($totalAlerts),
                'hint' => 'periode filter',
                'icon' => 'solar:danger-triangle-bold',
                'bg' => 'bg-pink',
                'gradient' => 'bg-gradient-end-5',
            ],
            [
                'label' => 'Rasio Alert / Unit',
                'value' => number_format($ratioPerUnit, 2),
                'hint' => 'alert ÷ unit beroperasi',
                'icon' => 'solar:bus-bold',
                'bg' => 'bg-cyan',
                'gradient' => 'bg-gradient-end-6',
            ],
        ];
    }

    /**
     * Snapshot HARI INI (bukan window filter start/end) — untuk kartu unit beroperasi.
     *
     * @return array{date_label:string, units_operating:int, operators_checked_in:int, total_alerts:int, ratio_per_unit:float, ratio_per_operator:float}
     */
    private function buildTodaySnapshot(string $tz): array
    {
        $todayStart = Carbon::now($tz)->startOfDay()->format('Y-m-d H:i:s');
        $todayEnd = Carbon::now($tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');

        $unitsOperating = $this->reader->unitsOperatingInRange($todayStart, $todayEnd);
        $operatorsCheckedIn = count($this->reader->distinctCheckinSids($todayStart, $todayEnd));
        $totalAlerts = $this->reader->alertSummary($todayStart, $todayEnd)['total'] ?? 0;

        return [
            'date_label' => Carbon::now($tz)->translatedFormat('d M Y'),
            'units_operating' => $unitsOperating,
            'operators_checked_in' => $operatorsCheckedIn,
            'total_alerts' => $totalAlerts,
            'ratio_per_unit' => $unitsOperating > 0 ? round($totalAlerts / $unitsOperating, 2) : 0.0,
            'ratio_per_operator' => $operatorsCheckedIn > 0 ? round($totalAlerts / $operatorsCheckedIn, 2) : 0.0,
        ];
    }

    /**
     * @return array{start:string, end:string, site:string, perusahaan:string}
     */
    private function readFilters(Request $request): array
    {
        $read = static fn (mixed $v): string => is_string($v) ? mb_substr(trim($v), 0, 10) : '';
        $readName = static function (mixed $v): string {
            if (! is_string($v)) {
                return '';
            }

            return mb_substr(trim($v), 0, 80);
        };
        $tz = (string) config('app.timezone');
        $today = Carbon::now($tz)->toDateString();

        $end = $read($request->query('end', ''));
        if ($end === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1) {
            $end = $today;
        }

        $start = $read($request->query('start', ''));
        if ($start === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1) {
            $start = Carbon::parse($end, $tz)->subDays(self::DEFAULT_WINDOW_DAYS - 1)->toDateString();
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start' => $start,
            'end' => $end,
            'site' => $readName($request->query('site', '')),
            'perusahaan' => $readName($request->query('perusahaan', '')),
        ];
    }

    private function dateRangeLabel(string $start, string $end): string
    {
        try {
            $tz = (string) config('app.timezone');
            $startLabel = Carbon::parse($start, $tz)->translatedFormat('d M Y');
            $endLabel = Carbon::parse($end, $tz)->translatedFormat('d M Y');

            return $start === $end ? $startLabel : "{$startLabel} - {$endLabel}";
        } catch (Throwable) {
            return "{$start} - {$end}";
        }
    }

    /**
     * @param  array{start:string, end:string, site?:string, perusahaan?:string}  $filters
     * @return array<string, mixed>
     */
    private function emptyPayload(array $filters): array
    {
        return [
            'up' => false,
            'filters' => $filters,
            'filterOptions' => ['sites' => [], 'companies' => []],
            'dateLabel' => $this->dateRangeLabel($filters['start'], $filters['end']),
            'today' => ['date_label' => '-', 'units_operating' => 0, 'operators_checked_in' => 0, 'total_alerts' => 0, 'ratio_per_unit' => 0.0, 'ratio_per_operator' => 0.0],
            'kpis' => $this->buildKpis(['total' => 0, 'l1_dismissed' => 0], 0, 0, 0),
            'summary' => ['total' => 0, 'l1_reviewed' => 0, 'l1_confirmed' => 0, 'l1_dismissed' => 0, 'l1_belum' => 0, 'l2_reviewed' => 0, 'l2_confirmed' => 0, 'post_event_eligible' => 0],
            'byUnit' => [],
            'byOperator' => [],
            'quadrant' => [],
            'unitsOperating' => 0,
            'postEvent' => ['total' => 0, 'behazard' => 0, 'berecord' => 0, 'distinct_sids' => []],
            'turnaround' => [],
            'funnel' => [],
            'neverPostEvent' => ['window_days' => self::NEVER_POST_EVENT_WINDOW_DAYS, 'total_dengan_alert' => 0, 'total_belum_post_event' => 0, 'persentase' => 0.0],
            'slovin' => ['population' => 0, 'margin_of_error' => SlovinSamplingCalculator::DEFAULT_MARGIN_OF_ERROR, 'target_sample_size' => 0, 'l1_reviewed' => 0, 'l2_reviewed' => 0, 'post_event' => 0],
            'qaSummary' => ['population' => 0, 'target_sample_size' => 0, 'total_sampled' => 0, 'total_audited' => 0, 'pending' => 0, 'benar_dismiss' => 0, 'false_negative' => 0, 'tidak_jelas' => 0, 'false_negative_rate' => null, 'estimated_false_negative_count' => null],
            'qaPending' => [],
        ];
    }
}
