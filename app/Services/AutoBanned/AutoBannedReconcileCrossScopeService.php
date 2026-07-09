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
use Illuminate\Support\Collection;

class AutoBannedReconcileCrossScopeService
{
    private const MAX_TICKETS_PER_SID = 5;

    /**
     * @param  Collection<int, SidBannedLog|SidBannedLogWeekly>  $gapRows
     * @return Collection<int, SidBannedLog|SidBannedLogWeekly>
     */
    public function attachCrossScopeTickets(Collection $gapRows, AutoBannedReconcileGapType $gapType): Collection
    {
        if ($gapRows->isEmpty()) {
            return $gapRows;
        }

        $sids = $gapRows
            ->pluck('sid')
            ->map(static fn ($sid): string => strtoupper(trim((string) $sid)))
            ->filter(static fn (string $sid): bool => $sid !== '')
            ->unique()
            ->values()
            ->all();

        if ($sids === []) {
            return $gapRows;
        }

        $otherBySid = $gapType->isWeekly()
            ? $this->summarizeDailyTicketsBySid($sids)
            : $this->summarizeWeeklyTicketsBySid($sids);

        return $gapRows->map(function (SidBannedLog|SidBannedLogWeekly $row) use ($otherBySid): SidBannedLog|SidBannedLogWeekly {
            $sid = strtoupper(trim((string) ($row->sid ?? '')));
            $row->setRelation('reconcileCrossScopeTickets', $otherBySid[$sid] ?? []);

            return $row;
        });
    }

