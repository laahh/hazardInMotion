<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedReconcileGapType;
use App\Enums\AutoBannedSidAutomationStatus;
use App\Enums\AutoBannedUnbanStatus;
use App\Models\AutoBannedUnbanRequest;
use App\Models\SidBannedLog;
use App\Models\SidBannedLogWeekly;
use App\Models\SidUnbanLog;
use App\Support\AutoBanned\AutoBannedSchema;
use App\Support\AutoBanned\AutoBannedSiteOptions;
use App\Support\AutoBanned\ScrDailyBannedColumns;
use App\Support\AutoBanned\ScrWeeklyBannedColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AutoBannedPipelineGapService
{
    public const DEFAULT_MIN_DAYS_OLD = 3;

    public function __construct(
        private readonly AutoBannedBannedChainService $chainService,
    ) {}

    /**
     * @return array{
     *     gap_type: string,
     *     min_days_old: int,
     *     site: string,
     *     sid: string,
     *     q: string
     * }
     */
    public function resolveFilters(Request $request): array
    {
        $gapType = AutoBannedReconcileGapType::tryFrom(trim((string) $request->query('gap_type', '')))
            ?? AutoBannedReconcileGapType::NoRequest;

        $defaultMinDaysOld = $gapType->defaultMinDaysOld();
        $minDaysOld = $request->query->has('min_days_old')
            ? (int) $request->query('min_days_old')
            : $defaultMinDaysOld;

        if ($minDaysOld < 0) {
            $minDaysOld = $defaultMinDaysOld;
        }

        return [
            'gap_type' => $gapType->value,
            'min_days_old' => $minDaysOld,
            'site' => trim((string) $request->query('site', '')),
            'sid' => strtoupper(trim((string) $request->query('sid', ''))),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    public function resolveGapType(array $filters): AutoBannedReconcileGapType
    {
        return AutoBannedReconcileGapType::tryFrom(trim((string) ($filters['gap_type'] ?? '')))
            ?? AutoBannedReconcileGapType::NoRequest;
    }

    public function bannedLogTableAvailable(AutoBannedReconcileGapType $gapType): bool
    {
        return $gapType->isWeekly()
            ? AutoBannedSchema::hasSidBannedLogWeeklyTable()
            : AutoBannedSchema::hasSidBannedLogTable();
    }

    /**
     * @param  array{
     *     gap_type?: string,
     *     min_days_old?: int,
     *     site?: string,
     *     sid?: string,
     *     q?: string
     * }  $filters
     * @return Collection<int, SidBannedLog|SidBannedLogWeekly>
     */
    public function gapBanLogs(array $filters = []): Collection
    {
        $gapType = $this->resolveGapType($filters);

        if (! $this->bannedLogTableAvailable($gapType)) {
            return collect();
        }

        $rows = match ($gapType) {
            AutoBannedReconcileGapType::WeeklyMissingUnbanLog => $this->gapWeeklyBanLogsMissingUnbanLog($filters),
            AutoBannedReconcileGapType::WeeklyNoRequest => $this->gapWeeklyBanLogsWithoutRequest($filters),
            AutoBannedReconcileGapType::MissingUnbanLog => $this->gapBanLogsMissingUnbanLog($filters),
            AutoBannedReconcileGapType::NoRequest => $this->gapBanLogsWithoutRequest($filters),
        };

        if ($gapType->isMissingUnbanLog()) {
            $rows = $gapType->isWeekly()
                ? $this->attachLatestApprovedWeeklyUnbanRequests($rows)
                : $this->attachLatestApprovedUnbanRequests($rows);
        }

        return $gapType->isWeekly()
            ? $this->chainService->attachWeeklyChainGaps($rows)
            : $this->chainService->attachDailyChainGaps($rows);
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return Collection<int, SidBannedLog>
     */
    private function gapBanLogsWithoutRequest(array $filters): Collection
    {
        $query = $this->baseDailyBannedLogQuery($filters);

        $this->excludeMatchedDailyUnbanLogs($query);
        $this->excludeWithDailyUnbanRequest($query);

        return $this->fetchDailyGapBanLogs($query);
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return Collection<int, SidBannedLog>
     */
    private function gapBanLogsMissingUnbanLog(array $filters): Collection
    {
        if (! AutoBannedSchema::hasUnbanRequestsTable()) {
            return collect();
        }

        $query = $this->baseDailyBannedLogQuery($filters);

        $this->excludeMatchedDailyUnbanLogs($query);
        $this->requireApprovedDailyUnbanRequest($query);

        return $this->fetchDailyGapBanLogs($query);
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return Collection<int, SidBannedLogWeekly>
     */
    private function gapWeeklyBanLogsWithoutRequest(array $filters): Collection
    {
        $query = $this->baseWeeklyBannedLogQuery($filters);

        $this->excludeMatchedWeeklyUnbanLogs($query);
        $this->excludeWithWeeklyUnbanRequest($query);

        return $this->fetchWeeklyGapBanLogs($query);
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return Collection<int, SidBannedLogWeekly>
     */
    private function gapWeeklyBanLogsMissingUnbanLog(array $filters): Collection
    {
        if (! AutoBannedSchema::hasUnbanRequestsTable()
            || ! AutoBannedSchema::hasUnbanRequestScrWeeklyBannedColumn()) {
            return collect();
        }

        $query = $this->baseWeeklyBannedLogQuery($filters);

        $this->excludeMatchedWeeklyUnbanLogs($query);
        $this->requireApprovedWeeklyUnbanRequest($query);

        return $this->fetchWeeklyGapBanLogs($query);
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return Builder<SidBannedLog>
     */
    private function baseDailyBannedLogQuery(array $filters): Builder
    {
        $minDaysOld = max(0, (int) ($filters['min_days_old'] ?? self::DEFAULT_MIN_DAYS_OLD));

        $query = SidBannedLog::query()
            ->whereNotNull('filter_date')
            ->whereNotNull('scr_daily_banned_id');

        $this->chainService->scopeDailySuccessBanned($query);

        if ($minDaysOld > 0) {
            $cutoffDate = Carbon::now()->subDays($minDaysOld)->toDateString();
            $query->whereDate('filter_date', '<=', $cutoffDate);
        }

        $this->applyDailySearchFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return Builder<SidBannedLogWeekly>
     */
    private function baseWeeklyBannedLogQuery(array $filters): Builder
    {
        $minDaysOld = max(0, (int) ($filters['min_days_old'] ?? self::DEFAULT_MIN_DAYS_OLD));

        $query = SidBannedLogWeekly::query()
            ->whereNotNull('filter_date')
            ->whereNotNull('scr_weekly_banned_id');

        $this->chainService->scopeWeeklySuccessBanned($query);

        if ($minDaysOld > 0) {
            $cutoffDate = Carbon::now()->subDays($minDaysOld)->toDateString();
            $query->whereDate('filter_date', '<=', $cutoffDate);
        }

        $this->applyWeeklySearchFilters($query, $filters);

        return $query;
    }

    /**
     * @param  Builder<SidBannedLog>  $query
     * @return Collection<int, SidBannedLog>
     */
    private function fetchDailyGapBanLogs(Builder $query): Collection
    {
        if (AutoBannedSchema::hasScrDailyBannedTable()) {
            $query->with([
                'scrDailyBanned:id,filter_date,'.ScrDailyBannedColumns::SITE.','.ScrDailyBannedColumns::BANNED_STATUS,
            ]);
        }

        return $query
            ->orderByDesc('filter_date')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get([
                'id',
                'scr_daily_banned_id',
                'filter_date',
                'filter_shift',
                'nik',
                'sid',
                'nama',
                'perusahaan',
                'site_dedicated',
                'banned_status',
                'banned_reason',
                'status_onsite',
                'automation_status',
                'completed_at',
                'started_at',
            ]);
    }

    /**
     * @param  Builder<SidBannedLogWeekly>  $query
     * @return Collection<int, SidBannedLogWeekly>
     */
    private function fetchWeeklyGapBanLogs(Builder $query): Collection
    {
        if (AutoBannedSchema::hasScrWeeklyBannedTable()) {
            $query->with([
                'scrWeeklyBanned:id,'.ScrWeeklyBannedColumns::ISO_YEAR.','.ScrWeeklyBannedColumns::ISO_WEEK.','.ScrWeeklyBannedColumns::SITE.','.ScrWeeklyBannedColumns::BANNED_STATUS,
            ]);
        }

        return $query
            ->orderByDesc('filter_date')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get([
                'id',
                'scr_weekly_banned_id',
                'filter_date',
                'filter_shift',
                'nik',
                'sid',
                'nama',
                'perusahaan',
                'site_dedicated',
                'banned_status',
                'banned_reason',
                'status_onsite',
                'automation_status',
                'completed_at',
                'started_at',
            ]);
    }

    /**
     * @param  Collection<int, SidBannedLog>  $banLogs
     * @return Collection<int, SidBannedLog>
     */
    private function attachLatestApprovedUnbanRequests(Collection $banLogs): Collection
    {
        if ($banLogs->isEmpty() || ! AutoBannedSchema::hasUnbanRequestsTable()) {
            return $banLogs;
        }

        $scrIds = $banLogs
            ->pluck('scr_daily_banned_id')
            ->filter(static fn ($id): bool => $id !== null)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($scrIds === []) {
            return $banLogs;
        }

        $requests = AutoBannedUnbanRequest::query()
            ->whereIn('scr_daily_banned_id', $scrIds)
            ->where('status', AutoBannedUnbanStatus::Approved->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $requestsByScrId = [];
        foreach ($requests as $request) {
            $scrId = (int) $request->scr_daily_banned_id;
            if (! isset($requestsByScrId[$scrId])) {
                $requestsByScrId[$scrId] = $request;
            }
        }

        return $banLogs->map(function (SidBannedLog $banLog) use ($requestsByScrId): SidBannedLog {
            $scrId = $banLog->scr_daily_banned_id !== null ? (int) $banLog->scr_daily_banned_id : null;
            $banLog->setRelation(
                'reconcileUnbanRequest',
                $scrId !== null ? ($requestsByScrId[$scrId] ?? null) : null,
            );

            return $banLog;
        });
    }

    /**
     * @param  Collection<int, SidBannedLogWeekly>  $banLogs
     * @return Collection<int, SidBannedLogWeekly>
     */
    private function attachLatestApprovedWeeklyUnbanRequests(Collection $banLogs): Collection
    {
        if ($banLogs->isEmpty() || ! AutoBannedSchema::hasUnbanRequestsTable()) {
            return $banLogs;
        }

        $scrIds = $banLogs
            ->pluck('scr_weekly_banned_id')
            ->filter(static fn ($id): bool => $id !== null)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($scrIds === []) {
            return $banLogs;
        }

        $requests = AutoBannedUnbanRequest::query()
            ->whereIn('scr_weekly_banned_id', $scrIds)
            ->where('status', AutoBannedUnbanStatus::Approved->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $requestsByScrId = [];
        foreach ($requests as $request) {
            $scrId = (int) $request->scr_weekly_banned_id;
            if (! isset($requestsByScrId[$scrId])) {
                $requestsByScrId[$scrId] = $request;
            }
        }

        return $banLogs->map(function (SidBannedLogWeekly $banLog) use ($requestsByScrId): SidBannedLogWeekly {
            $scrId = $banLog->scr_weekly_banned_id !== null ? (int) $banLog->scr_weekly_banned_id : null;
            $banLog->setRelation(
                'reconcileUnbanRequest',
                $scrId !== null ? ($requestsByScrId[$scrId] ?? null) : null,
            );

            return $banLog;
        });
    }

    /**
     * @param  array{gap_type?: string, site?: string, sid?: string, q?: string}  $filters
     * @return array{sites: Collection<int, string>}
     */
    public function filterOptions(array $filters = []): array
    {
        $gapType = $this->resolveGapType($filters);

        if (! $this->bannedLogTableAvailable($gapType)) {
            return ['sites' => collect()];
        }

        if ($gapType->isWeekly()) {
        $query = SidBannedLogWeekly::query()
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->whereNotNull('site_dedicated')
                ->where('site_dedicated', '!=', '');

            if (($filters['site'] ?? '') !== '') {
                AutoBannedSiteOptions::applyBannedLogWeeklySiteFilter($query, $filters['site']);
            }

            $sites = $query
                ->select('site_dedicated')
                ->distinct()
                ->orderBy('site_dedicated')
                ->pluck('site_dedicated')
                ->values();

            return [
                'sites' => AutoBannedSiteOptions::mergeFilterOptions($sites),
            ];
        }

        $query = SidBannedLog::query()
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->whereNotNull('site_dedicated')
            ->where('site_dedicated', '!=', '');

        if (($filters['site'] ?? '') !== '') {
            AutoBannedSiteOptions::applyBannedLogSiteFilter($query, $filters['site']);
        }

        $sites = $query
            ->select('site_dedicated')
            ->distinct()
            ->orderBy('site_dedicated')
            ->pluck('site_dedicated')
            ->values();

        return [
            'sites' => AutoBannedSiteOptions::mergeFilterOptions($sites),
        ];
    }

    /**
     * @param  Builder<SidBannedLog>  $query
     */
    private function excludeMatchedDailyUnbanLogs(Builder $query): void
    {
        if (! AutoBannedSchema::hasSidUnbanLogTable()) {
            return;
        }

        $success = AutoBannedSidAutomationStatus::Success->value;

        $query->whereRaw("
            NOT EXISTS (
                SELECT 1 FROM sid_unban_log ul
                WHERE ul.automation_status = ?
                  AND sid_banned_log.scr_daily_banned_id IS NOT NULL
                  AND ul.scr_daily_banned_id = sid_banned_log.scr_daily_banned_id
            )
        ", [$success]);
    }

    /**
     * @param  Builder<SidBannedLogWeekly>  $query
     */
    private function excludeMatchedWeeklyUnbanLogs(Builder $query): void
    {
        if (! AutoBannedSchema::hasSidUnbanLogTable()) {
            return;
        }

        $success = AutoBannedSidAutomationStatus::Success->value;

        if (AutoBannedSchema::hasSidUnbanLogScrWeeklyBannedColumn()) {
            $query->whereRaw("
                NOT EXISTS (
                    SELECT 1 FROM sid_unban_log ul
                    WHERE ul.automation_status = ?
                      AND sid_banned_log_weekly.scr_weekly_banned_id IS NOT NULL
                      AND ul.scr_weekly_banned_id = sid_banned_log_weekly.scr_weekly_banned_id
                )
            ", [$success]);

            return;
        }

        $query->whereRaw("
            NOT EXISTS (
                SELECT 1 FROM sid_unban_log ul
                WHERE ul.automation_status = ?
                  AND sid_banned_log_weekly.sid IS NOT NULL
                  AND sid_banned_log_weekly.sid != ''
                  AND UPPER(TRIM(ul.sid)) = UPPER(TRIM(sid_banned_log_weekly.sid))
                  AND ul.completed_at >= COALESCE(sid_banned_log_weekly.completed_at, sid_banned_log_weekly.started_at)
            )
        ", [$success]);
    }

    /**
     * @param  Builder<SidBannedLog>  $query
     */
    private function excludeWithDailyUnbanRequest(Builder $query): void
    {
        if (! AutoBannedSchema::hasUnbanRequestsTable()) {
            return;
        }

        $query->whereRaw("
            NOT EXISTS (
                SELECT 1 FROM auto_banned_unban_requests ur
                WHERE sid_banned_log.scr_daily_banned_id IS NOT NULL
                  AND ur.scr_daily_banned_id = sid_banned_log.scr_daily_banned_id
            )
        ");
    }

    /**
     * @param  Builder<SidBannedLogWeekly>  $query
     */
    private function excludeWithWeeklyUnbanRequest(Builder $query): void
    {
        if (! AutoBannedSchema::hasUnbanRequestsTable()
            || ! AutoBannedSchema::hasUnbanRequestScrWeeklyBannedColumn()) {
            return;
        }

        $query->whereRaw("
            NOT EXISTS (
                SELECT 1 FROM auto_banned_unban_requests ur
                WHERE sid_banned_log_weekly.scr_weekly_banned_id IS NOT NULL
                  AND ur.scr_weekly_banned_id = sid_banned_log_weekly.scr_weekly_banned_id
            )
        ");
    }

    /**
     * @param  Builder<SidBannedLog>  $query
     */
    private function requireApprovedDailyUnbanRequest(Builder $query): void
    {
        if (! AutoBannedSchema::hasUnbanRequestsTable()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $approved = AutoBannedUnbanStatus::Approved->value;

        $query->whereRaw("
            EXISTS (
                SELECT 1 FROM auto_banned_unban_requests ur
                WHERE sid_banned_log.scr_daily_banned_id IS NOT NULL
                  AND ur.scr_daily_banned_id = sid_banned_log.scr_daily_banned_id
                  AND ur.status = ?
            )
        ", [$approved]);
    }

    /**
     * @param  Builder<SidBannedLogWeekly>  $query
     */
    private function requireApprovedWeeklyUnbanRequest(Builder $query): void
    {
        if (! AutoBannedSchema::hasUnbanRequestsTable()
            || ! AutoBannedSchema::hasUnbanRequestScrWeeklyBannedColumn()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $approved = AutoBannedUnbanStatus::Approved->value;

        $query->whereRaw("
            EXISTS (
                SELECT 1 FROM auto_banned_unban_requests ur
                WHERE sid_banned_log_weekly.scr_weekly_banned_id IS NOT NULL
                  AND ur.scr_weekly_banned_id = sid_banned_log_weekly.scr_weekly_banned_id
                  AND ur.status = ?
            )
        ", [$approved]);
    }

    /**
     * @param  Builder<SidBannedLog>  $query
     * @param  array{site?: string, sid?: string, q?: string}  $filters
     */
    private function applyDailySearchFilters(Builder $query, array $filters): void
    {
        if (($filters['site'] ?? '') !== '') {
            AutoBannedSiteOptions::applyBannedLogSiteFilter($query, $filters['site']);
        }

        $sid = strtoupper(trim((string) ($filters['sid'] ?? '')));
        if ($sid !== '') {
            $query->whereRaw('UPPER(TRIM(sid)) LIKE ?', ['%'.$sid.'%']);
        }

        if (($filters['q'] ?? '') !== '' && $sid === '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('nama', 'like', $term)
                    ->orWhere('nik', 'like', $term)
                    ->orWhere('sid', 'like', $term)
                    ->orWhere('banned_reason', 'like', $term)
                    ->orWhere('perusahaan', 'like', $term);
            });
        }
    }

    /**
     * @param  Builder<SidBannedLogWeekly>  $query
     * @param  array{site?: string, sid?: string, q?: string}  $filters
     */
    private function applyWeeklySearchFilters(Builder $query, array $filters): void
    {
        if (($filters['site'] ?? '') !== '') {
            AutoBannedSiteOptions::applyBannedLogWeeklySiteFilter($query, $filters['site']);
        }

        $sid = strtoupper(trim((string) ($filters['sid'] ?? '')));
        if ($sid !== '') {
            $query->whereRaw('UPPER(TRIM(sid)) LIKE ?', ['%'.$sid.'%']);
        }

        if (($filters['q'] ?? '') !== '' && $sid === '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('nama', 'like', $term)
                    ->orWhere('nik', 'like', $term)
                    ->orWhere('sid', 'like', $term)
                    ->orWhere('banned_reason', 'like', $term)
                    ->orWhere('perusahaan', 'like', $term);
            });
        }
    }

    /**
     * Jelaskan mengapa riwayat banned untuk SID tidak masuk tab gap aktif (saat hasil filter kosong).
     *
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return Collection<int, array{
     *     ban_log_id: int,
     *     scope: string,
     *     scr_ref_id: ?int,
     *     filter_date: string,
     *     ticket_code: string,
     *     in_current_gap: bool,
     *     reasons: array<int, string>,
     *     suggested_gap_type: ?string
     * }>
     */
    public function explainGapExclusionsForSid(array $filters, AutoBannedReconcileGapType $gapType): Collection
    {
        $sid = strtoupper(trim((string) ($filters['sid'] ?? '')));
        if ($sid === '') {
            return collect();
        }

        return $gapType->isWeekly()
            ? $this->explainWeeklyGapExclusionsForSid($filters, $gapType, $sid)
            : $this->explainDailyGapExclusionsForSid($filters, $gapType, $sid);
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return Collection<int, array{
     *     ban_log_id: int,
     *     scope: string,
     *     scr_ref_id: ?int,
     *     filter_date: string,
     *     ticket_code: string,
     *     in_current_gap: bool,
     *     reasons: array<int, string>,
     *     suggested_gap_type: ?string
     * }>
     */
    private function explainDailyGapExclusionsForSid(array $filters, AutoBannedReconcileGapType $gapType, string $sid): Collection
    {
        if (! AutoBannedSchema::hasSidBannedLogTable()) {
            return collect();
        }

        $query = SidBannedLog::query()
            ->whereRaw('UPPER(TRIM(sid)) LIKE ?', ['%'.$sid.'%'])
            ->orderByDesc('filter_date')
            ->orderByDesc('id')
            ->limit(20);

        if (($filters['site'] ?? '') !== '') {
            AutoBannedSiteOptions::applyBannedLogSiteFilter($query, $filters['site']);
        }

        $logs = $query->get([
            'id',
            'scr_daily_banned_id',
            'filter_date',
            'banned_status',
            'automation_status',
        ]);

        return $logs->map(fn (SidBannedLog $log): array => $this->buildDailyExclusionExplanation($log, $filters, $gapType));
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return Collection<int, array{
     *     ban_log_id: int,
     *     scope: string,
     *     scr_ref_id: ?int,
     *     filter_date: string,
     *     ticket_code: string,
     *     in_current_gap: bool,
     *     reasons: array<int, string>,
     *     suggested_gap_type: ?string
     * }>
     */
    private function explainWeeklyGapExclusionsForSid(array $filters, AutoBannedReconcileGapType $gapType, string $sid): Collection
    {
        if (! AutoBannedSchema::hasSidBannedLogWeeklyTable()) {
            return collect();
        }

        $query = SidBannedLogWeekly::query()
            ->whereRaw('UPPER(TRIM(sid)) LIKE ?', ['%'.$sid.'%'])
            ->orderByDesc('filter_date')
            ->orderByDesc('id')
            ->limit(20);

        if (($filters['site'] ?? '') !== '') {
            AutoBannedSiteOptions::applyBannedLogWeeklySiteFilter($query, $filters['site']);
        }

        $logs = $query->get([
            'id',
            'scr_weekly_banned_id',
            'filter_date',
            'banned_status',
            'automation_status',
        ]);

        return $logs->map(fn (SidBannedLogWeekly $log): array => $this->buildWeeklyExclusionExplanation($log, $filters, $gapType));
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return array{
     *     ban_log_id: int,
     *     scope: string,
     *     scr_ref_id: ?int,
     *     filter_date: string,
     *     ticket_code: string,
     *     in_current_gap: bool,
     *     reasons: array<int, string>,
     *     suggested_gap_type: ?string
     * }
     */
    private function buildDailyExclusionExplanation(SidBannedLog $log, array $filters, AutoBannedReconcileGapType $gapType): array
    {
        $reasons = [];
        $scrRefId = $log->scr_daily_banned_id !== null ? (int) $log->scr_daily_banned_id : null;
        $ticketCode = trim((string) ($log->banned_status ?? '')) ?: 'Daily #'.$log->id;
        $suggestedGapType = null;

        $status = $log->automation_status instanceof AutoBannedSidAutomationStatus
            ? $log->automation_status->value
            : strtoupper(trim((string) $log->automation_status));

        if ($status !== AutoBannedSidAutomationStatus::Success->value) {
            $reasons[] = 'Hanya banned berstatus SUCCESS yang wajib punya pengajuan + log unban ('.($status ?: '—').').';
        }

        if ($log->filter_date === null) {
            $reasons[] = 'filter_date kosong.';
        }

        if ($scrRefId === null) {
            $reasons[] = 'scr_daily_banned_id kosong — tidak bisa direkonsiliasi.';
        }

        $minDaysOld = max(0, (int) ($filters['min_days_old'] ?? self::DEFAULT_MIN_DAYS_OLD));
        if ($minDaysOld > 0 && $log->filter_date !== null) {
            $cutoffDate = Carbon::now()->subDays($minDaysOld)->startOfDay();
            if ($log->filter_date->greaterThan($cutoffDate)) {
                $reasons[] = 'filter_date '.$log->filter_date->format('d M Y').' belum memenuhi H-'.$minDaysOld.' (cutoff '.$cutoffDate->format('d M Y').'). Turunkan Min. hari lalu ke 0.';
            }
        }

        $unbanLog = null;
        if ($scrRefId !== null && AutoBannedSchema::hasSidUnbanLogTable()) {
            $unbanLog = SidUnbanLog::query()
                ->where('scr_daily_banned_id', $scrRefId)
                ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
                ->orderByDesc('completed_at')
                ->first(['id', 'completed_at']);
        }

        if ($unbanLog !== null) {
            $reasons[] = 'Sudah ada sid_unban_log SUCCESS #'.$unbanLog->id.' untuk scr_daily_banned_id '.$scrRefId.' — tiket daily sudah selesai.';
        }

        $request = null;
        if ($scrRefId !== null && AutoBannedSchema::hasUnbanRequestsTable()) {
            $request = AutoBannedUnbanRequest::query()
                ->where('scr_daily_banned_id', $scrRefId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first(['id', 'status', 'created_at']);
        }

        if ($request !== null) {
            $requestStatus = $request->status instanceof AutoBannedUnbanStatus
                ? $request->status
                : AutoBannedUnbanStatus::tryFrom((string) $request->status);
            $statusLabel = $requestStatus?->label() ?? (string) $request->status;

            if ($gapType === AutoBannedReconcileGapType::NoRequest) {
                $reasons[] = 'Sudah ada pengajuan unban #'.$request->id.' ('.$statusLabel.') untuk scr_daily_banned_id '.$scrRefId.'.';
                if ($requestStatus === AutoBannedUnbanStatus::Approved && $unbanLog === null) {
                    $suggestedGapType = AutoBannedReconcileGapType::MissingUnbanLog->value;
                }
            } elseif ($requestStatus !== AutoBannedUnbanStatus::Approved) {
                $reasons[] = 'Pengajuan #'.$request->id.' berstatus '.$statusLabel.' — tab ini hanya untuk pengajuan Disetujui tanpa log unban.';
                $suggestedGapType = AutoBannedReconcileGapType::NoRequest->value;
            }
        } elseif ($gapType === AutoBannedReconcileGapType::MissingUnbanLog) {
            $reasons[] = 'Belum ada pengajuan Disetujui untuk scr_daily_banned_id '.($scrRefId ?? '—').'.';
            $suggestedGapType = AutoBannedReconcileGapType::NoRequest->value;
        }

        if ($gapType === AutoBannedReconcileGapType::NoRequest && $unbanLog === null && $request === null && $reasons === []) {
            $reasons[] = 'Seharusnya muncul di tab ini — coba reset filter atau refresh halaman.';
        }

        return [
            'ban_log_id' => (int) $log->id,
            'scope' => 'Daily',
            'scr_ref_id' => $scrRefId,
            'filter_date' => $log->filter_date?->format('d M Y') ?? '—',
            'ticket_code' => $ticketCode,
            'in_current_gap' => $reasons === [],
            'reasons' => $reasons,
            'suggested_gap_type' => $suggestedGapType,
        ];
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return array{
     *     ban_log_id: int,
     *     scope: string,
     *     scr_ref_id: ?int,
     *     filter_date: string,
     *     ticket_code: string,
     *     in_current_gap: bool,
     *     reasons: array<int, string>,
     *     suggested_gap_type: ?string
     * }
     */
    private function buildWeeklyExclusionExplanation(SidBannedLogWeekly $log, array $filters, AutoBannedReconcileGapType $gapType): array
    {
        $reasons = [];
        $scrRefId = $log->scr_weekly_banned_id !== null ? (int) $log->scr_weekly_banned_id : null;
        $ticketCode = trim((string) ($log->banned_status ?? '')) ?: 'Weekly #'.$log->id;
        $suggestedGapType = null;

        $status = $log->automation_status instanceof AutoBannedSidAutomationStatus
            ? $log->automation_status->value
            : strtoupper(trim((string) $log->automation_status));

        if ($status !== AutoBannedSidAutomationStatus::Success->value) {
            $reasons[] = 'Hanya banned berstatus SUCCESS yang wajib punya pengajuan + log unban ('.($status ?: '—').').';
        }

        if ($log->filter_date === null) {
            $reasons[] = 'filter_date kosong.';
        }

        if ($scrRefId === null) {
            $reasons[] = 'scr_weekly_banned_id kosong — tidak bisa direkonsiliasi.';
        }

        $minDaysOld = max(0, (int) ($filters['min_days_old'] ?? self::DEFAULT_MIN_DAYS_OLD));
        if ($minDaysOld > 0 && $log->filter_date !== null) {
            $cutoffDate = Carbon::now()->subDays($minDaysOld)->startOfDay();
            if ($log->filter_date->greaterThan($cutoffDate)) {
                $reasons[] = 'filter_date '.$log->filter_date->format('d M Y').' belum memenuhi H-'.$minDaysOld.' (cutoff '.$cutoffDate->format('d M Y').'). Turunkan Min. hari lalu ke 0.';
            }
        }

        $unbanLog = null;
        if ($scrRefId !== null
            && AutoBannedSchema::hasSidUnbanLogTable()
            && AutoBannedSchema::hasSidUnbanLogScrWeeklyBannedColumn()) {
            $unbanLog = SidUnbanLog::query()
                ->where('scr_weekly_banned_id', $scrRefId)
                ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
                ->orderByDesc('completed_at')
                ->first(['id', 'completed_at']);
        }

        if ($unbanLog !== null) {
            $reasons[] = 'Sudah ada sid_unban_log SUCCESS #'.$unbanLog->id.' untuk scr_weekly_banned_id '.$scrRefId.' — tiket weekly sudah selesai.';
        }

        $request = null;
        if ($scrRefId !== null
            && AutoBannedSchema::hasUnbanRequestsTable()
            && AutoBannedSchema::hasUnbanRequestScrWeeklyBannedColumn()) {
            $request = AutoBannedUnbanRequest::query()
                ->where('scr_weekly_banned_id', $scrRefId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first(['id', 'status', 'created_at']);
        }

        if ($request !== null) {
            $requestStatus = $request->status instanceof AutoBannedUnbanStatus
                ? $request->status
                : AutoBannedUnbanStatus::tryFrom((string) $request->status);
            $statusLabel = $requestStatus?->label() ?? (string) $request->status;

            if ($gapType === AutoBannedReconcileGapType::WeeklyNoRequest) {
                $reasons[] = 'Sudah ada pengajuan unban #'.$request->id.' ('.$statusLabel.') untuk scr_weekly_banned_id '.$scrRefId.'.';
                if ($requestStatus === AutoBannedUnbanStatus::Approved && $unbanLog === null) {
                    $suggestedGapType = AutoBannedReconcileGapType::WeeklyMissingUnbanLog->value;
                }
            } elseif ($requestStatus !== AutoBannedUnbanStatus::Approved) {
                $reasons[] = 'Pengajuan #'.$request->id.' berstatus '.$statusLabel.' — tab ini hanya untuk pengajuan Disetujui tanpa log unban.';
                $suggestedGapType = AutoBannedReconcileGapType::WeeklyNoRequest->value;
            }
        } elseif ($gapType === AutoBannedReconcileGapType::WeeklyMissingUnbanLog) {
            $reasons[] = 'Belum ada pengajuan Disetujui untuk scr_weekly_banned_id '.($scrRefId ?? '—').'.';
            $suggestedGapType = AutoBannedReconcileGapType::WeeklyNoRequest->value;
        }

        return [
            'ban_log_id' => (int) $log->id,
            'scope' => 'Weekly',
            'scr_ref_id' => $scrRefId,
            'filter_date' => $log->filter_date?->format('d M Y') ?? '—',
            'ticket_code' => $ticketCode,
            'in_current_gap' => $reasons === [],
            'reasons' => $reasons,
            'suggested_gap_type' => $suggestedGapType,
        ];
    }
}
