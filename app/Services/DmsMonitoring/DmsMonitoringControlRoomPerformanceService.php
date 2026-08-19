<?php

declare(strict_types=1);

namespace App\Services\DmsMonitoring;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Matriks heatmap performa control room per perusahaan × site.
 */
class DmsMonitoringControlRoomPerformanceService
{
    private const LEAD_TIME_SECONDS = 300;

    /** @var list<array{key:string, label:string}> */
    private const METRICS = [
        ['key' => 'alert_intervention', 'label' => '% Alert yang diintervensi'],
        ['key' => 'unit_intervention', 'label' => '% Unit yang diintervensi'],
        ['key' => 'lead_time_under_5min', 'label' => 'Lead Time intervensi alert real time (Under 5 Menit)'],
    ];

    public function __construct(
        private readonly DmsAlertMonitoringDataReader $reader,
    ) {}

    /**
     * @param  array{start:string, end:string, site?:string, perusahaan?:string}  $filters
     * @return array<string, mixed>
     */
    public function matrix(array $filters): array
    {
        $empty = $this->emptyPayload();
        $tz = (string) config('app.timezone');
        $siteFilter = trim((string) ($filters['site'] ?? ''));
        $companyFilter = trim((string) ($filters['perusahaan'] ?? ''));

        $this->reader->applyScope(
            $siteFilter !== '' ? $siteFilter : null,
            $companyFilter !== '' ? $companyFilter : null,
        );

        if (! $this->reader->isUp()) {
            return $empty;
        }

        try {
            $startDay = Carbon::parse((string) $filters['start'], $tz)->startOfDay();
            $endDay = Carbon::parse((string) $filters['end'], $tz)->startOfDay();
            if ($startDay->gt($endDay)) {
                [$startDay, $endDay] = [$endDay->copy(), $startDay->copy()];
            }

            $start = $startDay->format('Y-m-d H:i:s');
            $end = $endDay->copy()->addDay()->format('Y-m-d H:i:s');
            $rawRows = $this->reader->controlRoomPerformanceRows($start, $end);

            return $this->buildMatrix($rawRows);
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @param  list<array{
     *     perusahaan:string,
     *     site:string,
     *     total_alert:int,
     *     alert_intervened:int,
     *     total_unit:int,
     *     unit_intervened:int,
     *     alert_under_5min:int
     * }>  $rawRows
     * @return array<string, mixed>
     */
    private function buildMatrix(array $rawRows): array
    {
        // Agregasi per perusahaan — sum semua site dalam satu perusahaan
        $byCompany = [];
        foreach ($rawRows as $row) {
            $company = (string) ($row['perusahaan'] ?? '-');
            if (! isset($byCompany[$company])) {
                $byCompany[$company] = [
                    'perusahaan' => $company,
                    'total_alert' => 0,
                    'alert_intervened' => 0,
                    'total_unit' => 0,
                    'unit_intervened' => 0,
                    'alert_under_5min' => 0,
                    'sites' => [],
                ];
            }
            $byCompany[$company]['total_alert'] += (int) ($row['total_alert'] ?? 0);
            $byCompany[$company]['alert_intervened'] += (int) ($row['alert_intervened'] ?? 0);
            $byCompany[$company]['total_unit'] += (int) ($row['total_unit'] ?? 0);
            $byCompany[$company]['unit_intervened'] += (int) ($row['unit_intervened'] ?? 0);
            $byCompany[$company]['alert_under_5min'] += (int) ($row['alert_under_5min'] ?? 0);
            $byCompany[$company]['sites'][] = (string) ($row['site'] ?? '-');
        }

        ksort($byCompany);

        $columns = [];
        foreach ($byCompany as $company => $data) {
            $columns[] = [
                'key' => $company,
                'company' => $company,
                'sites' => $data['sites'],
            ];
        }

        $rows = [];
        foreach (self::METRICS as $metric) {
            $cells = [];
            foreach ($columns as $column) {
                $cells[$column['key']] = $this->buildCell($metric['key'], $byCompany[$column['company']] ?? null);
            }
            $rows[] = [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'cells' => $cells,
            ];
        }

        return [
            'title' => 'Performa Control Room',
            'subtitle' => 'Intervensi alert & lead time real time per perusahaan',
            'metrics' => self::METRICS,
            'companies' => array_values($byCompany),
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{
     *     perusahaan:string,
     *     site:string,
     *     total_alert:int,
     *     alert_intervened:int,
     *     total_unit:int,
     *     unit_intervened:int,
     *     alert_under_5min:int
     * }|null  $row
     * @return array{pct:float, pct_label:string, numerator:int, denominator:int, tone:string}
     */
    private function buildCell(string $metricKey, ?array $row): array
    {
        if ($row === null) {
            return [
                'pct' => 0.0,
                'pct_label' => '0%',
                'numerator' => 0,
                'denominator' => 0,
                'tone' => 'empty',
            ];
        }

        [$numerator, $denominator] = match ($metricKey) {
            'alert_intervention' => [
                (int) ($row['alert_intervened'] ?? 0),
                (int) ($row['total_alert'] ?? 0),
            ],
            'unit_intervention' => [
                (int) ($row['unit_intervened'] ?? 0),
                (int) ($row['total_unit'] ?? 0),
            ],
            'lead_time_under_5min' => [
                (int) ($row['alert_under_5min'] ?? 0),
                (int) ($row['alert_intervened'] ?? 0),
            ],
            default => [0, 0],
        };

        $pct = $denominator > 0 ? round(($numerator / $denominator) * 100, 2) : 0.0;

        return [
            'pct' => $pct,
            'pct_label' => $this->formatPctLabel($pct),
            'numerator' => $numerator,
            'denominator' => $denominator,
            'tone' => $this->toneFromPct($pct, $denominator),
        ];
    }

    private function formatPctLabel(float $pct): string
    {
        if ($pct <= 0.0) {
            return '0%';
        }

        if (abs($pct - round($pct)) < 0.001) {
            return (string) (int) round($pct).'%';
        }

        return number_format($pct, 2).'%';
    }

    private function toneFromPct(float $pct, int $denominator): string
    {
        if ($denominator <= 0) {
            return 'empty';
        }
        if ($pct >= 90.0) {
            return 'excellent';
        }
        if ($pct >= 80.0) {
            return 'good';
        }
        if ($pct >= 50.0) {
            return 'warn';
        }
        if ($pct >= 30.0) {
            return 'bad';
        }

        return 'critical';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'title' => 'Performa Control Room',
            'subtitle' => 'Intervensi alert & lead time real time per perusahaan',
            'metrics' => self::METRICS,
            'companies' => [],
            'columns' => [],
            'rows' => array_map(static fn (array $metric): array => [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'cells' => [],
            ], self::METRICS),
        ];
    }
}
