<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedSidAutomationStatus;
use App\Models\ScrDailyBanned;
use App\Models\SidBannedLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use App\Support\AutoBanned\ScrDailyBannedColumns;

class AutoBannedDailyDashboardService
{
    private const SCR_TABLE = ScrDailyBannedColumns::TABLE;

    private const LOG_TABLE = 'sid_banned_log';

    /**
     * @return array{filter_date: string, site: string, perusahaan: string, automation_status: string, q: string}
     */
    public function resolveFilters(Request $request): array
    {
        return [
            'filter_date' => trim((string) $request->query('filter_date', '')),
            'site' => trim((string) $request->query('site', '')),
            'perusahaan' => trim((string) $request->query('perusahaan', '')),
            'automation_status' => trim((string) $request->query('automation_status', '')),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    public function scrTableAvailable(): bool
    {
        return Schema::hasTable(self::SCR_TABLE);
    }

    public function logTableAvailable(): bool
    {
        return Schema::hasTable(self::LOG_TABLE);
    }

    /**
     * @param  array{filter_date?: string, site?: string, perusahaan?: string, automation_status?: string, q?: string}  $filters
     * @return array{
     *     filters: array{filter_date: string, site: string, perusahaan: string, automation_status: string, q: string},
     *     period: array{filter_date: string, scraped_at: ?string},
     *     filterOptions: array{dates: Collection, sites: Collection, perusahaan: Collection, automationStatuses: Collection},
     *     stats: array<string, int|float>,
     *     bannedRows: Collection,
     *     logRows: Collection,
     *     scrTableAvailable: bool,
     *     logTableAvailable: bool
     * }
     */
    public function buildDashboard(array $filters): array
    {
        $scrAvailable = $this->scrTableAvailable();
        $logAvailable = $this->logTableAvailable();
        $period = $this->resolvePeriod($filters, $scrAvailable, $logAvailable);
        $resolvedFilters = array_merge($filters, [
            'filter_date' => $period['filter_date'],
        ]);

        $filterOptions = $scrAvailable
            ? $this->filterOptions($resolvedFilters)
            : $this->filterOptionsFromLog($resolvedFilters, $logAvailable);

        $bannedRows = $scrAvailable
            ? $this->bannedRows($resolvedFilters, $logAvailable)
            : collect();

        $logRows = $logAvailable
            ? $this->logRows($resolvedFilters)
            : collect();

        $stats = $this->buildStats($resolvedFilters, $scrAvailable, $logAvailable);
        $chartData = $this->buildChartData($resolvedFilters, $scrAvailable, $logAvailable, $stats);

        return [
            'filters' => $resolvedFilters,
            'period' => $period,
            'filterOptions' => $filterOptions,
            'stats' => $stats,
            'chartData' => $chartData,
            'bannedRows' => $bannedRows,
            'logRows' => $logRows,
            'scrTableAvailable' => $scrAvailable,
            'logTableAvailable' => $logAvailable,
        ];
    }

    /**
     * @param  array{filter_date?: string}  $filters
     * @return array{filter_date: string, scraped_at: ?string}
     */
    private function resolvePeriod(array $filters, bool $scrAvailable, bool $logAvailable): array
    {
        $requestedDate = $filters['filter_date'] ?? '';

        if ($requestedDate !== '') {
            $scrapedAt = null;
            if ($scrAvailable) {
                $latestScrape = ScrDailyBanned::query()
                    ->whereDate('filter_date', $requestedDate)
                    ->max('scraped_at');
                $scrapedAt = $latestScrape ? (string) $latestScrape : null;
            }

            return [
                'filter_date' => $requestedDate,
                'scraped_at' => $scrapedAt,
            ];
        }

        if ($scrAvailable) {
            $latest = ScrDailyBanned::query()
                ->select(['filter_date', 'scraped_at'])
                ->orderByDesc('filter_date')
                ->orderByDesc('scraped_at')
                ->first();

            return [
                'filter_date' => $latest?->filter_date?->toDateString() ?? '',
                'scraped_at' => $latest?->scraped_at?->toDateTimeString(),
            ];
        }

        if ($logAvailable) {
            $latestLogDate = SidBannedLog::query()
                ->whereNotNull('filter_date')
                ->max('filter_date');

            return [
                'filter_date' => $latestLogDate
                    ? Carbon::parse((string) $latestLogDate)->toDateString()
                    : '',
                'scraped_at' => null,
            ];
        }

        return [
            'filter_date' => '',
            'scraped_at' => null,
        ];
    }

    /**
     * @param  array{filter_date: string, site?: string, perusahaan?: string}  $filters
     * @return array{dates: Collection, sites: Collection, perusahaan: Collection, automationStatuses: Collection}
     */
    private function filterOptions(array $filters): array
    {
        $baseQuery = ScrDailyBanned::query();

        if (($filters['filter_date'] ?? '') !== '') {
            $baseQuery->whereDate('filter_date', $filters['filter_date']);
        }

        $dates = ScrDailyBanned::query()
            ->select('filter_date')
            ->distinct()
            ->orderByDesc('filter_date')
            ->pluck('filter_date')
            ->map(fn ($date) => $date instanceof Carbon ? $date->toDateString() : (string) $date)
            ->values();

        $sites = (clone $baseQuery)
            ->whereNotNull(ScrDailyBannedColumns::SITE)
            ->where(ScrDailyBannedColumns::SITE, '!=', '')
            ->select(ScrDailyBannedColumns::SITE)
            ->distinct()
            ->orderBy(ScrDailyBannedColumns::SITE)
            ->pluck(ScrDailyBannedColumns::SITE)
            ->values();

        $perusahaan = (clone $baseQuery)
            ->whereNotNull(ScrDailyBannedColumns::PERUSAHAAN)
            ->where(ScrDailyBannedColumns::PERUSAHAAN, '!=', '')
            ->select(ScrDailyBannedColumns::PERUSAHAAN)
            ->distinct()
            ->orderBy(ScrDailyBannedColumns::PERUSAHAAN)
            ->pluck(ScrDailyBannedColumns::PERUSAHAAN)
            ->values();

        return [
            'dates' => $dates,
            'sites' => $sites,
            'perusahaan' => $perusahaan,
            'automationStatuses' => $this->automationStatusOptions(),
        ];
    }

    /**
     * @param  array{filter_date?: string}  $filters
     * @return array{dates: Collection, sites: Collection, perusahaan: Collection, automationStatuses: Collection}
     */
    private function filterOptionsFromLog(array $filters, bool $logAvailable): array
    {
        if (! $logAvailable) {
            return [
                'dates' => collect(),
                'sites' => collect(),
                'perusahaan' => collect(),
                'automationStatuses' => $this->automationStatusOptions(),
            ];
        }

        $baseQuery = SidBannedLog::query();

        if (($filters['filter_date'] ?? '') !== '') {
            $baseQuery->whereDate('filter_date', $filters['filter_date']);
        }

        $dates = SidBannedLog::query()
            ->select('filter_date')
            ->whereNotNull('filter_date')
            ->distinct()
            ->orderByDesc('filter_date')
            ->pluck('filter_date')
            ->map(fn ($date) => $date instanceof Carbon ? $date->toDateString() : (string) $date)
            ->values();

        $sites = (clone $baseQuery)
            ->whereNotNull('site_dedicated')
            ->where('site_dedicated', '!=', '')
            ->select('site_dedicated')
            ->distinct()
            ->orderBy('site_dedicated')
            ->pluck('site_dedicated')
            ->values();

        $perusahaan = (clone $baseQuery)
            ->whereNotNull('perusahaan')
            ->where('perusahaan', '!=', '')
            ->select('perusahaan')
            ->distinct()
            ->orderBy('perusahaan')
            ->pluck('perusahaan')
            ->values();

        return [
            'dates' => $dates,
            'sites' => $sites,
            'perusahaan' => $perusahaan,
            'automationStatuses' => $this->automationStatusOptions(),
        ];
    }

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    private function automationStatusOptions(): Collection
    {
        return collect(AutoBannedSidAutomationStatus::cases())
            ->map(fn (AutoBannedSidAutomationStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]);
    }

    /**
     * @param  array{filter_date: string, site?: string, perusahaan?: string, automation_status?: string, q?: string}  $filters
     */
    private function applyScrFilters(Builder $query, array $filters): Builder
    {
        if (($filters['filter_date'] ?? '') !== '') {
            $query->whereDate('filter_date', $filters['filter_date']);
        }

        if (($filters['site'] ?? '') !== '') {
            $query->where(ScrDailyBannedColumns::SITE, $filters['site']);
        }

        if (($filters['perusahaan'] ?? '') !== '') {
            $query->where(ScrDailyBannedColumns::PERUSAHAAN, $filters['perusahaan']);
        }

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where(ScrDailyBannedColumns::NIK, 'like', $term)
                    ->orWhere(ScrDailyBannedColumns::NAMA, 'like', $term)
                    ->orWhere(ScrDailyBannedColumns::SID, 'like', $term)
                    ->orWhere(ScrDailyBannedColumns::BANNED_REASON, 'like', $term)
                    ->orWhere(ScrDailyBannedColumns::BANNED_STATUS, 'like', $term);
            });
        }

