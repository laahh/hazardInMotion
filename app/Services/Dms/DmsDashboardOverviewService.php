<?php

declare(strict_types=1);

namespace App\Services\Dms;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Payload dashboard DMS (layout WowDash CRM) dari bcsid.mv_dms_alert.
 * Semua query windowed lewat DmsDashboardDataSource — tidak SELECT *
 * dan tidak loop per-SID.
 */
final class DmsDashboardOverviewService
{
    private const CHART_DAYS = 12;

    private const WEEK_DAYS = 7;

    private const GROWTH_WEEKS = 4;

    private const SPARKLINE_DAYS = 9;

    public function __construct(
        private readonly DmsDashboardDataSource $reader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(?string $startDate = null, ?string $endDate = null, ?string $site = null, ?string $perusahaan = null): array
    {
        $this->reader->applyScope($site, $perusahaan);
        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);
        $windows = $this->buildWindows($now, $tz, $startDate, $endDate);

        if (! $this->reader->isUp()) {
            return $this->emptyPayload($now, $windows);
        }

        try {
            return $this->buildPayload($now, $tz, $windows);
        } catch (Throwable $e) {
            report($e);

            return $this->emptyPayload($now, $windows);
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildWindows(Carbon $now, string $tz, ?string $startDate, ?string $endDate): array
    {
        $periodMode = $this->isValidDate($startDate) && $this->isValidDate($endDate);
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $todayStart->copy()->addDay();
        $yesterdayStart = $todayStart->copy()->subDay();
        $chartStart = $todayStart->copy()->subDays(self::CHART_DAYS - 1);
        $weekStart = $todayStart->copy()->subDays(self::WEEK_DAYS - 1);
        $prevWeekStart = $weekStart->copy()->subDays(self::WEEK_DAYS);
        $prevWeekEnd = $weekStart->copy();
        $kpiDeltaLabel = 'vs kemarin';
        $dateLabel = $now->translatedFormat('d M Y');

        if ($periodMode) {
            $endDay = Carbon::parse((string) $endDate, $tz)->startOfDay();
            $startDay = Carbon::parse((string) $startDate, $tz)->startOfDay();
            if ($startDay->gt($endDay)) {
                [$startDay, $endDay] = [$endDay->copy(), $startDay->copy()];
            }

            $periodDays = max(1, $startDay->diffInDays($endDay) + 1);
            $periodEnd = $endDay->copy()->addDay();
            $prevStart = $startDay->copy()->subDays($periodDays);

            $todayStart = $startDay->copy();
            $todayEnd = $periodEnd->copy();
            $yesterdayStart = $prevStart->copy();
            $weekStart = $periodDays >= self::WEEK_DAYS
                ? $endDay->copy()->subDays(self::WEEK_DAYS - 1)
                : $startDay->copy();
            $chartStart = $periodDays > 31
                ? $endDay->copy()->subDays(30)
                : $startDay->copy();
            if ($chartStart->diffInDays($endDay) + 1 < self::CHART_DAYS) {
                $chartStart = $endDay->copy()->subDays(self::CHART_DAYS - 1);
            }
            $prevWeekStart = $weekStart->copy()->subDays(self::WEEK_DAYS);
            $prevWeekEnd = $weekStart->copy();
            $kpiDeltaLabel = 'vs periode sebelumnya';
            $dateLabel = $startDay->equalTo($endDay)
                ? $startDay->translatedFormat('d M Y')
                : $startDay->translatedFormat('d M Y').' - '.$endDay->translatedFormat('d M Y');
        }

        return [
            'todayStart' => $todayStart->format('Y-m-d H:i:s'),
            'todayEnd' => $todayEnd->format('Y-m-d H:i:s'),
            'yesterdayStart' => $yesterdayStart->format('Y-m-d H:i:s'),
            'chartStart' => $chartStart->format('Y-m-d H:i:s'),
            'weekStart' => $weekStart->format('Y-m-d H:i:s'),
            'prevWeekStart' => $prevWeekStart->format('Y-m-d H:i:s'),
            'prevWeekEnd' => $prevWeekEnd->format('Y-m-d H:i:s'),
            'kpiDeltaLabel' => $kpiDeltaLabel,
            'dateLabel' => $dateLabel,
        ];
    }

    private function isValidDate(?string $date): bool
    {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
    }

    /**
     * @param  array<string, string>  $windows
     * @return array<string, mixed>
     */
    private function buildPayload(Carbon $now, string $tz, array $windows): array
    {
        $today = $this->reader->alertSummary($windows['todayStart'], $windows['todayEnd']);
        $yesterday = $this->reader->alertSummary($windows['yesterdayStart'], $windows['todayStart']);
        $week = $this->reader->alertSummary($windows['weekStart'], $windows['todayEnd']);
        $prevWeek = $this->reader->alertSummary($windows['prevWeekStart'], $windows['prevWeekEnd']);

        $operatorsToday = $this->reader->countOperatorCheckinsInRange($windows['todayStart'], $windows['todayEnd']);
        $operatorsYesterday = $this->reader->countOperatorCheckinsInRange($windows['yesterdayStart'], $windows['todayStart']);
        $unitsToday = $this->reader->unitsOperatingInRange($windows['todayStart'], $windows['todayEnd']);
        $unitsYesterday = $this->reader->unitsOperatingInRange($windows['yesterdayStart'], $windows['todayStart']);
        $unitsOnline = $this->reader->unitsOperatingNow(30);

        $dailyRaw = $this->reader->dailyAlertSeries($windows['chartStart'], $windows['todayEnd']);
        $daily = $this->fillDailySeries($windows['chartStart'], $windows['todayEnd'], $dailyRaw, $tz);
        $weekDaily = array_slice($daily, -self::WEEK_DAYS);
        $sparkline = array_slice($daily, -self::SPARKLINE_DAYS);
        $operatorCheckinSparkline = $this->operatorCheckinSparkline($sparkline, $tz);
        $unitOperatingSparkline = $this->unitOperatingSparkline($sparkline, $tz);

        $categories = $this->mapCategories($this->reader->categoryQuadrant($windows['weekStart'], $windows['todayEnd']));
        $sites = $this->mapSites($this->reader->alertsBySite($windows['weekStart'], $windows['todayEnd'], 6));
        $topOperators = $this->mapOperators($this->reader->alertsByOperator($windows['weekStart'], $windows['todayEnd'], 6));
        $recentAll = $this->mapRecent($this->reader->recentAlerts($windows['weekStart'], $windows['todayEnd'], 8, false), $tz);
        $recentConfirmed = $this->mapRecent($this->reader->recentAlerts($windows['weekStart'], $windows['todayEnd'], 8, true), $tz);

        $confirmRate = $this->confirmationRate($today);
        $confirmRateYesterday = $this->confirmationRate($yesterday);
        $weekConfirmRate = $this->confirmationRate($week);

        $kpis = [
            [
                'label' => 'Total Orang Checkin',
                'value' => number_format($operatorsToday),
                'icon' => 'mingcute:user-follow-fill',
                'bg' => 'bg-primary-600',
                'gradient' => 'bg-gradient-end-1',
                'chart' => 'new-user-chart',
                'color' => '#487fff',
                'sparkline' => $operatorCheckinSparkline,
                'delta' => $this->delta($operatorsToday, $operatorsYesterday, false),
            ],
            [
                'label' => 'Total Alert',
                'value' => number_format($today['total']),
                'icon' => 'solar:danger-triangle-bold',
                'bg' => 'bg-success-main',
                'gradient' => 'bg-gradient-end-2',
                'chart' => 'active-user-chart',
                'color' => '#45b369',
                'sparkline' => array_column($sparkline, 'total'),
                'delta' => $this->delta($today['total'], $yesterday['total'], true),
            ],
            [
                'label' => 'Rasio Alert / Orang',
                'value' => number_format($operatorsToday > 0 ? $today['total'] / $operatorsToday : 0, 2),
                'icon' => 'solar:chart-2-bold',
                'bg' => 'bg-yellow',
                'gradient' => 'bg-gradient-end-3',
                'chart' => 'total-sales-chart',
                'color' => '#f4941e',
                'sparkline' => $this->alertPerPersonSparkline($sparkline, $operatorCheckinSparkline),
                'delta' => $this->deltaFloat(
                    $operatorsToday > 0 ? round($today['total'] / $operatorsToday, 2) : 0.0,
                    $operatorsYesterday > 0 ? round($yesterday['total'] / $operatorsYesterday, 2) : 0.0,
                    true,
                    '',
                ),
            ],
            [
                'label' => 'Unit Beroperasi',
                'value' => number_format($unitsToday > 0 ? $unitsToday : $unitsOnline),
                'icon' => 'solar:wheel-bold',
                'bg' => 'bg-purple',
                'gradient' => 'bg-gradient-end-4',
                'chart' => 'conversion-user-chart',
                'color' => '#8252e9',
                'sparkline' => $unitOperatingSparkline,
                'delta' => $this->delta($unitsToday, $unitsYesterday, false),
            ],
            [
                'label' => 'Total Alert',
                'value' => number_format($today['total']),
                'icon' => 'solar:danger-triangle-bold',
                'bg' => 'bg-pink',
                'gradient' => 'bg-gradient-end-5',
                'chart' => 'leads-chart',
                'color' => '#de3ace',
                'sparkline' => array_column($sparkline, 'total'),
                'delta' => $this->delta($today['total'], $yesterday['total'], true),
            ],
            [
                'label' => 'Rasio Alert / Unit',
                'value' => number_format($unitsToday > 0 ? $today['total'] / $unitsToday : 0, 2),
                'icon' => 'solar:bus-bold',
                'bg' => 'bg-cyan',
                'gradient' => 'bg-gradient-end-6',
                'chart' => 'total-profit-chart',
                'color' => '#00b8f2',
                'sparkline' => $this->alertPerUnitSparkline($sparkline, $unitOperatingSparkline),
                'delta' => $this->deltaFloat(
                    $unitsToday > 0 ? round($today['total'] / $unitsToday, 2) : 0.0,
                    $unitsYesterday > 0 ? round($yesterday['total'] / $unitsYesterday, 2) : 0.0,
                    true,
                    '',
                ),
            ],
        ];

        $growth = $this->buildFourWeekGrowth($windows['todayEnd'], $tz);

        return [
            'up' => true,
            'dateLabel' => $windows['dateLabel'] ?? $now->translatedFormat('d M Y'),
            'kpiDeltaLabel' => $windows['kpiDeltaLabel'] ?? 'vs kemarin',
            'kpis' => $kpis,
            'growth' => $growth,
            'statistic' => [
                'title' => 'Statistik Alert',
                'subtitle' => self::CHART_DAYS.' hari terakhir',
                'total' => number_format($week['total']),
                'confirmed' => number_format($week['l1_confirmed']),
                'dismissed' => number_format($week['l1_dismissed']),
                'labels' => array_column($daily, 'short'),
                'series' => array_column($daily, 'total'),
            ],
            'categories' => $categories,
            'overview' => [
                'confirmed' => $week['l1_confirmed'],
                'dismissed' => $week['l1_dismissed'],
                'pending' => $week['l1_belum'],
                'rate' => $weekConfirmRate,
            ],
            'weeklyStatus' => [
                'confirmed' => array_column($weekDaily, 'confirmed'),
                'pending' => array_column($weekDaily, 'pending'),
                'dismissed' => array_column($weekDaily, 'dismissed'),
                'labels' => array_column($weekDaily, 'weekday'),
                'totals' => [
                    'confirmed' => array_sum(array_column($weekDaily, 'confirmed')),
                    'pending' => array_sum(array_column($weekDaily, 'pending')),
                    'dismissed' => array_sum(array_column($weekDaily, 'dismissed')),
                ],
            ],
            'sites' => $sites,
            'topOperators' => $topOperators,
            'recentAll' => $recentAll,
            'recentConfirmed' => $recentConfirmed,
            'recentReviews' => $recentAll,
        ];
    }

    /**
     * Agregasi alert per minggu untuk 4 minggu terakhir (berakhir di hari sebelum $endExclusive).
     *
     * @return array{title:string, subtitle:string, total:string, delta:array{text:string, class:string}, labels:list<string>, series:list<int>}
     */
    private function buildFourWeekGrowth(string $endExclusive, string $tz): array
    {
        $endDay = Carbon::parse($endExclusive, $tz)->startOfDay()->subDay();
        $startDay = $endDay->copy()->subDays((self::GROWTH_WEEKS * self::WEEK_DAYS) - 1);
        $start = $startDay->format('Y-m-d H:i:s');
        $end = Carbon::parse($endExclusive, $tz)->startOfDay()->format('Y-m-d H:i:s');

        $dailyRaw = $this->reader->dailyAlertSeries($start, $end);
        $daily = $this->fillDailySeries($start, $end, $dailyRaw, $tz);

        $labels = [];
        $series = [];
        for ($week = 0; $week < self::GROWTH_WEEKS; $week++) {
            $chunk = array_slice($daily, $week * self::WEEK_DAYS, self::WEEK_DAYS);
            $weekTotal = array_sum(array_column($chunk, 'total'));
            $first = $chunk[0]['hari'] ?? null;
            $label = 'W'.($week + 1);
            if (is_string($first) && $first !== '') {
                $label = Carbon::parse($first, $tz)->isoFormat('D MMM');
            }
            $labels[] = $label;
            $series[] = $weekTotal;
        }

        $fourWeekTotal = array_sum($series);
        $latestWeek = (int) ($series[self::GROWTH_WEEKS - 1] ?? 0);
        $previousWeek = (int) ($series[self::GROWTH_WEEKS - 2] ?? 0);

        return [
            'title' => 'Alert Last 4 Week',
            'subtitle' => 'Weekly Report',
            'total' => number_format($fourWeekTotal),
            'delta' => $this->delta($latestWeek, $previousWeek, true),
            'labels' => $labels,
            'series' => $series,
        ];
    }

    /**
     * @param  list<array{hari:string, total:int, confirmed:int, dismissed:int, pending:int, operators:int}>  $rows
     * @return list<array{hari:string, short:string, weekday:string, total:int, confirmed:int, dismissed:int, pending:int, operators:int}>
     */
    private function fillDailySeries(string $start, string $end, array $rows, string $tz): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['hari']] = $row;
        }

