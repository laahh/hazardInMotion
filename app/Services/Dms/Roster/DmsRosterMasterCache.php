<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\DmsRosterMasterUpload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan dms_roster_master_jadwal (±350 ribu baris) untuk halaman Master
 * Roster — Overview, Approval, Edit, Assign Role.
 *
 * Kenapa: di MySQL dev satu GROUP BY penuh atas tabel ini butuh 17–40 dtk
 * walau sendirian, dan dulu tiap request menjalankan beberapa sekaligus
 * (TRIM(perusahaan) tak bisa memakai index) → antrean query sampai 40 menit
 * dan semua halaman 504.
 *
 * Sekarang hanya dua query dasar, dihitung di LATAR oleh
 * `dms:master-roster-ringkas` (terjadwal tiap menit, hanya bila versi data
 * berubah):
 *   A  per (upload, ejaan perusahaan, site): rentang tanggal
 *   B  per (ejaan perusahaan, site, SID) di upload yang tidak ditolak: status
 * Halaman membaca hasil terakhir (boleh tertinggal ±1–2 menit setelah
 * upload/approve/edit) dan menurunkan semua agregat di PHP. Hanya bila
 * ringkasan belum pernah ada, request pertama menghitungnya (dengan kunci).
 */
final class DmsRosterMasterCache
{
    private const KUNCI = 'dms_roster:master_ringkas:v1';

    private const TUNGGU_KUNCI_DETIK = 120;

    /** @var array{versi:string,dihitung:string,a:list<array{0:int,1:string,2:string,3:string,4:string}>,b:list<array{0:string,1:string,2:string,3:bool,4:bool}>,upload:array<int,array{status:string,dibuat:?string}>}|null */
    private static ?array $ringkas = null;

    /**
     * @return array{versi:string,dihitung:string,a:list<array{0:int,1:string,2:string,3:string,4:string}>,b:list<array{0:string,1:string,2:string,3:bool,4:bool}>,upload:array<int,array{status:string,dibuat:?string}>}
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
        if (! $paksa && is_array($ada) && ($ada['versi'] ?? null) === self::versi()) {
            return ['dihitung' => false, 'detik' => 0.0];
        }

        $mulai = microtime(true);
        self::hitungDanSimpan();

        return ['dihitung' => true, 'detik' => round(microtime(true) - $mulai, 1)];
    }

    /**
     * Ejaan perusahaan (TRIM, '' = kosong) per upload.
     *
     * @return array<int, list<string>>
     */
    public static function ptPerUpload(): array
    {
        $out = [];
        foreach (self::ringkas()['a'] as [$upload, $pt]) {
            $out[$upload][$pt] = true;
        }

        return array_map('array_keys', $out);
    }

    /**
     * Semua ejaan perusahaan non-kosong di jadwal.
     *
     * @return list<string>
     */
    public static function ejaan(): array
    {
        $semua = [];
        foreach (self::ringkas()['a'] as [, $pt]) {
            if ($pt !== '') {
                $semua[$pt] = true;
            }
        }

        return array_map('strval', array_keys($semua));
    }

    /**
     * Pasangan (ejaan, SID) di upload yang tidak ditolak.
     *
     * @return list<object{pt:string,sid:string,aktif:int}>
     */
    public static function pasangan(): array
    {
        $out = [];
        foreach (self::ringkas()['b'] as [$pt, , $sid]) {
            if ($pt !== '') {
                $out[$pt."\0".$sid] = (object) ['pt' => $pt, 'sid' => $sid, 'aktif' => 1];
            }
        }

        return array_values($out);
    }

    /**
     * SID (huruf besar) di roster tidak ditolak dengan salah satu ejaan ini.
     *
     * @param  list<string>  $ejaan
     * @return array<string, true>
     */
    public static function sidRoster(array $ejaan): array
    {
        $cari = array_flip($ejaan === [] ? [''] : $ejaan);
        $out = [];
        foreach (self::ringkas()['b'] as [$pt, , $sid]) {
            if (isset($cari[$pt])) {
                $out[$sid] = true;
            }
        }

        return $out;
    }

    /**
     * Per (perusahaan, site) di upload tidak ditolak — lihat
     * DmsRosterMasterService::uploadPerSite().
     *
     * @return list<array{perusahaan:string,site:string,disetujui:bool,menunggu:bool,karyawan:int}>
     */
    public static function uploadPerSite(): array
    {
        $grup = [];
        foreach (self::ringkas()['b'] as [$pt, $site, $sid, $ok, $tunggu]) {
            $nama = $pt === '' ? DmsRosterMasterService::TANPA_PERUSAHAAN : $pt;
            // MySQL dulu mengelompokkan tanpa membedakan huruf.
            $k = mb_strtolower($nama)."\0".mb_strtolower($site);
            $grup[$k] ??= ['perusahaan' => $nama, 'site' => $site, 'disetujui' => false, 'menunggu' => false, 'sid' => []];
            $grup[$k]['disetujui'] = $grup[$k]['disetujui'] || $ok;
            $grup[$k]['menunggu'] = $grup[$k]['menunggu'] || $tunggu;
            $grup[$k]['sid'][$sid] = true;
        }

        return array_values(array_map(static function (array $g): array {
            $g['karyawan'] = count($g['sid']);
            unset($g['sid']);

            return $g;
        }, $grup));
    }

