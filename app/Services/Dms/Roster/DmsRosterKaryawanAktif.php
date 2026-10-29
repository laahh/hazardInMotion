<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Karyawan aktif (populasi.view_karyawan = bcsid.bep_vw_safety_karyawan_aktif)
 * untuk filter perusahaan di template Master Roster: memilih perusahaan →
 * template terisi SID, nama, jabatan, site.
 *
 * Diambil LANGSUNG dari view saat dibutuhkan (permintaan user), dengan dua
 * query kecil — bukan seluruh ±23 rb baris sekaligus, yang terlalu berat
 * dikirim lewat koneksi web → OLAP:
 *   perusahaan()        201 baris: nama perusahaan + jumlah karyawan
 *   untukPerusahaan()   karyawan satu perusahaan saja (PAMA ±3.700)
 * Keduanya ±7 dtk: view ini TIDAK boleh diberi WHERE langsung (predikat
 * terdorong ke m_karyawan 6 GB, >30 dtk), jadi dibaca lewat CTE MATERIALIZED
 * lalu disaring. Hasil disimpan 1 jam supaya halaman berikutnya instan.
 *
 * Bila OLAP tidak terjangkau, dipakai salinan lengkap terakhir yang diisi
 * dms:sync-roster-rfid (Tahap 8, segarkan()); alasan gagalnya di galatTerakhir().
 */
final class DmsRosterKaryawanAktif
{
    /** Salinan lengkap dari command terjadwal — cadangan. */
    private const KUNCI_LENGKAP = 'dms_roster:karyawan_aktif:v1';

    private const KUNCI_PT = 'dms_roster:karyawan_aktif:pt:v2';

    private const KUNCI_PER_PT = 'dms_roster:karyawan_aktif:per_pt:v1:';

    private const TTL_DETIK = 3600;

    /**
     * Gagal terhubung diingat sebentar: mencoba koneksi yang tidak terjangkau
     * makan ±19 dtk, jangan sampai tiap buka halaman menunggu selama itu.
     * "Coba ambil lagi" ($segarkan) selalu mencoba ulang.
     */
    private const KUNCI_GAGAL = 'dms_roster:karyawan_aktif:gagal:v2';

    private const TTL_GAGAL_DETIK = 60;

    private ?string $galat = null;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * Daftar perusahaan + jumlah karyawan aktifnya, urut nama, beserta site-nya
     * (untuk pilihan site yang mengikuti perusahaan).
     *
     * @return list<array{nama:string,jumlah:int,site:list<array{nama:string,jumlah:int}>}>|null
     *         null bila view tidak terjangkau dan belum ada salinan.
     */
    public function perusahaan(bool $segarkan = false): ?array
    {
        if (! $segarkan && is_array($ada = Cache::get(self::KUNCI_PT))) {
            return $ada;
        }
        if (! $segarkan && is_string($gagal = Cache::get(self::KUNCI_GAGAL))) {
            $this->galat = $gagal;
            $lengkap = Cache::get(self::KUNCI_LENGKAP);

            return is_array($lengkap) ? self::hitungPerusahaan($lengkap['karyawan']) : null;
        }

        try {
            $rows = $this->olap->select(
                "WITH k AS MATERIALIZED (
                     SELECT trim(kode_sid) AS sid, trim(nama_perusahaan) AS perusahaan, coalesce(trim(site_dedicated), '') AS site
                     FROM {$this->view()}
                 )
                 SELECT perusahaan, site, count(DISTINCT sid) AS jumlah
                 FROM k WHERE coalesce(perusahaan, '') <> '' AND coalesce(sid, '') <> ''
                 GROUP BY perusahaan, site",
                [],
                $this->timeout(),
            );
            $hasil = self::kelompokkan(array_map(
                static fn (object $r): array => ['perusahaan' => (string) $r->perusahaan, 'site' => (string) $r->site, 'jumlah' => (int) $r->jumlah],
                $rows,
            ));
            Cache::put(self::KUNCI_PT, $hasil, self::TTL_DETIK);
            Cache::forget(self::KUNCI_GAGAL);

            return $hasil;
        } catch (Throwable $e) {
            $this->catatGagal($e);
        }

        $lengkap = Cache::get(self::KUNCI_LENGKAP);

