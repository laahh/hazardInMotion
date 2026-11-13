<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\User;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use App\Services\SportEvaluation\RfidClickHouseClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Approver Roster per departemen: manager di config
 * dms_roster.master.approver_departemen ditambah karyawan berjabatan fungsional
 * Manager (data karyawan aktif) boleh me-review roster yang memuat karyawan
 * departemennya, selain jalur Assign Role yang sudah ada.
 *
 * File roster tidak menyimpan departemen, jadi departemen dibaca per SID dari
 * salinan cache yang diisi dms:sync-roster-rfid: request web di server tidak
 * bisa ke OLAP. Tanpa salinan = tidak ada akses departemen.
 *
 * Keputusan manager hanya berlaku untuk karyawan departemennya
 * (DmsRosterReviewDepartemen). File yang juga memuat perusahaan lain hanya bisa
 * dilihat.
 */
final class DmsRosterApproverDepartemen
{
    public const KUNCI = 'dms_roster:departemen_karyawan:v1';

    private const TTL_HARI = 2;

    private const TIMEOUT_MS = 180000;

    /** SID manager (dari jabatan) => departemen. */
    public const KUNCI_MANAGER = 'dms_roster:manager_jabatan:v1';

    private const TTL_MANAGER_MENIT = 360;

    /** Gagal/kosong membaca ClickHouse: jangan dicoba ulang di tiap request. */
    private const TTL_GAGAL_MENIT = 5;

    /** @var array<string, string>|null */
    private ?array $manager = null;

    /** @var array<string, list<int>> */
    private array $uploadPerDepartemen = [];

