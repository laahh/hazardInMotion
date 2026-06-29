<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedSidAutomationStatus;
use App\Enums\AutoBannedUnbanStatus;
use App\Models\AutoBannedMasterSod;
use App\Models\AutoBannedStatusSnapshot;
use App\Models\AutoBannedUnbanRequest;
use App\Models\ScrAutoBannedTbcSap;
use App\Models\ScrDailyBanned;
use App\Models\SidBannedLog;
use App\Models\User;
use App\Support\AutoBanned\AutoBannedSchema;
use App\Support\AutoBanned\ScrDailyBannedColumns;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AutoBannedTreatmentService
{
    public function __construct(
        private readonly AutoBannedStatusNormalizer $normalizer,
        private readonly AutoBannedStatusResolver $statusResolver,
    ) {}

    /**
     * @return array{sid: string, karyawan: string, perusahaan: string, site_dedicated: string, banned_reason: string, status_banned_ref: string, week: string, iso_year: string}|null
     */
    public function resolveSidContext(string $sid, string $week, string $year): ?array
    {
        $sid = strtoupper(trim($sid));
        $week = $this->normalizer->normalizeWeek($week);
        $year = trim($year);

        if ($sid === '' || $week === '' || $year === '') {
            return null;
        }

        if (Schema::hasTable('auto_banned_status_snapshots')) {
            $snapshot = AutoBannedStatusSnapshot::query()
                ->where('sid', $sid)
                ->where('week', $week)
                ->where('iso_year', $year)
                ->first();

            if ($snapshot !== null) {
                return [
                    'sid' => $snapshot->sid,
                    'karyawan' => trim((string) ($snapshot->karyawan ?? '')),
                    'perusahaan' => trim((string) ($snapshot->perusahaan ?? '')),
                    'site_dedicated' => trim((string) ($snapshot->site_dedicated ?? '')),
                    'banned_reason' => trim((string) ($snapshot->banned_reason ?? '')),
                    'status_banned_ref' => trim((string) ($snapshot->scrap_status_raw ?? '')),
                    'week' => $week,
                    'iso_year' => $year,
                ];
            }
        }

        if (! Schema::hasTable('scr_auto_banned_tbc_sap')) {
            return null;
        }

        $scrapRow = ScrAutoBannedTbcSap::query()
            ->whereRaw('UPPER(TRIM(CAST(SID AS CHAR))) = ?', [$sid])
            ->whereRaw('UPPER(TRIM(CAST(Week AS CHAR))) = ?', [$week])
            ->whereRaw('TRIM(CAST(ISO_Year AS CHAR)) = ?', [$year])
            ->orderByDesc('id')
            ->first();

        if ($scrapRow === null) {
            return null;
        }

        return [
            'sid' => $sid,
            'karyawan' => trim((string) ($scrapRow->Karyawan ?? '')),
            'perusahaan' => trim((string) ($scrapRow->Perusahaan ?? '')),
            'site_dedicated' => trim((string) ($scrapRow->Site_Dedicated ?? '')),
            'banned_reason' => trim((string) ($scrapRow->Banned_SID_Reason ?? '')),
            'status_banned_ref' => trim((string) ($scrapRow->Status_Banned_SID_SAP ?? '')),
            'week' => $week,
            'iso_year' => $year,
        ];
    }

    /**
     * Resolve SID context dari tabel scr_daily_banned (Tableau Daily Banned).
     *
     * @return array{sid: string, karyawan: string, perusahaan: string, site_dedicated: string, banned_reason: string, status_banned_ref: string, week: string, iso_year: string}|null
     */
    public function resolveSidContextFromScrDailyBanned(string $sid, ?int $scrDailyBannedId = null): ?array
    {
        if (! AutoBannedSchema::hasScrDailyBannedTable()) {
            return null;
        }

        $sid = strtoupper(trim($sid));
        if ($sid === '') {
            return null;
        }

        $query = ScrDailyBanned::query()
            ->whereRaw('UPPER(TRIM('.ScrDailyBannedColumns::SID.')) = ?', [$sid]);

        if ($scrDailyBannedId !== null) {
            $query->where('id', $scrDailyBannedId);
        } else {
            $query->orderByDesc('filter_date')->orderByDesc('scraped_at');
        }

        $row = $query->first([
            'id',
            ScrDailyBannedColumns::SID,
            ScrDailyBannedColumns::NAMA,
            ScrDailyBannedColumns::PERUSAHAAN,
            ScrDailyBannedColumns::SITE,
            ScrDailyBannedColumns::BANNED_REASON,
            ScrDailyBannedColumns::BANNED_STATUS,
        ]);

        if ($row === null) {
            return null;
        }

        $rowSid = strtoupper(trim((string) ($row->{ScrDailyBannedColumns::SID} ?? '')));

        return [
            'sid' => $rowSid !== '' ? $rowSid : $sid,
            'karyawan' => trim((string) ($row->{ScrDailyBannedColumns::NAMA} ?? '')),
            'perusahaan' => trim((string) ($row->{ScrDailyBannedColumns::PERUSAHAAN} ?? '')),
            'site_dedicated' => trim((string) ($row->{ScrDailyBannedColumns::SITE} ?? '')),
            'banned_reason' => trim((string) ($row->{ScrDailyBannedColumns::BANNED_REASON} ?? '')),
            'status_banned_ref' => trim((string) ($row->{ScrDailyBannedColumns::BANNED_STATUS} ?? '')),
            'week' => '',
            'iso_year' => '',
        ];
    }

    /**
     * Resolve SID context dari tabel sid_banned_log (automasi banned SUCCESS).
     *
     * @return array{sid: string, karyawan: string, perusahaan: string, site_dedicated: string, banned_reason: string, status_banned_ref: string, week: string, iso_year: string}|null
     */
    public function resolveSidContextFromSidBannedLog(string $sid, ?int $sidBannedLogId = null): ?array
    {
        if (! AutoBannedSchema::hasSidBannedLogTable()) {
            return null;
        }

        $sid = strtoupper(trim($sid));
        if ($sid === '') {
            return null;
        }

        $query = SidBannedLog::query()
            ->whereRaw('UPPER(TRIM(sid)) = ?', [$sid]);

        if ($sidBannedLogId !== null) {
            $query->where('id', $sidBannedLogId);
        } else {
            $query->orderByDesc('filter_date')
                ->orderByDesc('completed_at')
                ->orderByDesc('id');
        }

        if (AutoBannedSchema::hasScrDailyBannedTable()) {
            $query->with([
                'scrDailyBanned:id,'.ScrDailyBannedColumns::SITE,
            ]);
        }

        $row = $query->first([
            'id',
            'scr_daily_banned_id',
            'sid',
            'nama',
            'perusahaan',
            'site_dedicated',
            'banned_reason',
            'banned_status',
        ]);

        if ($row === null) {
            return null;
        }

        $rowSid = strtoupper(trim((string) ($row->sid ?? '')));
        $siteDedicated = trim((string) ($row->site_dedicated ?? ''));
        if ($siteDedicated === '') {
            $siteDedicated = trim((string) ($row->scrDailyBanned?->{ScrDailyBannedColumns::SITE} ?? ''));
        }

        return [
            'sid' => $rowSid !== '' ? $rowSid : $sid,
            'karyawan' => trim((string) ($row->nama ?? '')),
            'perusahaan' => trim((string) ($row->perusahaan ?? '')),
            'site_dedicated' => $siteDedicated,
            'banned_reason' => trim((string) ($row->banned_reason ?? '')),
            'status_banned_ref' => trim((string) ($row->banned_status ?? '')),
            'week' => '',
            'iso_year' => '',
        ];
    }

    /**
     * @return array<int, array{id: int, label: string, filter_date: ?string, banned_reason: string, site: string, nama: string}>
     */
    public function sidBannedLogOptionsForSid(string $sid): array
    {
        if (! AutoBannedSchema::hasSidBannedLogTable()) {
            return [];
        }

        $sid = strtoupper(trim($sid));
        if ($sid === '') {
            return [];
        }

        $requestedScrIds = AutoBannedUnbanRequest::requestedScrDailyBannedIdsForSid($sid);

        $query = SidBannedLog::query()
            ->whereRaw('UPPER(TRIM(sid)) = ?', [$sid])
            ->where('automation_status', AutoBannedSidAutomationStatus::Success->value);

        if ($requestedScrIds !== []) {
            $query->where(function ($inner) use ($requestedScrIds): void {
                $inner->whereNull('scr_daily_banned_id')
                    ->orWhereNotIn('scr_daily_banned_id', $requestedScrIds);
            });
        }

        return $query
            ->when(
                AutoBannedSchema::hasScrDailyBannedTable(),
                fn ($query) => $query->with([
                    'scrDailyBanned:id,'.ScrDailyBannedColumns::SITE,
                ]),
            )
            ->orderByDesc('filter_date')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get([
                'id',
                'scr_daily_banned_id',
                'filter_date',
                'completed_at',
                'banned_reason',
                'banned_status',
                'site_dedicated',
                'nama',
            ])
            ->map(function (SidBannedLog $row): array {
                $filterDate = $row->filter_date?->format('d M Y')
                    ?? $row->completed_at?->format('d M Y')
                    ?? '—';
                $reason = trim((string) ($row->banned_reason ?? '')) ?: '—';
                $site = trim((string) ($row->site_dedicated ?? ''));
                if ($site === '') {
                    $site = trim((string) ($row->scrDailyBanned?->{ScrDailyBannedColumns::SITE} ?? ''));
                }
                $site = $site !== '' ? $site : '—';

                return [
                    'id' => (int) $row->id,
                    'label' => sprintf('%s — %s (%s)', $filterDate, $reason, $site),
                    'filter_date' => $row->filter_date?->toDateString(),
                    'banned_reason' => $reason,
                    'site' => $site,
                    'nama' => trim((string) ($row->nama ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string, filter_date: ?string, banned_reason: string, site: string, nama: string}>
     */
    public function scrDailyBannedOptionsForSid(string $sid): array
    {
        if (! AutoBannedSchema::hasScrDailyBannedTable()) {
            return [];
        }

        $sid = strtoupper(trim($sid));
        if ($sid === '') {
            return [];
        }

        return ScrDailyBanned::query()
            ->whereRaw('UPPER(TRIM('.ScrDailyBannedColumns::SID.')) = ?', [$sid])
            ->orderByDesc('filter_date')
            ->orderByDesc('scraped_at')
            ->limit(50)
            ->get([
                'id',
                'filter_date',
                ScrDailyBannedColumns::BANNED_REASON,
                ScrDailyBannedColumns::BANNED_STATUS,
                ScrDailyBannedColumns::SITE,
                ScrDailyBannedColumns::NAMA,
            ])
            ->map(function (ScrDailyBanned $row): array {
                $filterDate = $row->filter_date?->format('d M Y') ?? '—';
                $reason = trim((string) ($row->{ScrDailyBannedColumns::BANNED_REASON} ?? '')) ?: '—';
                $site = trim((string) ($row->{ScrDailyBannedColumns::SITE} ?? '')) ?: '—';

                return [
                    'id' => (int) $row->id,
                    'label' => sprintf('%s — %s (%s)', $filterDate, $reason, $site),
                    'filter_date' => $row->filter_date?->toDateString(),
                    'banned_reason' => $reason,
                    'site' => $site,
                    'nama' => trim((string) ($row->{ScrDailyBannedColumns::NAMA} ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    public function storeTreatmentEvidence(
        string $sid,
        string $week,
        string $year,
        string $alasanPengajuan,
        UploadedFile $file,
        ?User $user = null,
        string $submitterName = '',
        ?int $scrDailyBannedId = null,
        ?string $noHp = null,
        ?int $sidBannedLogId = null,
    ): AutoBannedUnbanRequest {
        if (! Schema::hasTable('auto_banned_unban_requests')) {
            throw ValidationException::withMessages([
                'sid' => ['Tabel pengajuan treatment belum tersedia. Jalankan migration.'],
            ]);
        }

        $sid = strtoupper(trim($sid));
        $week = $this->normalizer->normalizeWeek($week);
        $year = trim($year);

        $context = $this->resolveSidContext($sid, $week, $year);
        if ($context === null && $sidBannedLogId !== null) {
            $context = $this->resolveSidContextFromSidBannedLog($sid, $sidBannedLogId);
        }
        if ($context === null) {
            $context = $this->resolveSidContextFromScrDailyBanned($sid, $scrDailyBannedId);
        }
        if ($context === null) {
            throw ValidationException::withMessages([
                'sid' => ['SID tidak ditemukan di data banned.'],
            ]);
        }

        $hasPending = AutoBannedUnbanRequest::query()
            ->where('sid', $sid)
            ->where('week', $week)
            ->where('iso_year', $year)
            ->where('status', AutoBannedUnbanStatus::Pending)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'sid' => ['SID ini masih memiliki pengajuan treatment yang menunggu review.'],
            ]);
        }

        $banLog = null;
        $scrRow = null;

        if ($sidBannedLogId !== null) {
            $banLog = $this->resolveSidBannedLogForSid($sid, $sidBannedLogId);
            if ($banLog->scr_daily_banned_id !== null) {
                $scrRow = ScrDailyBanned::query()->find($banLog->scr_daily_banned_id);
            }
        } else {
            $scrRow = $this->resolveScrDailyBannedForSid($sid, $scrDailyBannedId);
        }

        $directory = 'auto-banned/treatment-evidence/'.$year.'/'.$week;
        Storage::disk('local')->makeDirectory($directory);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin';
        $filename = $sid.'_'.time().'.'.$extension;

        $storedPath = $file->storeAs($directory, $filename, 'local');

        if ($storedPath === false || ! Storage::disk('local')->exists($storedPath)) {
            throw ValidationException::withMessages([
                'evidence_file' => ['Gagal menyimpan file ke server. Pastikan folder storage/app dapat ditulis (chmod) dan jalankan php artisan storage:link jika diperlukan.'],
            ]);
        }

        $submitterDisplayName = $user !== null
            ? trim((string) ($user->name ?? 'User'))
            : ($submitterName !== '' ? $submitterName : ($context['karyawan'] !== '' ? $context['karyawan'] : 'Form Publik'));

        if ($submitterDisplayName === '') {
            $submitterDisplayName = 'Form Publik';
        }

        $request = AutoBannedUnbanRequest::query()->create([
            'scr_daily_banned_id' => $scrRow?->id ?? $banLog?->scr_daily_banned_id,
            'sid' => $context['sid'],
            'karyawan' => $banLog !== null
                ? (trim((string) ($banLog->nama ?? '')) ?: ($context['karyawan'] !== '' ? $context['karyawan'] : $sid))
                : ($scrRow?->{ScrDailyBannedColumns::NAMA}
                    ? trim((string) $scrRow->{ScrDailyBannedColumns::NAMA})
                    : ($context['karyawan'] !== '' ? $context['karyawan'] : $sid)),
            'perusahaan' => ($banLog !== null ? trim((string) ($banLog->perusahaan ?? '')) : '')
                ?: ($context['perusahaan'] ?: null),
            'site_dedicated' => $banLog !== null
                ? (trim((string) ($banLog->site_dedicated ?? '')) ?: ($context['site_dedicated'] ?: null))
                : ($scrRow?->{ScrDailyBannedColumns::SITE}
                    ? trim((string) $scrRow->{ScrDailyBannedColumns::SITE})
                    : ($context['site_dedicated'] ?: null)),
            'banned_reason' => $banLog !== null
                ? (trim((string) ($banLog->banned_reason ?? '')) ?: ($context['banned_reason'] ?: null))
                : ($scrRow?->{ScrDailyBannedColumns::BANNED_REASON}
                    ? trim((string) $scrRow->{ScrDailyBannedColumns::BANNED_REASON})
                    : ($context['banned_reason'] ?: null)),
            'status_banned_ref' => $context['status_banned_ref'] ?: null,
            'alasan_pengajuan' => trim($alasanPengajuan),
            'evidence_file_path' => $storedPath,
            'evidence_original_name' => $file->getClientOriginalName(),
            'evidence_mime' => $file->getMimeType(),
            'evidence_uploaded_at' => now(),
            'status' => AutoBannedUnbanStatus::Pending,
            'week' => $week,
            'iso_year' => $year,
            'submitted_by_id' => $user?->id,
            'submitted_by_name' => $submitterDisplayName,
            'no_hp' => $this->normalizeStoredPhoneNumber($noHp),
        ]);

        $this->syncSnapshotWorkflow($sid, $week, $year);

        return $request;
    }

    private function resolveScrDailyBannedForSid(string $sid, ?int $scrDailyBannedId): ?ScrDailyBanned
    {
        if (! AutoBannedSchema::hasScrDailyBannedTable()) {
            return null;
        }

        if ($scrDailyBannedId === null) {
            throw ValidationException::withMessages([
                'scr_daily_banned_id' => ['Pilih record Daily Banned yang terkait.'],
            ]);
        }

        $scrRow = ScrDailyBanned::query()->find($scrDailyBannedId);
        if ($scrRow === null) {
            throw ValidationException::withMessages([
                'scr_daily_banned_id' => ['Record Daily Banned tidak ditemukan.'],
            ]);
        }

        $scrSid = strtoupper(trim((string) ($scrRow->{ScrDailyBannedColumns::SID} ?? '')));
        if ($scrSid !== $sid) {
            throw ValidationException::withMessages([
                'scr_daily_banned_id' => ['Record Daily Banned tidak cocok dengan SID yang dipilih.'],
            ]);
        }

        return $scrRow;
    }

    private function resolveSidBannedLogForSid(string $sid, ?int $sidBannedLogId): SidBannedLog
    {
        if (! AutoBannedSchema::hasSidBannedLogTable()) {
            throw ValidationException::withMessages([
                'sid_banned_log_id' => ['Tabel riwayat banned belum tersedia.'],
            ]);
        }

        if ($sidBannedLogId === null) {
            throw ValidationException::withMessages([
                'sid_banned_log_id' => ['Pilih riwayat banned yang terkait.'],
            ]);
        }

        $banLog = SidBannedLog::query()->find($sidBannedLogId);
        if ($banLog === null) {
            throw ValidationException::withMessages([
                'sid_banned_log_id' => ['Riwayat banned tidak ditemukan.'],
            ]);
        }

        $logSid = strtoupper(trim((string) ($banLog->sid ?? '')));
        if ($logSid !== $sid) {
            throw ValidationException::withMessages([
                'sid_banned_log_id' => ['Riwayat banned tidak cocok dengan SID yang dipilih.'],
            ]);
        }

        if ($banLog->automation_status !== AutoBannedSidAutomationStatus::Success) {
            throw ValidationException::withMessages([
                'sid_banned_log_id' => ['Riwayat banned belum berhasil diproses otomasi.'],
            ]);
        }

        $scrDailyBannedId = $banLog->scr_daily_banned_id !== null ? (int) $banLog->scr_daily_banned_id : null;
        if ($scrDailyBannedId !== null && in_array($scrDailyBannedId, AutoBannedUnbanRequest::requestedScrDailyBannedIdsForSid($sid), true)) {
            throw ValidationException::withMessages([
                'sid_banned_log_id' => ['Riwayat banned ini sudah pernah diajukan treatment.'],
            ]);
        }

        return $banLog;
    }

    public function resolveMasterSodWhatsappRedirectUrl(AutoBannedUnbanRequest $unbanRequest): ?string
    {
        if (! Schema::hasTable('auto_banned_master_sods')) {
            return null;
        }

        $site = trim((string) ($unbanRequest->site_dedicated ?? ''));
        if ($site === '') {
            return null;
        }

        $masterSod = AutoBannedMasterSod::query()
            ->whereRaw('UPPER(TRIM(site)) = ?', [mb_strtoupper($site)])
            ->orderBy('id')
            ->first();

        if ($masterSod === null) {
            return null;
        }

        $phone = $this->normalizeWhatsappPhone((string) $masterSod->no_hp);
        if ($phone === '') {
            return null;
        }

        $reviewUrl = route('auto-banned.sod-verification.index', [], true);

        $message = sprintf(
            "Halo %s,\n\nSaya %s (SID: %s) telah mengajukan treatment banned untuk periode %s %s.\n\nMohon bantuannya untuk review.\nLink review: %s\n\nTerima kasih.",
            trim((string) $masterSod->nama),
            trim((string) ($unbanRequest->karyawan ?? $unbanRequest->sid)),
            trim((string) $unbanRequest->sid),
            trim((string) ($unbanRequest->week ?? '')),
            trim((string) ($unbanRequest->iso_year ?? '')),
            $reviewUrl,
        );

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    public function resolveApplicantReviewWhatsappRedirectUrl(AutoBannedUnbanRequest $unbanRequest): ?string
    {
        $phone = $this->normalizeWhatsappPhone((string) ($unbanRequest->no_hp ?? ''));
        if ($phone === '') {
            return null;
        }

        $recipientName = trim((string) ($unbanRequest->karyawan ?? ''));
        if ($recipientName === '') {
            $recipientName = trim((string) ($unbanRequest->submitted_by_name ?? ''));
        }
        if ($recipientName === '') {
            $recipientName = trim((string) $unbanRequest->sid);
        }

        $week = trim((string) ($unbanRequest->week ?? ''));
        $year = trim((string) ($unbanRequest->iso_year ?? ''));
        $period = trim($week.' '.$year);
        $reviewerName = trim((string) ($unbanRequest->reviewed_by_name ?? 'SOD'));
        $catatan = trim((string) ($unbanRequest->catatan_review ?? ''));

        $message = match ($unbanRequest->status) {
            AutoBannedUnbanStatus::Approved => $this->buildApplicantApprovedWhatsappMessage(
                recipientName: $recipientName,
                sid: trim((string) $unbanRequest->sid),
                period: $period,
                reviewerName: $reviewerName,
                catatan: $catatan,
            ),
            AutoBannedUnbanStatus::Rejected => $this->buildApplicantRejectedWhatsappMessage(
                recipientName: $recipientName,
                sid: trim((string) $unbanRequest->sid),
                period: $period,
                reviewerName: $reviewerName,
                catatan: $catatan,
            ),
            default => null,
        };

        if ($message === null || $message === '') {
            return null;
        }

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    private function buildApplicantApprovedWhatsappMessage(
        string $recipientName,
        string $sid,
        string $period,
        string $reviewerName,
        string $catatan,
    ): string {
        $lines = [
            'Halo '.$recipientName.',',
            '',
            'Pengajuan treatment banned Anda telah *DISETUJUI*.',
            '',
            'SID: '.$sid,
        ];

        if ($period !== '') {
            $lines[] = 'Periode: '.$period;
        }

        $lines[] = 'Reviewer: '.$reviewerName;

        if ($catatan !== '') {
            $lines[] = '';
            $lines[] = 'Catatan: '.$catatan;
        }

        $lines[] = '';
        $lines[] = 'Terima kasih.';
        $lines[] = 'PT Berau Coal — Safety App';

        return implode("\n", $lines);
    }

    private function buildApplicantRejectedWhatsappMessage(
        string $recipientName,
        string $sid,
        string $period,
        string $reviewerName,
        string $catatan,
    ): string {
        $lines = [
            'Halo '.$recipientName.',',
            '',
            'Mohon maaf, pengajuan treatment banned Anda *DITOLAK*.',
            '',
            'SID: '.$sid,
        ];

        if ($period !== '') {
            $lines[] = 'Periode: '.$period;
        }

        $lines[] = 'Reviewer: '.$reviewerName;
        $lines[] = '';
        $lines[] = 'Catatan: '.($catatan !== '' ? $catatan : 'Tidak ada catatan.');
        $lines[] = '';
        $lines[] = 'Silakan koordinasi dengan SOD/site terkait untuk langkah selanjutnya.';
        $lines[] = 'PT Berau Coal — Safety App';

        return implode("\n", $lines);
    }

    private function normalizeWhatsappPhone(string $phone): string
    {
        return $this->normalizeStoredPhoneNumber($phone) ?? '';
    }

    private function normalizeStoredPhoneNumber(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        return $digits;
    }

    public function downloadEvidence(AutoBannedUnbanRequest $unbanRequest): StreamedResponse
    {
        $path = trim((string) ($unbanRequest->evidence_file_path ?? ''));

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            abort(404, 'File evidence tidak ditemukan.');
        }

        $filename = $unbanRequest->evidence_original_name ?: basename($path);

        return Storage::disk('local')->download($path, $filename);
    }

    private function syncSnapshotWorkflow(string $sid, string $week, string $year): void
    {
        if (! Schema::hasTable('auto_banned_status_snapshots')) {
            return;
        }

        $snapshot = AutoBannedStatusSnapshot::query()
            ->where('sid', $sid)
            ->where('week', $week)
            ->where('iso_year', $year)
            ->first();

        if ($snapshot === null) {
            return;
        }

        $this->statusResolver->syncWorkflowFromUnbanRequests($snapshot);

        if ($snapshot->isDirty()) {
            $snapshot->save();
        }
    }
}
