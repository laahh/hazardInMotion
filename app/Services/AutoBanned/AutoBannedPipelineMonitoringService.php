<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedPipelineStage;
use App\Enums\AutoBannedSidAutomationStatus;
use App\Enums\AutoBannedUnbanStatus;
use App\Models\AutoBannedUnbanRequest;
use App\Models\SidBannedLog;
use App\Models\SidUnbanLog;
use App\Support\AutoBanned\AutoBannedSchema;
use App\Support\AutoBanned\ScrDailyBannedColumns;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AutoBannedPipelineMonitoringService
{
    public function __construct(
        private readonly AutoBannedSlaCalculator $slaCalculator,
    ) {}

    /**
     * @return array{
     *     filter_date: string,
     *     site: string,
     *     perusahaan: string,
     *     pipeline_stage: string,
     *     q: string
     * }
     */
    public function resolveFilters(Request $request): array
    {
        return [
            'filter_date' => trim((string) $request->query('filter_date', '')),
            'site' => trim((string) $request->query('site', '')),
            'perusahaan' => trim((string) $request->query('perusahaan', '')),
            'pipeline_stage' => trim((string) $request->query('pipeline_stage', '')),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    public function bannedLogTableAvailable(): bool
    {
        return AutoBannedSchema::hasSidBannedLogTable();
    }

    /**
     * @param  array{
     *     filter_date?: string,
     *     site?: string,
     *     perusahaan?: string,
     *     pipeline_stage?: string,
     *     q?: string
     * }  $filters
     * @return array{
     *     filters: array<string, string>,
     *     period: array{filter_date: string},
     *     filterOptions: array<string, Collection>,
     *     stats: array<string, int|float>,
     *     pipelineRows: Collection<int, array<string, mixed>>,
     *     tableAvailable: bool
     * }
     */
    public function buildDashboard(array $filters): array
    {
        if (! $this->bannedLogTableAvailable()) {
            return [
                'filters' => array_merge($this->emptyFilters(), $filters),
                'period' => ['filter_date' => ''],
                'filterOptions' => $this->emptyFilterOptions(),
                'stats' => $this->emptyStats(),
                'pipelineRows' => collect(),
                'tableAvailable' => false,
            ];
        }

        $resolvedFilters = array_merge($this->emptyFilters(), $filters);
        $banLogs = $this->fetchBanLogs($resolvedFilters);
        $pipelineRows = $this->buildPipelineRows($banLogs);

        if (($resolvedFilters['pipeline_stage'] ?? '') !== ''
            && $resolvedFilters['pipeline_stage'] !== 'all') {
            $stageFilter = $resolvedFilters['pipeline_stage'];
            $pipelineRows = $pipelineRows->filter(
                static fn (array $row): bool => ($row['pipelineStage'] ?? '') === $stageFilter
            )->values();
        }

        if (($resolvedFilters['pipeline_stage'] ?? '') === 'overdue') {
            $pipelineRows = $pipelineRows->filter(
                static fn (array $row): bool => ($row['isOverdue'] ?? false) === true
                    && ($row['pipelineStage'] ?? '') !== AutoBannedPipelineStage::Unbanned->value
            )->values();
        }

        return [
            'filters' => $resolvedFilters,
            'period' => ['filter_date' => $resolvedFilters['filter_date']],
            'filterOptions' => $this->filterOptions(),
            'stats' => $this->buildStats($pipelineRows),
            'pipelineRows' => $pipelineRows,
            'tableAvailable' => true,
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Collection<int, SidBannedLog>
     */
    private function fetchBanLogs(array $filters): Collection
    {
        $query = SidBannedLog::query()
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value);

        if (($filters['filter_date'] ?? '') !== '') {
            $query->whereDate('filter_date', $filters['filter_date']);
        }

        if (($filters['site'] ?? '') !== '') {
            $site = $filters['site'];
            $query->where(function (Builder $inner) use ($site): void {
                $inner->where('site_dedicated', $site);

                if (AutoBannedSchema::hasScrDailyBannedTable()) {
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

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('sid', 'like', $term)
                    ->orWhere('nama', 'like', $term)
                    ->orWhere('nik', 'like', $term)
                    ->orWhere('perusahaan', 'like', $term)
                    ->orWhere('banned_reason', 'like', $term);
            });
        }

        if (AutoBannedSchema::hasScrDailyBannedTable()) {
            $query->with([
                'scrDailyBanned:id,filter_date,'.ScrDailyBannedColumns::SITE.','.ScrDailyBannedColumns::BANNED_STATUS,
            ]);
        }

        return $query
            ->orderByDesc('filter_date')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(1000)
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
     * @return Collection<int, array<string, mixed>>
     */
    private function buildPipelineRows(Collection $banLogs): Collection
    {
        if ($banLogs->isEmpty()) {
            return collect();
        }

        $scrIds = $banLogs
            ->pluck('scr_daily_banned_id')
            ->filter(static fn ($id): bool => $id !== null)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $sids = $banLogs
            ->pluck('sid')
            ->map(static fn ($sid): string => strtoupper(trim((string) $sid)))
            ->filter(static fn (string $sid): bool => $sid !== '')
            ->unique()
            ->values()
            ->all();

        $latestRequests = $this->latestUnbanRequestsByScrId($scrIds);
        $unbanLogsByScrId = $this->unbanLogsByScrId($scrIds);
        $unbanLogsBySid = $this->unbanLogsBySid($sids);

        $now = now()->timezone(config('app.timezone'));

        return $banLogs->map(function (SidBannedLog $banLog) use (
            $latestRequests,
            $unbanLogsByScrId,
            $unbanLogsBySid,
            $now,
        ): array {
            $scrId = $banLog->scr_daily_banned_id !== null ? (int) $banLog->scr_daily_banned_id : null;
            $sid = strtoupper(trim((string) ($banLog->sid ?? '')));

            /** @var AutoBannedUnbanRequest|null $unbanRequest */
            $unbanRequest = $scrId !== null ? ($latestRequests[$scrId] ?? null) : null;

            $unbanLog = $this->resolveMatchingUnbanLog(
                $banLog,
                $scrId,
                $sid,
                $unbanLogsByScrId,
                $unbanLogsBySid,
            );

            $pipeline = $this->resolvePipelineStage($unbanRequest, $unbanLog);
            $deadline = $this->resolveDeadline($banLog, $unbanRequest, $unbanLog, $pipeline, $now);

            return [
                'banLogId' => (int) $banLog->id,
                'scrDailyBannedId' => $scrId,
                'filterDate' => $banLog->filter_date?->format('d M Y'),
                'filterShift' => trim((string) ($banLog->filter_shift ?? '')),
                'sid' => $sid,
                'nik' => trim((string) ($banLog->nik ?? '')),
                'nama' => trim((string) ($banLog->nama ?? '')),
                'perusahaan' => trim((string) ($banLog->perusahaan ?? '')),
                'site' => $banLog->display_site,
                'bannedStatus' => trim((string) ($banLog->banned_status ?? '')),
                'bannedReason' => trim((string) ($banLog->banned_reason ?? '')),
                'bannedAt' => $banLog->completed_at?->format('d M Y H:i'),
                'bannedAtRaw' => $banLog->completed_at,
                'pipelineStage' => $pipeline->value,
                'pipelineLabel' => $pipeline->label(),
                'pipelineBadgeClass' => $pipeline->badgeClass(),
                'requestStatus' => $unbanRequest?->status?->value,
                'requestStatusLabel' => $unbanRequest?->status?->label(),
                'requestId' => $unbanRequest?->id,
                'requestSubmittedAt' => $unbanRequest?->created_at?->format('d M Y H:i'),
                'requestReviewedAt' => $unbanRequest?->reviewed_at?->format('d M Y H:i'),
                'requestReviewedBy' => trim((string) ($unbanRequest?->reviewed_by_name ?? '')),
                'hsctNotifiedAt' => $unbanRequest?->hsct_notified_at?->format('d M Y H:i'),
                'hasRequest' => $unbanRequest !== null,
                'isApproved' => $unbanRequest?->status === AutoBannedUnbanStatus::Approved,
                'unbanCompletedAt' => $unbanLog?->completed_at?->format('d M Y H:i'),
                'nextActionLabel' => $deadline['nextActionLabel'],
                'dueAt' => $deadline['dueAt'],
                'dueAtLabel' => $deadline['dueAtLabel'],
                'remainingLabel' => $deadline['remainingLabel'],
                'isOverdue' => $deadline['isOverdue'],
                'dueTone' => $deadline['dueTone'],
            ];
        });
    }

    /**
     * @param  array<int, int>  $scrIds
     * @return array<int, AutoBannedUnbanRequest>
     */
    private function latestUnbanRequestsByScrId(array $scrIds): array
    {
        if ($scrIds === [] || ! AutoBannedSchema::hasUnbanRequestsTable()) {
            return [];
        }

        $requests = AutoBannedUnbanRequest::query()
            ->whereIn('scr_daily_banned_id', $scrIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $map = [];
        foreach ($requests as $request) {
            $scrId = (int) $request->scr_daily_banned_id;
            if (! isset($map[$scrId])) {
                $map[$scrId] = $request;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $scrIds
     * @return array<int, SidUnbanLog>
     */
    private function unbanLogsByScrId(array $scrIds): array
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

        $map = [];
        foreach ($logs as $log) {
            $scrId = (int) $log->scr_daily_banned_id;
            if (! isset($map[$scrId])) {
                $map[$scrId] = $log;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $sids
     * @return array<string, Collection<int, SidUnbanLog>>
     */
    private function unbanLogsBySid(array $sids): array
    {
        if ($sids === [] || ! AutoBannedSchema::hasSidUnbanLogTable()) {
            return [];
        }

        $logs = SidUnbanLog::query()
            ->whereIn('sid', $sids)
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(static fn (SidUnbanLog $log): string => strtoupper(trim((string) $log->sid)));

        return $logs->all();
    }

    /**
     * @param  array<int, SidUnbanLog>  $unbanLogsByScrId
     * @param  array<string, Collection<int, SidUnbanLog>>  $unbanLogsBySid
     */
    private function resolveMatchingUnbanLog(
        SidBannedLog $banLog,
        ?int $scrId,
        string $sid,
        array $unbanLogsByScrId,
        array $unbanLogsBySid,
    ): ?SidUnbanLog {
        if ($scrId !== null && isset($unbanLogsByScrId[$scrId])) {
            return $unbanLogsByScrId[$scrId];
        }

        if ($sid === '' || ! isset($unbanLogsBySid[$sid])) {
            return null;
        }

        $banCompletedAt = $banLog->completed_at;
        if ($banCompletedAt === null) {
            return $unbanLogsBySid[$sid]->first();
        }

        return $unbanLogsBySid[$sid]->first(
            static fn (SidUnbanLog $log): bool => $log->completed_at !== null
                && $log->completed_at->greaterThanOrEqualTo($banCompletedAt),
        );
    }

    private function resolvePipelineStage(
        ?AutoBannedUnbanRequest $unbanRequest,
        ?SidUnbanLog $unbanLog,
    ): AutoBannedPipelineStage {
        if ($unbanLog !== null) {
            return AutoBannedPipelineStage::Unbanned;
        }

        if ($unbanRequest === null) {
            return AutoBannedPipelineStage::NoRequest;
        }

        return match ($unbanRequest->status) {
            AutoBannedUnbanStatus::Pending => AutoBannedPipelineStage::RequestPending,
            AutoBannedUnbanStatus::Approved => AutoBannedPipelineStage::AwaitingUnban,
            AutoBannedUnbanStatus::Rejected => AutoBannedPipelineStage::RequestRejected,
        };
    }

    /**
     * @return array{
     *     nextActionLabel: string,
     *     dueAt: ?CarbonInterface,
     *     dueAtLabel: string,
     *     remainingLabel: string,
     *     isOverdue: bool,
     *     dueTone: string
     * }
     */
    private function resolveDeadline(
        SidBannedLog $banLog,
        ?AutoBannedUnbanRequest $unbanRequest,
        ?SidUnbanLog $unbanLog,
        AutoBannedPipelineStage $pipeline,
        CarbonInterface $now,
    ): array {
        if ($pipeline === AutoBannedPipelineStage::Unbanned && $unbanLog?->completed_at !== null) {
            return [
                'nextActionLabel' => 'Selesai — sudah di-unban',
                'dueAt' => $unbanLog->completed_at,
                'dueAtLabel' => $unbanLog->completed_at->format('d M Y H:i'),
                'remainingLabel' => '—',
                'isOverdue' => false,
                'dueTone' => 'ok',
            ];
        }

        $dueAt = match ($pipeline) {
            AutoBannedPipelineStage::NoRequest,
            AutoBannedPipelineStage::RequestRejected => $this->addDays(
                $banLog->completed_at ?? $banLog->started_at ?? $banLog->filter_date?->startOfDay(),
                AutoBannedSlaCalculator::TREATMENT_DEADLINE_DAYS,
            ),
            AutoBannedPipelineStage::RequestPending => $this->addDays(
                $unbanRequest?->created_at,
                AutoBannedSlaCalculator::VERIFICATION_DEADLINE_DAYS,
            ),
            AutoBannedPipelineStage::AwaitingUnban => $this->addDays(
                $unbanRequest?->reviewed_at ?? $unbanRequest?->created_at,
                AutoBannedSlaCalculator::UNBAN_SLA_DAYS,
            ),
            default => null,
        };

        $nextActionLabel = match ($pipeline) {
            AutoBannedPipelineStage::NoRequest => 'Karyawan harus ajukan treatment',
            AutoBannedPipelineStage::RequestRejected => 'Ajukan ulang treatment',
            AutoBannedPipelineStage::RequestPending => 'SOD harus review pengajuan',
            AutoBannedPipelineStage::AwaitingUnban => 'Target unban fisik / HSECT',
            default => '—',
        };

        if ($dueAt === null) {
            return [
                'nextActionLabel' => $nextActionLabel,
                'dueAt' => null,
                'dueAtLabel' => '—',
                'remainingLabel' => '—',
                'isOverdue' => false,
                'dueTone' => 'muted',
            ];
        }

        $isOverdue = $now->greaterThan($dueAt);
        $remainingLabel = $this->formatRemaining($now, $dueAt, $isOverdue);

        return [
            'nextActionLabel' => $nextActionLabel,
            'dueAt' => $dueAt,
            'dueAtLabel' => $dueAt->format('d M Y H:i'),
            'remainingLabel' => $remainingLabel,
            'isOverdue' => $isOverdue,
            'dueTone' => $isOverdue ? 'danger' : 'wait',
        ];
    }

    private function addDays(mixed $base, int $days): ?CarbonInterface
    {
        if ($base === null) {
            return null;
        }

        $carbon = $base instanceof CarbonInterface
            ? $base->copy()
            : Carbon::parse((string) $base);

        return $carbon->addDays($days);
    }

    private function formatRemaining(CarbonInterface $now, CarbonInterface $dueAt, bool $isOverdue): string
    {
        if ($isOverdue) {
            $hours = (int) $dueAt->diffInHours($now);
            if ($hours >= 24) {
                return 'Lewat '.(int) floor($hours / 24).' hari';
            }

            return 'Lewat '.$hours.' jam';
        }

        if ($dueAt->isToday()) {
            return 'Hari ini';
        }

        if ($dueAt->isTomorrow()) {
            return 'Besok';
        }

        $days = (int) $now->copy()->startOfDay()->diffInDays($dueAt->copy()->startOfDay());

        return $days.' hari lagi';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int|float>
     */
    private function buildStats(Collection $rows): array
    {
        $stats = [
            'total' => $rows->count(),
            'unbanned' => 0,
            'no_request' => 0,
            'request_pending' => 0,
            'awaiting_unban' => 0,
            'request_rejected' => 0,
            'overdue' => 0,
            'with_request' => 0,
            'approved' => 0,
        ];

        foreach ($rows as $row) {
            $stage = (string) ($row['pipelineStage'] ?? '');
            if (isset($stats[$stage])) {
                $stats[$stage]++;
            }

            if (($row['hasRequest'] ?? false) === true) {
                $stats['with_request']++;
            }

            if (($row['isApproved'] ?? false) === true) {
                $stats['approved']++;
            }

            if (($row['isOverdue'] ?? false) === true && $stage !== AutoBannedPipelineStage::Unbanned->value) {
                $stats['overdue']++;
            }
        }

        return $stats;
    }

    /**
     * @return array<string, Collection>
     */
    private function filterOptions(): array
    {
        $dates = SidBannedLog::query()
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->selectRaw('DATE(filter_date) as ban_date')
            ->whereNotNull('filter_date')
            ->distinct()
            ->orderByDesc('ban_date')
            ->pluck('ban_date')
            ->map(static fn ($date) => $date instanceof Carbon ? $date->toDateString() : (string) $date)
            ->values();

        $sites = SidBannedLog::query()
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->whereNotNull('site_dedicated')
            ->where('site_dedicated', '!=', '')
            ->select('site_dedicated')
            ->distinct()
            ->orderBy('site_dedicated')
            ->pluck('site_dedicated')
            ->values();

        $perusahaan = SidBannedLog::query()
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
            ->whereNotNull('perusahaan')
            ->where('perusahaan', '!=', '')
            ->select('perusahaan')
            ->distinct()
            ->orderBy('perusahaan')
            ->pluck('perusahaan')
            ->values();

        $pipelineStages = collect(AutoBannedPipelineStage::cases())
            ->map(static fn (AutoBannedPipelineStage $stage): array => [
                'value' => $stage->value,
                'label' => $stage->label(),
            ])
            ->prepend(['value' => 'overdue', 'label' => 'Lewat Deadline'])
            ->prepend(['value' => 'all', 'label' => 'Semua Tahapan']);

        return [
            'dates' => $dates,
            'sites' => $sites,
            'perusahaan' => $perusahaan,
            'pipelineStages' => $pipelineStages,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function emptyFilters(): array
    {
        return [
            'filter_date' => '',
            'site' => '',
            'perusahaan' => '',
            'pipeline_stage' => '',
            'q' => '',
        ];
    }

    /**
     * @return array<string, Collection>
     */
    private function emptyFilterOptions(): array
    {
        return [
            'dates' => collect(),
            'sites' => collect(),
            'perusahaan' => collect(),
            'pipelineStages' => collect(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function emptyStats(): array
    {
        return [
            'total' => 0,
            'unbanned' => 0,
            'no_request' => 0,
            'request_pending' => 0,
            'awaiting_unban' => 0,
            'request_rejected' => 0,
            'overdue' => 0,
            'with_request' => 0,
            'approved' => 0,
        ];
    }
}
