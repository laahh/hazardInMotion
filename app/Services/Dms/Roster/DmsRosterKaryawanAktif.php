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
 *
 * Jabatan di dms_roster.master.jabatan_dikecualikan (VISITOR) tidak dianggap
 * karyawan aktif di mana pun kelas ini dibaca (jumlah per perusahaan/site,
 * daftar per perusahaan, salinan lengkap).
 */
final class DmsRosterKaryawanAktif
{
    /** Salinan lengkap dari command terjadwal — cadangan. */
    private const KUNCI_LENGKAP = 'dms_roster:karyawan_aktif:v1';

    // v3 / v2: sejak VISITOR dikecualikan — cache lama masih memuatnya.
    private const KUNCI_PT = 'dms_roster:karyawan_aktif:pt:v3';

    private const KUNCI_PER_PT = 'dms_roster:karyawan_aktif:per_pt:v2:';

    /** Daftar PT terakhir yang berhasil dihitung, tanpa batas umur (cadangan halaman). */
    private const KUNCI_PT_CADANGAN = 'dms_roster:karyawan_aktif:pt_cadangan:v1';

    private const TTL_DETIK = 3600;

    /**
     * Gagal terhubung diingat sebentar: mencoba koneksi yang tidak terjangkau
     * makan ±19 dtk, jangan sampai tiap buka halaman menunggu selama itu.
     * "Coba ambil lagi" ($segarkan) selalu mencoba ulang.
     */
    private const KUNCI_GAGAL = 'dms_roster:karyawan_aktif:gagal:v2';

    private const TTL_GAGAL_DETIK = 300;

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
        // View karyawan aktif bisa > 30 dtk; beberapa query live berturut-turut
        // di satu request = 504. Halaman memakai salinan lengkap (Tahap 8 sync)
        // bila ada; query view hanya bila salinan belum ada atau minta segarkan.
        $lengkap = $segarkan ? null : Cache::get(self::KUNCI_LENGKAP);
        if (is_array($lengkap)) {
            $hasil = self::hitungPerusahaan($lengkap['karyawan']);
            Cache::put(self::KUNCI_PT, $hasil, self::TTL_DETIK);
            Cache::forever(self::KUNCI_PT_CADANGAN, $hasil);

            return $hasil;
        }
        if (! $segarkan) {
            // Tanpa salinan: pakai hasil terakhir yang pernah dihitung (tanpa
            // batas umur), selebihnya null. Query view hanya lewat segarkan
            // (tombol/parameter segarkan_pt) atau Tahap 8 dms:sync-roster-rfid.
            $this->galat = Cache::get(self::KUNCI_GAGAL) ?: 'Salinan karyawan aktif belum ada — jalankan php artisan dms:sync-roster-rfid.';
            $cadangan = Cache::get(self::KUNCI_PT_CADANGAN);

            return is_array($cadangan) ? $cadangan : null;
        }

        try {
            $rows = $this->olap->select(
                "WITH k AS MATERIALIZED (
                     SELECT trim(kode_sid) AS sid, trim(nama_perusahaan) AS perusahaan, coalesce(trim(site_dedicated), '') AS site,
                            upper(trim(coalesce(jabatan_struktural, ''))) AS jabatan
                     FROM {$this->view()}
                 )
                 SELECT perusahaan, site, count(DISTINCT sid) AS jumlah
                 FROM k WHERE coalesce(perusahaan, '') <> '' AND coalesce(sid, '') <> ''
                   AND jabatan <> ALL(?::text[])
                 GROUP BY perusahaan, site",
                [DmsRosterRfidReader::arrayPg(self::jabatanDikecualikan())],
                $this->timeout(),
            );
            $hasil = self::kelompokkan(array_map(
                static fn (object $r): array => ['perusahaan' => (string) $r->perusahaan, 'site' => (string) $r->site, 'jumlah' => (int) $r->jumlah],
                $rows,
            ));
            Cache::put(self::KUNCI_PT, $hasil, self::TTL_DETIK);
            Cache::forever(self::KUNCI_PT_CADANGAN, $hasil);
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
        if (is_array($lengkap = Cache::get(self::KUNCI_LENGKAP))) {
            return self::dariLengkap($lengkap, $perusahaan);
        }
        if (is_string($gagal = Cache::get(self::KUNCI_GAGAL))) {
            $this->galat = $gagal;

            return null;
        }

