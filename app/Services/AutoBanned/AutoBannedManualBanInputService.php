<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedManualBanScope;
use App\Enums\AutoBannedSidAutomationStatus;
use App\Models\ScrDailyBanned;
use App\Models\ScrWeeklyBanned;
use App\Models\SidBannedLog;
use App\Models\SidBannedLogWeekly;
use App\Models\User;
use App\Services\PembatasanLV\PembatasanLVDriverOptionService;
use App\Support\AutoBanned\AutoBannedSchema;
use App\Support\AutoBanned\ScrDailyBannedColumns;
use App\Support\AutoBanned\ScrWeeklyBannedColumns;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AutoBannedManualBanInputService
{
    public const AUTOMATION_STEP = 'INPUTASI_MANUAL_BANNED';

    public function __construct(
        private readonly PembatasanLVDriverOptionService $karyawanOptionService,
    ) {}

    /**
     * @return Collection<int, array{
     *     id: string,
     *     sid: string,
     *     label: string,
     *     nama: string,
     *     nik: string,
     *     nama_perusahaan: string,
     *     site: string,
     *     dept: string,
     *     subtitle: string
     * }>
     */
    public function karyawanOptions(string $q = '', int $limit = 30): Collection
    {
        return $this->karyawanOptionService->options($q, $limit)->map(static fn (array $row): array => [
            'id' => $row['kode_sid'],
            'sid' => $row['kode_sid'],
            'label' => $row['nama'].' ('.$row['kode_sid'].')',
            'nama' => $row['nama'],
            'nik' => $row['nik'],
            'nama_perusahaan' => $row['nama_perusahaan'],
            'site' => $row['site'],
            'dept' => $row['dept'],
            'subtitle' => trim(collect([
                $row['kode_sid'],
                $row['nik'] ?: null,
                $row['nama_perusahaan'] ?: null,
                $row['site'] ?: null,
            ])->filter()->implode(' • ')),
        ])->values();
    }

    /**
     * @param  array{
     *     ban_scope: string,
     *     sid: string,
     *     nik?: string|null,
     *     nama: string,
     *     perusahaan?: string|null,
     *     site_dedicated?: string|null,
     *     filter_date?: string|null,
     *     filter_shift: string,
     *     iso_year?: string|null,
     *     iso_week?: string|null,
     *     banned_status: string,
     *     banned_reason: string,
     *     status_onsite?: string|null,
     *     banned_at?: string|null
     * }  $input
     * @return array{
     *     scope: string,
     *     scr_id: int,
     *     ban_log_id: int,
     *     sid: string,
     *     nama: string
     * }
     */
    public function createManualBan(array $input, ?User $actor = null): array
    {
        $scope = AutoBannedManualBanScope::from((string) $input['ban_scope']);

        if ($scope->isWeekly()) {
            if (! AutoBannedSchema::hasScrWeeklyBannedTable() || ! AutoBannedSchema::hasSidBannedLogWeeklyTable()) {
                throw ValidationException::withMessages([
                    'ban_scope' => ['Tabel scr_weekly_banned / sid_banned_log_weekly belum tersedia.'],
                ]);
            }
        } elseif (! AutoBannedSchema::hasScrDailyBannedTable() || ! AutoBannedSchema::hasSidBannedLogTable()) {
            throw ValidationException::withMessages([
                'ban_scope' => ['Tabel scr_daily_banned / sid_banned_log belum tersedia.'],
            ]);
        }

        $sid = strtoupper(trim((string) $input['sid']));
        $nama = trim((string) $input['nama']);
        $nik = trim((string) ($input['nik'] ?? ''));
        $perusahaan = trim((string) ($input['perusahaan'] ?? ''));
        $site = trim((string) ($input['site_dedicated'] ?? ''));
        $bannedStatus = trim((string) $input['banned_status']);
        $bannedReason = trim((string) $input['banned_reason']);
        $statusOnsite = trim((string) ($input['status_onsite'] ?? '')) ?: 'ONSITE';
        $filterShift = trim((string) $input['filter_shift']) ?: 'Shift 1';
        $bannedAt = isset($input['banned_at']) && $input['banned_at'] !== ''
            ? Carbon::parse((string) $input['banned_at'])
            : now();

        if ($sid === '' || $nama === '') {
            throw ValidationException::withMessages([
                'sid' => ['SID dan nama karyawan wajib diisi.'],
            ]);
        }

        return DB::transaction(function () use (
            $scope,
            $sid,
            $nama,
            $nik,
            $perusahaan,
            $site,
            $bannedStatus,
            $bannedReason,
            $statusOnsite,
            $filterShift,
            $bannedAt,
            $input,
        ): array {
            if ($scope->isWeekly()) {
                return $this->createWeeklyBan(
                    sid: $sid,
                    nama: $nama,
                    nik: $nik,
                    perusahaan: $perusahaan,
                    site: $site,
                    bannedStatus: $bannedStatus,
                    bannedReason: $bannedReason,
                    statusOnsite: $statusOnsite,
                    filterShift: $filterShift,
                    bannedAt: $bannedAt,
                    isoYear: (string) ($input['iso_year'] ?? ''),
                    isoWeek: (string) ($input['iso_week'] ?? ''),
                    filterDate: isset($input['filter_date']) && $input['filter_date'] !== ''
                        ? Carbon::parse((string) $input['filter_date'])->toDateString()
                        : null,
                );
            }

            return $this->createDailyBan(
                sid: $sid,
                nama: $nama,
                nik: $nik,
                perusahaan: $perusahaan,
                site: $site,
                bannedStatus: $bannedStatus,
                bannedReason: $bannedReason,
                statusOnsite: $statusOnsite,
                filterShift: $filterShift,
                bannedAt: $bannedAt,
                filterDate: Carbon::parse((string) $input['filter_date'])->toDateString(),
            );
        });
    }

    /**
     * @return array{scope: string, scr_id: int, ban_log_id: int, sid: string, nama: string}
     */
    private function createDailyBan(
        string $sid,
        string $nama,
        string $nik,
        string $perusahaan,
        string $site,
        string $bannedStatus,
        string $bannedReason,
        string $statusOnsite,
        string $filterShift,
        Carbon $bannedAt,
        string $filterDate,
    ): array {
        $scrPayload = $this->filterPayloadForTable('scr_daily_banned', [
            'scraped_at' => $bannedAt,
            'filter_date' => $filterDate,
            'filter_shift' => $filterShift,
            'view_name' => 'Manual Input — Daily Banned',
            'content_url' => 'manual/inputasi-reconcile/daily',
            ScrDailyBannedColumns::SID => $sid,
            ScrDailyBannedColumns::NIK => $nik !== '' ? $nik : null,
            ScrDailyBannedColumns::NAMA => $nama,
            ScrDailyBannedColumns::PERUSAHAAN => $perusahaan !== '' ? $perusahaan : null,
            ScrDailyBannedColumns::SITE => $site !== '' ? $site : null,
            ScrDailyBannedColumns::BANNED_REASON => $bannedReason,
            ScrDailyBannedColumns::BANNED_STATUS => $bannedStatus,
            ScrDailyBannedColumns::ONSITE_STATUS => $statusOnsite,
            ScrDailyBannedColumns::SAP_LABEL => 'N/A',
            ScrDailyBannedColumns::RFID_QUALITY => null,
            ScrDailyBannedColumns::HZR => 0,
            ScrDailyBannedColumns::INS => 0,
            ScrDailyBannedColumns::OBS_OAK => 0,
            ScrDailyBannedColumns::RFID => 0,
            'Batch_Evaluasi_Daily_Label' => $filterShift.' | '.$filterDate,
        ]);

        $scr = ScrDailyBanned::query()->create($scrPayload);

        $banLogPayload = $this->filterPayloadForTable('sid_banned_log', [
            'scr_daily_banned_id' => (int) $scr->id,
            'filter_date' => $filterDate,
            'filter_shift' => $filterShift,
            'nik' => $nik !== '' ? $nik : null,
            'sid' => $sid,
            'nama' => $nama,
            'perusahaan' => $perusahaan !== '' ? $perusahaan : null,
            'site_dedicated' => $site !== '' ? $site : null,
            'banned_status' => $bannedStatus,
            'banned_reason' => $bannedReason,
            'status_onsite' => $statusOnsite,
            'automation_status' => AutoBannedSidAutomationStatus::Success->value,
            'automation_step' => self::AUTOMATION_STEP,
            'work_permit_kategori' => 'BAN SID',
            'work_permit_jenis' => 'BAN SID - RFID SAP & TBC',
            'started_at' => $bannedAt,
            'completed_at' => $bannedAt,
            'created_at' => $bannedAt,
            'updated_at' => $bannedAt,
        ]);

        $banLog = SidBannedLog::query()->create($banLogPayload);

        return [
            'scope' => AutoBannedManualBanScope::Daily->value,
            'scr_id' => (int) $scr->id,
            'ban_log_id' => (int) $banLog->id,
            'sid' => $sid,
            'nama' => $nama,
        ];
    }

    /**
     * @return array{scope: string, scr_id: int, ban_log_id: int, sid: string, nama: string}
     */
    private function createWeeklyBan(
        string $sid,
        string $nama,
        string $nik,
        string $perusahaan,
        string $site,
        string $bannedStatus,
        string $bannedReason,
        string $statusOnsite,
        string $filterShift,
        Carbon $bannedAt,
        string $isoYear,
        string $isoWeek,
        ?string $filterDate,
    ): array {
        $isoWeek = ltrim($isoWeek, '0');
        if ($isoWeek === '') {
            $isoWeek = '0';
        }

        $resolvedFilterDate = $filterDate
            ?? Carbon::now()
                ->setISODate((int) $isoYear, (int) $isoWeek)
                ->startOfWeek(Carbon::MONDAY)
                ->toDateString();

        $scrPayload = $this->filterPayloadForTable('scr_weekly_banned', [
            'scraped_at' => $bannedAt,
            ScrWeeklyBannedColumns::ISO_YEAR => $isoYear,
            ScrWeeklyBannedColumns::ISO_WEEK => $isoWeek,
            'filter_shift' => $filterShift,
            'view_name' => 'Manual Input — Weekly Banned',
            'content_url' => 'manual/inputasi-reconcile/weekly',
            ScrWeeklyBannedColumns::SID => $sid,
            ScrWeeklyBannedColumns::NIK => $nik !== '' ? $nik : null,
            ScrWeeklyBannedColumns::NAMA => $nama,
            ScrWeeklyBannedColumns::PERUSAHAAN => $perusahaan !== '' ? $perusahaan : null,
            ScrWeeklyBannedColumns::SITE => $site !== '' ? $site : null,
            ScrWeeklyBannedColumns::BANNED_REASON => $bannedReason,
            ScrWeeklyBannedColumns::BANNED_STATUS => $bannedStatus,
            ScrWeeklyBannedColumns::ONSITE_STATUS => $statusOnsite,
            'SAP_Weekly_Label' => 'N/A',
            'n_COACH_Weekly' => 0,
            'n_Hari_RFID_Onsite_Weekly' => 0,
            'n_HZR_Weekly' => 0,
            'n_INS_Weekly' => 0,
            'n_OBS_OAK_Weekly' => 0,
            'n_TBC_Weekly' => 0,
            'Target_SAP_Weekly' => 0,
            'Target_TBC_Weekly' => 0,
            'last_update' => $bannedAt->format('n/j/Y g:i:s A'),
            'last_update_1' => $bannedAt->format('n/j/Y g:i:s A'),
        ]);

        $scr = ScrWeeklyBanned::query()->create($scrPayload);

        $banLogPayload = $this->filterPayloadForTable('sid_banned_log_weekly', [
            'scr_weekly_banned_id' => (int) $scr->id,
            'filter_date' => $resolvedFilterDate,
            'filter_shift' => $filterShift,
            'nik' => $nik !== '' ? $nik : null,
            'sid' => $sid,
            'nama' => $nama,
            'perusahaan' => $perusahaan !== '' ? $perusahaan : null,
            'site_dedicated' => $site !== '' ? $site : null,
            'banned_status' => $bannedStatus,
            'banned_reason' => $bannedReason,
            'status_onsite' => $statusOnsite,
            'automation_status' => AutoBannedSidAutomationStatus::Success->value,
            'automation_step' => self::AUTOMATION_STEP,
            'work_permit_kategori' => 'BAN SID',
            'work_permit_jenis' => 'BAN SID - RFID SAP & TBC',
            'started_at' => $bannedAt,
            'completed_at' => $bannedAt,
            'created_at' => $bannedAt,
            'updated_at' => $bannedAt,
        ]);

        $banLog = SidBannedLogWeekly::query()->create($banLogPayload);

        return [
            'scope' => AutoBannedManualBanScope::Weekly->value,
            'scr_id' => (int) $scr->id,
            'ban_log_id' => (int) $banLog->id,
            'sid' => $sid,
            'nama' => $nama,
        ];
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