    /**
     * @param  array<int, string>  $sids
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function summarizeDailyTicketsBySid(array $sids): array
    {
        if (! AutoBannedSchema::hasSidBannedLogTable() || $sids === []) {
            return [];
        }

        $eligible = AutoBannedSidAutomationStatus::reconcileEligibleValues();
        $placeholders = implode(',', array_fill(0, count($sids), '?'));

        $logs = SidBannedLog::query()
            ->whereRaw('UPPER(TRIM(sid)) IN ('.$placeholders.')', $sids)
            ->whereIn('automation_status', $eligible)
            ->whereNotNull('filter_date')
            ->orderByDesc('filter_date')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'scr_daily_banned_id',
                'sid',
                'filter_date',
                'banned_status',
                'completed_at',
                'started_at',
            ]);

        return $this->buildSummariesBySid(
            logs: $logs,
            isWeekly: false,
            scrRefKey: 'scr_daily_banned_id',
        );
    }

    /**
     * @param  array<int, string>  $sids
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function summarizeWeeklyTicketsBySid(array $sids): array
    {
        if (! AutoBannedSchema::hasSidBannedLogWeeklyTable() || $sids === []) {
            return [];
        }

        $eligible = AutoBannedSidAutomationStatus::reconcileEligibleValues();
        $placeholders = implode(',', array_fill(0, count($sids), '?'));

        $logs = SidBannedLogWeekly::query()
            ->whereRaw('UPPER(TRIM(sid)) IN ('.$placeholders.')', $sids)
            ->whereIn('automation_status', $eligible)
            ->whereNotNull('filter_date')
            ->orderByDesc('filter_date')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'scr_weekly_banned_id',
                'sid',
                'filter_date',
                'banned_status',
                'completed_at',
                'started_at',
            ]);

        return $this->buildSummariesBySid(
            logs: $logs,
            isWeekly: true,
            scrRefKey: 'scr_weekly_banned_id',
        );
    }

    /**
     * @param  Collection<int, SidBannedLog|SidBannedLogWeekly>  $logs
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildSummariesBySid(Collection $logs, bool $isWeekly, string $scrRefKey): array
    {
        if ($logs->isEmpty()) {
            return [];
        }

        $scrIds = $logs
            ->pluck($scrRefKey)
            ->filter(static fn ($id): bool => $id !== null)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $requestsByScrId = $this->loadLatestRequestsByScrId($scrIds, $isWeekly);
        $unbanByScrId = $this->loadSuccessUnbanLogsByScrId($scrIds, $isWeekly);

        $grouped = [];

        foreach ($logs as $log) {
            $sid = strtoupper(trim((string) ($log->sid ?? '')));
            if ($sid === '') {
                continue;
            }

            if (! isset($grouped[$sid])) {
                $grouped[$sid] = [];
            }

            if (count($grouped[$sid]) >= self::MAX_TICKETS_PER_SID) {
                continue;
            }

            $scrRefId = $log->{$scrRefKey} !== null ? (int) $log->{$scrRefKey} : null;
            $request = $scrRefId !== null ? ($requestsByScrId[$scrRefId] ?? null) : null;
            $hasUnbanLog = $this->hasMatchingUnbanLog(
                scrRefId: $scrRefId,
                isWeekly: $isWeekly,
                unbanByScrId: $unbanByScrId,
            );

            $grouped[$sid][] = $this->formatTicketSummary(
                log: $log,
                isWeekly: $isWeekly,
                scrRefId: $scrRefId,
                request: $request,
                hasUnbanLog: $hasUnbanLog,
            );
        }

        return $grouped;
    }

    /**
     * @param  array<int, int>  $scrIds
     * @return array<int, AutoBannedUnbanRequest>
     */
    private function loadLatestRequestsByScrId(array $scrIds, bool $isWeekly): array
    {
        if ($scrIds === [] || ! AutoBannedSchema::hasUnbanRequestsTable()) {
            return [];
        }

        $scrColumn = $isWeekly ? 'scr_weekly_banned_id' : 'scr_daily_banned_id';

        if ($isWeekly && ! AutoBannedSchema::hasUnbanRequestScrWeeklyBannedColumn()) {
            return [];
        }

        $requests = AutoBannedUnbanRequest::query()
            ->whereIn($scrColumn, $scrIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $indexed = [];
        foreach ($requests as $request) {
            $scrId = (int) $request->{$scrColumn};
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
    private function loadSuccessUnbanLogsByScrId(array $scrIds, bool $isWeekly): array
    {
        if ($scrIds === [] || ! AutoBannedSchema::hasSidUnbanLogTable()) {
            return [];
        }

        $scrColumn = $isWeekly ? 'scr_weekly_banned_id' : 'scr_daily_banned_id';

        if ($isWeekly && ! AutoBannedSchema::hasSidUnbanLogScrWeeklyBannedColumn()) {
            return [];
        }

        $logs = SidUnbanLog::query()
            ->whereIn($scrColumn, $scrIds)
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        $indexed = [];
        foreach ($logs as $log) {
            $scrId = (int) $log->{$scrColumn};
            if (! isset($indexed[$scrId])) {
                $indexed[$scrId] = $log;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<int, SidUnbanLog>  $unbanByScrId
     */
    private function hasMatchingUnbanLog(
        ?int $scrRefId,
        bool $isWeekly,
        array $unbanByScrId,
    ): bool {
        if ($scrRefId === null) {
            return false;
        }

        if ($isWeekly && ! AutoBannedSchema::hasSidUnbanLogScrWeeklyBannedColumn()) {
            return false;
        }

        return isset($unbanByScrId[$scrRefId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTicketSummary(
        SidBannedLog|SidBannedLogWeekly $log,
        bool $isWeekly,
        ?int $scrRefId,
        ?AutoBannedUnbanRequest $request,
        bool $hasUnbanLog,
    ): array {
        $scopeLabel = $isWeekly ? 'Weekly' : 'Daily';
        $ticketRef = trim((string) ($log->banned_status ?? ''));
        $ticketCode = $ticketRef !== '' ? $ticketRef : ($scopeLabel.' #'.$log->id);

        if ($hasUnbanLog) {
            return [
                'scope' => $isWeekly ? 'weekly' : 'daily',
                'scope_label' => $scopeLabel,
                'ban_log_id' => (int) $log->id,
                'scr_ref_id' => $scrRefId,
                'ticket_code' => $ticketCode,
                'filter_date' => $log->filter_date?->format('d M Y') ?? '—',
                'status_label' => 'Selesai',
                'status_tone' => 'ok',
                'gap_type' => null,
                'is_separate_ticket' => true,
                'note' => 'Tiket terpisah — tidak menutup gap '.$scopeLabel.' di tab ini.',
            ];
        }

        if ($request === null) {
            return [
                'scope' => $isWeekly ? 'weekly' : 'daily',
                'scope_label' => $scopeLabel,
                'ban_log_id' => (int) $log->id,
                'scr_ref_id' => $scrRefId,
                'ticket_code' => $ticketCode,
                'filter_date' => $log->filter_date?->format('d M Y') ?? '—',
                'status_label' => 'Tanpa pengajuan',
                'status_tone' => 'warn',
                'gap_type' => $isWeekly
                    ? AutoBannedReconcileGapType::WeeklyNoRequest->value
                    : AutoBannedReconcileGapType::NoRequest->value,
                'is_separate_ticket' => true,
                'note' => 'Perlu rekonsiliasi di tab '.$scopeLabel.' · Tanpa pengajuan.',
            ];
        }

        $status = $request->status instanceof AutoBannedUnbanStatus
            ? $request->status
            : AutoBannedUnbanStatus::tryFrom((string) $request->status);

        if ($status === AutoBannedUnbanStatus::Approved) {
            return [
                'scope' => $isWeekly ? 'weekly' : 'daily',
                'scope_label' => $scopeLabel,
                'ban_log_id' => (int) $log->id,
                'scr_ref_id' => $scrRefId,
                'ticket_code' => $ticketCode,
                'filter_date' => $log->filter_date?->format('d M Y') ?? '—',
                'status_label' => 'Tanpa log unban',
                'status_tone' => 'wait',
                'gap_type' => $isWeekly
                    ? AutoBannedReconcileGapType::WeeklyMissingUnbanLog->value
                    : AutoBannedReconcileGapType::MissingUnbanLog->value,
                'is_separate_ticket' => true,
                'note' => 'Pengajuan Disetujui ada — backfill log di tab '.$scopeLabel.' · Tanpa log unban.',
            ];
        }

        return [
            'scope' => $isWeekly ? 'weekly' : 'daily',
            'scope_label' => $scopeLabel,
            'ban_log_id' => (int) $log->id,
            'scr_ref_id' => $scrRefId,
            'ticket_code' => $ticketCode,
            'filter_date' => $log->filter_date?->format('d M Y') ?? '—',
            'status_label' => $status?->label() ?? 'Pengajuan ada',
            'status_tone' => $status === AutoBannedUnbanStatus::Rejected ? 'danger' : 'info',
            'gap_type' => null,
            'is_separate_ticket' => true,
            'note' => 'Tiket terpisah — selesaikan pengajuan '.$scopeLabel.' lewat alur normal.',
        ];
    }
}
