<?php

declare(strict_types=1);

namespace App\Services\DmsMonitoring;

use App\Services\PraOperasi\PraOperasiOperatorRosterReader;
use App\Services\SportEvaluation\SportEvaluationKaryawanWellSiteResolver;
use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class DmsMonitoringOverallPeopleModalService
{
    private const PER_PAGE = 25;

    private const CACHE_TTL = 300;

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
    public function payload(array $filters, int $page = 1, string $status = 'with_alert'): array
    {
        $this->reader->applyScope(
            $filters['site'] !== '' ? $filters['site'] : null,
            $filters['perusahaan'] !== '' ? $filters['perusahaan'] : null,
        );

        if (! $this->reader->isUp() || ! $this->rfidReader->isUp()) {
            return $this->errorPayload('Koneksi ke sumber data operator tidak tersedia.');
        }

        $cacheKey = 'dms_monitoring:overall_people.payload.v3:'.md5(json_encode([
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

            return $this->errorPayload('Gagal memuat overview orang & alert.');
        }
    }

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    public function personAlertDetails(array $filters, string $sid): array
    {
        $this->reader->applyScope(
            $filters['site'] !== '' ? $filters['site'] : null,
            $filters['perusahaan'] !== '' ? $filters['perusahaan'] : null,
        );

        try {
            $tz = (string) config('app.timezone');
            $start = Carbon::parse($filters['start'], $tz)->startOfDay()->format('Y-m-d H:i:s');
            $end = Carbon::parse($filters['end'], $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');

            return [
                'ok' => true,
                'sid' => $sid,
                'alerts' => $this->reader->operatorAlertDetails($start, $end, $sid),
            ];
        } catch (Throwable $e) {
            report($e);

            return $this->errorPayload('Gagal memuat detail alert orang.');
        }
    }

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    public function dayPayload(array $filters, string $day, string $status = 'without_alert', int $page = 1): array
    {
        $this->reader->applyScope(
            $filters['site'] !== '' ? $filters['site'] : null,
            $filters['perusahaan'] !== '' ? $filters['perusahaan'] : null,
        );

        if (! $this->reader->isUp()) {
            return $this->errorPayload('Koneksi ke hse_automation tidak tersedia.');
        }

        if ($day < $filters['start'] || $day > $filters['end']) {
            return $this->errorPayload('Tanggal detail di luar range filter.');
        }

        $cacheKey = 'dms_monitoring:overall_people.day.v2:'.md5(json_encode([
            $filters,
            $day,
            $status,
            $page,
            $this->reader->scopeCacheSuffixForKey(),
        ]));

        try {
            /** @var array<string, mixed> */
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters, $day, $status, $page): array {
                $tz = (string) config('app.timezone');
                $dayStart = Carbon::parse($day, $tz)->startOfDay()->format('Y-m-d H:i:s');
                $dayEnd = Carbon::parse($day, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');
                $table = $this->reader->operatorCheckinPeopleForDay($dayStart, $dayEnd, $status, $page, self::PER_PAGE);
                $statusLabel = match ($status) {
                    'with_alert' => 'dengan alert',
                    'all' => 'check-in',
                    default => 'tanpa alert',
                };

                return [
                    'ok' => true,
                    'day' => $day,
                    'status' => $status,
                    'label' => 'Orang '.$statusLabel.' pada '.Carbon::parse($day, $tz)->translatedFormat('d M Y'),
                    'table' => ['rows' => $table['rows']],
                    'pagination' => [
                        'page' => $page,
                        'per_page' => self::PER_PAGE,
                        'total_rows' => (int) ($table['total'] ?? 0),
                        'total_pages' => max(1, (int) ceil(($table['total'] ?? 0) / self::PER_PAGE)),
                    ],
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return $this->errorPayload('Gagal memuat detail orang harian.');
        }
    }

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    private function buildPayload(array $filters, int $page, string $status): array
    {
        try {
            $tz = (string) config('app.timezone');
            $start = Carbon::parse($filters['start'], $tz)->startOfDay()->format('Y-m-d H:i:s');
            $end = Carbon::parse($filters['end'], $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');

            $breakdown = $this->reader->operatorCheckinAlertBreakdown($start, $end);
            $alertTotal = (int) ($this->reader->alertSummary($start, $end)['total'] ?? 0);
            $checkinTotal = (int) ($breakdown['checkin_total'] ?? 0);
            $withAlert = (int) ($breakdown['with_alert'] ?? 0);
            $withoutAlert = (int) ($breakdown['without_alert'] ?? 0);
            $dailyBar = $this->buildDailyPeopleChart($start, $end, $tz, $breakdown['days'] ?? []);

            $cohort = [];
            $alertMap = [];
            try {
                $cohort = $this->filterCohort(
                    $this->operatorCheckinCohort($start, $end),
                    $filters['site'],
                    $filters['perusahaan'],
                );
                $siteFilter = $filters['site'] !== '' ? $filters['site'] : null;
                $companyFilter = $filters['perusahaan'] !== '' ? $filters['perusahaan'] : null;
                $cohortSids = array_map(static fn (array $row): string => (string) ($row['kode_sid'] ?? ''), $cohort);
                $alertMap = $this->reader->alertCountForOperatorSids(
                    $start,
                    $end,
                    $cohortSids,
                    $siteFilter,
                    $companyFilter,
                );
            } catch (Throwable $e) {
                report($e);
            }

            $tableResult = $this->paginatedTableRows($cohort, $alertMap, $status, $page);
            $cohortSummary = $this->buildSummary($cohort, $alertMap);
            $controlChart = $this->buildControlChart($start, $end, $tz);
            $topPeople = $this->topPeopleWithAlerts($cohort, $alertMap, 5);
            $topSids = array_map(static fn (array $row): string => (string) $row['kode_sid'], $topPeople);

            return [
                'ok' => true,
                'label' => 'Overview Orang & Alert',
                'period' => ['start' => $filters['start'], 'end' => $filters['end']],
                'summary' => [
                    [
                        'key' => 'people_checkin',
                        'label' => 'Orang Checkin RFID',
                        'value' => number_format($checkinTotal),
                        'hint' => 'Orang-hari di range filter (sama dengan kartu KPI)',
                        'icon' => 'mingcute:user-follow-fill',
                        'color' => '#487fff',
                    ],
                    [
                        'key' => 'people_without_alert',
                        'label' => 'Orang Tanpa Alert',
                        'value' => number_format($withoutAlert),
                        'hint' => 'Orang-hari check-in tanpa alert di hari yang sama',
                        'icon' => 'solar:shield-check-bold',
                        'color' => '#45b369',
                    ],
                    [
                        'key' => 'people_with_alert',
                        'label' => 'Orang Dengan Alert',
                        'value' => number_format($withAlert),
                        'hint' => 'Orang-hari check-in yang punya alert di hari yang sama',
                        'icon' => 'solar:danger-triangle-bold',
                        'color' => '#f4941e',
                    ],
                    [
                        'key' => 'ratio',
                        'label' => 'Rasio Alert / Orang',
                        'value' => number_format($checkinTotal > 0 ? $alertTotal / $checkinTotal : 0, 2),
                        'hint' => number_format($alertTotal).' alert ÷ '.$checkinTotal.' orang-hari',
                        'icon' => 'solar:chart-2-bold',
                        'color' => '#8252e9',
                    ],
                ],
                'top_units' => array_map(static fn (array $row): array => [
                    'unit' => (string) $row['nama'],
                    'site' => (string) $row['site'],
                    'perusahaan' => (string) $row['perusahaan'],
                    'alert_count' => (int) $row['alert_count'],
                ], $topPeople),
                'top_units_chart' => $this->reader->dailyAlertsForTopOperatorSids($start, $end, $topSids),
                'control_chart' => $controlChart,
                'daily_bar' => $dailyBar,
                'table' => [
                    'rows' => $tableResult['rows'],
                ],
                'table_tabs' => [
                    ['key' => 'with_alert', 'label' => 'Orang Dengan Alert', 'count' => $cohortSummary['with_alert']],
                    ['key' => 'without_alert', 'label' => 'Orang Tanpa Alert', 'count' => $cohortSummary['without_alert']],
                ],
                'table_active' => $status,
                'pagination' => [
                    'page' => $page,
                    'per_page' => self::PER_PAGE,
                    'total_rows' => $tableResult['total'],
                    'total_pages' => max(1, (int) ceil($tableResult['total'] / self::PER_PAGE)),
                ],
            ];
        } catch (Throwable $e) {
            report($e);

            throw $e;
        }
    }

    /**
     * @return list<array{kode_sid:string,nama:string,jabatan:string,perusahaan:string,site:string,checked_in_at:string,gate:string}>
     */
    private function operatorCheckinCohort(string $start, string $end): array
    {
        $cacheKey = 'dms_monitoring:kpi.operator_cohort.fungsional:'.md5($start.'|'.$end);

        return Cache::remember($cacheKey, 900, function () use ($start, $end): array {
            $roster = $this->rosterReader->fungsionalOperatorRoster();
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
            $siteMap = $this->siteResolver->siteMap();

            if ($fromDate === $toDate) {
                return $this->buildCohortFromSingleDayCheckins(
                    $roster,
                    $this->rfidReader->firstPassedCheckinsForSids($fromDate, $sids),
                    $siteMap,
                );
            }

            $byDay = $this->rfidReader->firstPassedCheckinsByDayForSids($fromDate, $toDate, $sids);

            return $this->buildCohortFromByDayCheckins($roster, $byDay, $siteMap);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $roster
     * @param  array<string, array<string, mixed>>  $checkins
     * @param  array<string, string>  $siteMap
     * @return list<array{kode_sid:string,nama:string,jabatan:string,perusahaan:string,site:string,checked_in_at:string,gate:string}>
     */
    private function buildCohortFromSingleDayCheckins(array $roster, array $checkins, array $siteMap): array
    {
        $cohort = [];
        foreach ($roster as $operator) {
            $upper = mb_strtoupper((string) ($operator['kode_sid'] ?? ''));
            if ($upper === '' || ! isset($checkins[$upper])) {
                continue;
            }

            $best = $checkins[$upper];
            $company = trim((string) ($operator['perusahaan'] ?? ''));
            if ($company === '') {
                $company = trim((string) ($best['perusahaan'] ?? ''));
            }

            $cohort[] = [
                'kode_sid' => (string) $operator['kode_sid'],
                'nama' => trim((string) ($operator['nama'] ?? '')) !== '' ? (string) $operator['nama'] : (string) ($best['nama_karyawan'] ?? '-'),
                'jabatan' => (string) ($operator['jabatan'] ?? ''),
                'perusahaan' => $company !== '' ? $company : '-',
                'site' => $this->resolveSiteFromMap($upper, $siteMap),
                'checked_in_at' => (string) $best['checked_in_at'],
                'gate' => (string) ($best['gate'] ?? '-'),
            ];
        }

        return $cohort;
    }

    /**
     * @param  list<array<string, mixed>>  $roster
     * @param  array<string, array<string, array<string, mixed>>>  $byDay
     * @param  array<string, string>  $siteMap
     * @return list<array{kode_sid:string,nama:string,jabatan:string,perusahaan:string,site:string,checked_in_at:string,gate:string}>
     */
    private function buildCohortFromByDayCheckins(array $roster, array $byDay, array $siteMap): array
    {
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
                'nama' => trim((string) ($operator['nama'] ?? '')) !== '' ? (string) $operator['nama'] : (string) ($best['nama_karyawan'] ?? '-'),
                'jabatan' => (string) ($operator['jabatan'] ?? ''),
                'perusahaan' => $company !== '' ? $company : '-',
                'site' => $this->resolveSiteFromMap($upper, $siteMap),
                'checked_in_at' => (string) $best['checked_in_at'],
                'gate' => (string) ($best['gate'] ?? '-'),
            ];
        }

        return $cohort;
    }

    /**
     * @param  array<string, string>  $siteMap
     */
    private function resolveSiteFromMap(string $upperSid, array $siteMap): string
    {
        $site = trim($siteMap[$upperSid] ?? '');

        return $site !== '' ? $site : '-';
    }

    /**
     * @param  list<array{kode_sid:string,nama:string,jabatan:string,perusahaan:string,site:string,checked_in_at:string,gate:string}>  $cohort
     * @return list<array{kode_sid:string,nama:string,jabatan:string,perusahaan:string,site:string,checked_in_at:string,gate:string}>
     */
    private function filterCohort(array $cohort, string $site, string $perusahaan): array
    {
        return array_values(array_filter($cohort, static function (array $row) use ($site, $perusahaan): bool {
            if ($site !== '' && ($row['site'] ?? '') !== $site) {
                return false;
            }
            if ($perusahaan !== '' && ($row['perusahaan'] ?? '') !== $perusahaan) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  list<array{kode_sid:string,nama:string,jabatan:string,perusahaan:string,site:string,checked_in_at:string,gate:string}>  $cohort
     * @param  array<string,int>  $alertMap
     * @return array{checkin_total:int,with_alert:int,without_alert:int,total_alerts:int,ratio_per_person:float}
     */
    private function buildSummary(array $cohort, array $alertMap): array
    {
        $checkinTotal = count($cohort);
        $withAlert = 0;
        $totalAlerts = 0;
        foreach ($cohort as $row) {
            $sid = mb_strtoupper((string) ($row['kode_sid'] ?? ''));
            $count = (int) ($alertMap[$sid] ?? 0);
            $totalAlerts += $count;
            if ($count > 0) {
                $withAlert++;
            }
        }

        return [
            'checkin_total' => $checkinTotal,
            'with_alert' => $withAlert,
            'without_alert' => max(0, $checkinTotal - $withAlert),
            'total_alerts' => $totalAlerts,
            'ratio_per_person' => $checkinTotal > 0 ? round($totalAlerts / $checkinTotal, 2) : 0.0,
        ];
    }

    /**
     * @param  list<array{kode_sid:string,nama:string,jabatan:string,perusahaan:string,site:string,checked_in_at:string,gate:string}>  $cohort
     * @param  array<string,int>  $alertMap
     * @return list<array<string,mixed>>
     */
    private function topPeopleWithAlerts(array $cohort, array $alertMap, int $limit): array
    {
        $entries = [];
        foreach ($cohort as $row) {
            $sid = mb_strtoupper((string) ($row['kode_sid'] ?? ''));
            $alertCount = (int) ($alertMap[$sid] ?? 0);
            if ($alertCount <= 0) {
                continue;
            }
            $entries[] = $row + ['alert_count' => $alertCount];
        }

        usort($entries, static fn (array $a, array $b): int => ($b['alert_count'] <=> $a['alert_count']) ?: strcmp((string) $a['nama'], (string) $b['nama']));

        return array_slice($entries, 0, $limit);
    }

    /**
     * @param  list<array{kode_sid:string,nama:string,jabatan:string,perusahaan:string,site:string,checked_in_at:string,gate:string}>  $cohort
     * @param  array<string,int>  $alertMap
     * @return array{total:int, rows:list<array<string,mixed>>}
     */
    private function paginatedTableRows(array $cohort, array $alertMap, string $status, int $page): array
    {
        $entries = [];
        foreach ($cohort as $row) {
            $sid = mb_strtoupper((string) ($row['kode_sid'] ?? ''));
            $alertCount = (int) ($alertMap[$sid] ?? 0);
            $hasAlert = $alertCount > 0;
            if ($status === 'with_alert' && ! $hasAlert) {
                continue;
            }
            if ($status === 'without_alert' && $hasAlert) {
                continue;
            }
            $entries[] = [
                'row' => $row,
                'alert_count' => $alertCount,
                'has_alert' => $hasAlert,
            ];
        }

        usort($entries, static fn (array $a, array $b): int => ($b['alert_count'] <=> $a['alert_count']) ?: strcmp((string) $a['row']['nama'], (string) $b['row']['nama']));

        $total = count($entries);
        $offset = max(0, ($page - 1) * self::PER_PAGE);
        $slice = array_slice($entries, $offset, self::PER_PAGE);

        $rows = [];
        foreach ($slice as $entry) {
            $row = $entry['row'];
            $rows[] = [
                'kode_sid' => (string) ($row['kode_sid'] ?? '-'),
                'nama' => (string) ($row['nama'] ?? '-'),
                'jabatan' => (string) ($row['jabatan'] ?? '-'),
                'perusahaan' => (string) ($row['perusahaan'] ?? '-'),
                'site' => (string) ($row['site'] ?? '-'),
                'evidence_source' => 'RFID Check-in',
                'evidence_at' => (string) ($row['checked_in_at'] ?? '-'),
                'evidence_note' => 'Gate: '.((string) ($row['gate'] ?? '-')),
                'alert_count' => (int) $entry['alert_count'],
                'has_alert' => (bool) $entry['has_alert'],
            ];
        }

        return ['total' => $total, 'rows' => $rows];
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
        $indexed = [];
        foreach ($dailyRaw as $row) {
            $indexed[$row['hari']] = $row;
        }

        $labels = [];
        $values = [];
        $cursor = Carbon::parse($start, $tz)->startOfDay();
        $until = Carbon::parse($end, $tz)->startOfDay();
        while ($cursor->lt($until)) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->isoFormat('D MMM');
            $values[] = (int) (($indexed[$key]['total'] ?? 0));
            $cursor->addDay();
        }

        $n = count($values);
        if ($n === 0) {
            return ['labels' => [], 'series' => [], 'mean' => 0.0, 'ucl' => 0.0, 'lcl' => 0.0, 'mean_series' => [], 'ucl_series' => [], 'lcl_series' => []];
        }
        $mean = array_sum($values) / $n;
        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }
        $std = $n > 1 ? sqrt($variance / ($n - 1)) : 0.0;
        $ucl = round($mean + (3 * $std), 2);
        $lcl = round($mean - (3 * $std), 2);
        $mean = round($mean, 2);

        return [
            'labels' => $labels,
            'series' => $values,
            'mean' => $mean,
            'ucl' => $ucl,
            'lcl' => $lcl,
            'mean_series' => array_fill(0, $n, $mean),
            'ucl_series' => array_fill(0, $n, $ucl),
            'lcl_series' => array_fill(0, $n, $lcl),
        ];
    }

    /**
     * @param  list<array{hari:string, operators:int, with_alert:int, without_alert:int}>  $rows
     * @return array{
     *     labels:list<string>,
     *     iso_dates:list<string>,
     *     series:list<array{name:string, data:list<int>}>,
     *     totals:array{checkin:int, with_alert:int, without_alert:int}
     * }
     */
    private function buildDailyPeopleChart(string $start, string $end, string $tz, array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) ($row['hari'] ?? '')] = $row;
        }

        $labels = [];
        $isoDates = [];
        $checkin = [];
        $withAlert = [];
        $withoutAlert = [];
        $cursor = Carbon::parse($start, $tz)->startOfDay();
        $until = Carbon::parse($end, $tz)->startOfDay();
        while ($cursor->lt($until)) {
            $key = $cursor->toDateString();
            $hit = $indexed[$key] ?? [];
            $labels[] = $cursor->isoFormat('D MMM');
            $isoDates[] = $key;
            $checkin[] = (int) ($hit['operators'] ?? 0);
            $withAlert[] = (int) ($hit['with_alert'] ?? 0);
            $withoutAlert[] = (int) ($hit['without_alert'] ?? 0);
            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'iso_dates' => $isoDates,
            'series' => [
                ['name' => 'Orang checkin', 'data' => $checkin],
                ['name' => 'Dengan alert', 'data' => $withAlert],
                ['name' => 'Tanpa alert', 'data' => $withoutAlert],
            ],
            'totals' => [
                'checkin' => array_sum($checkin),
                'with_alert' => array_sum($withAlert),
                'without_alert' => array_sum($withoutAlert),
            ],
        ];
    }

    /**
     * @return array{ok:false,message:string}
     */
    private function errorPayload(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
