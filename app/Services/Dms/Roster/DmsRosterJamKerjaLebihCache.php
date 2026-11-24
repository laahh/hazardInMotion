<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\DmsRosterMasterUpload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Jumlah hari (tidak harus berturutan) tiap SID punya jadwal Master Roster
 * APPROVED dengan jam kerja > ambang (config dms_roster.total_karyawan.
 * jam_kerja_lebih_jam) — dasar kartu "Total Karyawan" baru di Ringkasan
 * Roster (DmsRosterTotalKaryawanReader) dan calon aturan banned
 * (> banned_hari, lihat config dms_roster.total_karyawan.banned_hari).
 *
 * Dihitung dari dms_roster_master_jadwal (±350 ribu baris) — mahal untuk
 * query per-request (lihat DmsRosterMasterCache), jadi dihitung di LATAR
 * oleh `dms:master-roster-jam-kerja-ringkas` (terjadwal tiap menit, hanya
 * bila versi data berubah — versi dipakai bersama DmsRosterMasterCache).
 *
 * Jadwal yang berlaku untuk (SID, tanggal) = baris dari upload APPROVED
 * yang paling akhir disetujui (ROW_NUMBER partisi SID+tanggal), sama
 * dengan definisi di DmsRosterMasterService.
 */
final class DmsRosterJamKerjaLebihCache
{
    private const KUNCI = 'dms_roster:jam_kerja_lebih:v1';

    private const TUNGGU_KUNCI_DETIK = 120;

    /** @var array{versi:string,dihitung:string,hari:array<string,int>}|null */
    private static ?array $ringkas = null;

    /**
     * @return array{versi:string,dihitung:string,hari:array<string,int>}
     */
    public static function ringkas(): array
    {
        if (self::$ringkas !== null) {
            return self::$ringkas;
        }
        $ada = Cache::get(self::KUNCI);
        if (is_array($ada)) {
            return self::$ringkas = $ada;
        }

        return self::$ringkas = Cache::lock(self::KUNCI.':kunci', self::TUNGGU_KUNCI_DETIK)->block(
            self::TUNGGU_KUNCI_DETIK,
            static fn (): array => is_array($lagi = Cache::get(self::KUNCI)) ? $lagi : self::hitungDanSimpan(),
        );
    }

    /**
     * Hitung ulang bila versi data berubah (atau dipaksa). Untuk command.
     *
     * @return array{dihitung:bool, detik:float}
     */
    public static function segarkan(bool $paksa = false): array
    {
        $ada = Cache::get(self::KUNCI);
        if (! $paksa && is_array($ada) && ($ada['versi'] ?? null) === DmsRosterMasterCache::versi()) {
            return ['dihitung' => false, 'detik' => 0.0];
        }

        $mulai = microtime(true);
        self::hitungDanSimpan();

        return ['dihitung' => true, 'detik' => round(microtime(true) - $mulai, 1)];
    }

    /** SID (huruf besar) dengan minimal satu hari jam kerja > ambang. */
    public static function sidLebih(): array
    {
        return array_keys(self::ringkas()['hari']);
    }

    /** Jumlah hari per SID (huruf besar) dengan jam kerja > ambang. */
    public static function hariPerSid(): array
    {
        return self::ringkas()['hari'];
    }

    /** SID yang sudah lewat ambang banned (config total_karyawan.banned_hari). */
    public static function sidBanned(): array
    {
        $ambang = (int) config('dms_roster.total_karyawan.banned_hari', 70);

        return array_keys(array_filter(self::ringkas()['hari'], static fn (int $n): bool => $n > $ambang));
    }

    /** Untuk tes. */
    public static function lupakan(): void
    {
        self::$ringkas = null;
    }

    /**
     * @return array{versi:string,dihitung:string,hari:array<string,int>}
     */
    private static function hitungDanSimpan(): array
    {
        // Versi dibaca SEBELUM query: perubahan di tengah perhitungan
        // memicu hitung ulang pada putaran berikutnya. Dipakai bersama
        // DmsRosterMasterCache supaya tidak ada dua query versi terpisah.
        $versi = DmsRosterMasterCache::versi();
        $ambangMenit = (int) config('dms_roster.total_karyawan.jam_kerja_lebih_jam', 11) * 60;

        $rows = DB::select('
            SELECT sid, COUNT(*) AS hari_lebih
            FROM (
                SELECT
                    UPPER(TRIM(j.kode_sid)) AS sid,
                    j.tanggal,
                    COALESCE(j.menit_total_kerja, j.menit_kerja) AS menit,
                    ROW_NUMBER() OVER (
                        PARTITION BY j.kode_sid, j.tanggal
                        ORDER BY COALESCE(u.direview_pada, u.created_at) DESC, u.id DESC
                    ) AS rn
                FROM dms_roster_master_jadwal j
                INNER JOIN dms_roster_master_upload u ON u.id = j.upload_id
                WHERE u.status = ?
            ) t
            WHERE t.rn = 1 AND t.menit > ?
            GROUP BY sid
        ', [DmsRosterMasterUpload::STATUS_APPROVED, $ambangMenit]);

        $hari = [];
        foreach ($rows as $r) {
            $sid = (string) $r->sid;
            if ($sid !== '') {
                $hari[$sid] = (int) $r->hari_lebih;
            }
        }

        $hasil = ['versi' => $versi, 'dihitung' => now()->toDateTimeString(), 'hari' => $hari];
        Cache::forever(self::KUNCI, $hasil);

        return self::$ringkas = $hasil;
    }
}
