<?php

declare(strict_types=1);

namespace App\Services\DmsMonitoring;

use App\Services\PraOperasi\PraOperasiOperatorRosterReader;
use App\Services\SportEvaluation\SportEvaluationKaryawanWellSiteResolver;
use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Drill-down detail KPI dashboard DMS — Site → Perusahaan → baris detail.
 */
class DmsMonitoringKpiDetailService
{
    private const PER_PAGE = 50;

    /** @var list<string> */
    public const METRICS = [
        'operator_checkin',
        'total_alert',
        'ratio_per_person',
        'units_operating',
        'ratio_per_unit',
    ];

    /** @var array<string, string> */
    private const METRIC_LABELS = [
        'operator_checkin' => 'Total Orang Checkin',
        'total_alert' => 'Total Alert',
        'ratio_per_person' => 'Rasio Alert / Orang',
        'units_operating' => 'Unit Beroperasi',
        'ratio_per_unit' => 'Rasio Alert / Unit',
    ];

    public function __construct(
        private readonly DmsAlertMonitoringDataReader $reader,
        private readonly PraOperasiOperatorRosterReader $rosterReader,
        private readonly SportEvaluationPvtRfidCheckinReader $rfidReader,
        private readonly SportEvaluationKaryawanWellSiteResolver $siteResolver,
    ) {}

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    public function detail(
        string $metric,
        array $filters,
        string $level,
        ?string $parentSite,
        ?string $parentCompany,
        int $page,
    ): array {
        if (! in_array($metric, self::METRICS, true)) {
            return $this->errorPayload('Metrik tidak dikenali.');
        }

        $tz = (string) config('app.timezone');
        $this->reader->applyScope(
            $filters['site'] !== '' ? $filters['site'] : null,
            $filters['perusahaan'] !== '' ? $filters['perusahaan'] : null,
        );

        if (! $this->reader->isUp()) {
            return $this->errorPayload('Koneksi ke hse_automation tidak tersedia.');
        }

        try {
            $start = Carbon::parse($filters['start'], $tz)->startOfDay()->format('Y-m-d H:i:s');
            $end = Carbon::parse($filters['end'], $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');
            $parentSite = $this->sanitizeName($parentSite);
            $parentCompany = $this->sanitizeName($parentCompany);

            return match ($metric) {
                'operator_checkin' => $this->operatorCheckinDetail($start, $end, $level, $parentSite, $parentCompany, $page, $filters),
                'total_alert' => $this->totalAlertDetail($start, $end, $level, $parentSite, $parentCompany, $page, $filters),
                'ratio_per_person' => $this->ratioPerPersonDetail($start, $end, $level, $parentSite, $parentCompany, $page, $filters),
                'units_operating' => $this->unitsOperatingDetail($start, $end, $level, $parentSite, $parentCompany, $page, $filters),
                'ratio_per_unit' => $this->ratioPerUnitDetail($start, $end, $level, $parentSite, $parentCompany, $page, $filters),
                default => $this->errorPayload('Metrik tidak dikenali.'),
            };
        } catch (Throwable $e) {
            report($e);

            return $this->errorPayload('Gagal memuat detail KPI.');
        }
    }

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    private function operatorCheckinDetail(
        string $start,
        string $end,
        string $level,
        ?string $parentSite,
        ?string $parentCompany,
        int $page,
        array $filters,
    ): array {
        $cohort = $this->operatorCheckinCohort($start, $end);
        $total = count($cohort);

        return match ($level) {
            'sites' => $this->wrapSitesLevel(
                'operator_checkin',
                $this->aggregateCohortBySite($cohort),
                $total,
                $filters,
            ),
            'companies' => $this->wrapCompaniesLevel(
                'operator_checkin',
                $parentSite,
                $this->aggregateCohortByCompany($this->filterCohort($cohort, $parentSite, null)),
                count($this->filterCohort($cohort, $parentSite, null)),
                $filters,
            ),
            'rows' => $this->wrapRowsLevel(
                'operator_checkin',
                $parentSite,
                $parentCompany,
                $this->paginateArray(
                    $this->filterCohort($cohort, $parentSite, $parentCompany),
                    $page,
                ),
                count($this->filterCohort($cohort, $parentSite, $parentCompany)),
                $page,
                $filters,
                [
                    ['key' => 'nama', 'label' => 'Nama'],
                    ['key' => 'kode_sid', 'label' => 'SID'],
                    ['key' => 'jabatan', 'label' => 'Jabatan'],
                    ['key' => 'perusahaan', 'label' => 'Perusahaan'],
                    ['key' => 'site', 'label' => 'Site'],
                    ['key' => 'checked_in_at', 'label' => 'Check-in'],
                    ['key' => 'gate', 'label' => 'Gate'],
                ],
            ),
            default => $this->errorPayload('Level tidak valid.'),
        };
    }

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    private function totalAlertDetail(
        string $start,
        string $end,
        string $level,
        ?string $parentSite,
        ?string $parentCompany,
        int $page,
        array $filters,
    ): array {
        $summary = $this->reader->alertSummary($start, $end);
        $total = (int) ($summary['total'] ?? 0);

        return match ($level) {
            'sites' => $this->wrapSitesLevel(
                'total_alert',
                array_map(static fn (array $r): array => [
                    'site' => $r['site'],
                    'value' => $r['value'],
                ], $this->reader->alertCountBySite($start, $end)),
                $total,
                $filters,
            ),
            'companies' => $this->wrapCompaniesLevel(
                'total_alert',
                $parentSite,
                array_map(static fn (array $r): array => [
                    'perusahaan' => $r['perusahaan'],
                    'value' => $r['value'],
                ], $this->reader->alertCountBySiteAndCompany($start, $end, (string) $parentSite)),
                array_sum($this->reader->alertCountMapByCompanyInSite($start, $end, (string) $parentSite)),
                $filters,
            ),
            'rows' => $this->wrapAlertRows($start, $end, $parentSite, $parentCompany, $page, $filters),
            default => $this->errorPayload('Level tidak valid.'),
        };
    }

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    private function ratioPerPersonDetail(
        string $start,
        string $end,
        string $level,
        ?string $parentSite,
        ?string $parentCompany,
        int $page,
        array $filters,
    ): array {
        $cohort = $this->operatorCheckinCohort($start, $end);
        $alertTotal = (int) ($this->reader->alertSummary($start, $end)['total'] ?? 0);
        $checkinTotal = count($cohort);
        $total = $checkinTotal > 0 ? round($alertTotal / $checkinTotal, 2) : 0.0;

        return match ($level) {
            'sites' => $this->wrapSitesLevel(
                'ratio_per_person',
                $this->ratioRowsBySite($start, $end, $cohort),
                $total,
                $filters,
                true,
            ),
            'companies' => $this->wrapCompaniesLevel(
                'ratio_per_person',
                $parentSite,
                $this->ratioRowsByCompany($start, $end, $cohort, (string) $parentSite),
                $this->ratioTotalForScope($start, $end, $cohort, (string) $parentSite, null),
                $filters,
                true,
            ),
            'rows' => $this->wrapRowsLevel(
                'ratio_per_person',
                $parentSite,
                $parentCompany,
                $this->paginateArray(
                    $this->ratioOperatorRows($start, $end, $cohort, $parentSite, $parentCompany),
                    $page,
                ),
                count($this->ratioOperatorRows($start, $end, $cohort, $parentSite, $parentCompany)),
                $page,
                $filters,
                [
                    ['key' => 'nama', 'label' => 'Nama'],
                    ['key' => 'kode_sid', 'label' => 'SID'],
                    ['key' => 'checkin', 'label' => 'Check-in'],
                    ['key' => 'alert_count', 'label' => 'Alert'],
                    ['key' => 'value', 'label' => 'Rasio'],
                ],
                true,
            ),
            default => $this->errorPayload('Level tidak valid.'),
        };
    }

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    private function unitsOperatingDetail(
        string $start,
        string $end,
        string $level,
        ?string $parentSite,
        ?string $parentCompany,
        int $page,
        array $filters,
    ): array {
        $unitsMoving = $this->reader->unitsOperatingNow(30);
        $unitsInPeriod = $this->reader->unitsOperatingInRange($start, $end);
        $alertUnitsTotal = array_sum(array_column($this->reader->distinctAlertUnitsBySite($start, $end), 'value'));

        $summary = [
            ['label' => 'Unit bergerak (30 mnt)', 'value' => number_format($unitsMoving), 'hint' => 'dms_vehicle_statuses jika ada data; fallback dms_vehicle_status_alerts'],
            ['label' => 'Unit bergerak (periode)', 'value' => number_format($unitsInPeriod), 'hint' => 'dms_vehicle_statuses jika ada data; fallback dms_vehicle_status_alerts'],
            ['label' => 'Unit dengan alert', 'value' => number_format($alertUnitsTotal), 'hint' => 'Per site — mv_dms_alert'],
        ];

        $base = match ($level) {
            'sites' => $this->wrapSitesLevel(
                'units_operating',
                array_map(static fn (array $r): array => [
                    'site' => $r['site'],
                    'value' => $r['value'],
                ], $this->reader->distinctUnitsBySite($start, $end)),
                array_sum(array_column($this->reader->distinctUnitsBySite($start, $end), 'value')),
                $filters,
                false,
                $summary,
            ),
            'companies' => $this->wrapCompaniesLevel(
                'units_operating',
                $parentSite,
                array_map(static fn (array $r): array => [
                    'perusahaan' => $r['perusahaan'],
                    'value' => $r['value'],
                ], $this->reader->distinctUnitsBySiteAndCompany($start, $end, (string) $parentSite)),
                array_sum($this->reader->unitCountMapByCompanyInSite($start, $end, (string) $parentSite)),
                $filters,
                false,
                $summary,
            ),
            'rows' => $this->wrapOperatingUnitRows($start, $end, $parentSite, $parentCompany, $page, $filters, $summary),
            default => $this->errorPayload('Level tidak valid.'),
        };

        return $base;
    }

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    private function ratioPerUnitDetail(
        string $start,
        string $end,
        string $level,
        ?string $parentSite,
        ?string $parentCompany,
        int $page,
        array $filters,
    ): array {
        $alertTotal = (int) ($this->reader->alertSummary($start, $end)['total'] ?? 0);
        $unitRows = $this->reader->distinctUnitsBySite($start, $end);
        $unitTotal = array_sum(array_column($unitRows, 'value'));
        $total = $unitTotal > 0 ? round($alertTotal / $unitTotal, 2) : 0.0;

        return match ($level) {
            'sites' => $this->wrapSitesLevel(
                'ratio_per_unit',
                $this->ratioUnitRowsBySite($start, $end),
                $total,
                $filters,
                true,
            ),
            'companies' => $this->wrapCompaniesLevel(
                'ratio_per_unit',
                $parentSite,
                $this->ratioUnitRowsByCompany($start, $end, (string) $parentSite),
                $this->ratioUnitTotalForSite($start, $end, (string) $parentSite),
                $filters,
                true,
            ),
            'rows' => $this->wrapUnitRows($start, $end, $parentSite, $parentCompany, $page, $filters, [], true),
            default => $this->errorPayload('Level tidak valid.'),
        };
    }

    /**
     * @return list<array{kode_sid:string, nama:string, jabatan:string, perusahaan:string, site:string, checked_in_at:string, gate:string}>
     */
    private function operatorCheckinCohort(string $start, string $end): array
    {
        if (! $this->rfidReader->isUp()) {
            return [];
        }

        $cacheKey = 'dms_monitoring:kpi.operator_cohort:'.md5($start.'|'.$end);

        /** @var list<array{kode_sid:string, nama:string, jabatan:string, perusahaan:string, site:string, checked_in_at:string, gate:string}> */
        return Cache::remember($cacheKey, 900, function () use ($start, $end): array {
            $roster = $this->rosterReader->operatorRoster();
            if ($roster === []) {
                return [];
            }

            $tz = (string) config('app.timezone');
            $fromDate = Carbon::parse($start, $tz)->toDateString();
            $toDate = Carbon::parse($end, $tz)->subDay()->toDateString();
            if ($toDate < $fromDate) {
                $toDate = $fromDate;
            }

            $sids = array_map(static fn (array $o): string => (string) ($o['kode_sid'] ?? ''), $roster);
            $byDay = $this->rfidReader->firstPassedCheckinsByDayForSids($fromDate, $toDate, $sids);

            $cohort = [];
            foreach ($roster as $operator) {
                $upper = mb_strtoupper((string) ($operator['kode_sid'] ?? ''));
                if ($upper === '') {
                    continue;
                }

                $best = null;
                foreach ($byDay as $dayCheckins) {
                    if (! isset($dayCheckins[$upper])) {
                        continue;
                    }
                    $checkin = $dayCheckins[$upper];
                    if ($best === null || $checkin['checked_in_at'] < $best['checked_in_at']) {
                        $best = $checkin;
                    }
                }

                if ($best === null) {
                    continue;
                }

                $company = trim((string) ($operator['perusahaan'] ?? ''));
                if ($company === '') {
                    $company = trim((string) ($best['perusahaan'] ?? ''));
                }

                $cohort[] = [
                    'kode_sid' => (string) $operator['kode_sid'],
                    'nama' => trim((string) ($operator['nama'] ?? '')) !== ''
                        ? (string) $operator['nama']
                        : (string) ($best['nama_karyawan'] ?? '-'),
                    'jabatan' => (string) ($operator['jabatan'] ?? ''),
                    'perusahaan' => $company !== '' ? $company : '-',
                    'site' => $this->siteResolver->resolveOrDash($operator['kode_sid'], ''),
                    'checked_in_at' => (string) $best['checked_in_at'],
                    'gate' => (string) ($best['gate'] ?? '-'),
                ];
            }

            return $cohort;
        });
    }

    /**
     * @param  list<array{kode_sid:string, nama:string, jabatan:string, perusahaan:string, site:string, checked_in_at:string, gate:string}>  $cohort
     * @return list<array{site:string, value:int}>
     */
    private function aggregateCohortBySite(array $cohort): array
    {
        $grouped = [];
        foreach ($cohort as $row) {
            $site = (string) ($row['site'] ?? '-');
            $grouped[$site] = ($grouped[$site] ?? 0) + 1;
        }
        arsort($grouped);
        $out = [];
        foreach ($grouped as $site => $value) {
            $out[] = ['site' => $site, 'value' => $value];
        }

        return $out;
    }

    /**
     * @param  list<array{kode_sid:string, nama:string, jabatan:string, perusahaan:string, site:string, checked_in_at:string, gate:string}>  $cohort
     * @return list<array{perusahaan:string, value:int}>
     */
    private function aggregateCohortByCompany(array $cohort): array
    {
        $grouped = [];
        foreach ($cohort as $row) {
            $company = (string) ($row['perusahaan'] ?? '-');
            $grouped[$company] = ($grouped[$company] ?? 0) + 1;
        }
        arsort($grouped);
        $out = [];
        foreach ($grouped as $name => $value) {
            $out[] = ['perusahaan' => $name, 'value' => $value];
        }

        return $out;
    }

    /**
     * @param  list<array{kode_sid:string, nama:string, jabatan:string, perusahaan:string, site:string, checked_in_at:string, gate:string}>  $cohort
     * @return list<array{kode_sid:string, nama:string, jabatan:string, perusahaan:string, site:string, checked_in_at:string, gate:string}>
     */
    private function filterCohort(array $cohort, ?string $site, ?string $company): array
    {
        return array_values(array_filter($cohort, static function (array $row) use ($site, $company): bool {
            if ($site !== null && $site !== '' && ($row['site'] ?? '') !== $site) {
                return false;
            }
            if ($company !== null && $company !== '' && ($row['perusahaan'] ?? '') !== $company) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  list<array{kode_sid:string, nama:string, jabatan:string, perusahaan:string, site:string, checked_in_at:string, gate:string}>  $cohort
     * @return list<array{site:string, value:float|int}>
     */
    private function ratioRowsBySite(string $start, string $end, array $cohort): array
    {
        $alerts = $this->reader->alertCountMapBySite($start, $end);
        $checkins = [];
        foreach ($cohort as $row) {
            $site = (string) ($row['site'] ?? '-');
            $checkins[$site] = ($checkins[$site] ?? 0) + 1;
        }

        $sites = array_unique(array_merge(array_keys($alerts), array_keys($checkins)));
        $rows = [];
        foreach ($sites as $site) {
            $people = (int) ($checkins[$site] ?? 0);
            $alertCount = (int) ($alerts[$site] ?? 0);
            if ($people === 0 && $alertCount === 0) {
                continue;
            }
            $rows[] = [
                'site' => $site,
                'value' => $people > 0 ? round($alertCount / $people, 2) : 0.0,
                'alert_count' => $alertCount,
                'checkin_count' => $people,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['value'] <=> $a['value']));

        return $rows;
    }

    /**
     * @param  list<array{kode_sid:string, nama:string, jabatan:string, perusahaan:string, site:string, checked_in_at:string, gate:string}>  $cohort
     * @return list<array{perusahaan:string, value:float, alert_count:int, checkin_count:int}>
     */
    private function ratioRowsByCompany(string $start, string $end, array $cohort, string $site): array
    {
        $alerts = $this->reader->alertCountMapByCompanyInSite($start, $end, $site);
        $filtered = $this->filterCohort($cohort, $site, null);
        $checkins = [];
        foreach ($filtered as $row) {
            $company = (string) ($row['perusahaan'] ?? '-');
            $checkins[$company] = ($checkins[$company] ?? 0) + 1;
        }

        $companies = array_unique(array_merge(array_keys($alerts), array_keys($checkins)));
        $rows = [];
        foreach ($companies as $company) {
            $people = (int) ($checkins[$company] ?? 0);
            $alertCount = (int) ($alerts[$company] ?? 0);
            if ($people === 0 && $alertCount === 0) {
                continue;
            }
            $rows[] = [
                'perusahaan' => $company,
                'value' => $people > 0 ? round($alertCount / $people, 2) : 0.0,
                'alert_count' => $alertCount,
                'checkin_count' => $people,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['value'] <=> $a['value']));

        return $rows;
    }

    /**
     * @param  list<array{kode_sid:string, nama:string, jabatan:string, perusahaan:string, site:string, checked_in_at:string, gate:string}>  $cohort
     */
    private function ratioTotalForScope(
        string $start,
        string $end,
        array $cohort,
        ?string $site,
        ?string $company,
    ): float {
        $filtered = $this->filterCohort($cohort, $site, $company);
        $people = count($filtered);
        if ($people === 0) {
            return 0.0;
        }

        $alertMap = $this->reader->alertCountByOperatorSid($start, $end, $site, $company);
        $alertSum = 0;
        foreach ($filtered as $row) {
            $upper = mb_strtoupper((string) ($row['kode_sid'] ?? ''));
            $alertSum += (int) ($alertMap[$upper] ?? 0);
        }

        return round($alertSum / $people, 2);
    }

    /**
     * @param  list<array{kode_sid:string, nama:string, jabatan:string, perusahaan:string, site:string, checked_in_at:string, gate:string}>  $cohort
     * @return list<array<string, mixed>>
     */
    private function ratioOperatorRows(
        string $start,
        string $end,
        array $cohort,
        ?string $site,
        ?string $company,
    ): array {
        $filtered = $this->filterCohort($cohort, $site, $company);
        $alertMap = $this->reader->alertCountByOperatorSid($start, $end, $site, $company);
        $rows = [];
        foreach ($filtered as $row) {
            $upper = mb_strtoupper((string) ($row['kode_sid'] ?? ''));
            $alertCount = (int) ($alertMap[$upper] ?? 0);
            $rows[] = [
                'nama' => (string) ($row['nama'] ?? '-'),
                'kode_sid' => (string) ($row['kode_sid'] ?? '-'),
                'checkin' => 'Ya',
                'alert_count' => $alertCount,
                'value' => number_format($alertCount, 0),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['alert_count'] <=> $a['alert_count']));

        return $rows;
    }

    /**
     * @return list<array{site:string, value:float, alert_count:int, unit_count:int}>
     */
    private function ratioUnitRowsBySite(string $start, string $end): array
    {
        $alerts = $this->reader->alertCountMapBySite($start, $end);
        $units = $this->reader->unitCountMapBySite($start, $end);
        $sites = array_unique(array_merge(array_keys($alerts), array_keys($units)));
        $rows = [];
        foreach ($sites as $site) {
            $unitCount = (int) ($units[$site] ?? 0);
            $alertCount = (int) ($alerts[$site] ?? 0);
            if ($unitCount === 0 && $alertCount === 0) {
                continue;
            }
            $rows[] = [
                'site' => $site,
                'value' => $unitCount > 0 ? round($alertCount / $unitCount, 2) : 0.0,
                'alert_count' => $alertCount,
                'unit_count' => $unitCount,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['value'] <=> $a['value']));

        return $rows;
    }

    /**
     * @return list<array{perusahaan:string, value:float, alert_count:int, unit_count:int}>
     */
    private function ratioUnitRowsByCompany(string $start, string $end, string $site): array
    {
        $alerts = $this->reader->alertCountMapByCompanyInSite($start, $end, $site);
        $units = $this->reader->unitCountMapByCompanyInSite($start, $end, $site);
        $companies = array_unique(array_merge(array_keys($alerts), array_keys($units)));
        $rows = [];
        foreach ($companies as $company) {
            $unitCount = (int) ($units[$company] ?? 0);
            $alertCount = (int) ($alerts[$company] ?? 0);
            if ($unitCount === 0 && $alertCount === 0) {
                continue;
            }
            $rows[] = [
                'perusahaan' => $company,
                'value' => $unitCount > 0 ? round($alertCount / $unitCount, 2) : 0.0,
                'alert_count' => $alertCount,
                'unit_count' => $unitCount,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['value'] <=> $a['value']));

        return $rows;
    }

    private function ratioUnitTotalForSite(string $start, string $end, string $site): float
    {
        $alerts = array_sum($this->reader->alertCountMapByCompanyInSite($start, $end, $site));
        $units = array_sum($this->reader->unitCountMapByCompanyInSite($start, $end, $site));

        return $units > 0 ? round($alerts / $units, 2) : 0.0;
    }

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    private function wrapAlertRows(
        string $start,
        string $end,
        ?string $parentSite,
        ?string $parentCompany,
        int $page,
        array $filters,
    ): array {
        $result = $this->reader->alertDetailRows($start, $end, $parentSite, $parentCompany, $page, self::PER_PAGE);

        return $this->wrapRowsLevel(
            'total_alert',
            $parentSite,
            $parentCompany,
            $result['rows'],
            $result['total'],
            $page,
            $filters,
            [
                ['key' => 'id_alert', 'label' => 'ID Alert'],
                ['key' => 'nama', 'label' => 'Nama'],
                ['key' => 'nama_pelanggaran', 'label' => 'Pelanggaran'],
                ['key' => 'unit', 'label' => 'Unit'],
                ['key' => 'waktu_deteksi', 'label' => 'Waktu'],
                ['key' => 'status_label', 'label' => 'Status L1'],
            ],
        );
    }

    /**
     * @param  list<array{label:string, value:string, hint:string}>  $summary
     * @return array<string, mixed>
     */
    private function wrapOperatingUnitRows(
        string $start,
        string $end,
        ?string $parentSite,
        ?string $parentCompany,
        int $page,
        array $filters,
        array $summary = [],
    ): array {
        $result = $this->reader->operatingUnitDetailRows($start, $end, $parentSite, $parentCompany, $page, self::PER_PAGE);

        $payload = $this->wrapRowsLevel(
            'units_operating',
            $parentSite,
            $parentCompany,
            $result['rows'],
            $result['total'],
            $page,
            $filters,
            [
                ['key' => 'unit', 'label' => 'Unit'],
                ['key' => 'site', 'label' => 'Site'],
                ['key' => 'perusahaan', 'label' => 'Perusahaan'],
                ['key' => 'value', 'label' => 'Beroperasi'],
            ],
            false,
        );

        if ($summary !== []) {
            $payload['summary'] = $summary;
        }

        return $payload;
    }

    /**
     * @param  list<array{label:string, value:string, hint:string}>  $summary
     * @return array<string, mixed>
     */
    private function wrapUnitRows(
        string $start,
        string $end,
        ?string $parentSite,
        ?string $parentCompany,
        int $page,
        array $filters,
        array $summary = [],
        bool $asRatio = false,
    ): array {
        $result = $this->reader->unitDetailRows($start, $end, $parentSite, $parentCompany, $page, self::PER_PAGE);
        $rows = $result['rows'];
        if ($asRatio) {
            $rows = array_map(static function (array $row): array {
                return [
                    'unit' => (string) ($row['unit'] ?? '-'),
                    'site' => (string) ($row['site'] ?? '-'),
                    'perusahaan' => (string) ($row['perusahaan'] ?? '-'),
                    'alert_count' => (int) ($row['value'] ?? 0),
                    'value' => number_format((int) ($row['value'] ?? 0), 0),
                ];
            }, $rows);
        }

        $metric = $asRatio ? 'ratio_per_unit' : 'units_operating';
        $columns = $asRatio
            ? [
                ['key' => 'unit', 'label' => 'Unit'],
                ['key' => 'site', 'label' => 'Site'],
                ['key' => 'perusahaan', 'label' => 'Perusahaan'],
                ['key' => 'alert_count', 'label' => 'Alert'],
            ]
            : [
                ['key' => 'unit', 'label' => 'Unit'],
                ['key' => 'site', 'label' => 'Site'],
                ['key' => 'perusahaan', 'label' => 'Perusahaan'],
                ['key' => 'value', 'label' => 'Total Alert'],
            ];

        $payload = $this->wrapRowsLevel(
            $metric,
            $parentSite,
            $parentCompany,
            $rows,
            $result['total'],
            $page,
            $filters,
            $columns,
            $asRatio,
        );

        if ($summary !== [] && ! $asRatio) {
            $payload['summary'] = $summary;
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{label:string, value:string, hint?:string}>  $summary
     * @return array<string, mixed>
     */
    private function wrapSitesLevel(
        string $metric,
        array $rows,
        int|float $total,
        array $filters,
        bool $isFloat = false,
        array $summary = [],
    ): array {
        $formatted = array_map(function (array $row) use ($isFloat): array {
            $value = $row['value'] ?? 0;

            return [
                'label' => (string) ($row['site'] ?? '-'),
                'value' => $isFloat ? number_format((float) $value, 2) : number_format((int) $value),
                'raw' => $value,
                'meta' => array_filter([
                    'alert_count' => $row['alert_count'] ?? null,
                    'checkin_count' => $row['checkin_count'] ?? null,
                    'unit_count' => $row['unit_count'] ?? null,
                ], static fn ($v): bool => $v !== null),
                'drill' => ['level' => 'companies', 'parent_site' => (string) ($row['site'] ?? '')],
            ];
        }, $rows);

        return $this->basePayload($metric, 'sites', $total, $formatted, $filters, $summary, $isFloat, [
            ['key' => 'label', 'label' => 'Site'],
            ['key' => 'value', 'label' => 'Nilai'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{label:string, value:string, hint?:string}>  $summary
     * @return array<string, mixed>
     */
    private function wrapCompaniesLevel(
        string $metric,
        ?string $parentSite,
        array $rows,
        int|float $total,
        array $filters,
        bool $isFloat = false,
        array $summary = [],
    ): array {
        if ($parentSite === null || $parentSite === '') {
            return $this->errorPayload('Site induk wajib untuk level perusahaan.');
        }

        $formatted = array_map(function (array $row) use ($parentSite, $isFloat): array {
            $value = $row['value'] ?? 0;

            return [
                'label' => (string) ($row['perusahaan'] ?? '-'),
                'value' => $isFloat ? number_format((float) $value, 2) : number_format((int) $value),
                'raw' => $value,
                'meta' => array_filter([
                    'alert_count' => $row['alert_count'] ?? null,
                    'checkin_count' => $row['checkin_count'] ?? null,
                    'unit_count' => $row['unit_count'] ?? null,
                ], static fn ($v): bool => $v !== null),
                'drill' => [
                    'level' => 'rows',
                    'parent_site' => $parentSite,
                    'parent_company' => (string) ($row['perusahaan'] ?? ''),
                ],
            ];
        }, $rows);

        return $this->basePayload($metric, 'companies', $total, $formatted, $filters, $summary, $isFloat, [
            ['key' => 'label', 'label' => 'Perusahaan'],
            ['key' => 'value', 'label' => 'Nilai'],
        ], $parentSite);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key:string, label:string}>  $columns
     * @return array<string, mixed>
     */
    private function wrapRowsLevel(
        string $metric,
        ?string $parentSite,
        ?string $parentCompany,
        array $rows,
        int $totalRows,
        int $page,
        array $filters,
        array $columns,
        bool $isFloat = false,
    ): array {
        if ($parentSite === null || $parentSite === '' || $parentCompany === null || $parentCompany === '') {
            return $this->errorPayload('Site dan perusahaan induk wajib untuk level detail.');
        }

        $formatted = array_map(function (array $row) use ($isFloat): array {
            if (isset($row['value']) && is_numeric($row['value']) && $isFloat) {
                $row['value'] = number_format((float) $row['value'], 2);
            } elseif (isset($row['value']) && is_int($row['value'])) {
                $row['value'] = number_format($row['value']);
            }

            return $row;
        }, $rows);

        return $this->basePayload(
            $metric,
            'rows',
            $totalRows,
            $formatted,
            $filters,
            [],
            $isFloat,
            $columns,
            $parentSite,
            $parentCompany,
            $page,
            $totalRows,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key:string, label:string}>  $columns
     * @param  list<array{label:string, value:string, hint?:string}>  $summary
     * @return array<string, mixed>
     */
    private function basePayload(
        string $metric,
        string $level,
        int|float $total,
        array $rows,
        array $filters,
        array $summary = [],
        bool $isFloat = false,
        array $columns = [],
        ?string $parentSite = null,
        ?string $parentCompany = null,
        int $page = 1,
        int $totalRows = 0,
    ): array {
        return [
            'ok' => true,
            'metric' => $metric,
            'label' => self::METRIC_LABELS[$metric] ?? $metric,
            'level' => $level,
            'total' => $isFloat ? number_format((float) $total, 2) : number_format((int) $total),
            'total_raw' => $total,
            'value_label' => $isFloat ? 'Rasio' : 'Jumlah',
            'rows' => $rows,
            'columns' => $columns,
            'summary' => $summary,
            'breadcrumb' => $this->breadcrumb($level, $parentSite, $parentCompany),
            'filters' => $filters,
            'drillable' => $level !== 'rows',
            'pagination' => $level === 'rows'
                ? [
                    'page' => $page,
                    'per_page' => self::PER_PAGE,
                    'total_rows' => $totalRows,
                    'total_pages' => (int) max(1, ceil($totalRows / self::PER_PAGE)),
                ]
                : null,
        ];
    }

    /**
     * @return list<array{label:string, level:string, parent_site?:string, parent_company?:string}>
     */
    private function breadcrumb(string $level, ?string $parentSite, ?string $parentCompany): array
    {
        $crumbs = [
            ['label' => 'Semua Site', 'level' => 'sites'],
        ];
        if ($level === 'companies' || $level === 'rows') {
            $crumbs[] = ['label' => (string) $parentSite, 'level' => 'companies', 'parent_site' => (string) $parentSite];
        }
        if ($level === 'rows') {
            $crumbs[] = [
                'label' => (string) $parentCompany,
                'level' => 'rows',
                'parent_site' => (string) $parentSite,
                'parent_company' => (string) $parentCompany,
            ];
        }

        return $crumbs;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<mixed>
     */
    private function paginateArray(array $items, int $page): array
    {
        $offset = max(0, ($page - 1) * self::PER_PAGE);

        return array_slice($items, $offset, self::PER_PAGE);
    }

    private function sanitizeName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = mb_substr(trim($value), 0, 80);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Matriks kuadran site untuk widget "Earning Statistic":
     * X = check-in, Y = alert/orang, Z = total alert, warna = WoW rasio.
     *
     * @param  array{start:string, end:string, site?:string, perusahaan?:string}  $filters
     * @return array<string, mixed>
     */
    public function siteQuadrantMatrix(array $filters): array
    {
        $cacheKey = 'dms_monitoring:site_quadrant_matrix:'.md5(json_encode([
            'start' => (string) ($filters['start'] ?? ''),
            'end' => (string) ($filters['end'] ?? ''),
            'site' => (string) ($filters['site'] ?? ''),
            'perusahaan' => (string) ($filters['perusahaan'] ?? ''),
        ]));

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, 1800, fn (): array => $this->buildSiteQuadrantMatrix($filters));
    }

    /**
     * @param  array{start:string, end:string, site?:string, perusahaan?:string}  $filters
     * @return array<string, mixed>
     */
    private function buildSiteQuadrantMatrix(array $filters): array
    {
        $empty = $this->emptyQuadrantPayload();
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

            $periodDays = max(1, $startDay->diffInDays($endDay) + 1);
            $start = $startDay->format('Y-m-d H:i:s');
            $end = $endDay->copy()->addDay()->format('Y-m-d H:i:s');

            $prevEndDay = $startDay->copy()->subDay();
            $prevStartDay = $prevEndDay->copy()->subDays($periodDays - 1);
            $prevStart = $prevStartDay->format('Y-m-d H:i:s');
            $prevEnd = $startDay->format('Y-m-d H:i:s');

            $currentCohort = $this->filterCohort(
                $this->operatorCheckinCohort($start, $end),
                $siteFilter !== '' ? $siteFilter : null,
                $companyFilter !== '' ? $companyFilter : null,
            );
            $prevCohort = $this->filterCohort(
                $this->operatorCheckinCohort($prevStart, $prevEnd),
                $siteFilter !== '' ? $siteFilter : null,
                $companyFilter !== '' ? $companyFilter : null,
            );

            $currentRows = $this->ratioRowsBySite($start, $end, $currentCohort);
            $prevBySite = [];
            foreach ($this->ratioRowsBySite($prevStart, $prevEnd, $prevCohort) as $row) {
                $prevBySite[(string) $row['site']] = (float) $row['value'];
            }

            usort($currentRows, static fn (array $a, array $b): int => ((int) ($b['alert_count'] ?? 0) <=> (int) ($a['alert_count'] ?? 0)));
            $currentRows = array_slice($currentRows, 0, 20);

            $points = [];
            $xValues = [];
            $yValues = [];
            $totalCheckin = 0;
            $totalAlert = 0;

            foreach ($currentRows as $row) {
                $site = (string) ($row['site'] ?? '-');
                $checkin = (int) ($row['checkin_count'] ?? 0);
                $alert = (int) ($row['alert_count'] ?? 0);
                $ratio = (float) ($row['value'] ?? 0);
                $prevRatio = $prevBySite[$site] ?? null;
                $wow = $prevRatio !== null ? round($ratio - $prevRatio, 2) : 0.0;
                $direction = $wow > 0.01 ? 'up' : ($wow < -0.01 ? 'down' : 'flat');
                $color = match ($direction) {
                    'up' => '#ef4a00',
                    'down' => '#45b369',
                    default => '#487fff',
                };

                $points[] = [
                    'site' => $site,
                    'x' => $checkin,
                    'y' => $ratio,
                    'z' => max($alert, 1),
                    'alert_count' => $alert,
                    'checkin_count' => $checkin,
                    'ratio' => $ratio,
                    'wow' => $wow,
                    'direction' => $direction,
                    'color' => $color,
                    'arrow' => match ($direction) {
                        'up' => '↑',
                        'down' => '↓',
                        default => '→',
                    },
                ];
                $xValues[] = $checkin;
                $yValues[] = $ratio;
                $totalCheckin += $checkin;
                $totalAlert += $alert;
            }

            $overallRatio = $totalCheckin > 0 ? round($totalAlert / $totalCheckin, 2) : 0.0;
            $xMedian = $this->median($xValues);
            $yMedian = $this->median($yValues);

            foreach ($points as &$point) {
                $highX = (float) $point['x'] >= $xMedian;
                $highY = (float) $point['y'] >= $yMedian;
                $point['quadrant'] = match (true) {
                    $highX && $highY => 'q1',
                    ! $highX && $highY => 'q2',
                    $highX && ! $highY => 'q3',
                    default => 'q4',
                };
            }
            unset($point);

            return [
                'title' => 'Matriks Site',
                'subtitle' => 'Exposure vs Alert Intensity per Site',
                'mode' => 'quadrant',
                'total' => number_format($totalAlert),
                'confirmed' => number_format($totalCheckin),
                'dismissed' => number_format($overallRatio, 2),
                'x_median' => $xMedian,
                'y_median' => $yMedian,
                'overall' => [
                    'checkin' => $totalCheckin,
                    'alert' => $totalAlert,
                    'ratio' => $overallRatio,
                ],
                'quadrants' => $this->buildQuadrantGroups($points),
                'points' => $points,
                'labels' => [],
                'series' => [],
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return array<string, array<string, mixed>>
     */
    private function buildQuadrantGroups(array $points): array
    {
        $defs = [
            'q2' => [
                'key' => 'q2',
                'label' => 'Q2 – Localized High Risk',
                'description' => 'Orang relatif sedikit, tetapi alert per orang tinggi',
                'bg' => '#fff7ed',
                'border' => '#f97316',
                'text' => '#c2410c',
                'icon' => 'solar:danger-triangle-bold',
            ],
            'q1' => [
                'key' => 'q1',
                'label' => 'Q1 – Critical Exposure',
                'description' => 'Orang tinggi dan alert per orang tinggi',
                'bg' => '#fef2f2',
                'border' => '#ef4444',
                'text' => '#b91c1c',
                'icon' => 'solar:shield-warning-bold',
            ],
            'q4' => [
                'key' => 'q4',
                'label' => 'Q4 – Low Exposure, Controlled',
                'description' => 'Orang rendah dan alert per orang rendah',
                'bg' => '#ecfdf5',
                'border' => '#22c55e',
                'text' => '#15803d',
                'icon' => 'solar:shield-check-bold',
            ],
            'q3' => [
                'key' => 'q3',
                'label' => 'Q3 – High Exposure, Controlled',
                'description' => 'Exposure tinggi namun intensity terkendali',
                'bg' => '#eff6ff',
                'border' => '#3b82f6',
                'text' => '#1d4ed8',
                'icon' => 'solar:shield-check-bold',
            ],
        ];

        $groups = [];
        foreach ($defs as $key => $def) {
            $groups[$key] = $def + ['sites' => []];
        }

        foreach ($points as $point) {
            $key = (string) ($point['quadrant'] ?? 'q4');
            if (! isset($groups[$key])) {
                $key = 'q4';
            }
            $groups[$key]['sites'][] = [
                'site' => (string) ($point['site'] ?? '-'),
                'checkin_count' => (int) ($point['checkin_count'] ?? 0),
                'alert_count' => (int) ($point['alert_count'] ?? 0),
                'ratio' => (float) ($point['ratio'] ?? 0),
                'wow' => (float) ($point['wow'] ?? 0),
                'arrow' => (string) ($point['arrow'] ?? '→'),
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyQuadrantPayload(): array
    {
        return [
            'title' => 'Matriks Site',
            'subtitle' => 'Exposure vs Alert Intensity per Site',
            'mode' => 'quadrant',
            'total' => '0',
            'confirmed' => '0',
            'dismissed' => '0.00',
            'x_median' => 0.0,
            'y_median' => 0.0,
            'overall' => ['checkin' => 0, 'alert' => 0, 'ratio' => 0.0],
            'quadrants' => $this->buildQuadrantGroups([]),
            'points' => [],
            'labels' => [],
            'series' => [],
        ];
    }

    /**
     * @param  list<float|int>  $values
     */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);
        if ($count % 2 === 1) {
            return round((float) $values[$mid], 2);
        }

        return round(((float) $values[$mid - 1] + (float) $values[$mid]) / 2, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function errorPayload(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'rows' => [],
            'columns' => [],
            'summary' => [],
            'breadcrumb' => [['label' => 'Semua Site', 'level' => 'sites']],
            'pagination' => null,
        ];
    }
}