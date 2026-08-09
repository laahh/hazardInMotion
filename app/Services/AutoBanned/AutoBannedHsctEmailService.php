<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedBanStatus;
use App\Enums\AutoBannedHsctCampaignStatus;
use App\Enums\AutoBannedHsctEmailType;
use App\Enums\AutoBannedHsctSyncStatus;
use App\Enums\AutoBannedUnbanStatus;
use App\Mail\AutoBannedHsctEmailMail;
use App\Models\AutoBannedHsctCampaign;
use App\Models\AutoBannedHsctCampaignItem;
use App\Models\AutoBannedHsctEmailLog;
use App\Models\AutoBannedStatusSnapshot;
use App\Models\AutoBannedUnbanRequest;
use App\Models\ScrAutoBannedTbcSap;
use App\Services\Mail\ResilientSmtpMailService;
use App\Support\AutoBanned\AutoBannedSchema;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AutoBannedHsctEmailService
{
    public function __construct(
        private readonly AutoBannedStatusNormalizer $normalizer,
        private readonly AutoBannedHsctListExcelExporter $excelExporter,
        private readonly AutoBannedScrapPollService $scrapPollService,
        private readonly AutoBannedOverviewService $overviewService,
        private readonly ResilientSmtpMailService $resilientMail,
    ) {}

    public function tablesAvailable(): bool
    {
        return Schema::hasTable('auto_banned_hsct_campaigns')
            && Schema::hasTable('auto_banned_hsct_campaign_items')
            && Schema::hasTable('auto_banned_hsct_email_logs');
    }

    /**
     * @return array{action: string, message: string, sent: bool}
     */
    public function runScheduled(?string $week = null, ?string $year = null, bool $force = false): array
    {
        if (! $this->tablesAvailable()) {
            return ['action' => 'skip', 'message' => 'Tabel campaign email belum tersedia.', 'sent' => false];
        }

        $period = $this->resolvePeriod($week, $year);
        $now = Carbon::now(config('auto_banned.hsct.timezone', 'Asia/Makassar'));
        $campaign = $this->findCampaign($period['week'], $period['year']);

        if ($campaign === null && ($force || $this->isInitialSendDay($now))) {
            return $this->sendInitialEmail($period['week'], $period['year'], $force);
        }

        if ($campaign !== null && $campaign->status === AutoBannedHsctCampaignStatus::Active) {
            $this->syncNewItemsToCampaign($campaign);
            $campaign->refresh();

            if ($force || ! $this->isInitialSendDay($now)) {
                return $this->sendReminderEmail($campaign, $force);
            }

            return ['action' => 'skip', 'message' => 'Hari Selasa — email awal sudah ada untuk periode ini.', 'sent' => false];
        }

        if ($campaign?->status === AutoBannedHsctCampaignStatus::Completed) {
            return ['action' => 'skip', 'message' => 'Semua SID sudah dikonfirmasi banned.', 'sent' => false];
        }

        return ['action' => 'skip', 'message' => 'Belum waktunya email awal (Selasa).', 'sent' => false];
    }

    /**
     * @return array{action: string, message: string, sent: bool}
     */
    public function sendInitialEmail(string $week, string $year, bool $force = false): array
    {
        if (! $force && $this->findCampaign($week, $year) !== null) {
            return ['action' => 'skip', 'message' => 'Campaign periode ini sudah ada.', 'sent' => false];
        }

        $recipients = $this->recipients();
        if ($recipients === []) {
            return ['action' => 'error', 'message' => 'AUTO_BANNED_HSCT_EMAILS belum dikonfigurasi.', 'sent' => false];
        }

        $sourceItems = $this->resolveInitialBannedSourceItems($week, $year);
        if ($sourceItems->isEmpty()) {
            return ['action' => 'skip', 'message' => 'Tidak ada data banned untuk dikirim.', 'sent' => false];
        }

        $lockSeconds = (int) config('auto_banned.hsct.send_lock_seconds', 600);
        $lock = Cache::lock('auto_banned:hsct-email-send', $lockSeconds);

        if (! $lock->get()) {
            return [
                'action' => 'skip',
                'message' => 'Proses kirim email HSECT sedang berjalan di proses lain. Coba lagi setelah selesai.',
                'sent' => false,
            ];
        }

        try {
            if ($force) {
                $this->deleteExistingCampaignWithRetry($week, $year);
            }

            $campaign = DB::transaction(function () use ($week, $year, $sourceItems): AutoBannedHsctCampaign {
                $campaign = AutoBannedHsctCampaign::query()->create([
                    'week' => $week,
                    'iso_year' => $year,
                    'status' => AutoBannedHsctCampaignStatus::Active,
                    'total_items' => $sourceItems->count(),
                    'confirmed_items' => 0,
                    'reminder_count' => 0,
                    'initial_sent_at' => now(),
                ]);

                $this->insertCampaignItems($campaign, $sourceItems);

                return $campaign;
            });

            $this->markSnapshotsSentBulk($sourceItems, $week, $year);

            $payload = $this->formatPayload($sourceItems);
            $sent = $this->dispatchMail(
                recipients: $recipients,
                emailType: AutoBannedHsctEmailType::Initial,
                reminderNumber: 1,
                week: $week,
                year: $year,
                employees: $payload,
                totalInitial: $sourceItems->count(),
                confirmedCount: 0,
                pendingCount: $sourceItems->count(),
            );

            AutoBannedHsctEmailLog::query()->create([
                'campaign_id' => $campaign->id,
                'email_type' => AutoBannedHsctEmailType::Initial,
                'reminder_number' => 1,
                'week' => $week,
                'iso_year' => $year,
                'recipients' => implode(', ', $recipients),
                'total_in_list' => $sourceItems->count(),
                'pending_count' => $sourceItems->count(),
                'confirmed_count' => 0,
                'payload' => $payload,
                'status' => $sent ? 'sent' : 'failed',
                'error_message' => $sent ? null : 'Gagal kirim email',
                'sent_at' => now(),
            ]);

            return [
                'action' => 'initial',
                'message' => $sent
                    ? "Email awal terkirim ke HSECT ({$sourceItems->count()} SID)."
                    : 'Campaign dibuat tetapi email gagal dikirim.',
                'sent' => $sent,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{action: string, message: string, sent: bool}
     */
    public function sendReminderEmail(AutoBannedHsctCampaign $campaign, bool $force = false): array
    {
        $recipients = $this->recipients();
        if ($recipients === []) {
            return ['action' => 'error', 'message' => 'AUTO_BANNED_HSCT_EMAILS belum dikonfigurasi.', 'sent' => false];
        }

        $this->syncCampaignConfirmation($campaign);
        $campaign->refresh();

        if ($campaign->status === AutoBannedHsctCampaignStatus::Completed) {
            return ['action' => 'completed', 'message' => 'Semua SID sudah banned — campaign ditutup.', 'sent' => false];
        }

        $timezone = config('auto_banned.hsct.timezone', 'Asia/Makassar');
        $pendingItems = $campaign->items()
            ->where('is_confirmed', false)
            ->get()
            ->filter(function (AutoBannedHsctCampaignItem $item) use ($force, $timezone, $campaign): bool {
                $snapshot = $this->resolveItemSnapshot($item, $campaign);

                if ($snapshot === null) {
                    return false;
                }

                if ($snapshot->hsct_sync_status !== AutoBannedHsctSyncStatus::Sent) {
                    return false;
                }

                if ($snapshot->hsct_confirmed_at !== null) {
                    return false;
                }

                if (! $force && $snapshot->hsct_sent_at !== null) {
                    $sentAt = $snapshot->hsct_sent_at->timezone($timezone);

                    if ($sentAt->isToday()) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        if ($pendingItems->isEmpty()) {
            return [
                'action' => 'skip',
                'message' => 'Tidak ada SID eligible untuk reminder (semua sudah terbanned atau baru dikirim hari ini).',
                'sent' => false,
            ];
        }

        if (! $force && $campaign->last_reminder_at !== null && $campaign->last_reminder_at->isToday()) {
            return ['action' => 'skip', 'message' => 'Reminder hari ini sudah dikirim.', 'sent' => false];
        }

        $reminderNumber = $campaign->reminder_count + 2;
        $payload = $pendingItems->map(fn (AutoBannedHsctCampaignItem $item) => [
            'sid' => $item->sid,
            'karyawan' => $item->karyawan,
            'site' => $item->site_dedicated,
            'perusahaan' => $item->perusahaan,
            'reason' => $item->banned_reason,
        ])->values()->all();

        $sent = $this->dispatchMail(
            recipients: $recipients,
            emailType: AutoBannedHsctEmailType::Reminder,
            reminderNumber: $reminderNumber,
            week: $campaign->week,
            year: $campaign->iso_year,
            employees: $payload,
            totalInitial: $campaign->total_items,
            confirmedCount: $campaign->confirmed_items,
            pendingCount: $pendingItems->count(),
        );

        AutoBannedHsctEmailLog::query()->create([
            'campaign_id' => $campaign->id,
            'email_type' => AutoBannedHsctEmailType::Reminder,
            'reminder_number' => $reminderNumber,
            'week' => $campaign->week,
            'iso_year' => $campaign->iso_year,
            'recipients' => implode(', ', $recipients),
            'total_in_list' => $pendingItems->count(),
            'pending_count' => $pendingItems->count(),
            'confirmed_count' => $campaign->confirmed_items,
            'payload' => $payload,
            'status' => $sent ? 'sent' : 'failed',
            'error_message' => $sent ? null : 'Gagal kirim email',
            'sent_at' => now(),
        ]);

        $campaign->update([
            'reminder_count' => $campaign->reminder_count + 1,
            'last_reminder_at' => now(),
        ]);

        return [
            'action' => 'reminder',
            'message' => $sent
                ? "Reminder #{$reminderNumber} terkirim ({$pendingItems->count()} SID belum banned)."
                : "Reminder #{$reminderNumber} gagal dikirim.",
            'sent' => $sent,
        ];
    }

    public function syncNewItemsToCampaign(AutoBannedHsctCampaign $campaign): int
    {
        $existingSids = $campaign->items()->pluck('sid')->all();
        $newItems = $this->resolveBannedSourceItems($campaign->week, $campaign->iso_year)
            ->filter(fn (array $item): bool => ! in_array($item['sid'], $existingSids, true));

        if ($newItems->isEmpty()) {
            return 0;
        }

        foreach ($newItems as $item) {
            AutoBannedHsctCampaignItem::query()->create([
                'campaign_id' => $campaign->id,
                'snapshot_id' => $item['snapshot_id'],
                'sid' => $item['sid'],
                'karyawan' => $item['karyawan'],
                'perusahaan' => $item['perusahaan'],
                'site_dedicated' => $item['site'],
                'banned_reason' => $item['reason'],
                'is_confirmed' => false,
            ]);

            if ($item['snapshot_id'] !== null) {
                $this->markSnapshotSent($item['sid'], $campaign->week, $campaign->iso_year, $item['snapshot_id']);
            } else {
                $this->markSnapshotSent($item['sid'], $campaign->week, $campaign->iso_year, null);
            }
        }

        $campaign->update([
            'total_items' => $campaign->items()->count(),
        ]);

        return $newItems->count();
    }

    public function syncCampaignConfirmation(AutoBannedHsctCampaign $campaign): void
    {
        $items = $campaign->items()->get();

        foreach ($items as $item) {
            if ($item->is_confirmed) {
                continue;
            }

            $snapshot = $this->resolveItemSnapshot($item, $campaign);

            if ($snapshot !== null && $snapshot->hsct_sync_status === AutoBannedHsctSyncStatus::Confirmed) {
                $item->update([
                    'is_confirmed' => true,
                    'confirmed_at' => $snapshot->hsct_confirmed_at ?? now(),
                ]);
            }
        }

        $confirmed = $campaign->items()->where('is_confirmed', true)->count();
        $campaign->update(['confirmed_items' => $confirmed]);

        if ($confirmed >= $campaign->total_items && $campaign->total_items > 0) {
            $campaign->update([
                'status' => AutoBannedHsctCampaignStatus::Completed,
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * List lengkap untuk email awal — semua baris scrape periode, tanpa cek status HSECT/banned.
     *
     * @return Collection<int, array{snapshot_id: ?int, sid: string, karyawan: string, site: string, perusahaan: string, reason: string}>
     */
    public function resolveInitialBannedSourceItems(string $week, string $year): Collection
    {
        if (! AutoBannedSchema::hasScrapTable()) {
            return collect();
        }

        return $this->buildItemsFromScrapTable($week, $year, filterByHsctStatus: false);
    }

    /**
     * List untuk reminder / SID baru — hanya yang belum terkirim/terkonfirmasi HSECT.
     *
     * @return Collection<int, array{snapshot_id: ?int, sid: string, karyawan: string, site: string, perusahaan: string, reason: string}>
     */
    public function resolveBannedSourceItems(string $week, string $year): Collection
    {
        if (AutoBannedSchema::hasScrapTable()) {
            $scrapItems = $this->buildItemsFromScrapTable($week, $year, filterByHsctStatus: true);

            if ($scrapItems->isNotEmpty()) {
                return $scrapItems;
            }
        }

        if (! Schema::hasTable('auto_banned_status_snapshots')) {
            return collect();
        }

        return AutoBannedStatusSnapshot::query()
            ->where('week', $week)
            ->where('iso_year', $year)
            ->where('hsct_sync_status', AutoBannedHsctSyncStatus::Pending)
            ->orderBy('karyawan')
            ->get()
            ->map(fn (AutoBannedStatusSnapshot $s) => [
                'snapshot_id' => $s->id,
                'sid' => $s->sid,
                'karyawan' => $s->karyawan ?? '',
                'site' => $s->site_dedicated ?? '',
                'perusahaan' => $s->perusahaan ?? '',
                'reason' => $s->banned_reason ?? 'Tidak ada SAP',
            ]);
    }

    /**
     * Semua baris di scr_auto_banned_tbc_sap = list yang harus di-banned (sumber utama HSECT).
     *
     * @return Collection<int, array{snapshot_id: ?int, sid: string, karyawan: string, site: string, perusahaan: string, reason: string}>
     */
    private function buildItemsFromScrapTable(string $week, string $year, bool $filterByHsctStatus = true): Collection
    {
        $query = ScrAutoBannedTbcSap::query()
            ->whereNotNull('SID')
            ->where('SID', '!=', '');

        if ($week !== '') {
            $query->whereRaw('UPPER(TRIM(CAST(Week AS CHAR))) = ?', [$this->normalizer->normalizeWeek($week)]);
        }

        if ($year !== '') {
            $query->whereRaw('TRIM(CAST(ISO_Year AS CHAR)) = ?', [trim($year)]);
        }

        $rows = $query->orderBy('Karyawan')->orderBy('SID')->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $snapshots = Schema::hasTable('auto_banned_status_snapshots')
            ? AutoBannedStatusSnapshot::query()
                ->where('week', $week)
                ->where('iso_year', $year)
                ->get()
                ->keyBy('sid')
            : collect();

        $seenSids = [];

        return $rows
            ->map(function (ScrAutoBannedTbcSap $row) use ($snapshots): array {
                $sid = trim((string) ($row->SID ?? ''));
                /** @var AutoBannedStatusSnapshot|null $snapshot */
                $snapshot = $snapshots->get($sid);

                return [
                    'snapshot_id' => $snapshot?->id,
                    'sid' => $sid,
                    'karyawan' => trim((string) ($row->Karyawan ?? '')),
                    'site' => trim((string) ($row->Site_Dedicated ?? '')),
                    'perusahaan' => trim((string) ($row->Perusahaan ?? '')),
                    'reason' => trim((string) ($row->Banned_SID_Reason ?? '')) ?: 'Tidak ada SAP',
                    'hsct_sync_status' => $snapshot?->hsct_sync_status,
                ];
            })
            ->filter(function (array $item) use ($filterByHsctStatus, &$seenSids): bool {
                if ($item['sid'] === '' || isset($seenSids[$item['sid']])) {
                    return false;
                }

                $seenSids[$item['sid']] = true;

                if (! $filterByHsctStatus) {
                    return true;
                }

                $status = $item['hsct_sync_status'];

                if ($status === null) {
                    return true;
                }

                return ! in_array($status, [
                    AutoBannedHsctSyncStatus::Sent,
                    AutoBannedHsctSyncStatus::Confirmed,
                ], true);
            })
            ->map(fn (array $item): array => [
                'snapshot_id' => $item['snapshot_id'],
                'sid' => $item['sid'],
                'karyawan' => $item['karyawan'],
                'site' => $item['site'],
                'perusahaan' => $item['perusahaan'],
                'reason' => $item['reason'],
            ])
            ->values();
    }

    private function syncScrapBeforeResolve(string $week, string $year): void
    {
        if (! $this->scrapPollService->scrTableAvailable()
            || ! $this->scrapPollService->snapshotsTableAvailable()) {
            return;
        }

        if (! $this->scrapPollService->shouldPoll()) {
            return;
        }

        try {
            $this->scrapPollService->poll($week, $year);
        } catch (\Throwable $exception) {
            Log::warning('AutoBanned poll before HSECT email skipped', [
                'week' => $week,
                'year' => $year,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function markSnapshotSent(string $sid, string $week, string $year, ?int $snapshotId): void
    {
        if (! Schema::hasTable('auto_banned_status_snapshots')) {
            return;
        }

        $query = AutoBannedStatusSnapshot::query()
            ->where('sid', $sid)
            ->where('week', $week)
            ->where('iso_year', $year);

        if ($snapshotId !== null) {
            $query = AutoBannedStatusSnapshot::query()->where('id', $snapshotId);
        }

        $query->update([
            'hsct_sync_status' => AutoBannedHsctSyncStatus::Sent->value,
            'hsct_sent_at' => now(),
        ]);
    }

    /**
     * @param  Collection<int, array{snapshot_id: ?int, sid: string, karyawan: string, site: string, perusahaan: string, reason: string}>  $sourceItems
     */
    private function markSnapshotsSentBulk(Collection $sourceItems, string $week, string $year): void
    {
        if (! Schema::hasTable('auto_banned_status_snapshots')) {
            return;
        }

        $sids = $sourceItems
            ->pluck('sid')
            ->filter(fn (string $sid): bool => $sid !== '')
            ->unique()
            ->values()
            ->all();

        if ($sids === []) {
            return;
        }

        AutoBannedStatusSnapshot::query()
            ->where('week', $week)
            ->where('iso_year', $year)
            ->whereIn('sid', $sids)
            ->update([
                'hsct_sync_status' => AutoBannedHsctSyncStatus::Sent->value,
                'hsct_sent_at' => now(),
            ]);
    }

    /**
     * @param  Collection<int, array{snapshot_id: ?int, sid: string, karyawan: string, site: string, perusahaan: string, reason: string}>  $sourceItems
     */
    private function insertCampaignItems(AutoBannedHsctCampaign $campaign, Collection $sourceItems): void
    {
        $timestamp = now();

        $rows = $sourceItems->map(fn (array $item): array => [
            'campaign_id' => $campaign->id,
            'snapshot_id' => $item['snapshot_id'],
            'sid' => $item['sid'],
            'karyawan' => $item['karyawan'],
            'perusahaan' => $item['perusahaan'],
            'site_dedicated' => $item['site'],
            'banned_reason' => $item['reason'],
            'is_confirmed' => false,
            'confirmed_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            AutoBannedHsctCampaignItem::query()->insert($chunk);
        }
    }

    private function deleteExistingCampaignWithRetry(string $week, string $year, int $maxAttempts = 5): void
    {
        $attempt = 0;

        while (true) {
            $existing = $this->findCampaign($week, $year);

            if ($existing === null) {
                return;
            }

            try {
                $existing->delete();

                return;
            } catch (QueryException $exception) {
                $attempt++;

                if ($attempt >= $maxAttempts || ! $this->isLockOrDeadlockException($exception)) {
                    throw $exception;
                }

                usleep(random_int(200_000, 800_000));
            }
        }
    }

    private function isLockOrDeadlockException(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = $exception->getMessage();

        return $code === '40001'
            || $code === '1213'
            || $code === '1205'
            || str_contains($message, 'Deadlock')
            || str_contains($message, 'Lock wait timeout');
    }

    public function findCampaign(string $week, string $year): ?AutoBannedHsctCampaign
    {
        return AutoBannedHsctCampaign::query()
            ->where('week', $week)
            ->where('iso_year', $year)
            ->first();
    }

    /**
     * @return array{week: string, year: string}
     */
    public function resolvePeriod(?string $week, ?string $year): array
    {
        $normalizedWeek = $this->normalizer->normalizeWeek((string) ($week ?? ''));
        $normalizedYear = trim((string) ($year ?? ''));

        if ($normalizedWeek !== '' && $normalizedYear !== '') {
            return ['week' => $normalizedWeek, 'year' => $normalizedYear];
        }

        $period = $this->overviewService->resolvePeriod([
            'week' => $normalizedWeek,
            'year' => $normalizedYear,
        ]);

        return [
            'week' => $period['week'],
            'year' => $period['year'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function recipients(): array
    {
        return config('auto_banned.hsct.recipients', []);
    }

    public function isInitialSendDay(Carbon $now): bool
    {
        return $now->dayOfWeek === (int) config('auto_banned.hsct.initial_day', 2);
    }

    /**
     * @return Collection<int, AutoBannedHsctEmailLog>
     */
    public function emailHistory(?string $week = null, ?string $year = null, int $limit = 20): Collection
    {
        if (! Schema::hasTable('auto_banned_hsct_email_logs')) {
            return collect();
        }

        $query = AutoBannedHsctEmailLog::query()
            ->with('campaign')
            ->orderByDesc('sent_at')
            ->limit($limit);

        if ($week !== null && $week !== '') {
            $query->where('week', $this->normalizer->normalizeWeek($week));
        }

        if ($year !== null && $year !== '') {
            $query->where('iso_year', trim($year));
        }

        return $query->get();
    }

    /**
     * @return array{
     *     totalDispatches: int,
     *     totalSidSent: int,
     *     initialDispatches: int,
     *     reminderDispatches: int,
     *     lastSentAt: ?string,
     *     lastSentLabel: ?string,
     *     lastTotalSent: int
     * }
     */
    public function emailHistorySummary(?string $week = null, ?string $year = null): array
    {
        $logs = $this->emailHistory($week, $year, 500)
            ->where('status', 'sent');

        $lastLog = $logs->first();

        return [
            'totalDispatches' => $logs->count(),
            'totalSidSent' => (int) $logs->sum('total_in_list'),
            'initialDispatches' => $logs->where('email_type', AutoBannedHsctEmailType::Initial)->count(),
            'reminderDispatches' => $logs->where('email_type', AutoBannedHsctEmailType::Reminder)->count(),
            'lastSentAt' => $lastLog?->sent_at?->toDateTimeString(),
            'lastSentLabel' => $lastLog?->sent_at?->timezone(config('auto_banned.hsct.timezone', 'Asia/Makassar'))->format('d M Y H:i').' WITA',
            'lastTotalSent' => (int) ($lastLog?->total_in_list ?? 0),
        ];
    }

    /**
     * @return Collection<int, AutoBannedHsctCampaignItem>
     */
    public function pendingCampaignItems(?string $week = null, ?string $year = null): Collection
    {
        if (! Schema::hasTable('auto_banned_hsct_campaign_items')) {
            return collect();
        }

        $period = $this->resolvePeriod($week, $year);
        $campaign = $this->findCampaign($period['week'], $period['year']);

        if ($campaign === null) {
            return collect();
        }

        $this->syncCampaignConfirmation($campaign);

        return $campaign->items()->where('is_confirmed', false)->orderBy('karyawan')->get();
    }

    public function confirmCampaignItem(AutoBannedHsctCampaignItem $item): void
    {
        $campaign = $item->campaign;
        $snapshot = $campaign !== null
            ? $this->resolveItemSnapshot($item, $campaign)
            : null;

        if ($snapshot !== null) {
            $snapshot->update([
                'hsct_sync_status' => AutoBannedHsctSyncStatus::Confirmed,
                'hsct_confirmed_at' => now(),
                'ban_status' => AutoBannedBanStatus::CloseBanned,
            ]);
        }

        $item->update([
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);

        if ($campaign !== null) {
            $this->syncCampaignConfirmation($campaign);
        }
    }

    private function resolveItemSnapshot(
        AutoBannedHsctCampaignItem $item,
        AutoBannedHsctCampaign $campaign,
    ): ?AutoBannedStatusSnapshot {
        if ($item->snapshot_id !== null) {
            return AutoBannedStatusSnapshot::query()->find($item->snapshot_id);
        }

        return AutoBannedStatusSnapshot::query()
            ->where('sid', $item->sid)
            ->where('week', $campaign->week)
            ->where('iso_year', $campaign->iso_year)
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeCampaignSummary(?string $week = null, ?string $year = null): ?array
    {
        $period = $this->resolvePeriod($week, $year);
        $campaign = $this->findCampaign($period['week'], $period['year']);

        if ($campaign === null) {
            return null;
        }

        $this->syncCampaignConfirmation($campaign);
        $campaign->refresh();

        return [
            'week' => $campaign->week,
            'year' => $campaign->iso_year,
            'status' => $campaign->status,
            'totalItems' => $campaign->total_items,
            'confirmedItems' => $campaign->confirmed_items,
            'pendingItems' => max(0, $campaign->total_items - $campaign->confirmed_items),
            'reminderCount' => $campaign->reminder_count,
            'initialSentAt' => $campaign->initial_sent_at?->format('d M Y H:i'),
            'lastReminderAt' => $campaign->last_reminder_at?->format('d M Y H:i'),
            'completedAt' => $campaign->completed_at?->format('d M Y H:i'),
            'allBanned' => $campaign->status === AutoBannedHsctCampaignStatus::Completed,
        ];
    }

    /**
     * @param  array<int, array{sid: string, karyawan: string, site: string, perusahaan: string, reason: string}>  $employees
     * @return array{
     *     rows: array<int, array{perusahaan: string, site: string, count: int}>,
     *     perusahaanCount: int,
     *     siteCount: int
     * }
     */
    public function buildListSummaries(array $employees): array
    {
        $collection = collect($employees);

        $rows = $collection
            ->groupBy(function (array $row): string {
                $perusahaan = trim($row['perusahaan'] ?? '') !== '' ? trim($row['perusahaan']) : '—';
                $site = trim($row['site'] ?? '') !== '' ? trim($row['site']) : '—';

                return $perusahaan.'|'.$site;
            })
            ->map(function ($group, string $key): array {
                [$perusahaan, $site] = array_pad(explode('|', $key, 2), 2, '—');

                return [
                    'perusahaan' => $perusahaan,
                    'site' => $site,
                    'count' => $group->count(),
                ];
            })
            ->sortBy([
                ['perusahaan', 'asc'],
                ['site', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'perusahaanCount' => $collection
                ->map(fn (array $row): string => trim($row['perusahaan'] ?? '') !== '' ? trim($row['perusahaan']) : '—')
                ->unique()
                ->count(),
            'siteCount' => $collection
                ->map(fn (array $row): string => trim($row['site'] ?? '') !== '' ? trim($row['site']) : '—')
                ->unique()
                ->count(),
        ];
    }

    /**
     * @return array{action: string, message: string, sent: bool, count: int}
     */
    public function sendUnbanBatchEmail(?string $week = null, ?string $year = null, bool $force = false): array
    {
        if (! Schema::hasTable('auto_banned_unban_requests')) {
            return ['action' => 'error', 'message' => 'Tabel pengajuan unban belum tersedia.', 'sent' => false, 'count' => 0];
        }

        $recipients = $this->recipients();
        if ($recipients === []) {
            return ['action' => 'error', 'message' => 'AUTO_BANNED_HSCT_EMAILS belum dikonfigurasi.', 'sent' => false, 'count' => 0];
        }

        $requests = $this->resolveApprovedUnbanRequestsForHsct($week, $year, $force);

        if ($requests->isEmpty()) {
            return [
                'action' => 'skip',
                'message' => 'Tidak ada pengajuan unban approved yang belum dikirim ke HSECT.',
                'sent' => false,
                'count' => 0,
            ];
        }

        [$displayWeek, $displayYear] = $this->resolveUnbanEmailPeriodLabel($week, $year, $requests);

        $employees = $requests
            ->map(fn (AutoBannedUnbanRequest $row) => $this->formatUnbanRequestPayload($row))
            ->values()
            ->all();

        $sent = $this->dispatchMail(
            recipients: $recipients,
            emailType: AutoBannedHsctEmailType::UnbanRequest,
            reminderNumber: 1,
            week: $displayWeek,
            year: $displayYear,
            employees: $employees,
            totalInitial: $requests->count(),
            confirmedCount: 0,
            pendingCount: $requests->count(),
            reviewUrl: route('auto-banned.sod-verification.index', [], true),
        );

        if ($sent) {
            AutoBannedUnbanRequest::query()
                ->whereIn('id', $requests->pluck('id'))
                ->update(['hsct_notified_at' => now()]);
        }

        Log::info('AutoBanned unban HSECT batch email '.($sent ? 'sent' : 'failed'), [
            'week_filter' => $week,
            'year_filter' => $year,
            'count' => $requests->count(),
            'recipients' => $recipients,
        ]);

        return [
            'action' => $sent ? 'unban_batch' : 'error',
            'message' => $sent
                ? "Email unban HSECT terkirim ({$requests->count()} pengajuan dalam 1 email)."
                : 'Gagal mengirim email unban HSECT.',
            'sent' => $sent,
            'count' => $requests->count(),
        ];
    }

    /**
     * Pengajuan unban yang sudah disetujui SOD dan belum pernah dikirim ke HSECT.
     *
     * @return Collection<int, AutoBannedUnbanRequest>
     */
    public function resolveApprovedUnbanRequestsForHsct(?string $week = null, ?string $year = null, bool $includeNotified = false): Collection
    {
        $query = AutoBannedUnbanRequest::query()
            ->where('status', AutoBannedUnbanStatus::Approved)
            ->orderBy('iso_year')
            ->orderBy('week')
            ->orderBy('site_dedicated')
            ->orderBy('karyawan')
            ->orderBy('sid');

        if ($week !== null && trim($week) !== '') {
            $query->where('week', $this->normalizer->normalizeWeek($week));
        }

        if ($year !== null && trim($year) !== '') {
            $query->where('iso_year', trim($year));
        }

        if (! $includeNotified) {
            $query->whereNull('hsct_notified_at');
        }

        return $query->get();
    }

    /**
     * @deprecated Gunakan resolveApprovedUnbanRequestsForHsct()
     *
     * @return Collection<int, AutoBannedUnbanRequest>
     */
    public function resolvePendingUnbanRequests(?string $week = null, ?string $year = null, bool $includeNotified = false): Collection
    {
        return $this->resolveApprovedUnbanRequestsForHsct($week, $year, $includeNotified);
    }

    /**
     * @param  Collection<int, AutoBannedUnbanRequest>  $requests
     * @return array{0: string, 1: string}
     */
    private function resolveUnbanEmailPeriodLabel(?string $week, ?string $year, Collection $requests): array
    {
        if ($week !== null && trim($week) !== '' && $year !== null && trim($year) !== '') {
            $period = $this->resolvePeriod($week, $year);

            return [$period['week'], $period['year']];
        }

        if ($week !== null && trim($week) !== '') {
            return [$this->normalizer->normalizeWeek($week), 'Semua Tahun'];
        }

        if ($year !== null && trim($year) !== '') {
            return ['Semua Minggu', trim($year)];
        }

        $periods = $requests
            ->map(fn (AutoBannedUnbanRequest $row): string => trim(($row->week ?? '').' '.($row->iso_year ?? '')))
            ->filter()
            ->unique()
            ->values();

        if ($periods->count() === 1) {
            [$singleWeek, $singleYear] = array_pad(explode(' ', (string) $periods->first(), 2), 2, '');

            return [trim($singleWeek), trim($singleYear)];
        }

        return ['Semua', 'Periode'];
    }

    /**
     * @return array{
     *     sid: string,
     *     karyawan: string,
     *     site: string,
     *     perusahaan: string,
     *     reason: string,
     *     alasan_pengajuan: string,
     *     submitted_by: string
     * }
     */
    private function formatUnbanRequestPayload(AutoBannedUnbanRequest $unbanRequest): array
    {
        return [
            'sid' => trim((string) $unbanRequest->sid),
            'karyawan' => trim((string) ($unbanRequest->karyawan ?? '')),
            'site' => trim((string) ($unbanRequest->site_dedicated ?? '')),
            'perusahaan' => trim((string) ($unbanRequest->perusahaan ?? '')),
            'reason' => trim((string) ($unbanRequest->banned_reason ?? '')) ?: '—',
            'alasan_pengajuan' => trim((string) ($unbanRequest->alasan_pengajuan ?? '')),
            'submitted_by' => trim((string) ($unbanRequest->submitted_by_name ?? '')) ?: 'Form Publik',
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $items
     * @return array<int, array{sid: string, karyawan: string, site: string, perusahaan: string, reason: string}>
     */
    private function formatPayload(Collection|array $items): array
    {
        $collection = $items instanceof Collection ? $items : collect($items);

        return $collection->map(fn (array $item) => [
            'sid' => $item['sid'],
            'karyawan' => $item['karyawan'] ?? '',
            'site' => $item['site'] ?? '',
            'perusahaan' => $item['perusahaan'] ?? '',
            'reason' => $item['reason'] ?? '',
        ])->values()->all();
    }

    /**
     * @param  array<int, string>  $recipients
     * @param  array<int, array{sid: string, karyawan: string, site: string, perusahaan: string, reason: string}>  $employees
     */
    private function dispatchMail(
        array $recipients,
        AutoBannedHsctEmailType $emailType,
        int $reminderNumber,
        string $week,
        string $year,
        array $employees,
        int $totalInitial,
        int $confirmedCount,
        int $pendingCount,
        ?string $reviewUrl = null,
    ): bool {
        $excelPath = '';
        $excelFilename = '';

        try {
            $excel = $this->excelExporter->createTempFile(
                employees: $employees,
                week: $week,
                year: $year,
                emailType: $emailType,
                reminderNumber: $reminderNumber,
            );
            $excelPath = $excel['path'];
            $excelFilename = $excel['filename'];
            $summaries = $this->buildListSummaries($employees);

            $mailable = new AutoBannedHsctEmailMail(
                emailType: $emailType,
                reminderNumber: $reminderNumber,
                week: $week,
                isoYear: $year,
                employees: $employees,
                totalInitial: $totalInitial,
                confirmedCount: $confirmedCount,
                pendingCount: $pendingCount,
                excelPath: $excelPath,
                excelFilename: $excelFilename,
                summaryRows: $summaries['rows'],
                perusahaanCount: $summaries['perusahaanCount'],
                siteCount: $summaries['siteCount'],
                reviewUrl: $reviewUrl,
            );

            foreach ($recipients as $email) {
                $this->resilientMail->send($mailable, $email);
            }

            return true;
        } catch (\Throwable $exception) {
            Log::error('AutoBanned HSECT email failed', [
                'type' => $emailType->value,
                'error' => $exception->getMessage(),
            ]);

            return false;
        } finally {
            $this->excelExporter->deleteTempFile($excelPath);
        }
    }
}