    /**
     * Ringkasan per perusahaan (nama resmi lewat $peta) — lihat
     * DmsRosterMasterService::perusahaanTerupload().
     *
     * @param  array<string, string>  $peta  ejaan => nama resmi
     * @return list<array<string, mixed>>
     */
    public static function perusahaanTerupload(array $peta): array
    {
        $r = self::ringkas();
        $resmi = static function (string $pt) use ($peta): string {
            $nama = $pt === '' ? DmsRosterMasterService::TANPA_PERUSAHAAN : $pt;

            return $peta[$nama] ?? $nama;
        };

        $grup = [];
        foreach ($r['a'] as [$upload, $pt, $site, $dari, $sampai]) {
            $info = $r['upload'][$upload] ?? null;
            if ($info === null || $info['status'] === DmsRosterMasterUpload::STATUS_REJECTED) {
                continue;
            }
            $nama = $resmi($pt);
            $k = mb_strtolower($nama);
            $g = $grup[$k] ?? ['perusahaan' => $nama, 'ejaan' => [], 'sid' => [], 'upload' => [], 'dari' => $dari, 'sampai' => $sampai, 'terakhir' => null, 'site' => []];
            $g['ejaan'][$pt === '' ? DmsRosterMasterService::TANPA_PERUSAHAAN : $pt] = true;
            $g['upload'][$upload] = $info['status'];
            $g['dari'] = min($g['dari'], $dari);
            $g['sampai'] = max($g['sampai'], $sampai);
            if ($info['dibuat'] !== null && ($g['terakhir'] === null || $info['dibuat'] > $g['terakhir'])) {
                $g['terakhir'] = $info['dibuat'];
            }
            if ($site !== '') {
                $g['site'][$site] = true;
            }
            $grup[$k] = $g;
        }
        foreach ($r['b'] as [$pt, , $sid]) {
            $k = mb_strtolower($resmi($pt));
            if (isset($grup[$k])) {
                $grup[$k]['sid'][$sid] = true;
            }
        }

        $out = [];
        foreach ($grup as $g) {
            $site = array_map('strval', array_keys($g['site']));
            sort($site);
            $status = array_count_values($g['upload']);
            $out[] = [
                'perusahaan' => $g['perusahaan'],
                'ejaan' => array_map('strval', array_keys($g['ejaan'])),
                'karyawan' => count($g['sid']),
                'upload' => count($g['upload']),
                'disetujui' => $status[DmsRosterMasterUpload::STATUS_APPROVED] ?? 0,
                'menunggu' => $status[DmsRosterMasterUpload::STATUS_PENDING] ?? 0,
                'dari' => $g['dari'],
                'sampai' => $g['sampai'],
                'terakhir' => $g['terakhir'],
                'site' => $site,
            ];
        }
        usort($out, static fn (array $x, array $y): int => strcasecmp($x['perusahaan'], $y['perusahaan']));

        return $out;
    }

    /** Versi data yang murah dibaca (tabel upload kecil + MAX(id) lewat primary key). */
    public static function versi(): string
    {
        $u = DB::table('dms_roster_master_upload')
            ->selectRaw('COUNT(*) AS n, MAX(id) AS id, MAX(updated_at) AS ubah')
            ->first();
        $j = DB::table('dms_roster_master_jadwal')->max('id');
        try {
            $e = DB::table('dms_roster_master_edit_log')->max('id');
        } catch (\Throwable) {
            $e = null;
        }

        return md5(implode('|', [$u->n ?? 0, $u->id ?? 0, $u->ubah ?? '', $j ?? 0, $e ?? 0]));
    }

    /** Untuk tes. */
    public static function lupakan(): void
    {
        self::$ringkas = null;
    }

    /**
     * @return array{versi:string,dihitung:string,a:list<array{0:int,1:string,2:string,3:string,4:string}>,b:list<array{0:string,1:string,2:string,3:bool,4:bool}>,upload:array<int,array{status:string,dibuat:?string}>}
     */
    private static function hitungDanSimpan(): array
    {
        // Versi dibaca SEBELUM query: perubahan di tengah perhitungan
        // memicu hitung ulang pada putaran berikutnya.
        $versi = self::versi();

        $a = [];
        foreach (DB::select("SELECT j.upload_id, TRIM(COALESCE(j.perusahaan, '')) AS pt, TRIM(COALESCE(j.site, '')) AS site,
                    MIN(j.tanggal) AS dari, MAX(j.tanggal) AS sampai
                FROM dms_roster_master_jadwal j GROUP BY 1, 2, 3") as $x) {
            $a[] = [(int) $x->upload_id, (string) $x->pt, (string) $x->site, (string) $x->dari, (string) $x->sampai];
        }

        $b = [];
        foreach (DB::select("SELECT TRIM(COALESCE(j.perusahaan, '')) AS pt, TRIM(COALESCE(j.site, '')) AS site, UPPER(TRIM(j.kode_sid)) AS sid,
                    MAX(u.status = ?) AS ok, MAX(u.status = ?) AS tunggu
                FROM dms_roster_master_jadwal j
                JOIN dms_roster_master_upload u ON u.id = j.upload_id
                WHERE u.status <> ?
                GROUP BY 1, 2, 3", [DmsRosterMasterUpload::STATUS_APPROVED, DmsRosterMasterUpload::STATUS_PENDING, DmsRosterMasterUpload::STATUS_REJECTED]) as $x) {
            $b[] = [(string) $x->pt, (string) $x->site, (string) $x->sid, (bool) $x->ok, (bool) $x->tunggu];
        }

        $upload = [];
        foreach (DB::table('dms_roster_master_upload')->get(['id', 'status', 'created_at']) as $x) {
            $upload[(int) $x->id] = ['status' => (string) $x->status, 'dibuat' => $x->created_at === null ? null : (string) $x->created_at];
        }

        $hasil = ['versi' => $versi, 'dihitung' => now()->toDateTimeString(), 'a' => $a, 'b' => $b, 'upload' => $upload];
        Cache::forever(self::KUNCI, $hasil);

        return self::$ringkas = $hasil;
    }
}
