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
        $unbanLog ??= $this->findSuccessDailyUnbanLog($scrDailyBannedId, $request);

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
        $unbanLog ??= $this->findSuccessWeeklyUnbanLog($scrWeeklyBannedId, $request);

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
        $approvedRequestsByScrId = $this->loadApprovedDailyRequestsByScrId($scrIds);
        $unbanByScrId = $this->loadSuccessDailyUnbanLogsByScrId($scrIds);
        $unbanByRequestId = $this->loadSuccessUnbanLogsByRequestId(
            collect($approvedRequestsByScrId)->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
        );

        return $banLogs->map(function (SidBannedLog $banLog) use ($requestsByScrId, $approvedRequestsByScrId, $unbanByScrId, $unbanByRequestId): SidBannedLog {
            $scrId = $banLog->scr_daily_banned_id !== null ? (int) $banLog->scr_daily_banned_id : null;
            $approvedRequest = $scrId !== null ? ($approvedRequestsByScrId[$scrId] ?? null) : null;
            $unbanLog = $scrId !== null
                ? ($unbanByScrId[$scrId] ?? ($approvedRequest !== null
                    ? ($unbanByRequestId[(int) $approvedRequest->id] ?? null)
                    : null))
                : null;
            $gap = $this->resolveDailyChainGap(
                $scrId,
                $scrId !== null ? ($requestsByScrId[$scrId] ?? null) : null,
                $unbanLog,
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
        $approvedRequestsByScrId = $this->loadApprovedWeeklyRequestsByScrId($scrIds);
        $unbanByScrId = $this->loadSuccessWeeklyUnbanLogsByScrId($scrIds);
        $unbanByRequestId = $this->loadSuccessUnbanLogsByRequestId(
            collect($approvedRequestsByScrId)->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
        );

        return $banLogs->map(function (SidBannedLogWeekly $banLog) use ($requestsByScrId, $approvedRequestsByScrId, $unbanByScrId, $unbanByRequestId): SidBannedLogWeekly {
            $scrId = $banLog->scr_weekly_banned_id !== null ? (int) $banLog->scr_weekly_banned_id : null;
            $approvedRequest = $scrId !== null ? ($approvedRequestsByScrId[$scrId] ?? null) : null;
            $unbanLog = $scrId !== null
                ? ($unbanByScrId[$scrId] ?? ($approvedRequest !== null
                    ? ($unbanByRequestId[(int) $approvedRequest->id] ?? null)
                    : null))
                : null;
            $gap = $this->resolveWeeklyChainGap(
                $scrId,
                $scrId !== null ? ($requestsByScrId[$scrId] ?? null) : null,
                $unbanLog,
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

    private function findSuccessDailyUnbanLog(int $scrDailyBannedId, ?AutoBannedUnbanRequest $request = null): ?SidUnbanLog
    {
        if (! AutoBannedSchema::hasSidUnbanLogTable()) {
            return null;
        }

        $byScr = SidUnbanLog::query()
            ->where('scr_daily_banned_id', $scrDailyBannedId)
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        if ($byScr !== null) {
            return $byScr;
        }

        $request ??= $this->findApprovedDailyRequest($scrDailyBannedId);
        if ($request === null) {
            return null;
        }

        return $this->findSuccessUnbanLogByRequestId((int) $request->id);
    }

    private function findSuccessWeeklyUnbanLog(int $scrWeeklyBannedId, ?AutoBannedUnbanRequest $request = null): ?SidUnbanLog
    {
        if (! AutoBannedSchema::hasSidUnbanLogTable()
            || ! AutoBannedSchema::hasSidUnbanLogScrWeeklyBannedColumn()) {
            return null;
        }

        $byScr = SidUnbanLog::query()
            ->where('scr_weekly_banned_id', $scrWeeklyBannedId)
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        if ($byScr !== null) {
            return $byScr;
        }

        $request ??= $this->findApprovedWeeklyRequest($scrWeeklyBannedId);
        if ($request === null) {
            return null;
        }

        return $this->findSuccessUnbanLogByRequestId((int) $request->id);
    }

    private function findApprovedDailyRequest(int $scrDailyBannedId): ?AutoBannedUnbanRequest
    {
        if (! AutoBannedSchema::hasUnbanRequestsTable()) {
            return null;
        }

        return AutoBannedUnbanRequest::query()
            ->where('scr_daily_banned_id', $scrDailyBannedId)
            ->where('status', AutoBannedUnbanStatus::Approved->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    private function findApprovedWeeklyRequest(int $scrWeeklyBannedId): ?AutoBannedUnbanRequest
    {
        if (! AutoBannedSchema::hasUnbanRequestsTable()
            || ! AutoBannedSchema::hasUnbanRequestScrWeeklyBannedColumn()) {
            return null;
        }

        return AutoBannedUnbanRequest::query()
            ->where('scr_weekly_banned_id', $scrWeeklyBannedId)
            ->where('status', AutoBannedUnbanStatus::Approved->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    private function findSuccessUnbanLogByRequestId(int $unbanRequestId): ?SidUnbanLog
    {
        if (! AutoBannedSchema::hasSidUnbanLogTable()) {
            return null;
        }

        return SidUnbanLog::query()
            ->where('unban_request_id', $unbanRequestId)
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

        $indexed = [];

        $logs = SidUnbanLog::query()
            ->whereIn('scr_daily_banned_id', $scrIds)
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        foreach ($logs as $log) {
            $scrId = (int) $log->scr_daily_banned_id;
            if (! isset($indexed[$scrId])) {
                $indexed[$scrId] = $log;
            }
        }

        $approvedRequestsByScrId = $this->loadApprovedDailyRequestsByScrId($scrIds);
        $unbanByRequestId = $this->loadSuccessUnbanLogsByRequestId(
            collect($approvedRequestsByScrId)->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
        );

        foreach ($approvedRequestsByScrId as $scrId => $request) {
            if (isset($indexed[$scrId])) {
                continue;
            }

            $unbanLog = $unbanByRequestId[(int) $request->id] ?? null;
            if ($unbanLog !== null) {
                $indexed[$scrId] = $unbanLog;
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

        $indexed = [];

        $logs = SidUnbanLog::query()
            ->whereIn('scr_weekly_banned_id', $scrIds)
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        foreach ($logs as $log) {
            $scrId = (int) $log->scr_weekly_banned_id;
            if (! isset($indexed[$scrId])) {
                $indexed[$scrId] = $log;
            }
        }

        $approvedRequestsByScrId = $this->loadApprovedWeeklyRequestsByScrId($scrIds);
        $unbanByRequestId = $this->loadSuccessUnbanLogsByRequestId(
            collect($approvedRequestsByScrId)->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
        );

        foreach ($approvedRequestsByScrId as $scrId => $request) {
            if (isset($indexed[$scrId])) {
                continue;
            }

            $unbanLog = $unbanByRequestId[(int) $request->id] ?? null;
            if ($unbanLog !== null) {
                $indexed[$scrId] = $unbanLog;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<int, int>  $scrIds
     * @return array<int, AutoBannedUnbanRequest>
     */
    private function loadApprovedDailyRequestsByScrId(array $scrIds): array
    {
        if ($scrIds === [] || ! AutoBannedSchema::hasUnbanRequestsTable()) {
            return [];
        }

        $requests = AutoBannedUnbanRequest::query()
            ->whereIn('scr_daily_banned_id', $scrIds)
            ->where('status', AutoBannedUnbanStatus::Approved->value)
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
    private function loadApprovedWeeklyRequestsByScrId(array $scrIds): array
    {
        if ($scrIds === [] || ! AutoBannedSchema::hasUnbanRequestsTable()
            || ! AutoBannedSchema::hasUnbanRequestScrWeeklyBannedColumn()) {
            return [];
        }

        $requests = AutoBannedUnbanRequest::query()
            ->whereIn('scr_weekly_banned_id', $scrIds)
            ->where('status', AutoBannedUnbanStatus::Approved->value)
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
     * @param  array<int, int>  $unbanRequestIds
     * @return array<int, SidUnbanLog>
     */
    private function loadSuccessUnbanLogsByRequestId(array $unbanRequestIds): array
    {
        if ($unbanRequestIds === [] || ! AutoBannedSchema::hasSidUnbanLogTable()) {
            return [];
        }

        $logs = SidUnbanLog::query()
            ->whereIn('unban_request_id', $unbanRequestIds)
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        $indexed = [];
        foreach ($logs as $log) {
            $requestId = (int) $log->unban_request_id;
            if (! isset($indexed[$requestId])) {
                $indexed[$requestId] = $log;
            }
        }

        return $indexed;
    }
}
