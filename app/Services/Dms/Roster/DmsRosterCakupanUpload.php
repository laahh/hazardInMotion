<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

/**
 * Perbandingan cakupan upload roster: seluruh perusahaan & site karyawan
 * aktif (view safety, DmsRosterKaryawanAktif) terhadap roster yang sudah
 * di-upload (dms_roster_master_jadwal, tanpa yang ditolak).
 *
 * Status (perusahaan maupun site):
 *   sudah     ada roster DISETUJUI
 *   menunggu  hanya ada roster yang menunggu approval
 *   belum     belum ada roster sama sekali
 *
 * Nama perusahaan dicocokkan seperti Assign Role (DmsRosterAksesPerusahaan):
 * ejaan sama (abaikan spasi/tanda baca/awalan PT) didahulukan; bila tidak
 * ada, singkatan ("PT PMJ") dicocokkan ke nama lengkap berinisial sama.
 * Site dicocokkan tanpa membedakan huruf & spasi ("BMO 2" = "bmo2").
 * Karyawan aktif tanpa site ("-") tidak ikut dihitung sebagai site.
 *
 * Murni, tanpa DB.
 */
final class DmsRosterCakupanUpload
{
    public const STATUS = ['belum', 'menunggu', 'sudah'];

    /**
     * @param  list<array{nama:string,jumlah:int,site:list<array{nama:string,jumlah:int}>}>  $aktif  DmsRosterKaryawanAktif::perusahaan()
     * @param  list<array{perusahaan:string,site:string,disetujui:bool,menunggu:bool,karyawan:int}>  $upload  DmsRosterMasterService::uploadPerSite()
     * @return array{perusahaan: list<array<string, mixed>>, ringkas: array<string, mixed>, tidak_cocok: list<string>}
     */
    public static function bandingkan(array $aktif, array $upload): array
    {
        $namaUpload = array_values(array_unique(array_column($upload, 'perusahaan')));
        $cocok = self::cocokkan(array_column($aktif, 'nama'), $namaUpload);

        $perNama = [];
        foreach ($upload as $u) {
            $perNama[$u['perusahaan']][] = $u;
        }

        $baris = [];
        $terpakai = [];
        foreach ($aktif as $i => $pt) {
            $alias = $cocok[$i] ?? [];
            $rows = [];
            foreach ($alias as $nama) {
                $terpakai[$nama] = true;
                array_push($rows, ...($perNama[$nama] ?? []));
            }

            $perSite = [];
            foreach ($rows as $r) {
                $k = self::kunciSite($r['site']);
                $perSite[$k] = [
                    'disetujui' => ($perSite[$k]['disetujui'] ?? false) || $r['disetujui'],
                    'menunggu' => ($perSite[$k]['menunggu'] ?? false) || $r['menunggu'],
                    'nama' => $perSite[$k]['nama'] ?? $r['site'],
                ];
            }

            $site = [];
            $dikenal = [];
            foreach ($pt['site'] as $s) {
                if ($s['nama'] === '-' || trim($s['nama']) === '') {
                    continue;
                }
                $k = self::kunciSite($s['nama']);
                $dikenal[$k] = true;
                $site[] = ['nama' => $s['nama'], 'karyawan' => $s['jumlah'], 'status' => self::status($perSite[$k] ?? null)];
            }
            $siteLain = [];
            foreach ($perSite as $k => $s) {
                if (! isset($dikenal[$k]) && $k !== '') {
                    $siteLain[] = $s['nama'];
                }
            }

            $baris[] = [
                'nama' => $pt['nama'],
                'karyawan' => $pt['jumlah'],
                'status' => self::status($rows === [] ? null : [
                    'disetujui' => in_array(true, array_column($rows, 'disetujui'), true),
                    'menunggu' => in_array(true, array_column($rows, 'menunggu'), true),
                ]),
                // Ejaan upload yang berbeda dari nama di data karyawan.
                'alias' => array_values(array_filter($alias, static fn (string $a): bool => self::tulisan($a) !== self::tulisan($pt['nama']))),
                'karyawan_roster' => array_sum(array_column($rows, 'karyawan')),
                'site' => $site,
                'site_sudah' => count(array_filter($site, static fn (array $s): bool => $s['status'] === 'sudah')),
                // Site di roster yang tidak ada di data karyawan aktif PT ini.
                'site_lain' => $siteLain,
            ];
        }

        return [
            'perusahaan' => $baris,
            'ringkas' => self::ringkas($baris),
            'tidak_cocok' => array_values(array_filter($namaUpload, static fn (string $n): bool => ! isset($terpakai[$n]))),
        ];
    }

    /**
     * Nama upload yang cocok per perusahaan aktif (indeks $aktif).
     *
     * @param  list<string>  $aktif
     * @param  list<string>  $upload
     * @return array<int, list<string>>
     */
    private static function cocokkan(array $aktif, array $upload): array
    {
        $hasil = [];
        foreach ($upload as $nama) {
            $persis = array_keys(array_filter($aktif, static fn (string $a): bool => DmsRosterAksesPerusahaan::samaNama($a, $nama)));
            $target = $persis !== []
                ? $persis
                : array_keys(array_filter($aktif, static fn (string $a): bool => DmsRosterAksesPerusahaan::terlihat([$a], [$nama])));
            foreach ($target as $i) {
                $hasil[$i][] = $nama;
            }
        }

        return $hasil;
    }

    /**
     * @param  array{disetujui:bool,menunggu:bool}|null  $s
     */
    private static function status(?array $s): string
    {
        return match (true) {
            $s === null => 'belum',
            $s['disetujui'] => 'sudah',
            $s['menunggu'] => 'menunggu',
            default => 'belum',
        };
    }

    /** Tulisan apa adanya (huruf besar, spasi dirapikan) — untuk menampilkan ejaan lain. */
    private static function tulisan(string $nama): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($nama)) ?? trim($nama));
    }

    private static function kunciSite(string $site): string
    {
        return mb_strtoupper(preg_replace('/[^\p{L}\p{N}]+/u', '', $site) ?? $site);
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, mixed>
     */
    private static function ringkas(array $baris): array
    {
        $pt = array_fill_keys(self::STATUS, 0);
        $site = array_fill_keys(self::STATUS, 0);
        $karyawan = array_fill_keys(self::STATUS, 0);
        foreach ($baris as $b) {
            $pt[$b['status']]++;
            $karyawan[$b['status']] += $b['karyawan'];
            foreach ($b['site'] as $s) {
                $site[$s['status']]++;
            }
        }

        return [
            'pt' => $pt + ['total' => count($baris)],
            'site' => $site + ['total' => array_sum($site)],
            'karyawan' => $karyawan + ['total' => array_sum($karyawan)],
        ];
    }
}
