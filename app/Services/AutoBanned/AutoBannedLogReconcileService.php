<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedReconcileUnbanLogMode;
use App\Enums\AutoBannedSidAutomationStatus;
use App\Enums\AutoBannedUnbanStatus;
use App\Models\AutoBannedUnbanRequest;
use App\Models\SidBannedLog;
use App\Models\SidUnbanLog;
use App\Models\User;
use App\Support\AutoBanned\AutoBannedSchema;
use App\Support\AutoBanned\ScrDailyBannedColumns;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AutoBannedLogReconcileService
{
    public const AUTOMATION_STEP = 'INPUTASI_REKONSILIASI';

    public const DEFAULT_ALASAN = 'Rekonsiliasi manual — unban dilakukan di luar sistem automasi.';

    /**
     * @param  array<int, int>  $banLogIds
     * @return array{processed: int, skipped: int, errors: array<int, string>}
     */
    public function reconcileBanLogs(
        array $banLogIds,
        User $actor,
        string $alasanPengajuan = self::DEFAULT_ALASAN,
        ?Carbon $unbanCompletedAt = null,
        AutoBannedReconcileUnbanLogMode $unbanLogMode = AutoBannedReconcileUnbanLogMode::Success,
    ): array {
        if (! AutoBannedSchema::hasUnbanRequestsTable()) {
            throw ValidationException::withMessages([
                'ban_log_ids' => ['Tabel auto_banned_unban_requests belum tersedia.'],
            ]);
        }

        if ($unbanLogMode->createsUnbanLog() && ! AutoBannedSchema::hasSidUnbanLogTable()) {
            throw ValidationException::withMessages([
                'ban_log_ids' => ['Tabel sid_unban_log belum tersedia.'],
            ]);
        }

        $banLogIds = collect($banLogIds)
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($banLogIds === []) {
            throw ValidationException::withMessages([
                'ban_log_ids' => ['Pilih minimal satu riwayat banned.'],
            ]);
        }

        $alasanPengajuan = trim($alasanPengajuan) !== '' ? trim($alasanPengajuan) : self::DEFAULT_ALASAN;
        $unbanCompletedAt ??= now();

        $processed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($banLogIds as $banLogId) {
            try {
                DB::transaction(function () use ($banLogId, $actor, $alasanPengajuan, $unbanCompletedAt, $unbanLogMode): void {
                    $this->reconcileSingleBanLog($banLogId, $actor, $alasanPengajuan, $unbanCompletedAt, $unbanLogMode);
                });
                $processed++;
            } catch (\Throwable $exception) {
                $skipped++;
                $errors[$banLogId] = $exception->getMessage();
            }
        }

        return array_merge(compact('processed', 'skipped', 'errors'), [
            'unban_log_mode' => $unbanLogMode->value,
        ]);
    }

    private function reconcileSingleBanLog(
        int $banLogId,
        User $actor,
        string $alasanPengajuan,
        Carbon $unbanCompletedAt,
        AutoBannedReconcileUnbanLogMode $unbanLogMode,
    ): void {
        if ($unbanLogMode->requiresExistingRequest()) {
            $this->reconcileUnbanLogOnly($banLogId, $unbanCompletedAt);

            return;
        }

        $banLog = SidBannedLog::query()->find($banLogId);

        if ($banLog === null) {
            throw new \RuntimeException('Riwayat banned #'.$banLogId.' tidak ditemukan.');
        }

        if (! $this->isReconcileEligibleBanLog($banLog)) {
            throw new \RuntimeException('Riwayat banned #'.$banLogId.' bukan status SUCCESS/SKIPPED yang boleh direkonsiliasi.');
        }

        if ($unbanLogMode->createsUnbanLog() && $this->hasMatchingUnbanLog($banLog)) {
            throw new \RuntimeException('Riwayat banned #'.$banLogId.' sudah memiliki log unban SUCCESS.');
        }

        if ($unbanLogMode->createsUnbanRequest() && $this->hasUnbanRequest($banLog)) {
            throw new \RuntimeException('Riwayat banned #'.$banLogId.' sudah memiliki pengajuan unban.');
        }

        if ($banLog->scr_daily_banned_id === null) {
            throw new \RuntimeException('Riwayat banned #'.$banLogId.' tidak terhubung ke scr_daily_banned_id.');
        }

        $sid = strtoupper(trim((string) ($banLog->sid ?? '')));
        if ($sid === '') {
            throw new \RuntimeException('Riwayat banned #'.$banLogId.' tidak memiliki SID.');
        }

        $banLog->loadMissing([
            'scrDailyBanned:id,'.ScrDailyBannedColumns::SID.','.ScrDailyBannedColumns::NAMA.','.ScrDailyBannedColumns::PERUSAHAAN.','.ScrDailyBannedColumns::SITE.','.ScrDailyBannedColumns::BANNED_REASON.','.ScrDailyBannedColumns::BANNED_STATUS,
        ]);

        $reviewedAt = $unbanCompletedAt->copy();
        $submittedAt = $banLog->completed_at?->copy()->addHours(AutoBannedSlaCalculator::AUTOMATION_UNBAN_HOURS)
            ?? $unbanCompletedAt->copy()->subHour();

        if ($submittedAt->greaterThan($reviewedAt)) {
            $submittedAt = $reviewedAt->copy()->subHour();
        }

        $period = $this->resolvePeriodFromBanLog($banLog);
        $actorName = trim((string) ($actor->name ?? 'Admin'));

        $unbanRequest = AutoBannedUnbanRequest::query()->create(
            $this->filterPayloadForTable('auto_banned_unban_requests', [
                'scr_daily_banned_id' => (int) $banLog->scr_daily_banned_id,
                'sid' => $sid,
                'karyawan' => $this->resolveKaryawanName($banLog),
                'perusahaan' => trim((string) ($banLog->perusahaan ?? '')) ?: null,
                'site_dedicated' => $banLog->display_site !== '' ? $banLog->display_site : null,
                'banned_reason' => trim((string) ($banLog->banned_reason ?? '')) ?: null,
                'status_banned_ref' => trim((string) ($banLog->banned_status ?? '')) ?: null,
                'alasan_pengajuan' => $alasanPengajuan,
                'status' => AutoBannedUnbanStatus::Approved->value,
                'week' => $period['week'],
                'iso_year' => $period['year'],
                'submitted_by_id' => $actor->id,
                'submitted_by_name' => $actorName,
                'reviewed_by_id' => $actor->id,
                'reviewed_by_name' => $actorName,
                'reviewed_at' => $reviewedAt,
                'catatan_review' => $unbanLogMode === AutoBannedReconcileUnbanLogMode::BelumSukses
                    ? 'Inputasi rekonsiliasi — pengajuan disetujui, unban belum SUCCESS.'
                    : 'Inputasi rekonsiliasi log manual.',
                'hsct_notified_at' => $reviewedAt,
                'created_at' => $submittedAt,
                'updated_at' => $reviewedAt,
            ]),
        );

        if (! $unbanLogMode->createsUnbanLog()) {
            return;
        }

        SidUnbanLog::query()->create(
            $this->buildUnbanLogPayload($banLog, $unbanCompletedAt, (int) $unbanRequest->id),
        );
    }

    private function reconcileUnbanLogOnly(int $banLogId, Carbon $unbanCompletedAt): void
    {
        $banLog = SidBannedLog::query()->find($banLogId);

        if ($banLog === null) {
            throw new \RuntimeException('Riwayat banned #'.$banLogId.' tidak ditemukan.');
        }

        if (! $this->isReconcileEligibleBanLog($banLog)) {
            throw new \RuntimeException('Riwayat banned #'.$banLogId.' bukan status SUCCESS/SKIPPED yang boleh direkonsiliasi.');
        }

        if ($banLog->scr_daily_banned_id === null) {
            throw new \RuntimeException('Riwayat banned #'.$banLogId.' tidak terhubung ke scr_daily_banned_id.');
        }

        if ($this->hasMatchingUnbanLog($banLog)) {
            throw new \RuntimeException('Riwayat banned #'.$banLogId.' sudah memiliki log unban SUCCESS.');
        }

        $unbanRequest = $this->findApprovedUnbanRequest($banLog);
        if ($unbanRequest === null) {
            throw new \RuntimeException(
                'Riwayat banned #'.$banLogId.' belum memiliki pengajuan Disetujui dengan scr_daily_banned_id yang sama.',
            );
        }

        if ((int) $unbanRequest->scr_daily_banned_id !== (int) $banLog->scr_daily_banned_id) {
            throw new \RuntimeException(
                'scr_daily_banned_id pengajuan (#'.$unbanRequest->id.') tidak cocok dengan banned #'.$banLogId.'.',
            );
        }

        $banLog->loadMissing([
            'scrDailyBanned:id,'.ScrDailyBannedColumns::SID.','.ScrDailyBannedColumns::NAMA.','.ScrDailyBannedColumns::PERUSAHAAN.','.ScrDailyBannedColumns::SITE.','.ScrDailyBannedColumns::BANNED_REASON.','.ScrDailyBannedColumns::BANNED_STATUS,
        ]);

        SidUnbanLog::query()->create(
            $this->buildUnbanLogPayload($banLog, $unbanCompletedAt, (int) $unbanRequest->id),
        );
    }

    private function isReconcileEligibleBanLog(SidBannedLog $banLog): bool
    {
        $status = $banLog->automation_status;

        if ($status instanceof AutoBannedSidAutomationStatus) {
            return $status->isReconcileEligible();
        }

        return in_array(
            strtoupper(trim((string) $status)),
            AutoBannedSidAutomationStatus::reconcileEligibleValues(),
            true,
        );
    }

    private function hasMatchingUnbanLog(SidBannedLog $banLog): bool
    {
        if (! AutoBannedSchema::hasSidUnbanLogTable()) {
            return false;
        }

        $scrId = $banLog->scr_daily_banned_id !== null ? (int) $banLog->scr_daily_banned_id : null;
        $sid = strtoupper(trim((string) ($banLog->sid ?? '')));

        if ($scrId !== null) {
            $byScr = SidUnbanLog::query()
                ->where('scr_daily_banned_id', $scrId)
                ->where('automation_status', AutoBannedSidAutomationStatus::Success->value)
                ->exists();

            if ($byScr) {
                return true;
            }
        }

        if ($sid === '') {
            return false;
        }

        $banCompletedAt = $banLog->completed_at ?? $banLog->started_at;

        $query = SidUnbanLog::query()
            ->whereRaw('UPPER(TRIM(sid)) = ?', [$sid])
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value);

        if ($banCompletedAt !== null) {
            $query->where('completed_at', '>=', $banCompletedAt);
        }

        return $query->exists();
    }

    private function hasUnbanRequest(SidBannedLog $banLog): bool
    {
        return $this->findAnyUnbanRequest($banLog) !== null;
    }

    private function findApprovedUnbanRequest(SidBannedLog $banLog): ?AutoBannedUnbanRequest
    {
        if (! AutoBannedSchema::hasUnbanRequestsTable() || $banLog->scr_daily_banned_id === null) {
            return null;
        }

        return AutoBannedUnbanRequest::query()
            ->where('scr_daily_banned_id', (int) $banLog->scr_daily_banned_id)
            ->where('status', AutoBannedUnbanStatus::Approved->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    private function findAnyUnbanRequest(SidBannedLog $banLog): ?AutoBannedUnbanRequest
    {
        if (! AutoBannedSchema::hasUnbanRequestsTable() || $banLog->scr_daily_banned_id === null) {
            return null;
        }

        return AutoBannedUnbanRequest::query()
            ->where('scr_daily_banned_id', (int) $banLog->scr_daily_banned_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{week: string, year: string}
     */
    private function resolvePeriodFromBanLog(SidBannedLog $banLog): array
    {
        $baseDate = $banLog->filter_date ?? $banLog->completed_at ?? now();

        return [
            'week' => 'W'.$baseDate->isoWeek(),
            'year' => (string) $baseDate->isoWeekYear(),
        ];
    }

    private function resolveKaryawanName(SidBannedLog $banLog): string
    {
        $nama = trim((string) ($banLog->nama ?? ''));
        if ($nama !== '') {
            return $nama;
        }

        $scrNama = trim((string) ($banLog->scrDailyBanned?->{ScrDailyBannedColumns::NAMA} ?? ''));

        return $scrNama !== '' ? $scrNama : strtoupper(trim((string) ($banLog->sid ?? 'SID')));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUnbanLogPayload(SidBannedLog $banLog, Carbon $unbanCompletedAt, int $unbanRequestId): array
    {
        $startedAt = $banLog->completed_at?->copy()->addHours(AutoBannedSlaCalculator::AUTOMATION_UNBAN_HOURS)
            ?? $unbanCompletedAt->copy()->subMinutes(5);

        if ($startedAt->greaterThan($unbanCompletedAt)) {
            $startedAt = $unbanCompletedAt->copy()->subMinutes(5);
        }

        return $this->filterPayloadForTable('sid_unban_log', [
            'unban_request_id' => $unbanRequestId,
            'scr_daily_banned_id' => $banLog->scr_daily_banned_id,
            'filter_date' => $banLog->filter_date,
            'filter_shift' => $banLog->filter_shift,
            'nik' => $banLog->nik,
            'sid' => strtoupper(trim((string) ($banLog->sid ?? ''))),
            'nama' => $this->resolveKaryawanName($banLog),
            'perusahaan' => $banLog->perusahaan,
            'site_dedicated' => $banLog->site_dedicated,
            'banned_status' => $banLog->banned_status,
            'banned_reason' => $banLog->banned_reason,
            'status_onsite' => $banLog->status_onsite,
            'work_permit_kategori' => $banLog->work_permit_kategori ?? null,
            'work_permit_jenis' => $banLog->work_permit_jenis ?? null,
            'automation_status' => AutoBannedSidAutomationStatus::Success->value,
            'automation_step' => self::AUTOMATION_STEP,
            'started_at' => $startedAt,
            'completed_at' => $unbanCompletedAt,
            'created_at' => $unbanCompletedAt,
            'updated_at' => $unbanCompletedAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterPayloadForTable(string $table, array $payload): array
    {
        static $columnCache = [];

        if (! isset($columnCache[$table])) {
            $columnCache[$table] = Schema::hasTable($table)
                ? Schema::getColumnListing($table)
                : [];
        }

        return array_intersect_key(
            $payload,
            array_flip($columnCache[$table]),
        );
    }
}