        return is_array($lengkap) ? self::hitungPerusahaan($lengkap['karyawan']) : null;
    }

    /**
     * Karyawan satu perusahaan (nama persis), opsional satu site, urut site
     * lalu nama. Site '' = semua site; site "-" = karyawan tanpa site.
     *
     * @return list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>|null
     */
    public function untukPerusahaan(string $perusahaan, string $site = ''): ?array
    {
        $semua = $this->karyawanPerusahaan($perusahaan);
        if ($semua === null || $site === '') {
            return $semua;
        }
        $site = $site === '-' ? '' : trim($site);

        return array_values(array_filter($semua, static fn (array $k): bool => $k['site'] === $site));
    }

    /**
     * @return list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>|null
     */
    private function karyawanPerusahaan(string $perusahaan): ?array
    {
        $kunci = self::KUNCI_PER_PT.md5($perusahaan);
        if (is_array($ada = Cache::get($kunci))) {
            return $ada;
        }

        try {
            $rows = $this->olap->select(
                "WITH k AS MATERIALIZED (
                     SELECT trim(kode_sid) AS sid, nama, trim(nama_perusahaan) AS perusahaan,
                            jabatan_struktural AS jabatan, site_dedicated AS site
                     FROM {$this->view()}
                 )
                 SELECT * FROM k WHERE perusahaan = ?",
                [trim($perusahaan)],
                $this->timeout(),
            );
            $hasil = self::urutkan(self::rapikan($rows));
            Cache::put($kunci, $hasil, self::TTL_DETIK);

            return $hasil;
        } catch (Throwable $e) {
            $this->catatGagal($e);
        }

        $lengkap = Cache::get(self::KUNCI_LENGKAP);
        if (! is_array($lengkap)) {
            return null;
        }

        return self::urutkan(array_values(array_filter($lengkap['karyawan'], static fn (array $k): bool => $k['perusahaan'] === trim($perusahaan))));
    }

    /**
     * Perusahaan aktif tiap SID — dari salinan lengkap (Tahap 8); bila belum
     * ada, langsung dari view untuk SID itu saja (hasil di-cache 1 jam).
     * Gagal = array kosong, pemanggil memperlakukannya sebagai "tidak tahu".
     *
     * @param  list<string>  $sids
     * @return array<string, string> SID (huruf besar) => nama perusahaan
     */
    public function perusahaanSid(array $sids): array
    {
        $sids = array_values(array_unique(array_filter(array_map(static fn ($s): string => mb_strtoupper(trim((string) $s)), $sids))));
        if ($sids === []) {
            return [];
        }

        $lengkap = Cache::get(self::KUNCI_LENGKAP);
        if (is_array($lengkap)) {
            $cari = array_flip($sids);
            $out = [];
            foreach ($lengkap['karyawan'] as $k) {
                if (isset($cari[$k['sid']]) && $k['perusahaan'] !== '') {
                    $out[$k['sid']] = $k['perusahaan'];
                }
            }

            return $out;
        }

        sort($sids);

        return Cache::remember(self::KUNCI_PER_PT.'sid:'.md5(implode(',', $sids)), self::TTL_DETIK, function () use ($sids): array {
            try {
                $rows = $this->olap->select(
                    "SELECT DISTINCT ON (upper(trim(kode_sid))) upper(trim(kode_sid)) AS sid, trim(nama_perusahaan) AS perusahaan
                     FROM {$this->view()}
                     WHERE upper(trim(kode_sid)) = ANY(?::text[])",
                    [DmsRosterRfidReader::arrayPg($sids)],
                    $this->timeout(),
                );
            } catch (Throwable $e) {
                $this->catatGagal($e);

                return [];
            }

            $out = [];
            foreach ($rows as $r) {
                if (trim((string) $r->perusahaan) !== '') {
                    $out[(string) $r->sid] = trim((string) $r->perusahaan);
                }
            }

            return $out;
        });
    }

    /**
     * Salinan lengkap untuk cadangan — dipanggil dms:sync-roster-rfid (Tahap 8),
     * yang terhubung ke OLAP dari CLI. Ikut menyegarkan daftar perusahaan.
     *
     * @return array{diambil:string, karyawan: list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>}|null
     */
    public function segarkan(): ?array
    {
        try {
            $rows = $this->olap->select(
                "SELECT trim(kode_sid) AS sid, nama, trim(nama_perusahaan) AS perusahaan,
                        jabatan_struktural AS jabatan, site_dedicated AS site
                 FROM {$this->view()}",
                [],
                $this->timeout(),
            );
        } catch (Throwable $e) {
            $this->catatGagal($e);

            return null;
        }

        $data = ['diambil' => now()->toDateTimeString(), 'karyawan' => self::rapikan($rows)];
        Cache::put(self::KUNCI_LENGKAP, $data, now()->addDays(2));
        Cache::put(self::KUNCI_PT, self::hitungPerusahaan($data['karyawan']), self::TTL_DETIK);

        return $data;
    }

    public function galatTerakhir(): ?string
    {
        return $this->galat;
    }

    /**
     * Baris view → daftar unik per SID (baris pertama menang). Murni.
     *
     * @param  list<object>  $rows
     * @return list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>
     */
    public static function rapikan(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $sid = mb_strtoupper(trim((string) ($r->sid ?? '')));
            if ($sid === '' || isset($out[$sid])) {
                continue;
            }
            $out[$sid] = [
                'sid' => $sid,
                'nama' => trim((string) ($r->nama ?? '')),
                'perusahaan' => trim((string) ($r->perusahaan ?? '')),
                'jabatan' => trim((string) ($r->jabatan ?? '')),
                'site' => trim((string) ($r->site ?? '')),
            ];
        }

        return array_values($out);
    }

    /**
     * @param  list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>  $karyawan
     * @return list<array{nama:string,jumlah:int,site:list<array{nama:string,jumlah:int}>}>
     */
    private static function hitungPerusahaan(array $karyawan): array
    {
        $baris = [];
        foreach ($karyawan as $k) {
            if ($k['perusahaan'] === '') {
                continue;
            }
            $kunci = $k['perusahaan']."\0".$k['site'];
            $baris[$kunci] ??= ['perusahaan' => $k['perusahaan'], 'site' => $k['site'], 'jumlah' => 0];
            $baris[$kunci]['jumlah']++;
        }

        return self::kelompokkan(array_values($baris));
    }

    /**
     * Baris (perusahaan, site, jumlah) → perusahaan dengan daftar site-nya,
     * keduanya urut nama. Site kosong ditulis "-". Murni.
     *
     * @param  list<array{perusahaan:string,site:string,jumlah:int}>  $baris
     * @return list<array{nama:string,jumlah:int,site:list<array{nama:string,jumlah:int}>}>
     */
    public static function kelompokkan(array $baris): array
    {
        $pt = [];
        foreach ($baris as $b) {
            $pt[$b['perusahaan']] ??= ['nama' => $b['perusahaan'], 'jumlah' => 0, 'site' => []];
            $pt[$b['perusahaan']]['jumlah'] += $b['jumlah'];
            $pt[$b['perusahaan']]['site'][] = ['nama' => $b['site'] === '' ? '-' : $b['site'], 'jumlah' => $b['jumlah']];
        }
        uksort($pt, 'strcasecmp');
        foreach ($pt as &$p) {
            usort($p['site'], static fn (array $a, array $b): int => strcasecmp($a['nama'], $b['nama']));
        }
        unset($p);

        return array_values($pt);
    }

    /**
     * @param  list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>  $karyawan
     * @return list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>
     */
    private static function urutkan(array $karyawan): array
    {
        usort($karyawan, static fn (array $a, array $b): int => [$a['site'], $a['nama']] <=> [$b['site'], $b['nama']]);

        return $karyawan;
    }

    private function catatGagal(Throwable $e): void
    {
        $this->galat = $e->getMessage();
        Cache::put(self::KUNCI_GAGAL, $this->galat, self::TTL_GAGAL_DETIK);
        Log::warning('DmsRoster karyawan aktif gagal: '.$e->getMessage());
    }

    private function view(): string
    {
        $view = (string) config('dms_roster.populasi.view_karyawan', 'bcsid.bep_vw_safety_karyawan_aktif');
        if (preg_match('/^[a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*$/i', $view) !== 1) {
            throw new \InvalidArgumentException('dms_roster.populasi.view_karyawan tidak valid.');
        }

        return $view;
    }

    private function timeout(): int
    {
        return (int) config('dms_roster.reg_live.timeout_cari_ms', 20000);
    }
}
