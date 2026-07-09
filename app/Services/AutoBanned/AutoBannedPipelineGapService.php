<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedReconcileGapType;
use App\Enums\AutoBannedSidAutomationStatus;
use App\Enums\AutoBannedUnbanStatus;
use App\Models\AutoBannedUnbanRequest;
use App\Models\SidBannedLog;
use App\Support\AutoBanned\AutoBannedSchema;
use App\Support\AutoBanned\AutoBannedSiteOptions;
use App\Support\AutoBanned\ScrDailyBannedColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AutoBannedPipelineGapService
{
    public const DEFAULT_MIN_DAYS_OLD = 3;

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

    public function bannedLogTableAvailable(): bool
    {
        return AutoBannedSchema::hasSidBannedLogTable();
    }

    /**
     * @param  array{
     *     gap_type?: string,
     *     min_days_old?: int,
     *     site?: string,
     *     sid?: string,
     *     q?: string
     * }  $filters
     * @return Collection<int, SidBannedLog>
     */
    public function gapBanLogs(array $filters = []): Collection
    {
        if (! $this->bannedLogTableAvailable()) {
            return collect();
        }

        $gapType = $this->resolveGapType($filters);

        $rows = match ($gapType) {
            AutoBannedReconcileGapType::MissingUnbanLog => $this->gapBanLogsMissingUnbanLog($filters),
            AutoBannedReconcileGapType::NoRequest => $this->gapBanLogsWithoutRequest($filters),
        };

        if ($gapType === AutoBannedReconcileGapType::MissingUnbanLog) {
            return $this->attachLatestApprovedUnbanRequests($rows);
        }

        return $rows;
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return Collection<int, SidBannedLog>
     */
    private function gapBanLogsWithoutRequest(array $filters): Collection
    {
        $query = $this->baseBannedLogQuery($filters);

        $this->excludeMatchedUnbanLogs($query);
        $this->excludeWithUnbanRequest($query);

        return $this->fetchGapBanLogs($query);
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

        $query = $this->baseBannedLogQuery($filters);

        $this->excludeMatchedUnbanLogs($query);
        $this->requireApprovedUnbanRequest($query);

        return $this->fetchGapBanLogs($query);
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     */
    private function baseBannedLogQuery(array $filters): Builder
    {
        $minDaysOld = max(0, (int) ($filters['min_days_old'] ?? self::DEFAULT_MIN_DAYS_OLD));

        $query = SidBannedLog::query()
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->whereNotNull('filter_date')
            ->whereNotNull('scr_daily_banned_id');

        if ($minDaysOld > 0) {
            $cutoffDate = Carbon::now()->subDays($minDaysOld)->toDateString();
            $query->whereDate('filter_date', '<=', $cutoffDate);
        }

        $this->applySearchFilters($query, $filters);

        return $query;
    }

    /**
     * @param  Builder<SidBannedLog>  $query
     * @return Collection<int, SidBannedLog>
     */
    private function fetchGapBanLogs(Builder $query): Collection
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
     * @param  array{site?: string, sid?: string, q?: string}  $filters
     * @return array{sites: Collection<int, string>}
     */
    public function filterOptions(array $filters = []): array
    {
        if (! $this->bannedLogTableAvailable()) {
            return ['sites' => collect()];
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
    private function excludeMatchedUnbanLogs(Builder $query): void
    {
        if (! AutoBannedSchema::hasSidUnbanLogTable()) {
            return;
        }

        $success = AutoBannedSidAutomationStatus::Success->value;

        $query->whereRaw("
            NOT EXISTS (
                SELECT 1 FROM sid_unban_log ul
                WHERE ul.automation_status = ?
                  AND (
                      (sid_banned_log.scr_daily_banned_id IS NOT NULL
                       AND ul.scr_daily_banned_id = sid_banned_log.scr_daily_banned_id)
                      OR (
                          sid_banned_log.sid IS NOT NULL
                          AND sid_banned_log.sid != ''
                          AND UPPER(TRIM(ul.sid)) = UPPER(TRIM(sid_banned_log.sid))
                          AND ul.completed_at >= COALESCE(sid_banned_log.completed_at, sid_banned_log.started_at)
                      )
                  )
            )
        ", [$success]);
    }

    /**
     * @param  Builder<SidBannedLog>  $query
     */
    private function excludeWithUnbanRequest(Builder $query): void
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
     * @param  Builder<SidBannedLog>  $query
     */
    private function requireApprovedUnbanRequest(Builder $query): void
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
     * @param  Builder<SidBannedLog>  $query
     * @param  array{site?: string, sid?: string, q?: string}  $filters
     */
    private function applySearchFilters(Builder $query, array $filters): void
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
}
