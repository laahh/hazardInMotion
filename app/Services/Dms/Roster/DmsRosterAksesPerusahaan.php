<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Assign Role Master Roster: perusahaan yang boleh dilihat & di-approve user
 * di halaman Approval Roster (tabel dms_roster_master_akses).
 *
 * Cakupan user ditentukan begini:
 *   - punya assignment       → hanya perusahaan itu (admin pun ikut dibatasi,
 *                              supaya assign ke diri sendiri tetap berlaku)
 *   - tanpa assignment, admin → semua perusahaan (null)
 *   - tanpa assignment, lain  → tidak ada ([])
 *
 * Selama tabelnya belum dimigrasi, semua user tetap melihat semua perusahaan
 * seperti sebelum fitur ini ada.
 *
 * Satu upload bisa memuat beberapa perusahaan (perusahaan disimpan per baris
 * jadwal). Upload TERLIHAT bila salah satu perusahaannya masuk cakupan, tapi
 * hanya boleh DI-REVIEW bila semua perusahaannya masuk cakupan — approve
 * berlaku untuk seluruh isi file.
 *
 * Nama perusahaan di file upload tidak selalu sama dengan nama yang di-assign:
 * ada yang menulis singkatan ("PT PMJ") untuk "PT Puncak Makmur Jaya". Karena
 * itu singkatan dicocokkan ke kode PT dari nama lengkap (config
 * dms_roster.perusahaan, atau inisial — lihat DmsRosterSyncService::kodePt).
 * Dua nama lengkap tetap harus sama persis, supaya dua PT berbeda yang kebetulan
 * berinisial sama tidak tertukar.
 */
final class DmsRosterAksesPerusahaan
{
    public const TABEL = 'dms_roster_master_akses';

    private ?bool $siap = null;

    public function siap(): bool
    {
        if ($this->siap === null) {
            try {
                $this->siap = Schema::hasTable(self::TABEL);
            } catch (Throwable) {
                $this->siap = false;
            }
        }

        return $this->siap;
    }

    /**
     * @return list<string>|null null = semua perusahaan
     */
    public function cakupan(?User $user): ?array
    {
        if (! $this->siap()) {
            return null;
        }
        if ($user === null) {
            return [];
        }

        $pt = DB::table(self::TABEL)->where('user_id', $user->getKey())->orderBy('perusahaan')->pluck('perusahaan')
            ->map(static fn ($p): string => (string) $p)->all();
        if ($pt !== []) {
            return $pt;
        }

        return $user->isAdmin() ? null : [];
    }

