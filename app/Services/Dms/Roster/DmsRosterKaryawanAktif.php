<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daftar seluruh karyawan aktif (populasi.view_karyawan =
 * bcsid.bep_vw_safety_karyawan_aktif) untuk filter perusahaan di template
 * Master Roster: memilih perusahaan → template terisi SID, nama, jabatan, site.
 *
 * View ini TIDAK boleh diberi WHERE (predikat terdorong ke m_karyawan 6 GB,
 * >30 dtk), jadi dibaca utuh (±7 dtk, ±23 rb baris) lalu disaring di PHP,
 * dan memuat kode_sid ganda (baris pertama menang). Karena request web di
 * server sering gagal terhubung ke OLAP, hasilnya disimpan di cache: diisi
 * dms:sync-roster-rfid (Tahap 8) dan dibaca halaman; cache kosong baru
 * mencoba langsung.
 */
final class DmsRosterKaryawanAktif
{
    private const KUNCI = 'dms_roster:karyawan_aktif:v1';

    private const KUNCI_GAGAL = 'dms_roster:karyawan_aktif:gagal:v1';

    private ?string $galat = null;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * @return array{diambil:string, karyawan: list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>}|null
     */
    public function ambil(): ?array
    {
        $data = Cache::get(self::KUNCI);
        if (is_array($data)) {
            return $data;
        }
        if (is_string($gagal = Cache::get(self::KUNCI_GAGAL))) {
            $this->galat = $gagal;

            return null;
        }

        return $this->segarkan();
    }

    /**
     * Baca ulang view dari OLAP dan simpan (cache 2 hari: daftar karyawan
     * berubah lambat, dan cadangan tetap ada bila satu putaran sync gagal).
     *
     * @return array{diambil:string, karyawan: list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>}|null
     */
    public function segarkan(): ?array
    {
        $view = (string) config('dms_roster.populasi.view_karyawan', 'bcsid.bep_vw_safety_karyawan_aktif');
        if (preg_match('/^[a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*$/i', $view) !== 1) {
            $this->galat = 'dms_roster.populasi.view_karyawan tidak valid.';

            return null;
        }

        try {
            $rows = $this->olap->select(
                "SELECT trim(kode_sid) AS sid, nama, nama_perusahaan AS perusahaan,
                        jabatan_struktural AS jabatan, site_dedicated AS site
                 FROM {$view}",
                [],
                (int) config('dms_roster.reg_live.timeout_cari_ms', 20000),
            );
        } catch (Throwable $e) {
            $this->galat = $e->getMessage();
            Log::warning('DmsRoster karyawan aktif gagal: '.$e->getMessage());
            Cache::put(self::KUNCI_GAGAL, $this->galat, 300);

            return null;
        }

        $data = ['diambil' => now()->toDateTimeString(), 'karyawan' => self::rapikan($rows)];
        Cache::put(self::KUNCI, $data, now()->addDays(2));
        Cache::forget(self::KUNCI_GAGAL);

        return $data;
    }

    public function galatTerakhir(): ?string
    {
        return $this->galat;
    }

    /**
     * Daftar perusahaan + jumlah karyawannya, urut nama.
     *
     * @return list<array{nama:string,jumlah:int}>|null  null bila daftar karyawan belum tersedia.
     */
    public function perusahaan(): ?array
    {
        $data = $this->ambil();
        if ($data === null) {
            return null;
        }

        $jumlah = [];
        foreach ($data['karyawan'] as $k) {
            if ($k['perusahaan'] !== '') {
                $jumlah[$k['perusahaan']] = ($jumlah[$k['perusahaan']] ?? 0) + 1;
            }
        }
        uksort($jumlah, 'strcasecmp');

        return array_map(static fn (string $n, int $j): array => ['nama' => $n, 'jumlah' => $j], array_keys($jumlah), $jumlah);
    }

    /**
     * Karyawan satu perusahaan (nama persis), urut site lalu nama.
     *
     * @return list<array{sid:string,nama:string,perusahaan:string,jabatan:string,site:string}>|null
     */
    public function untukPerusahaan(string $perusahaan): ?array
    {
        $data = $this->ambil();
        if ($data === null) {
            return null;
        }

        $hasil = array_values(array_filter($data['karyawan'], static fn (array $k): bool => $k['perusahaan'] === $perusahaan));
        usort($hasil, static fn (array $a, array $b): int => [$a['site'], $a['nama']] <=> [$b['site'], $b['nama']]);

        return $hasil;
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
}
