<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedSidAutomationStatus;
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
     * @return array{min_days_old: int, site: string, sid: string, q: string}
     */
    public function resolveFilters(Request $request): array
    {
        $minDaysOld = (int) $request->query('min_days_old', (string) self::DEFAULT_MIN_DAYS_OLD);
        if ($minDaysOld < 0) {
            $minDaysOld = self::DEFAULT_MIN_DAYS_OLD;
        }

        return [
            'min_days_old' => $minDaysOld,
            'site' => trim((string) $request->query('site', '')),
            'sid' => strtoupper(trim((string) $request->query('sid', ''))),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    public function bannedLogTableAvailable(): bool
    {
        return AutoBannedSchema::hasSidBannedLogTable();
    }

    /**
     * @param  array{min_days_old?: int, site?: string, sid?: string, q?: string}  $filters
     * @return Collection<int, SidBannedLog>
     */
    public function gapBanLogs(array $filters = []): Collection
    {
        if (! $this->bannedLogTableAvailable()) {
            return collect();
        }

        $minDaysOld = max(0, (int) ($filters['min_days_old'] ?? self::DEFAULT_MIN_DAYS_OLD));
        $cutoffDate = Carbon::now()->subDays($minDaysOld)->toDateString();

        $query = SidBannedLog::query()
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->whereNotNull('filter_date')
            ->whereDate('filter_date', '<=', $cutoffDate);

        $this->excludeMatchedUnbanLogs($query);
        $this->excludeWithUnbanRequest($query);
        $this->applySearchFilters($query, $filters);

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