        $out = [];
        $cursor = Carbon::parse($start, $tz)->startOfDay();
        $until = Carbon::parse($end, $tz)->startOfDay();

        while ($cursor->lt($until)) {
            $key = $cursor->toDateString();
            $hit = $indexed[$key] ?? null;
            $out[] = [
                'hari' => $key,
                'short' => $cursor->isoFormat('D MMM'),
                'weekday' => $cursor->isoFormat('ddd'),
                'total' => (int) ($hit['total'] ?? 0),
                'confirmed' => (int) ($hit['confirmed'] ?? 0),
                'dismissed' => (int) ($hit['dismissed'] ?? 0),
                'pending' => (int) ($hit['pending'] ?? 0),
                'operators' => (int) ($hit['operators'] ?? 0),
            ];
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * @param  list<array{nama_pelanggaran:string, total:int, confirmed:int, confirmation_rate:float}>  $rows
     * @return list<array{name:string, total:int, pct:int, icon:string, barClass:string, textClass:string}>
     */
    private function mapCategories(array $rows): array
    {
        $top = array_slice($rows, 0, 4);
        $max = 0;
        foreach ($top as $row) {
            $max = max($max, (int) $row['total']);
        }

        $styles = [
            ['icon' => 'solar:eye-scan-bold', 'barClass' => 'bg-orange', 'textClass' => 'text-orange'],
            ['icon' => 'solar:user-speak-bold', 'barClass' => 'bg-success-main', 'textClass' => 'text-success-main'],
            ['icon' => 'solar:phone-calling-bold', 'barClass' => 'bg-info-main', 'textClass' => 'text-info-main'],
            ['icon' => 'solar:danger-triangle-bold', 'barClass' => 'bg-indigo', 'textClass' => 'text-indigo'],
        ];

        $mapped = [];
        foreach ($top as $i => $row) {
            $style = $styles[$i] ?? $styles[0];
            $mapped[] = [
                'name' => (string) $row['nama_pelanggaran'],
                'total' => (int) $row['total'],
                'pct' => $max > 0 ? (int) round(((int) $row['total'] / $max) * 100) : 0,
                'icon' => $style['icon'],
                'barClass' => $style['barClass'],
                'textClass' => $style['textClass'],
            ];
        }

        return $mapped;
    }

    /**
     * @param  list<array{site:string, total:int, confirmed:int}>  $rows
     * @return list<array{site:string, total:int, confirmed:int, pct:int, initials:string, barClass:string}>
     */
    private function mapSites(array $rows): array
    {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, (int) $row['total']);
        }

