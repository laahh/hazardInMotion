<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedBannedChainGap;
use App\Enums\AutoBannedSidAutomationStatus;
use App\Enums\AutoBannedUnbanStatus;
use App\Models\AutoBannedUnbanRequest;
use App\Models\SidBannedLog;
use App\Models\SidBannedLogWeekly;
use App\Models\SidUnbanLog;
use App\Support\AutoBanned\AutoBannedSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AutoBannedBannedChainService
{
    /**
     * Banned SUCCESS wajib memiliki pengajuan Disetujui + sid_unban_log SUCCESS per SCR ref tiket.
     */
    public function isDailyChainComplete(?int $scrDailyBannedId, ?AutoBannedUnbanRequest $request = null, ?SidUnbanLog $unbanLog = null): bool
    {
        return $this->resolveDailyChainGap($scrDailyBannedId, $request, $unbanLog) === AutoBannedBannedChainGap::Complete;
    }

    public function isWeeklyChainComplete(?int $scrWeeklyBannedId, ?AutoBannedUnbanRequest $request = null, ?SidUnbanLog $unbanLog = null): bool
    {
        return $this->resolveWeeklyChainGap($scrWeeklyBannedId, $request, $unbanLog) === AutoBannedBannedChainGap::Complete;
    }

    public function resolveDailyChainGap(
        ?int $scrDailyBannedId,
        ?AutoBannedUnbanRequest $request = null,
        ?SidUnbanLog $unbanLog = null,
    ): AutoBannedBannedChainGap {
        if ($scrDailyBannedId === null) {
            return AutoBannedBannedChainGap::MissingRequest;
        }

        $request ??= $this->findLatestDailyRequest($scrDailyBannedId);
        $unbanLog ??= $this->findSuccessDailyUnbanLog($scrDailyBannedId);

        if ($unbanLog !== null) {
            return AutoBannedBannedChainGap::Complete;
        }

        if ($request === null) {
            return AutoBannedBannedChainGap::MissingRequest;
        }

        $status = $request->status instanceof AutoBannedUnbanStatus
            ? $request->status
            : AutoBannedUnbanStatus::tryFrom((string) $request->status);

        return match ($status) {
            AutoBannedUnbanStatus::Approved => AutoBannedBannedChainGap::MissingUnban,
            AutoBannedUnbanStatus::Pending => AutoBannedBannedChainGap::RequestPending,
            AutoBannedUnbanStatus::Rejected => AutoBannedBannedChainGap::RequestRejected,
            default => AutoBannedBannedChainGap::MissingRequest,
        };
    }

    public function resolveWeeklyChainGap(
        ?int $scrWeeklyBannedId,
        ?AutoBannedUnbanRequest $request = null,
        ?SidUnbanLog $unbanLog = null,
    ): AutoBannedBannedChainGap {
        if ($scrWeeklyBannedId === null) {
            return AutoBannedBannedChainGap::MissingRequest;
        }

        $request ??= $this->findLatestWeeklyRequest($scrWeeklyBannedId);
        $unbanLog ??= $this->findSuccessWeeklyUnbanLog($scrWeeklyBannedId);

        if ($unbanLog !== null) {
            return AutoBannedBannedChainGap::Complete;
        }

        if ($request === null) {
            return AutoBannedBannedChainGap::MissingRequest;
        }

        $status = $request->status instanceof AutoBannedUnbanStatus
            ? $request->status
            : AutoBannedUnbanStatus::tryFrom((string) $request->status);

        return match ($status) {
            AutoBannedUnbanStatus::Approved => AutoBannedBannedChainGap::MissingUnban,
            AutoBannedUnbanStatus::Pending => AutoBannedBannedChainGap::RequestPending,
            AutoBannedUnbanStatus::Rejected => AutoBannedBannedChainGap::RequestRejected,
            default => AutoBannedBannedChainGap::MissingRequest,
        };
    }

    /**
     * @param  Collection<int, SidBannedLog>  $banLogs
     * @return Collection<int, SidBannedLog>
     */
    public function attachDailyChainGaps(Collection $banLogs): Collection
    {
        if ($banLogs->isEmpty()) {
            return $banLogs;
        }

        $scrIds = $banLogs
            ->pluck('scr_daily_banned_id')
            ->filter(static fn ($id): bool => $id !== null)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $requestsByScrId = $this->loadLatestDailyRequestsByScrId($scrIds);
        $unbanByScrId = $this->loadSuccessDailyUnbanLogsByScrId($scrIds);

        return $banLogs->map(function (SidBannedLog $banLog) use ($requestsByScrId, $unbanByScrId): SidBannedLog {
            $scrId = $banLog->scr_daily_banned_id !== null ? (int) $banLog->scr_daily_banned_id : null;
            $gap = $this->resolveDailyChainGap(
                $scrId,
                $scrId !== null ? ($requestsByScrId[$scrId] ?? null) : null,
                $scrId !== null ? ($unbanByScrId[$scrId] ?? null) : null,
            );
            $banLog->setRelation('bannedChainGap', $gap);

            return $banLog;
        });
    }

    /**
     * @param  Collection<int, SidBannedLogWeekly>  $banLogs
     * @return Collection<int, SidBannedLogWeekly>
     */
    public function attachWeeklyChainGaps(Collection $banLogs): Collection
    {
        if ($banLogs->isEmpty()) {
            return $banLogs;
        }

        $scrIds = $banLogs
            ->pluck('scr_weekly_banned_id')
            ->filter(static fn ($id): bool => $id !== null)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $requestsByScrId = $this->loadLatestWeeklyRequestsByScrId($scrIds);
        $unbanByScrId = $this->loadSuccessWeeklyUnbanLogsByScrId($scrIds);

        return $banLogs->map(function (SidBannedLogWeekly $banLog) use ($requestsByScrId, $unbanByScrId): SidBannedLogWeekly {
            $scrId = $banLog->scr_weekly_banned_id !== null ? (int) $banLog->scr_weekly_banned_id : null;
            $gap = $this->resolveWeeklyChainGap(
                $scrId,
                $scrId !== null ? ($requestsByScrId[$scrId] ?? null) : null,
                $scrId !== null ? ($unbanByScrId[$scrId] ?? null) : null,
            );
            $banLog->setRelation('bannedChainGap', $gap);

            return $banLog;
        });
    }

    /**
     * @param  Builder<SidBannedLog>  $query
     */
    public function scopeDailySuccessBanned(Builder $query): void
    {
        $query->where('automation_status', AutoBannedSidAutomationStatus::Success->value);
    }

    /**
     * @param  Builder<SidBannedLogWeekly>  $query
     */
    public function scopeWeeklySuccessBanned(Builder $query): void
    {
        $query->where('automation_status', AutoBannedSidAutomationStatus::Success->value);
    }

    private function findLatestDailyRequest(int $scrDailyBannedId): ?AutoBannedUnbanRequest
    {
        if (! AutoBannedSchema::hasUnbanRequestsTable()) {
            return null;
        }

        return AutoBannedUnbanRequest::query()
            ->where('scr_daily_banned_id', $scrDailyBannedId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    private function findLatestWeeklyRequest(int $scrWeeklyBannedId): ?AutoBannedUnbanRequest
    {
        if (! AutoBannedSchema::hasUnbanRequestsTable()
            || ! AutoBannedSchema::hasUnbanRequestScrWeeklyBannedColumn()) {
            return null;
        }

        return AutoBannedUnbanRequest::query()
            ->where('scr_weekly_banned_id', $scrWeeklyBannedId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    private function findSuccessDailyUnbanLog(int $scrDailyBannedId): ?SidUnbanLog
    {
        if (! AutoBannedSchema::hasSidUnbanLogTable()) {
            return null;
        }

        return SidUnbanLog::query()
            ->where('scr_daily_banned_id', $scrDailyBannedId)
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
    }

    private function findSuccessWeeklyUnbanLog(int $scrWeeklyBannedId): ?SidUnbanLog
    {
        if (! AutoBannedSchema::hasSidUnbanLogTable()
            || ! AutoBannedSchema::hasSidUnbanLogScrWeeklyBannedColumn()) {
            return null;
        }

        return SidUnbanLog::query()
            ->where('scr_weekly_banned_id', $scrWeeklyBannedId)
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<int, int>  $scrIds
     * @return array<int, AutoBannedUnbanRequest>
     */
    private function loadLatestDailyRequestsByScrId(array $scrIds): array
    {
        if ($scrIds === [] || ! AutoBannedSchema::hasUnbanRequestsTable()) {
            return [];
        }

        $requests = AutoBannedUnbanRequest::query()
            ->whereIn('scr_daily_banned_id', $scrIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $indexed = [];
        foreach ($requests as $request) {
            $scrId = (int) $request->scr_daily_banned_id;
            if (! isset($indexed[$scrId])) {
                $indexed[$scrId] = $request;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<int, int>  $scrIds
     * @return array<int, AutoBannedUnbanRequest>
     */
    private function loadLatestWeeklyRequestsByScrId(array $scrIds): array
    {
        if ($scrIds === [] || ! AutoBannedSchema::hasUnbanRequestsTable()
            || ! AutoBannedSchema::hasUnbanRequestScrWeeklyBannedColumn()) {
            return [];
        }

        $requests = AutoBannedUnbanRequest::query()
            ->whereIn('scr_weekly_banned_id', $scrIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $indexed = [];
        foreach ($requests as $request) {
            $scrId = (int) $request->scr_weekly_banned_id;
            if (! isset($indexed[$scrId])) {
                $indexed[$scrId] = $request;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<int, int>  $scrIds
     * @return array<int, SidUnbanLog>
     */
    private function loadSuccessDailyUnbanLogsByScrId(array $scrIds): array
    {
        if ($scrIds === [] || ! AutoBannedSchema::hasSidUnbanLogTable()) {
            return [];
        }

        $logs = SidUnbanLog::query()
            ->whereIn('scr_daily_banned_id', $scrIds)
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        $indexed = [];
        foreach ($logs as $log) {
            $scrId = (int) $log->scr_daily_banned_id;
            if (! isset($indexed[$scrId])) {
                $indexed[$scrId] = $log;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<int, int>  $scrIds
     * @return array<int, SidUnbanLog>
     */
    private function loadSuccessWeeklyUnbanLogsByScrId(array $scrIds): array
    {
        if ($scrIds === [] || ! AutoBannedSchema::hasSidUnbanLogTable()
            || ! AutoBannedSchema::hasSidUnbanLogScrWeeklyBannedColumn()) {
            return [];
        }

        $logs = SidUnbanLog::query()
            ->whereIn('scr_weekly_banned_id', $scrIds)
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        $indexed = [];
        foreach ($logs as $log) {
            $scrId = (int) $log->scr_weekly_banned_id;
            if (! isset($indexed[$scrId])) {
                $indexed[$scrId] = $log;
            }
        }

        return $indexed;
    }
}