        return $query;
    }

    /**
     * @param  array{filter_date: string, site?: string, perusahaan?: string, automation_status?: string, q?: string}  $filters
     */
    private function applyLogFilters(Builder $query, array $filters, bool $includeAutomationStatus = true): Builder
    {
        if (($filters['filter_date'] ?? '') !== '') {
            $query->whereDate('filter_date', $filters['filter_date']);
        }

        if (($filters['site'] ?? '') !== '') {
            $site = $filters['site'];
            $query->where(function (Builder $inner) use ($site): void {
                $inner->where('site_dedicated', $site);

                if ($this->scrTableAvailable()) {
                    $inner->orWhereHas(
                        'scrDailyBanned',
                        fn (Builder $scr) => $scr->where(ScrDailyBannedColumns::SITE, $site),
                    );
                }
            });
        }

        if (($filters['perusahaan'] ?? '') !== '') {
            $query->where('perusahaan', $filters['perusahaan']);
        }

        if ($includeAutomationStatus && ($filters['automation_status'] ?? '') !== '') {
            $query->where('automation_status', $filters['automation_status']);
        }

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('nik', 'like', $term)
                    ->orWhere('nama', 'like', $term)
                    ->orWhere('sid', 'like', $term)
                    ->orWhere('banned_reason', 'like', $term)
                    ->orWhere('error_message', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * @param  array{filter_date: string, site?: string, perusahaan?: string, q?: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function bannedRows(array $filters, bool $logAvailable): Collection
    {
        $query = ScrDailyBanned::query()
            ->select([
                'id',
                'scraped_at',
                'filter_date',
                ScrDailyBannedColumns::NIK,
                ScrDailyBannedColumns::NAMA,
                ScrDailyBannedColumns::SID,
                ScrDailyBannedColumns::PERUSAHAAN,
                ScrDailyBannedColumns::SITE,
                ScrDailyBannedColumns::BANNED_REASON,
                ScrDailyBannedColumns::BANNED_STATUS,
                ScrDailyBannedColumns::ONSITE_STATUS,
                ScrDailyBannedColumns::HZR,
                ScrDailyBannedColumns::INS,
                ScrDailyBannedColumns::OBS_OAK,
                ScrDailyBannedColumns::RFID,
                ScrDailyBannedColumns::SAP_LABEL,
            ]);

        $this->applyScrFilters($query, $filters);

        if ($logAvailable) {
            $query->with(['bannedLog:id,scr_daily_banned_id,automation_status,automation_step,completed_at']);
        }

        return $query
            ->orderBy(ScrDailyBannedColumns::NAMA)
            ->get()
            ->map(function (ScrDailyBanned $row) use ($logAvailable): array {
                $log = $logAvailable ? $row->bannedLog : null;

                return [
                    'id' => $row->id,
                    'filterDate' => $row->filter_date?->format('d M Y'),
                    'scrapedAt' => $row->scraped_at?->format('d M Y H:i'),
                    'nik' => (string) ($row->{ScrDailyBannedColumns::NIK} ?? ''),
                    'nama' => (string) ($row->{ScrDailyBannedColumns::NAMA} ?? ''),
                    'sid' => (string) ($row->{ScrDailyBannedColumns::SID} ?? ''),
                    'perusahaan' => (string) ($row->{ScrDailyBannedColumns::PERUSAHAAN} ?? ''),
                    'site' => (string) ($row->{ScrDailyBannedColumns::SITE} ?? ''),
                    'bannedReason' => (string) ($row->{ScrDailyBannedColumns::BANNED_REASON} ?? ''),
                    'bannedStatus' => (string) ($row->{ScrDailyBannedColumns::BANNED_STATUS} ?? ''),
                    'onsiteStatus' => (string) ($row->{ScrDailyBannedColumns::ONSITE_STATUS} ?? ''),
                    'hzr' => (string) ($row->{ScrDailyBannedColumns::HZR} ?? ''),
                    'ins' => (string) ($row->{ScrDailyBannedColumns::INS} ?? ''),
                    'obsOak' => (string) ($row->{ScrDailyBannedColumns::OBS_OAK} ?? ''),
                    'rfid' => (string) ($row->{ScrDailyBannedColumns::RFID} ?? ''),
                    'sapLabel' => (string) ($row->{ScrDailyBannedColumns::SAP_LABEL} ?? ''),
                    'isProcessed' => $log !== null,
                    'automationStatus' => $log?->automation_status,
                    'automationStep' => (string) ($log?->automation_step ?? ''),
                    'processedAt' => $log?->completed_at?->format('d M Y H:i'),
                ];
            });
    }

    /**
     * @param  array{filter_date: string, site?: string, perusahaan?: string, automation_status?: string, q?: string}  $filters
     * @return Collection<int, SidBannedLog>
     */
    private function logRows(array $filters): Collection
    {
        $query = SidBannedLog::query()
            ->select([
                'id',
                'scr_daily_banned_id',
                'filter_date',
                'nik',
                'sid',
                'nama',
                'perusahaan',
                'site_dedicated',
                'banned_status',
                'banned_reason',
                'status_onsite',
                'automation_status',
                'automation_step',
                'work_permit_kategori',
                'work_permit_jenis',
                'started_at',
                'completed_at',
                'error_message',
                'screenshot_work_permit',
                'screenshot_karyawan_saved',
                'created_at',
            ]);

        $this->applyLogFilters($query, $filters);

        if ($this->scrTableAvailable()) {
            $query->with([
                'scrDailyBanned:id,'.ScrDailyBannedColumns::SITE,
            ]);
        }

        return $query
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array{filter_date: string, site?: string, perusahaan?: string}  $filters
     * @return array<string, int|float>
     */
    private function buildStats(array $filters, bool $scrAvailable, bool $logAvailable): array
    {
        $stats = [
            'totalToBan' => 0,
            'totalBannedToday' => 0,
            'processed' => 0,
            'notProcessed' => 0,
            'pending' => 0,
            'processing' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'progressPct' => 0.0,
        ];

        if ($scrAvailable) {
            $scrQuery = ScrDailyBanned::query();
            $this->applyScrFilters($scrQuery, $filters);
            $stats['totalToBan'] = (int) $scrQuery->count();
        }

        if ($logAvailable) {
            $logQuery = SidBannedLog::query();
            $this->applyLogFilters($logQuery, $filters);
            $stats['processed'] = (int) (clone $logQuery)->count();
            $stats['totalBannedToday'] = $this->countBannedTodayFromLog($filters);

            foreach (AutoBannedSidAutomationStatus::cases() as $status) {
                $key = strtolower($status->value);
                if (array_key_exists($key, $stats)) {
                    $stats[$key] = (int) (clone $logQuery)
                        ->where('automation_status', $status->value)
                        ->count();
                }
            }
        }

        $stats['notProcessed'] = max(0, $stats['totalToBan'] - $stats['processed']);
        $stats['successRate'] = $stats['processed'] > 0
            ? round(($stats['success'] / $stats['processed']) * 100, 1)
            : 0.0;
        $stats['progressPct'] = $stats['totalToBan'] > 0
            ? round(($stats['success'] / $stats['totalToBan']) * 100, 1)
            : 0.0;

        return $stats;
    }

    /**
     * Total karyawan yang sudah di-banned (automasi SUCCESS) — kumulatif, tanpa filter tanggal.
     *
     * @param  array{site?: string, perusahaan?: string, q?: string}  $filters
     */
    public function countBannedSuccessTotal(array $filters = []): int
    {
        if (! $this->logTableAvailable()) {
            return 0;
        }

        $query = SidBannedLog::query()
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value);

        $this->applyLogFilters($query, array_merge([
            'filter_date' => '',
            'automation_status' => '',
        ], $filters), includeAutomationStatus: false);

        return (int) $query->count();
    }

    /**
     * Total karyawan yang sudah di-banned (automasi SUCCESS) dari sid_banned_log.
     *
     * @param  array{filter_date?: string, site?: string, perusahaan?: string, q?: string}  $filters
     */
    private function countBannedTodayFromLog(array $filters): int
    {
        $query = SidBannedLog::query()
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value);

        $this->applyLogFilters($query, $filters, includeAutomationStatus: false);

        return (int) $query->count();
    }

    /**
     * @param  array{filter_date: string, site?: string, perusahaan?: string}  $filters
     * @param  array<string, int|float>  $stats
     * @return array<string, mixed>
     */
    private function buildChartData(array $filters, bool $scrAvailable, bool $logAvailable, array $stats): array
    {
        $empty = [
            'bySite' => ['labels' => [], 'total' => [], 'success' => []],
            'byBannedStatus' => ['labels' => [], 'values' => []],
            'byOnsiteStatus' => ['labels' => [], 'values' => []],
            'byAutomationStatus' => ['labels' => [], 'values' => []],
            'topReasons' => ['labels' => [], 'values' => []],
            'topPerusahaan' => ['labels' => [], 'values' => []],
            'dailyTrend' => ['labels' => [], 'total' => [], 'success' => [], 'failed' => []],
            'processingSplit' => ['labels' => ['Sudah Diproses', 'Belum Diproses'], 'values' => [0, 0]],
            'avgAutomationMinutes' => null,
            'onsiteCount' => 0,
            'offsiteCount' => 0,
        ];

        if (! $scrAvailable && ! $logAvailable) {
            return $empty;
        }

        if ($scrAvailable) {
            $empty['bySite'] = $this->chartBySite($filters, $logAvailable);
            $empty['byBannedStatus'] = $this->chartGroupedScr($filters, ScrDailyBannedColumns::BANNED_STATUS, 8);
            $empty['byOnsiteStatus'] = $this->chartGroupedScr($filters, ScrDailyBannedColumns::ONSITE_STATUS, 6);
            $empty['topReasons'] = $this->chartGroupedScr($filters, ScrDailyBannedColumns::BANNED_REASON, 6);
            $empty['topPerusahaan'] = $this->chartGroupedScr($filters, ScrDailyBannedColumns::PERUSAHAAN, 8);
            $empty['dailyTrend'] = $this->chartDailyTrend($filters, $logAvailable);

            $onsiteQuery = ScrDailyBanned::query();
            $this->applyScrFilters($onsiteQuery, $filters);
            $empty['onsiteCount'] = (int) (clone $onsiteQuery)
                ->where(ScrDailyBannedColumns::ONSITE_STATUS, 'ONSITE')
                ->count();
            $empty['offsiteCount'] = max(0, (int) $stats['totalToBan'] - $empty['onsiteCount']);
        }

        if ($logAvailable) {
            $empty['byAutomationStatus'] = $this->chartAutomationStatus($filters);
            $empty['processingSplit'] = [
                'labels' => ['Sudah Diproses', 'Belum Diproses'],
                'values' => [(int) $stats['processed'], (int) $stats['notProcessed']],
            ];

            $durationQuery = SidBannedLog::query()
                ->whereNotNull('started_at')
                ->whereNotNull('completed_at')
                ->where('automation_status', AutoBannedSidAutomationStatus::Success->value);
            $this->applyLogFilters($durationQuery, $filters);

            $avgSeconds = $durationQuery
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_seconds')
                ->value('avg_seconds');

            $empty['avgAutomationMinutes'] = $avgSeconds !== null
                ? round((float) $avgSeconds / 60, 1)
                : null;
        } elseif ($scrAvailable) {
            $empty['processingSplit'] = [
                'labels' => ['Sudah Diproses', 'Belum Diproses'],
                'values' => [(int) $stats['processed'], (int) $stats['notProcessed']],
            ];
        }

        return $empty;
    }

    /**
     * @param  array{filter_date: string, site?: string, perusahaan?: string, q?: string}  $filters
     * @return array{labels: array<int, string>, total: array<int, int>, success: array<int, int>}
     */
    private function chartBySite(array $filters, bool $logAvailable): array
    {
        $siteColumn = ScrDailyBannedColumns::SITE;
        $scrQuery = ScrDailyBanned::query()
            ->selectRaw($siteColumn.' as label, COUNT(*) as total')
            ->whereNotNull($siteColumn)
            ->where($siteColumn, '!=', '');
        $this->applyScrFilters($scrQuery, $filters);

        $rows = $scrQuery
            ->groupBy($siteColumn)
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $successMap = [];
        if ($logAvailable) {
            $logQuery = SidBannedLog::query()
                ->selectRaw('site_dedicated as label, COUNT(*) as total')
                ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
                ->whereNotNull('site_dedicated')
                ->where('site_dedicated', '!=', '');
            $this->applyLogFilters($logQuery, $filters);
            $successMap = $logQuery
                ->groupBy('site_dedicated')
                ->pluck('total', 'label')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        return [
            'labels' => $rows->pluck('label')->map(fn ($v) => (string) $v)->all(),
            'total' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
            'success' => $rows->pluck('label')->map(fn ($site) => (int) ($successMap[$site] ?? 0))->all(),
        ];
    }

    /**
     * @param  array{filter_date: string, site?: string, perusahaan?: string, q?: string}  $filters
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function chartGroupedScr(array $filters, string $column, int $limit): array
    {
        $query = ScrDailyBanned::query()
            ->selectRaw($column.' as label, COUNT(*) as total')
            ->whereNotNull($column)
            ->where($column, '!=', '');
        $this->applyScrFilters($query, $filters);

        $rows = $query
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return [
            'labels' => $rows->pluck('label')->map(fn ($v) => \Illuminate\Support\Str::limit((string) $v, 40))->all(),
            'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * @param  array{filter_date: string, site?: string, perusahaan?: string, automation_status?: string, q?: string}  $filters
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function chartAutomationStatus(array $filters): array
    {
        $labels = [];
        $values = [];

        foreach (AutoBannedSidAutomationStatus::cases() as $status) {
            $query = SidBannedLog::query();
            $this->applyLogFilters($query, $filters);
            $count = (int) $query->where('automation_status', $status->value)->count();
            if ($count > 0) {
                $labels[] = $status->label();
                $values[] = $count;
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param  array{filter_date: string, site?: string, perusahaan?: string, q?: string}  $filters
     * @return array{labels: array<int, string>, total: array<int, int>, success: array<int, int>, failed: array<int, int>}
     */
    private function chartDailyTrend(array $filters, bool $logAvailable): array
    {
        $scrQuery = ScrDailyBanned::query()
            ->selectRaw('filter_date, COUNT(*) as total')
            ->groupBy('filter_date')
            ->orderByDesc('filter_date')
            ->limit(14);

        if (($filters['site'] ?? '') !== '') {
            $scrQuery->where(ScrDailyBannedColumns::SITE, $filters['site']);
        }
        if (($filters['perusahaan'] ?? '') !== '') {
            $scrQuery->where(ScrDailyBannedColumns::PERUSAHAAN, $filters['perusahaan']);
        }

        $rows = $scrQuery->get()->sortBy('filter_date')->values();
        $dates = $rows->pluck('filter_date')->map(function ($date) {
            $parsed = $date instanceof Carbon ? $date : Carbon::parse((string) $date);

            return $parsed->format('d M');
        })->all();

        $totalMap = $rows->mapWithKeys(function ($row) {
            $key = $row->filter_date instanceof Carbon
                ? $row->filter_date->toDateString()
                : Carbon::parse((string) $row->filter_date)->toDateString();

            return [$key => (int) $row->total];
        });

        $successMap = collect();
        $failedMap = collect();

        if ($logAvailable) {
            $logQuery = SidBannedLog::query()
                ->selectRaw('filter_date, automation_status, COUNT(*) as total')
                ->groupBy('filter_date', 'automation_status');

            if (($filters['site'] ?? '') !== '') {
                $logQuery->where('site_dedicated', $filters['site']);
            }
            if (($filters['perusahaan'] ?? '') !== '') {
                $logQuery->where('perusahaan', $filters['perusahaan']);
            }

            foreach ($logQuery->get() as $logRow) {
                $key = $logRow->filter_date instanceof Carbon
                    ? $logRow->filter_date->toDateString()
                    : Carbon::parse((string) $logRow->filter_date)->toDateString();

                if ($logRow->automation_status === AutoBannedSidAutomationStatus::Success->value) {
                    $successMap[$key] = (int) $logRow->total;
                }
                if ($logRow->automation_status === AutoBannedSidAutomationStatus::Failed->value) {
                    $failedMap[$key] = (int) $logRow->total;
                }
            }
        }

        $dateKeys = $rows->pluck('filter_date')->map(function ($date) {
            return $date instanceof Carbon ? $date->toDateString() : Carbon::parse((string) $date)->toDateString();
        });

        return [
            'labels' => $dates,
            'total' => $dateKeys->map(fn ($key) => (int) ($totalMap[$key] ?? 0))->all(),
            'success' => $dateKeys->map(fn ($key) => (int) ($successMap[$key] ?? 0))->all(),
            'failed' => $dateKeys->map(fn ($key) => (int) ($failedMap[$key] ?? 0))->all(),
        ];
    }
}