        $bars = ['bg-primary-600', 'bg-orange', 'bg-yellow', 'bg-success-main', 'bg-info-main', 'bg-purple'];
        $mapped = [];
        foreach ($rows as $i => $row) {
            $site = (string) $row['site'];
            $mapped[] = [
                'site' => $site,
                'total' => (int) $row['total'],
                'confirmed' => (int) $row['confirmed'],
                'pct' => $max > 0 ? (int) round(((int) $row['total'] / $max) * 100) : 0,
                'initials' => mb_strtoupper(mb_substr($site, 0, 2)),
                'barClass' => $bars[$i] ?? 'bg-primary-600',
            ];
        }

        return $mapped;
    }

    /**
     * @param  list<array{kode_sid:string, nama:string, total:int, confirmed:int}>  $rows
     * @return list<array{kode_sid:string, nama:string, total:int, confirmed:int, initials:string, color:string}>
     */
    private function mapOperators(array $rows): array
    {
        $colors = ['#487fff', '#45b369', '#f4941e', '#8252e9', '#de3ace', '#00b8f2'];
        $mapped = [];
        foreach ($rows as $i => $row) {
            $nama = (string) $row['nama'];
            $mapped[] = [
                'kode_sid' => (string) $row['kode_sid'],
                'nama' => $nama === '-' ? (string) $row['kode_sid'] : $nama,
                'total' => (int) $row['total'],
                'confirmed' => (int) $row['confirmed'],
                'initials' => $this->initials($nama === '-' ? (string) $row['kode_sid'] : $nama),
                'color' => $colors[$i] ?? '#487fff',
            ];
        }

        return $mapped;
    }

    /**
     * @param  list<array{id_alert:string, kode_sid:string, nama:string, nama_pelanggaran:string, unit:string, site:string, waktu_deteksi:string|null, sudah_direview_l1:bool, l1_confirmed:bool|null}>  $rows
     * @return list<array{id_alert:string, kode_sid:string, nama:string, nama_pelanggaran:string, unit:string, site:string, waktu:string, status_label:string, status_class:string}>
     */
    private function mapRecent(array $rows, string $tz): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $status = $this->alertStatus((bool) $row['sudah_direview_l1'], $row['l1_confirmed']);
            $waktu = '-';
            if (is_string($row['waktu_deteksi']) && $row['waktu_deteksi'] !== '') {
                try {
                    $waktu = Carbon::parse($row['waktu_deteksi'], $tz)->translatedFormat('d M Y H:i');
                } catch (Throwable) {
                    $waktu = $row['waktu_deteksi'];
                }
            }

            $mapped[] = [
                'id_alert' => (string) $row['id_alert'],
                'kode_sid' => (string) $row['kode_sid'],
                'nama' => (string) $row['nama'],
                'nama_pelanggaran' => (string) $row['nama_pelanggaran'],
                'unit' => (string) $row['unit'],
                'site' => (string) $row['site'],
                'waktu' => $waktu,
                'status_label' => $status['label'],
                'status_class' => $status['class'],
            ];
        }

        return $mapped;
    }

    /**
     * @return array{label:string, class:string}
     */
    private function alertStatus(bool $reviewed, ?bool $confirmed): array
    {
        if (! $reviewed) {
            return ['label' => 'Pending', 'class' => 'bg-warning-focus text-warning-main'];
        }

        if ($confirmed === true) {
            return ['label' => 'Confirmed', 'class' => 'bg-danger-focus text-danger-main'];
        }

        return ['label' => 'Dismissed', 'class' => 'bg-success-focus text-success-main'];
    }

    /**
     * @param  array{l1_reviewed?:int, l1_confirmed?:int}  $summary
     */
    private function confirmationRate(array $summary): float
    {
        $reviewed = (int) ($summary['l1_reviewed'] ?? 0);
        $confirmed = (int) ($summary['l1_confirmed'] ?? 0);

        return $reviewed > 0 ? round($confirmed / $reviewed * 100, 1) : 0.0;
    }

    /**
     * @param  list<array{confirmed:int, total:int}>  $days
     * @return list<float>
     */
    private function rateSparkline(array $days): array
    {
        $out = [];
        foreach ($days as $day) {
            $total = (int) ($day['total'] ?? 0);
            $confirmed = (int) ($day['confirmed'] ?? 0);
            $out[] = $total > 0 ? round($confirmed / $total * 100, 1) : 0.0;
        }

        return $out;
    }

    /**
     * @param  list<array{hari:string, total?:int}>  $days
     * @param  list<int>  $checkinCounts
     * @return list<float>
     */
    private function alertPerPersonSparkline(array $days, array $checkinCounts): array
    {
        $out = [];
        foreach ($days as $i => $day) {
            $people = (int) ($checkinCounts[$i] ?? 0);
            $total = (int) ($day['total'] ?? 0);
            $out[] = $people > 0 ? round($total / $people, 2) : 0.0;
        }

        return $out;
    }

    /**
     * @param  list<array{hari:string, total?:int}>  $days
     * @param  list<int>  $unitCounts
     * @return list<float>
     */
    private function alertPerUnitSparkline(array $days, array $unitCounts): array
    {
        $out = [];
        foreach ($days as $i => $day) {
            $units = (int) ($unitCounts[$i] ?? 0);
            $total = (int) ($day['total'] ?? 0);
            $out[] = $units > 0 ? round($total / $units, 2) : 0.0;
        }

        return $out;
    }

    /**
     * @param  list<array{hari:string}>  $days
     * @return list<int>
     */
    private function unitOperatingSparkline(array $days, string $tz): array
    {
        $out = [];
        foreach ($days as $day) {
            $hari = (string) ($day['hari'] ?? '');
            if ($hari === '') {
                $out[] = 0;
                continue;
            }
            try {
                $start = Carbon::parse($hari, $tz)->startOfDay()->format('Y-m-d H:i:s');
                $end = Carbon::parse($hari, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');
            } catch (Throwable) {
                $out[] = 0;
                continue;
            }
            $out[] = $this->reader->unitsOperatingInRange($start, $end);
        }

        return $out;
    }

    /**
     * @param  list<array{hari:string}>  $days
     * @return list<int>
     */
    private function operatorCheckinSparkline(array $days, string $tz): array
    {
        $out = [];
        foreach ($days as $day) {
            $hari = (string) ($day['hari'] ?? '');
            if ($hari === '') {
                $out[] = 0;
                continue;
            }
            try {
                $start = Carbon::parse($hari, $tz)->startOfDay()->format('Y-m-d H:i:s');
                $end = Carbon::parse($hari, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');
            } catch (Throwable) {
                $out[] = 0;
                continue;
            }
            $out[] = $this->reader->countOperatorCheckinsInRange($start, $end);
        }

        return $out;
    }

    /**
     * @return array{text:string, class:string, raw:int}
     */
    private function delta(int $current, int $previous, bool $increaseIsBad): array
    {
        $diff = $current - $previous;
        $isUp = $diff >= 0;
        $bad = $increaseIsBad ? $isUp : ! $isUp;
        $prefix = $isUp ? '+' : '';

        return [
            'text' => $prefix.number_format($diff),
            'class' => $bad ? 'bg-danger-focus text-danger-main' : 'bg-success-focus text-success-main',
            'raw' => $diff,
        ];
    }

    /**
     * @return array{text:string, class:string, raw:int}
     */
    private function deltaFloat(float $current, float $previous, bool $increaseIsBad, string $suffix): array
    {
        $diff = round($current - $previous, 1);
        $isUp = $diff >= 0;
        $bad = $increaseIsBad ? $isUp : ! $isUp;
        $prefix = $isUp ? '+' : '';

        return [
            'text' => $prefix.$diff.$suffix,
            'class' => $bad ? 'bg-danger-focus text-danger-main' : 'bg-success-focus text-success-main',
            'raw' => (int) round($diff),
        ];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if ($parts === []) {
            return 'OP';
        }

        $first = mb_substr($parts[0], 0, 1);
        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : mb_substr($parts[0], 1, 1);

        return mb_strtoupper($first.($second !== '' ? $second : $first));
    }

    /**
     * @param  array<string, string>  $windows
     * @return array<string, mixed>
     */
    private function emptyPayload(Carbon $now, array $windows): array
    {
        $zeroDelta = ['text' => '+0', 'class' => 'bg-success-focus text-success-main', 'raw' => 0];
        $emptySpark = array_fill(0, self::SPARKLINE_DAYS, 0);
        $emptyWeek = array_fill(0, self::WEEK_DAYS, 0);
        $emptyChart = array_fill(0, self::CHART_DAYS, 0);
        $weekLabels = [];
        $chartLabels = [];
        $weekdayLabels = [];
        $cursor = $now->copy()->startOfDay()->subDays(self::CHART_DAYS - 1);
        for ($i = 0; $i < self::CHART_DAYS; $i++) {
            $chartLabels[] = $cursor->isoFormat('D MMM');
            $cursor->addDay();
        }
        $cursor = $now->copy()->startOfDay()->subDays(self::WEEK_DAYS - 1);
        for ($i = 0; $i < self::WEEK_DAYS; $i++) {
            $weekLabels[] = $cursor->isoFormat('D MMM');
            $weekdayLabels[] = $cursor->isoFormat('ddd');
            $cursor->addDay();
        }

        $kpis = [
            ['label' => 'Total Orang Checkin', 'value' => '0', 'icon' => 'mingcute:user-follow-fill', 'bg' => 'bg-primary-600', 'gradient' => 'bg-gradient-end-1', 'chart' => 'new-user-chart', 'color' => '#487fff', 'sparkline' => $emptySpark, 'delta' => $zeroDelta],
            ['label' => 'Total Alert', 'value' => '0', 'icon' => 'solar:danger-triangle-bold', 'bg' => 'bg-success-main', 'gradient' => 'bg-gradient-end-2', 'chart' => 'active-user-chart', 'color' => '#45b369', 'sparkline' => $emptySpark, 'delta' => $zeroDelta],
            ['label' => 'Rasio Alert / Orang', 'value' => '0.00', 'icon' => 'solar:chart-2-bold', 'bg' => 'bg-yellow', 'gradient' => 'bg-gradient-end-3', 'chart' => 'total-sales-chart', 'color' => '#f4941e', 'sparkline' => $emptySpark, 'delta' => $zeroDelta],
            ['label' => 'Unit Beroperasi', 'value' => '0', 'icon' => 'solar:wheel-bold', 'bg' => 'bg-purple', 'gradient' => 'bg-gradient-end-4', 'chart' => 'conversion-user-chart', 'color' => '#8252e9', 'sparkline' => $emptySpark, 'delta' => $zeroDelta],
            ['label' => 'Total Alert', 'value' => '0', 'icon' => 'solar:danger-triangle-bold', 'bg' => 'bg-pink', 'gradient' => 'bg-gradient-end-5', 'chart' => 'leads-chart', 'color' => '#de3ace', 'sparkline' => $emptySpark, 'delta' => $zeroDelta],
            ['label' => 'Rasio Alert / Unit', 'value' => '0.00', 'icon' => 'solar:bus-bold', 'bg' => 'bg-cyan', 'gradient' => 'bg-gradient-end-6', 'chart' => 'total-profit-chart', 'color' => '#00b8f2', 'sparkline' => $emptySpark, 'delta' => $zeroDelta],
        ];

        return [
            'up' => false,
            'dateLabel' => $windows['dateLabel'] ?? $now->translatedFormat('d M Y'),
            'kpiDeltaLabel' => $windows['kpiDeltaLabel'] ?? 'vs kemarin',
            'kpis' => $kpis,
            'growth' => ['title' => 'Alert Last 4 Week', 'subtitle' => 'Weekly Report', 'total' => '0', 'delta' => $zeroDelta, 'labels' => ['W1', 'W2', 'W3', 'W4'], 'series' => [0, 0, 0, 0]],
            'statistic' => ['title' => 'Statistik Alert', 'subtitle' => self::CHART_DAYS.' hari terakhir', 'total' => '0', 'confirmed' => '0', 'dismissed' => '0', 'labels' => $chartLabels, 'series' => $emptyChart],
            'categories' => [],
            'overview' => ['confirmed' => 0, 'dismissed' => 0, 'pending' => 0, 'rate' => 0.0],
            'weeklyStatus' => ['confirmed' => $emptyWeek, 'pending' => $emptyWeek, 'dismissed' => $emptyWeek, 'labels' => $weekdayLabels, 'totals' => ['confirmed' => 0, 'pending' => 0, 'dismissed' => 0]],
            'sites' => [],
            'topOperators' => [],
            'recentAll' => [],
            'recentConfirmed' => [],
            'recentReviews' => [],
            'windows' => $windows,
        ];
    }
}
