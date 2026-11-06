<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\User;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Approver Roster per departemen: manager di config
 * dms_roster.master.approver_departemen boleh me-review roster yang memuat
 * karyawan departemennya, selain jalur Assign Role yang sudah ada.
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

    /** Departemen akun ini (SID login ada di kolom email), null bila bukan approver. */
    public function departemenUser(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }
        $sid = mb_strtoupper(trim((string) $user->email));
        $departemen = config('dms_roster.master.approver_departemen.approver.'.$sid);

        return is_string($departemen) ? $departemen : null;
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
        $peta = Cache::get(self::KUNCI);
        if ($departemen === null || ! is_array($peta)) {
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

            return null;
        }

        return $this->simpanSalinan($rows);
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