        try {
            $rows = $this->olap->select(
                "WITH k AS MATERIALIZED (
                     SELECT trim(kode_sid) AS sid, nama, trim(nama_perusahaan) AS perusahaan,
                            jabatan_struktural AS jabatan, site_dedicated AS site
                     FROM {$this->view()}
                 )
                 SELECT * FROM k WHERE perusahaan = ? AND upper(trim(coalesce(jabatan, ''))) <> ALL(?::text[])",
                [trim($perusahaan), DmsRosterRfidReader::arrayPg(self::jabatanDikecualikan())],
                $this->timeout(),
            );
            $hasil = self::urutkan(self::rapikan($rows));
            Cache::put($kunci, $hasil, self::TTL_DETIK);

            return $hasil;
        } catch (Throwable $e) {
            $this->catatGagal($e);
        }

        $lengkap = Cache::get(self::KUNCI_LENGKAP);

        return is_array($lengkap) ? self::dariLengkap($lengkap, $perusahaan) : null;
    }

    /**
     * @param  array{karyawan: list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>}  $lengkap
     * @return list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>
     */
    private static function dariLengkap(array $lengkap, string $perusahaan): array
    {
        return self::urutkan(array_values(array_filter(
            $lengkap['karyawan'],
            static fn (array $k): bool => $k['perusahaan'] === trim($perusahaan) && ! self::dikecualikan($k['jabatan']),
        )));
    }

    /**
     * SID karyawan aktif per perusahaan per site, dari salinan lengkap
     * (Tahap 8) saja — tanpa query ke view. Site kosong ditulis "-", sama
     * dengan perusahaan(). Null bila salinan belum ada.
     *
     * @return array<string, array<string, list<string>>>|null perusahaan => site => SID (huruf besar)
     */
    public function sidPerPerusahaan(): ?array
    {
        $lengkap = Cache::get(self::KUNCI_LENGKAP);
        if (! is_array($lengkap)) {
            return null;
        }

        $out = [];
        foreach ($lengkap['karyawan'] as $k) {
            if ($k['perusahaan'] !== '' && ! self::dikecualikan($k['jabatan'])) {
                $out[$k['perusahaan']][$k['site'] === '' ? '-' : $k['site']][] = $k['sid'];
            }
        }

        return $out;
    }

    /**
     * Perusahaan aktif tiap SID — dari salinan lengkap (Tahap 8) saja. Tanpa
     * salinan = array kosong (tidak query view: > 30 dtk → 504); pemanggil
     * memperlakukannya sebagai "tidak tahu".
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

        // Tanpa salinan: tidak query view (WHERE langsung di view = scan penuh,
        // > 30 dtk). Pemanggil (suara SID) cukup tanpa data ini.
        return [];
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
                (int) config('dms_roster.reg_live.timeout_lengkap_ms', 180000),
            );
        } catch (Throwable $e) {
            $this->catatGagal($e);

            return null;
        }

        $data = ['diambil' => now()->toDateTimeString(), 'karyawan' => self::rapikan($rows)];
        Cache::put(self::KUNCI_LENGKAP, $data, now()->addDays(2));
        Cache::put(self::KUNCI_PT, $pt = self::hitungPerusahaan($data['karyawan']), self::TTL_DETIK);
        Cache::forever(self::KUNCI_PT_CADANGAN, $pt);

        return $data;
    }

    public function galatTerakhir(): ?string
    {
        return $this->galat;
    }

    /**
     * Jabatan yang tidak dihitung sebagai karyawan aktif (huruf besar).
     *
     * @return list<string>
     */
    public static function jabatanDikecualikan(): array
    {
        return array_values(array_unique(array_map(
            static fn ($j): string => mb_strtoupper(trim((string) $j)),
            (array) config('dms_roster.master.jabatan_dikecualikan', ['VISITOR']),
        )));
    }

    public static function dikecualikan(string $jabatan): bool
    {
        return in_array(mb_strtoupper(trim($jabatan)), self::jabatanDikecualikan(), true);
    }

    /**
     * Baris view → daftar unik per SID (baris pertama menang), tanpa jabatan
     * yang dikecualikan (VISITOR). Murni.
     *
     * @param  list<object>  $rows
     * @return list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>
     */
    public static function rapikan(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $sid = mb_strtoupper(trim((string) ($r->sid ?? '')));
            if ($sid === '' || isset($out[$sid]) || self::dikecualikan((string) ($r->jabatan ?? ''))) {
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
            if ($k['perusahaan'] === '' || self::dikecualikan($k['jabatan'])) {
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