    /**
     * Semua perusahaan upload masuk cakupan → boleh approve/tolak.
     *
     * @param  list<string>|null  $cakupan
     * @param  list<string>  $ptUpload
     */
    public static function mencakup(?array $cakupan, array $ptUpload): bool
    {
        if ($cakupan === null) {
            return true;
        }
        if ($ptUpload === []) {
            return false;
        }
        $indeks = self::indeks($cakupan);
        foreach ($ptUpload as $pt) {
            if (! self::masuk($pt, $indeks)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Salah satu perusahaan upload masuk cakupan → upload boleh dilihat.
     *
     * @param  list<string>|null  $cakupan
     * @param  list<string>  $ptUpload
     */
    public static function terlihat(?array $cakupan, array $ptUpload): bool
    {
        if ($cakupan === null) {
            return true;
        }
        $indeks = self::indeks($cakupan);
        foreach ($ptUpload as $pt) {
            if (self::masuk($pt, $indeks)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Perusahaan yang ada di satu upload (dari baris jadwalnya).
     *
     * @return list<string>
     */
    public function perusahaanUpload(int $uploadId): array
    {
        return DB::table('dms_roster_master_jadwal')
            ->where('upload_id', $uploadId)
            ->selectRaw("DISTINCT COALESCE(NULLIF(TRIM(perusahaan), ''), ?) AS pt", [DmsRosterMasterService::TANPA_PERUSAHAAN])
            ->orderBy('pt')
            ->pluck('pt')
            ->map(static fn ($p): string => (string) $p)
            ->all();
    }

    /**
     * Batasi query DmsRosterMasterUpload ke upload yang terlihat oleh cakupan.
     *
     * @param  list<string>|null  $cakupan
     */
    public function batasiUpload(Builder $q, ?array $cakupan): Builder
    {
        if ($cakupan === null) {
            return $q;
        }
        if ($cakupan === []) {
            return $q->whereRaw('1 = 0');
        }

        $tanpa = in_array(DmsRosterMasterService::TANPA_PERUSAHAAN, $cakupan, true);
        // Ejaan perusahaan yang benar-benar ada di jadwal (termasuk singkatan)
        // yang cocok dengan cakupan; daftar distinct-nya kecil.
        $indeks = self::indeks($cakupan);
        $ejaan = DB::table('dms_roster_master_jadwal')
            ->selectRaw('DISTINCT TRIM(perusahaan) AS pt')
            ->whereNotNull('perusahaan')
            ->pluck('pt')
            ->map(static fn ($p): string => (string) $p)
            ->filter(static fn (string $p): bool => $p !== '' && self::masuk($p, $indeks))
            ->values()
            ->all();

        return $q->whereExists(static function ($sub) use ($ejaan, $tanpa): void {
            $sub->selectRaw('1')
                ->from('dms_roster_master_jadwal as j')
                ->whereColumn('j.upload_id', 'dms_roster_master_upload.id')
                ->where(static function ($w) use ($ejaan, $tanpa): void {
                    $w->whereIn(DB::raw('TRIM(j.perusahaan)'), $ejaan === [] ? [''] : $ejaan);
                    if ($tanpa) {
                        $w->orWhereNull('j.perusahaan')->orWhereRaw("TRIM(j.perusahaan) = ''");
                    }
                });
        });
    }

    /**
     * Daftar assignment per user untuk halaman Assign Role.
     *
     * @return list<array{user_id:int,nama:string,email:string,admin:bool,perusahaan:list<string>,diubah:?string,oleh:?string}>
     */
    public function daftar(string $cari = ''): array
    {
        if (! $this->siap()) {
            return [];
        }

        $baris = DB::table(self::TABEL.' as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->when($cari !== '', static fn ($q) => $q->where(static fn ($w) => $w
                ->where('u.name', 'like', '%'.$cari.'%')
                ->orWhere('u.email', 'like', '%'.$cari.'%')
                ->orWhere('a.perusahaan', 'like', '%'.$cari.'%')))
            ->orderBy('u.name')
            ->orderBy('a.perusahaan')
            ->get(['a.user_id', 'u.name', 'u.email', 'u.role', 'a.perusahaan', 'a.updated_at', 'a.dibuat_oleh']);

        $out = [];
        foreach ($baris as $b) {
            $id = (int) $b->user_id;
            $out[$id] ??= [
                'user_id' => $id,
                'nama' => (string) ($b->name ?? '(user #'.$id.' terhapus)'),
                'email' => (string) ($b->email ?? ''),
                'admin' => in_array((string) $b->role, ['admin', 'administrator'], true),
                'perusahaan' => [],
                'diubah' => null,
                'oleh' => null,
            ];
            $out[$id]['perusahaan'][] = (string) $b->perusahaan;
            if ($b->updated_at !== null && ($out[$id]['diubah'] === null || (string) $b->updated_at > $out[$id]['diubah'])) {
                $out[$id]['diubah'] = (string) $b->updated_at;
                $out[$id]['oleh'] = $b->dibuat_oleh === null ? null : (string) $b->dibuat_oleh;
            }
        }

        return array_values($out);
    }

    /**
     * Ganti seluruh perusahaan seorang user dengan daftar baru.
     *
     * @param  list<string>  $perusahaan
     */
    public function simpan(int $userId, array $perusahaan, string $oleh): int
    {
        $bersih = self::rapikan($perusahaan);
        $kini = now();

        DB::transaction(static function () use ($userId, $bersih, $oleh, $kini): void {
            DB::table(self::TABEL)->where('user_id', $userId)->delete();
            if ($bersih !== []) {
                DB::table(self::TABEL)->insert(array_map(static fn (string $pt): array => [
                    'user_id' => $userId,
                    'perusahaan' => $pt,
                    'dibuat_oleh' => mb_substr($oleh, 0, 150),
                    'created_at' => $kini,
                    'updated_at' => $kini,
                ], $bersih));
            }
        });

        return count($bersih);
    }

    public function hapus(int $userId): int
    {
        return DB::table(self::TABEL)->where('user_id', $userId)->delete();
    }

    /**
     * Trim, buang kosong & duplikat (tanpa membedakan huruf besar/kecil).
     *
     * @param  list<string>  $perusahaan
     * @return list<string>
     */
    public static function rapikan(array $perusahaan): array
    {
        $out = [];
        foreach ($perusahaan as $pt) {
            $pt = mb_substr(trim((string) $pt), 0, 255);
            if ($pt !== '') {
                $out[self::kunci($pt)] ??= $pt;
            }
        }
        $out = array_values($out);
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    private static function kunci(string $pt): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($pt)) ?? trim($pt));
    }

    /**
     * [nama persis, kode dari nama lengkap, singkatan yang di-assign apa adanya].
     *
     * @param  list<string>  $cakupan
     * @return array{0: array<string, true>, 1: array<string, true>, 2: array<string, true>}
     */
    private static function indeks(array $cakupan): array
    {
        $nama = [];
        $kodeNama = [];
        $singkatan = [];
        foreach ($cakupan as $pt) {
            $nama[self::kunci($pt)] = true;
            [$isSingkatan, $kode] = self::kode($pt);
            if ($isSingkatan) {
                $singkatan[$kode] = true;
            } else {
                $kodeNama[$kode] = true;
            }
        }

        return [$nama, $kodeNama, $singkatan];
    }

    /**
     * @param  array{0: array<string, true>, 1: array<string, true>, 2: array<string, true>}  $indeks
     */
    private static function masuk(string $pt, array $indeks): bool
    {
        [$nama, $kodeNama, $singkatan] = $indeks;
        if (isset($nama[self::kunci($pt)])) {
            return true;
        }
        [$isSingkatan, $kode] = self::kode($pt);

        // Singkatan cocok dengan nama lengkap mana pun; nama lengkap hanya
        // cocok dengan singkatan (bukan dengan nama lengkap lain berinisial sama).
        return $isSingkatan ? isset($kodeNama[$kode]) || isset($singkatan[$kode]) : isset($singkatan[$kode]);
    }

    /**
     * "PT PMJ" → [true, 'PMJ']; "PT Puncak Makmur Jaya" → [false, 'PMJ'].
     *
     * @return array{0: bool, 1: string}
     */
    private static function kode(string $pt): array
    {
        $pt = preg_replace('/\s+/u', ' ', trim($pt)) ?? trim($pt);
        $sisa = trim(preg_replace('/^(PT|CV|UD|KOPERASI)\.?\s+/i', '', $pt) ?? $pt);

        if (preg_match('/^[A-Z0-9&]{2,12}$/', $sisa) === 1) {
            return [true, $sisa];
        }

        return [false, mb_strtoupper(DmsRosterSyncService::kodePt($pt))];
    }
}
