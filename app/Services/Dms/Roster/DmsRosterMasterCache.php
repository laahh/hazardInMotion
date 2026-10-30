<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cache agregasi atas dms_roster_master_jadwal (±350 ribu baris) untuk jalur
 * halaman Master Roster.
 *
 * Kenapa: Overview, Approval, Edit dan Assign Role tiap request menjalankan
 * DISTINCT/GROUP BY penuh dengan TRIM(perusahaan) (tak bisa memakai index).
 * Satu query < 1 dtk, tapi puluhan request bersamaan (pengguna me-refresh
 * halaman yang 504) membuat MySQL dev mengantre 20–100 dtk per query dan ikut
 * menahan insert dms:sync-roster-rfid.
 *
 * Kunci cache memuat "versi" data yang murah dibaca (tabel upload ±200 baris
 * + MAX(id) jadwal lewat primary key): upload/approve/tolak/hapus/edit
 * mengubah versi, jadi hasil dihitung ulang sekali setelah data berubah.
 * Kunci (lock) mencegah request bersamaan menghitung hal yang sama.
 */
final class DmsRosterMasterCache
{
    private const TTL_DETIK = 1800;

    private const TUNGGU_KUNCI_DETIK = 90;

    private static ?string $versi = null;

    /** @var array<int, list<string>>|null */
    private static ?array $ptPerUpload = null;

    /**
     * Hasil $hitung untuk nama + versi data saat ini.
     *
     * @template T
     *
     * @param  callable(): T  $hitung
     * @return T
     */
    public static function ingat(string $nama, callable $hitung): mixed
    {
        $kunci = 'dms_roster:master_agregat:'.$nama.':'.self::versi();
        $ada = Cache::get($kunci);
        if ($ada !== null) {
            return $ada;
        }

        return Cache::lock($kunci.':kunci', self::TUNGGU_KUNCI_DETIK)->block(
            self::TUNGGU_KUNCI_DETIK,
            static fn (): mixed => Cache::remember($kunci, self::TTL_DETIK, $hitung),
        );
    }

    /**
     * Ejaan perusahaan (TRIM, '' = kosong) per upload — satu scan jadwal,
     * dipakai untuk daftar ejaan & penyaringan upload per cakupan.
     *
     * @return array<int, list<string>> upload_id => ejaan
     */
    public static function ptPerUpload(): array
    {
        return self::$ptPerUpload ??= self::ingat('pt_per_upload', static function (): array {
            $out = [];
            foreach (DB::table('dms_roster_master_jadwal')
                ->selectRaw("upload_id, TRIM(COALESCE(perusahaan, '')) AS pt")
                ->groupByRaw('1, 2')
                ->get() as $r) {
                $out[(int) $r->upload_id][] = (string) $r->pt;
            }

            return $out;
        });
    }

    /**
     * Semua ejaan perusahaan non-kosong di jadwal (distinct).
     *
     * @return list<string>
     */
    public static function ejaan(): array
    {
        $semua = [];
        foreach (self::ptPerUpload() as $pts) {
            foreach ($pts as $pt) {
                if ($pt !== '') {
                    $semua[$pt] = true;
                }
            }
        }

        return array_keys($semua);
    }

    public static function versi(): string
    {
        if (self::$versi !== null) {
            return self::$versi;
        }

        $u = DB::table('dms_roster_master_upload')
            ->selectRaw('COUNT(*) AS n, MAX(id) AS id, MAX(updated_at) AS ubah')
            ->first();
        $j = DB::table('dms_roster_master_jadwal')->max('id');
        try {
            $e = DB::table('dms_roster_master_edit_log')->max('id');
        } catch (\Throwable) {
            $e = null;
        }

        return self::$versi = md5(implode('|', [$u->n ?? 0, $u->id ?? 0, $u->ubah ?? '', $j ?? 0, $e ?? 0]));
    }

    /** Untuk tes / setelah menulis dalam proses yang sama. */
    public static function lupakan(): void
    {
        self::$versi = null;
        self::$ptPerUpload = null;
    }
}
