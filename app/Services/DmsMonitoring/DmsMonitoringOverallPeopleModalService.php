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

        try {
            $tz = (string) config('app.timezone');
            $start = Carbon::parse($filters['start'], $tz)->startOfDay()->format('Y-m-d H:i:s');
            $end = Carbon::parse($filters['end'], $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');

            $cohort = $this->filterCohort($this->operatorCheckinCohort($start, $end), $filters['site'], $filters['perusahaan']);
            $alertMap = $this->reader->alertCountByOperatorSid(
                $start,
                $end,
                $filters['site'] !== '' ? $filters['site'] : null,
                $filters['perusahaan'] !== '' ? $filters['perusahaan'] : null,
            );
            $summary = $this->buildSummary($cohort, $alertMap);
            $rows = $this->tableRows($cohort, $alertMap, $status);
            $paged = array_slice($rows, max(0, ($page - 1) * self::PER_PAGE), self::PER_PAGE);
            $controlChart = $this->buildControlChart($start, $end, $tz);

            $topPeople = array_slice(array_values(array_filter($rows, static fn (array $row): bool => (int) $row['alert_count'] > 0)), 0, 5);
            $topSids = array_map(static fn (array $row): string => (string) $row['kode_sid'], $topPeople);

            return [
                'ok' => true,
                'label' => 'Overview Orang & Alert',
                'period' => ['start' => $filters['start'], 'end' => $filters['end']],
                'summary' => [
                    ['key' => 'people_checkin', 'label' => 'Orang Checkin RFID', 'value' => number_format($summary['checkin_total']), 'hint' => 'Operator unik yang lolos check-in', 'icon' => 'mingcute:user-follow-fill', 'color' => '#487fff'],
                    ['key' => 'people_without_alert', 'label' => 'Orang Tanpa Alert', 'value' => number_format($summary['without_alert']), 'hint' => 'Sudah check-in namun tanpa alert DMS', 'icon' => 'solar:shield-check-bold', 'color' => '#45b369'],
                    ['key' => 'people_with_alert', 'label' => 'Orang Dengan Alert', 'value' => number_format($summary['with_alert']), 'hint' => 'Operator check-in yang punya alert', 'icon' => 'solar:danger-triangle-bold', 'color' => '#f4941e'],
                    ['key' => 'ratio', 'label' => 'Rasio Alert / Orang', 'value' => number_format($summary['ratio_per_person'], 2), 'hint' => number_format($summary['total_alerts']).' total alert', 'icon' => 'solar:chart-2-bold', 'color' => '#8252e9'],
                ],
                'top_units' => array_map(static fn (array $row): array => [
                    'unit' => (string) $row['nama'],
                    'site' => (string) $row['site'],
                    'perusahaan' => (string) $row['perusahaan'],
                    'alert_count' => (int) $row['alert_count'],
                ], $topPeople),
                'top_units_chart' => $this->reader->dailyAlertsForTopOperatorSids($start, $end, $topSids),
                'control_chart' => $controlChart,
                'table' => [
                    'rows' => $paged,
                ],
                'table_tabs' => [
                    ['key' => 'with_alert', 'label' => 'Orang Dengan Alert', 'count' => $summary['with_alert']],
                    ['key' => 'without_alert', 'label' => 'Orang Tanpa Alert', 'count' => $summary['without_alert']],
                ],
                'table_active' => $status,
                'pagination' => [
                    'page' => $page,
                    'per_page' => self::PER_PAGE,
                    'total_rows' => count($rows),
                    'total_pages' => max(1, (int) ceil(count($rows) / self::PER_PAGE)),
                ],
            ];
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
     * @return list<array{kode_sid:string,nama:string,jabatan:string,perusahaan:string,site:string,checked_in_at:string,gate:string}>
     */
    private function operatorCheckinCohort(string $start, string $end): array
    {
        $cacheKey = 'dms_monitoring:overall_people.operator_cohort:'.md5($start.'|'.$end);

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
                    'nama' => trim((string) ($operator['nama'] ?? '')) !== '' ? (string) $operator['nama'] : (string) ($best['nama_karyawan'] ?? '-'),
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
    private function tableRows(array $cohort, array $alertMap, string $status): array
    {
        $rows = [];
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

            $rows[] = [
                'kode_sid' => (string) ($row['kode_sid'] ?? '-'),
                'nama' => (string) ($row['nama'] ?? '-'),
                'jabatan' => (string) ($row['jabatan'] ?? '-'),
                'perusahaan' => (string) ($row['perusahaan'] ?? '-'),
                'site' => (string) ($row['site'] ?? '-'),
                'evidence_source' => 'RFID Check-in',
                'evidence_at' => (string) ($row['checked_in_at'] ?? '-'),
                'evidence_note' => 'Gate: '.((string) ($row['gate'] ?? '-')),
                'alert_count' => $alertCount,
                'has_alert' => $hasAlert,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['alert_count'] <=> $a['alert_count']) ?: strcmp((string) $a['nama'], (string) $b['nama']));

        return $rows;
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
     * @return array{ok:false,message:string}
     */
    private function errorPayload(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
