<?php

declare(strict_types=1);

namespace App\Services\DmsMonitoring;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Payload modal overall KPI — unit beroperasi, alert, UCL/LCL, tabel per unit.
 */
final class DmsMonitoringOverallModalService
{
    private const PER_PAGE = 25;

    private const CACHE_TTL = 300;

    public function __construct(
        private readonly DmsAlertMonitoringDataReader $reader,
    ) {}

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    public function payload(array $filters, int $page = 1, string $status = 'with_alert'): array
    {
        $this->reader->applyScope(
            $filters['site'] !== '' ? $filters['site'] : null,
            $filters['perusahaan'] !== '' ? $filters['perusahaan'] : null,
        );

        if (! $this->reader->isUp()) {
            return $this->errorPayload('Koneksi ke hse_automation tidak tersedia.');
        }

        $cacheKey = 'dms_monitoring:overall_unit.payload.v2:'.md5(json_encode([
            $filters,
            $page,
            $status,
            $this->reader->scopeCacheSuffixForKey(),
        ]));

        try {
            /** @var array<string, mixed> */
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters, $page, $status): array {
                return $this->buildPayload($filters, $page, $status);
            });
        } catch (Throwable $e) {
            report($e);

            return $this->errorPayload('Gagal memuat overview unit & alert.');
        }
    }

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    public function unitAlertDetails(array $filters, string $unit, string $unitSite, string $unitPerusahaan): array
    {
        $this->reader->applyScope(
            $filters['site'] !== '' ? $filters['site'] : null,
            $filters['perusahaan'] !== '' ? $filters['perusahaan'] : null,
        );

        if (! $this->reader->isUp()) {
            return $this->errorPayload('Koneksi ke hse_automation tidak tersedia.');
        }

        try {
            $tz = (string) config('app.timezone');
            $start = Carbon::parse($filters['start'], $tz)->startOfDay()->format('Y-m-d H:i:s');
            $end = Carbon::parse($filters['end'], $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');

            return [
                'ok' => true,
                'unit' => $unit,
                'site' => $unitSite,
                'perusahaan' => $unitPerusahaan,
                'alerts' => $this->reader->operatingUnitAlertDetails($start, $end, $unit, $unitSite, $unitPerusahaan),
            ];
        } catch (Throwable $e) {
            report($e);

            return $this->errorPayload('Gagal memuat detail alert unit.');
        }
    }

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    private function buildPayload(array $filters, int $page, string $status): array
    {
        $tz = (string) config('app.timezone');
        $start = Carbon::parse($filters['start'], $tz)->startOfDay()->format('Y-m-d H:i:s');
        $end = Carbon::parse($filters['end'], $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');

        $dailyBar = $this->buildDailyBarChart(
            $start,
            $end,
            $tz,
            $this->reader->dailyOperatingUnitSeries($start, $end),
            'units',
            'Unit beroperasi',
        );

        $summary = $this->reader->overallOperatingUnitsSummary($start, $end);
        $table = $this->reader->overallOperatingUnitsTable($start, $end, $page, self::PER_PAGE, $status);
        $controlChart = $this->buildControlChart($start, $end, $tz);

        $topUnitNames = array_map(
            static fn (array $row): string => (string) ($row['unit'] ?? ''),
            array_slice($summary['top_units'] ?? [], 0, 5),
        );
        $topUnitsChart = $this->reader->dailyAlertsForTopUnits($start, $end, $topUnitNames);

        $totalPages = max(1, (int) ceil(($table['total'] ?? 0) / self::PER_PAGE));

        return [
            'ok' => true,
            'label' => 'Overview Unit & Alert',
            'period' => [
                'start' => $filters['start'],
                'end' => $filters['end'],
            ],
            'summary' => [
                [
                    'key' => 'units_operating',
                    'label' => 'Unit Beroperasi',
                    'value' => number_format((int) ($summary['units_operating'] ?? 0)),
                    'hint' => 'Unit unik di tabel; batang harian = unit-hari',
                    'icon' => 'solar:wheel-bold',
                    'color' => '#8252e9',
                ],
                [
                    'key' => 'units_without_alert',
                    'label' => 'Beroperasi Tanpa Alert',
                    'value' => number_format((int) ($summary['units_without_alert'] ?? 0)),
                    'hint' => 'Unit aktif tanpa alert DMS',
                    'icon' => 'solar:shield-check-bold',
                    'color' => '#45b369',
                ],
                [
                    'key' => 'units_with_alert',
                    'label' => 'Unit Dengan Alert',
                    'value' => number_format((int) ($summary['units_with_alert'] ?? 0)),
                    'hint' => 'Unit aktif yang memiliki alert',
                    'icon' => 'solar:danger-triangle-bold',
                    'color' => '#f4941e',
                ],
                [
                    'key' => 'ratio',
                    'label' => 'Rasio Alert / Unit',
                    'value' => number_format((float) ($summary['ratio_per_unit'] ?? 0), 2),
                    'hint' => number_format((int) ($summary['total_alerts'] ?? 0)).' total alert',
                    'icon' => 'solar:chart-2-bold',
                    'color' => '#487fff',
                ],
            ],
            'top_units' => $summary['top_units'] ?? [],
            'control_chart' => $controlChart,
            'top_units_chart' => $topUnitsChart,
            'daily_bar' => $dailyBar,
            'table' => [
                'columns' => [
                    ['key' => 'unit', 'label' => 'Unit'],
                    ['key' => 'site', 'label' => 'Site'],
                    ['key' => 'perusahaan', 'label' => 'Perusahaan'],
                    ['key' => 'evidence', 'label' => 'Bukti Operasi'],
                    ['key' => 'status', 'label' => 'Status Alert'],
                    ['key' => 'alert_count', 'label' => 'Total Alert'],
                    ['key' => 'detail', 'label' => 'Detail'],
                ],
                'rows' => $table['rows'] ?? [],
            ],
            'table_tabs' => [
                ['key' => 'with_alert', 'label' => 'Unit Dengan Alert', 'count' => (int) ($summary['units_with_alert'] ?? 0)],
                ['key' => 'without_alert', 'label' => 'Unit Tanpa Alert', 'count' => (int) ($summary['units_without_alert'] ?? 0)],
            ],
            'table_active' => $status,
            'pagination' => [
                'page' => $page,
                'per_page' => self::PER_PAGE,
                'total_rows' => (int) ($table['total'] ?? 0),
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * @return array{
     *     labels:list<string>,
     *     series:list<int>,
     *     mean:float,
     *     ucl:float,
     *     lcl:float,
     *     mean_series:list<float>,
     *     ucl_series:list<float>,
     *     lcl_series:list<float>
     * }
     */
    private function buildControlChart(string $start, string $end, string $tz): array
    {
        $dailyRaw = $this->reader->dailyAlertSeries($start, $end);
        $filled = $this->fillDailySeries($start, $end, $dailyRaw, $tz);

        $labels = [];
        $values = [];
        foreach ($filled as $day) {
            $labels[] = (string) ($day['short'] ?? $day['hari']);
            $values[] = (int) ($day['total'] ?? 0);
        }

        $limits = $this->computeControlLimits($values);
        $n = count($values);

        return [
            'labels' => $labels,
            'series' => $values,
            'mean' => $limits['mean'],
            'ucl' => $limits['ucl'],
            'lcl' => $limits['lcl'],
            'mean_series' => array_fill(0, $n, $limits['mean']),
            'ucl_series' => array_fill(0, $n, $limits['ucl']),
            'lcl_series' => array_fill(0, $n, $limits['lcl']),
        ];
    }

    /**
     * @param  list<int>  $values
     * @return array{mean:float, ucl:float, lcl:float}
     */
    private function computeControlLimits(array $values): array
    {
        $n = count($values);
        if ($n === 0) {
            return ['mean' => 0.0, 'ucl' => 0.0, 'lcl' => 0.0];
        }

        $mean = array_sum($values) / $n;
        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }
        $std = $n > 1 ? sqrt($variance / ($n - 1)) : 0.0;

        return [
            'mean' => round($mean, 2),
            'ucl' => round($mean + (3 * $std), 2),
            'lcl' => round($mean - (3 * $std), 2),
        ];
    }

    /**
     * @param  list<array{hari:string, total:int, confirmed:int, dismissed:int, pending:int, operators:int}>  $rows
     * @return list<array{hari:string, short:string, total:int, confirmed:int, dismissed:int, pending:int, operators:int}>
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
     * @param  list<array<string, mixed>>  $rows
     * @return array{labels:list<string>, series:list<int>, total:int, name:string}
     */
    private function buildDailyBarChart(
        string $start,
        string $end,
        string $tz,
        array $rows,
        string $valueKey,
        string $name,
    ): array {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) ($row['hari'] ?? '')] = (int) ($row[$valueKey] ?? 0);
        }

        $labels = [];
        $values = [];
        $cursor = Carbon::parse($start, $tz)->startOfDay();
        $until = Carbon::parse($end, $tz)->startOfDay();
        while ($cursor->lt($until)) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->isoFormat('D MMM');
            $values[] = (int) ($indexed[$key] ?? 0);
            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'series' => $values,
            'total' => array_sum($values),
            'name' => $name,
        ];
    }

    /**
     * @return array{ok:false, message:string}
     */
    private function errorPayload(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
        ];
    }
}
