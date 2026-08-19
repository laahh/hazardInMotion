<?php

declare(strict_types=1);

namespace App\Services\DmsMonitoring;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Matriks heatmap performa control room per perusahaan besar × site.
 *
 * Hanya perusahaan yang terdaftar di MAJOR_COMPANIES yang ditampilkan.
 * Setiap site dalam satu perusahaan menjadi kolom tersendiri.
 */
class DmsMonitoringControlRoomPerformanceService
{
    /**
     * Daftar perusahaan besar yang ditampilkan di heatmap.
     * Pencocokan case-insensitive dan trim.
     *
     * @var list<string>
     */
    public const MAJOR_COMPANIES = [
        'PT Kaltim Diamond Coal',
        'PT Madhani Talatah Nusantara',
        'PT Pamapersada Nusantara',
        'PT Bumi Artlantis Raya',
        'PT Bukit Makmur Mandiri Utama',
        'PT Mutiara Tanjung Lestari',
        'PT Fajar Anugerah Dinamika',
    ];

    /** @var list<array{key:string, label:string}> */
    private const METRICS = [
        ['key' => 'alert_intervention',  'label' => '% Alert yang diintervensi'],
        ['key' => 'unit_intervention',   'label' => '% Unit yang diintervensi'],
        ['key' => 'lead_time_under_5min','label' => 'Lead Time intervensi alert real time (Under 5 Menit)'],
    ];

    /** Lookup canonical name → lower-trimmed variant untuk matching cepat */
    private readonly array $majorLower;

    public function __construct(
        private readonly DmsAlertMonitoringDataReader $reader,
    ) {
        $this->majorLower = array_map(
            static fn (string $n): string => mb_strtolower(trim($n)),
            self::MAJOR_COMPANIES,
        );
    }

    /**
     * @param  array{start:string, end:string, site?:string, perusahaan?:string}  $filters
     * @return array<string, mixed>
     */
    public function matrix(array $filters): array
    {
        $empty = $this->emptyPayload();
        $tz = (string) config('app.timezone');
        $siteFilter    = trim((string) ($filters['site'] ?? ''));
        $companyFilter = trim((string) ($filters['perusahaan'] ?? ''));

        $this->reader->applyScope(
            $siteFilter    !== '' ? $siteFilter    : null,
            $companyFilter !== '' ? $companyFilter : null,
        );

        if (! $this->reader->isUp()) {
            return $empty;
        }

        try {
            $startDay = Carbon::parse((string) $filters['start'], $tz)->startOfDay();
            $endDay   = Carbon::parse((string) $filters['end'],   $tz)->startOfDay();
            if ($startDay->gt($endDay)) {
                [$startDay, $endDay] = [$endDay->copy(), $startDay->copy()];
            }

            $start = $startDay->format('Y-m-d H:i:s');
            $end   = $endDay->copy()->addDay()->format('Y-m-d H:i:s');

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
        // indexed[company][site] = row data
        $indexed    = [];
        // urutan perusahaan sesuai MAJOR_COMPANIES, site diurutkan alphabetically
        $sitesByCompany = [];

        foreach ($rawRows as $row) {
            $rawCompany = (string) ($row['perusahaan'] ?? '-');
            $canonical  = $this->resolveCanonical($rawCompany);

            // Lewati jika bukan perusahaan besar
            if ($canonical === null) {
                continue;
            }

            $site = (string) ($row['site'] ?? '-');
            $indexed[$canonical][$site] = $row;

            if (! isset($sitesByCompany[$canonical])) {
                $sitesByCompany[$canonical] = [];
            }
            $sitesByCompany[$canonical][$site] = true;
        }

        // Bangun columns sesuai urutan MAJOR_COMPANIES → site alphabetical
        $columns         = [];
        $groupedCompanies = [];

        foreach (self::MAJOR_COMPANIES as $company) {
            if (! isset($sitesByCompany[$company])) {
                continue; // perusahaan tidak punya data pada periode ini
            }

            $sites = array_keys($sitesByCompany[$company]);
            sort($sites);

            $companyCols = [];
            foreach ($sites as $site) {
                $col = [
                    'key'     => $company.'|'.$site,
                    'company' => $company,
                    'site'    => $site,
                ];
                $columns[]     = $col;
                $companyCols[] = $col;
            }

            $groupedCompanies[] = [
                'name'    => $company,
                'columns' => $companyCols,
            ];
        }

        // Bangun baris metrik
        $rows = [];
        foreach (self::METRICS as $metric) {
            $cells = [];
            foreach ($columns as $col) {
                $rowData = $indexed[$col['company']][$col['site']] ?? null;
                $cells[$col['key']] = $this->buildCell($metric['key'], $rowData);
            }
            $rows[] = [
                'key'   => $metric['key'],
                'label' => $metric['label'],
                'cells' => $cells,
            ];
        }

        return [
            'title'     => 'Performa Control Room',
            'subtitle'  => 'Intervensi alert & lead time real time per perusahaan / site',
            'metrics'   => self::METRICS,
            'companies' => $groupedCompanies,
            'columns'   => $columns,
            'rows'      => $rows,
        ];
    }

    /**
     * Cocokkan nama perusahaan dari DB ke canonical name di MAJOR_COMPANIES.
     * Pencocokan case-insensitive + trim.
     */
    private function resolveCanonical(string $rawName): ?string
    {
        $lower = mb_strtolower(trim($rawName));
        $idx   = array_search($lower, $this->majorLower, true);

        return $idx !== false ? self::MAJOR_COMPANIES[$idx] : null;
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
            return ['pct' => 0.0, 'pct_label' => '0%', 'numerator' => 0, 'denominator' => 0, 'tone' => 'empty'];
        }

        [$numerator, $denominator] = match ($metricKey) {
            'alert_intervention'  => [(int) ($row['alert_intervened'] ?? 0), (int) ($row['total_alert']      ?? 0)],
            'unit_intervention'   => [(int) ($row['unit_intervened']  ?? 0), (int) ($row['total_unit']       ?? 0)],
            'lead_time_under_5min'=> [(int) ($row['alert_under_5min'] ?? 0), (int) ($row['alert_intervened'] ?? 0)],
            default               => [0, 0],
        };

        $pct = $denominator > 0 ? round(($numerator / $denominator) * 100, 2) : 0.0;

        return [
            'pct'         => $pct,
            'pct_label'   => $this->formatPctLabel($pct),
            'numerator'   => $numerator,
            'denominator' => $denominator,
            'tone'        => $this->toneFromPct($pct, $denominator),
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
        if ($denominator <= 0) { return 'empty'; }
        if ($pct >= 90.0)      { return 'excellent'; }
        if ($pct >= 80.0)      { return 'good'; }
        if ($pct >= 50.0)      { return 'warn'; }
        if ($pct >= 30.0)      { return 'bad'; }

        return 'critical';
    }

    /** @return array<string, mixed> */
    private function emptyPayload(): array
    {
        return [
            'title'     => 'Performa Control Room',
            'subtitle'  => 'Intervensi alert & lead time real time per perusahaan / site',
            'metrics'   => self::METRICS,
            'companies' => [],
            'columns'   => [],
            'rows'      => array_map(static fn (array $m): array => [
                'key'   => $m['key'],
                'label' => $m['label'],
                'cells' => [],
            ], self::METRICS),
        ];
    }
}