    private ?string $galat = null;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /** Nama departemen untuk pencocokan: huruf besar, spasi dirapikan. */
    public static function kunciDepartemen(string $departemen): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $departemen)));
    }

    /**
     * Baris (sid, departemen) → departemen => SID huruf besar. Murni.
     *
     * @param  list<object>  $rows
     * @return array<string, list<string>>
     */
    public static function petakan(array $rows): array
    {
        $peta = [];
        foreach ($rows as $r) {
            $sid = mb_strtoupper(trim((string) ($r->sid ?? '')));
            $departemen = self::kunciDepartemen((string) ($r->departemen ?? ''));
            if ($sid !== '' && $departemen !== '') {
                $peta[$departemen][] = $sid;
            }
        }

        return $peta;
    }

    /**
     * Departemen akun ini (SID login ada di kolom email), null bila bukan approver.
     * Urutan: daftar di config (penimpa manual), lalu jabatan fungsional Manager
     * perusahaan approver dari data karyawan aktif.
     */
    public function departemenUser(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }
        $sid = mb_strtoupper(trim((string) $user->email));
        if ($sid === '') {
            return null;
        }
        $departemen = config('dms_roster.master.approver_departemen.approver.'.$sid);
        if (is_string($departemen)) {
            return $departemen;
        }

        return $this->managerJabatan()[$sid] ?? null;
    }

    /**
     * Manager menurut jabatan (jabatan_fungsional di config jabatan_manager) di
     * perusahaan approver, dari ClickHouse obds. Cache 6 jam.
     *
     * @return array<string, string>
     */
    public function managerJabatan(): array
    {
        if ($this->manager !== null) {
            return $this->manager;
        }
        $simpan = Cache::get(self::KUNCI_MANAGER);
        if (is_array($simpan)) {
            return $this->manager = $simpan;
        }

        $jabatan = array_map(
            static fn ($j): string => RfidClickHouseClient::quote(mb_strtoupper(trim((string) $j))),
            (array) config('dms_roster.master.approver_departemen.jabatan_manager', ['MANAGER']),
        );
        if ($jabatan === []) {
            return $this->manager = [];
        }

        try {
            $rows = app(RfidClickHouseClient::class)->select(sprintf(
                "SELECT upper(trimBoth(ifNull(kode_sid, ''))) AS sid, any(trimBoth(ifNull(departement, ''))) AS departemen
                 FROM obds.bep_vw_safety_karyawan_aktif FINAL
                 WHERE upper(trimBoth(ifNull(nama_perusahaan, ''))) = upper(%s)
                   AND upper(trimBoth(ifNull(jabatan_fungsional, ''))) IN (%s)
                   AND trimBoth(ifNull(kode_sid, '')) <> '' AND trimBoth(ifNull(departement, '')) <> ''
                 GROUP BY sid",
                RfidClickHouseClient::quote($this->perusahaan()),
                implode(', ', $jabatan),
            ));
        } catch (Throwable $e) {
            Log::warning('DmsRoster manager dari jabatan gagal: '.mb_substr($e->getMessage(), 0, 300));
            Cache::put(self::KUNCI_MANAGER, [], now()->addMinutes(self::TTL_GAGAL_MENIT));

            return $this->manager = [];
        }

        $peta = self::petakanManager($rows);
        Cache::put(self::KUNCI_MANAGER, $peta, now()->addMinutes($peta === [] ? self::TTL_GAGAL_MENIT : self::TTL_MANAGER_MENIT));

        return $this->manager = $peta;
    }

    /**
     * Baris (sid, departemen) → SID huruf besar => departemen. Murni.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, string>
     */
    public static function petakanManager(array $rows): array
    {
        $peta = [];
        foreach ($rows as $r) {
            $sid = mb_strtoupper(trim((string) ($r['sid'] ?? '')));
            $departemen = trim((string) ($r['departemen'] ?? ''));
            if ($sid !== '' && $departemen !== '') {
                $peta[$sid] = $departemen;
            }
        }

        return $peta;
    }

    public function dataTersedia(): bool
    {
        return is_array(Cache::get(self::KUNCI));
    }

    /**
     * Upload yang memuat karyawan departemen akun ini.
     *
     * @return list<int>
     */
    public function uploadUntuk(?User $user): array
    {
        $departemen = $this->departemenUser($user);
        if ($departemen === null) {
            return [];
        }
        $kunci = self::kunciDepartemen($departemen);
        if (isset($this->uploadPerDepartemen[$kunci])) {
            return $this->uploadPerDepartemen[$kunci];
        }

        $sids = $this->sidUntuk($user);
        if ($sids === []) {
            return $this->uploadPerDepartemen[$kunci] = [];
        }

        return $this->uploadPerDepartemen[$kunci] = DB::table('dms_roster_master_jadwal')
            ->whereIn('kode_sid', $sids)
            ->distinct()
            ->orderBy('upload_id')
            ->pluck('upload_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * SID karyawan departemen akun ini.
     *
     * @return list<string>
     */
    public function sidUntuk(?User $user): array
    {
        $departemen = $this->departemenUser($user);
        if ($departemen === null) {
            return [];
        }
        $peta = Cache::get(self::KUNCI);
        if (! is_array($peta)) {
            // Salinan Tahap 10 belum ada / kedaluwarsa: isi dari ClickHouse.
            $peta = $this->segarkanClickHouse();
        }
        if (! is_array($peta)) {
            return [];
        }

        return $peta[self::kunciDepartemen($departemen)] ?? [];
    }

    public function bolehLihat(?User $user, int $uploadId): bool
    {
        return in_array($uploadId, $this->uploadUntuk($user), true);
    }

    /**
     * @param  list<string>  $ptUpload  DmsRosterAksesPerusahaan::perusahaanUpload()
     */
    public function bolehReview(?User $user, int $uploadId, array $ptUpload): bool
    {
        return $this->bolehLihat($user, $uploadId)
            && DmsRosterAksesPerusahaan::mencakup([$this->perusahaan()], $ptUpload);
    }

    /**
     * Salin departemen karyawan aktif ke cache. Gagal atau kosong = salinan
     * lama dipertahankan.
     *
     * @return array<string, list<string>>|null
     */
    public function segarkan(): ?array
    {
        try {
            $rows = $this->olap->select(
                "SELECT DISTINCT ON (trim(kode_sid)) trim(kode_sid) AS sid, departement AS departemen
                 FROM {$this->view()}
                 WHERE status_karyawan = ? AND upper(trim(nama_perusahaan)) = upper(?)
                   AND coalesce(trim(kode_sid), '') <> ''
                 ORDER BY trim(kode_sid), id DESC",
                [
                    (string) config('dms_roster.master.approver_departemen.status_karyawan', 'AKTIF'),
                    $this->perusahaan(),
                ],
                self::TIMEOUT_MS,
            );
        } catch (Throwable $e) {
            $this->galat = $e->getMessage();
            Log::warning('DmsRoster departemen karyawan gagal: '.$this->galat);

            return $this->segarkanClickHouse();
        }

        return $this->simpanSalinan($rows);
    }

    /**
     * Departemen karyawan dari salinan ClickHouse obds (dipakai bila OLAP tak
     * terjangkau, mis. di server prod). Gagal = null.
     *
     * @return array<string, list<string>>|null
     */
    public function segarkanClickHouse(): ?array
    {
        try {
            $rows = app(RfidClickHouseClient::class)->select(sprintf(
                "SELECT trimBoth(ifNull(kode_sid, '')) AS sid, any(ifNull(departement, '')) AS departemen
                 FROM obds.bep_vw_safety_karyawan_aktif FINAL
                 WHERE upper(trimBoth(ifNull(nama_perusahaan, ''))) = upper(%s)
                   AND trimBoth(ifNull(kode_sid, '')) <> ''
                 GROUP BY sid",
                RfidClickHouseClient::quote($this->perusahaan()),
            ));
        } catch (Throwable $e) {
            $this->galat = $e->getMessage();
            Log::warning('DmsRoster departemen karyawan (ClickHouse) gagal: '.mb_substr($this->galat, 0, 300));

            return null;
        }

        return $this->simpanSalinan(array_map(static fn (array $r): object => (object) $r, $rows));
    }

    /**
     * @param  list<object>  $rows
     * @return array<string, list<string>>|null
     */
    public function simpanSalinan(array $rows): ?array
    {
        $peta = self::petakan($rows);
        if ($peta === []) {
            $this->galat = 'Tidak ada karyawan ber-departemen untuk '.$this->perusahaan().'.';

            return null;
        }
        Cache::put(self::KUNCI, $peta, now()->addDays(self::TTL_HARI));

        return $peta;
    }

    public function galatTerakhir(): ?string
    {
        return $this->galat;
    }

    /**
     * Departemen approver yang tidak ada di salinan: ejaan di config berbeda
     * dengan data karyawan, sehingga manager-nya tidak mendapat roster.
     *
     * @param  array<string, list<string>>  $peta
     * @return list<string>
     */
    public static function departemenTakDikenal(array $peta): array
    {
        $daftar = (array) config('dms_roster.master.approver_departemen.approver', []);
        $tak = array_filter($daftar, static fn ($d): bool => ! isset($peta[self::kunciDepartemen((string) $d)]));

        return array_values(array_unique($tak));
    }

    private function perusahaan(): string
    {
        return (string) config('dms_roster.master.approver_departemen.perusahaan', 'PT Berau Coal');
    }

    private function view(): string
    {
        $view = (string) config('dms_roster.master.approver_departemen.view_karyawan', 'bcsid.bep_sid_karyawan');
        // Nama view disisipkan langsung ke SQL: hanya skema.nama polos.
        if (preg_match('/^[a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*$/i', $view) !== 1) {
            throw new InvalidArgumentException('dms_roster.master.approver_departemen.view_karyawan tidak valid.');
        }

        return $view;
    }
}
