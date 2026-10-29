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
 * Singkatan yang cocok ke beberapa perusahaan ("PT SMB" = Sambakungan Makmur
 * Bersama / Sumber Mitra Binungan) diputuskan lewat $suara: perusahaan aktif
 * pemilik mayoritas SID di roster itu; tanpa suara = tidak cocok (bukan
 * dihitung ganda). Salah ketik kecil (<= 2 huruf, satu kandidat jelas) ikut
 * dicocokkan.
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
     * @param  array<string, array<string, int>>  $suara  ejaan di roster => [perusahaan aktif pemilik SID => jumlah SID]
     * @return array{perusahaan: list<array<string, mixed>>, ringkas: array<string, mixed>, tidak_cocok: list<string>}
     */
    public static function bandingkan(array $aktif, array $upload, array $suara = []): array
    {
        $namaUpload = array_values(array_unique(array_column($upload, 'perusahaan')));
        $cocok = self::cocokkan(array_column($aktif, 'nama'), $namaUpload, $suara);

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
                    'roster' => ($perSite[$k]['roster'] ?? 0) + $r['karyawan'],
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
                $site[] = [
                    'nama' => $s['nama'],
                    'karyawan' => $s['jumlah'],
                    'status' => self::status($perSite[$k] ?? null),
                    // Karyawan di roster yang site-nya ini (untuk filter site).
                    'roster' => $perSite[$k]['roster'] ?? 0,
                ];
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
     * @param  array<string, array<string, int>>  $suara
     * @return array<int, list<string>>
     */
    private static function cocokkan(array $aktif, array $upload, array $suara = []): array
    {
        $hasil = [];
        foreach ($upload as $nama) {
            $persis = array_keys(array_filter($aktif, static fn (string $a): bool => DmsRosterAksesPerusahaan::samaNama($a, $nama)));
            $target = $persis !== []
                ? $persis
                : array_keys(array_filter($aktif, static fn (string $a): bool => DmsRosterAksesPerusahaan::terlihat([$a], [$nama])));
            if ($target === [] && ($mirip = self::salahKetik($aktif, $nama)) !== null) {
                $target = [$mirip];
            }
            if (count($target) > 1) {
                $target = self::pilihLewatSid($aktif, $target, $suara[$nama] ?? []);
            }
            foreach ($target as $i) {
                $hasil[$i][] = $nama;
            }
        }

        return $hasil;
    }

    /** Porsi SID minimal milik satu kandidat agar singkatan ambigu diputuskan ke sana. */
    private const SUARA_MAYORITAS = 0.5;

    /**
     * Singkatan ambigu: kandidat yang memiliki lebih dari separuh SID di
     * roster itu. Tidak ada mayoritas / tanpa data = tidak dicocokkan.
     *
     * @param  list<string>  $aktif
     * @param  list<int>  $kandidat
     * @param  array<string, int>  $suara  perusahaan aktif => jumlah SID
     * @return list<int>
     */
    private static function pilihLewatSid(array $aktif, array $kandidat, array $suara): array
    {
        $total = array_sum($suara);
        foreach ($kandidat as $i) {
            if ($total > 0 && ($suara[$aktif[$i]] ?? 0) / $total > self::SUARA_MAYORITAS) {
                return [$i];
            }
        }

        return [];
    }

    /** Selisih huruf maksimal yang dianggap salah ketik. */
    private const SALAH_KETIK_MAKS = 2;

    /** Kandidat kedua harus berselisih sejauh ini supaya tebakan tidak ambigu. */
    private const SALAH_KETIK_JARAK_PESAING = 5;

    /** Nama pendek (singkatan) tidak dicocokkan lewat salah ketik. */
    private const SALAH_KETIK_PANJANG_MIN = 10;

    /**
     * Salah ketik kecil di nama roster ("Eurotruk Transindo" vs "Eurotruk
     * Trasindo"): satu-satunya nama aktif berselisih <= 2 huruf, dan nama
     * terdekat berikutnya jauh berbeda. Null bila tidak ada / ambigu.
     *
     * @param  list<string>  $aktif
     */
    private static function salahKetik(array $aktif, string $nama): ?int
    {
        $kunci = DmsRosterAksesPerusahaan::kunciNama($nama);
        if (strlen($kunci) < self::SALAH_KETIK_PANJANG_MIN) {
            return null;
        }

        $jarak = [];
        foreach ($aktif as $i => $a) {
            $jarak[$i] = levenshtein($kunci, DmsRosterAksesPerusahaan::kunciNama($a));
        }
        asort($jarak);
        $urut = array_keys($jarak);
        $terdekat = $urut[0] ?? null;
        if ($terdekat === null || $jarak[$terdekat] > self::SALAH_KETIK_MAKS) {
            return null;
        }
        $pesaing = isset($urut[1]) ? $jarak[$urut[1]] : PHP_INT_MAX;

        return $pesaing - $jarak[$terdekat] >= self::SALAH_KETIK_JARAK_PESAING ? $terdekat : null;
    }

    /**
     * Peta ejaan nama di roster → nama perusahaan di data karyawan aktif,
     * dengan aturan pencocokan yang sama dengan bandingkan(). Ejaan yang
     * cocok ke lebih dari satu perusahaan (singkatan ambigu) atau tidak
     * ditemukan dibiarkan apa adanya.
     *
     * @param  list<string>  $aktif  nama perusahaan aktif
     * @param  list<string>  $upload  ejaan nama di roster
     * @param  array<string, array<string, int>>  $suara  lihat bandingkan()
     * @return array<string, string>
     */
    public static function peta(array $aktif, array $upload, array $suara = []): array
    {
        $aktif = array_values($aktif);
        $tujuan = [];
        foreach (self::cocokkan($aktif, $upload, $suara) as $i => $daftar) {
            foreach ($daftar as $nama) {
                $tujuan[$nama][] = $aktif[$i];
            }
        }

        $peta = [];
        foreach ($upload as $nama) {
            $peta[$nama] = count($tujuan[$nama] ?? []) === 1 ? $tujuan[$nama][0] : $nama;
        }

        return $peta;
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
