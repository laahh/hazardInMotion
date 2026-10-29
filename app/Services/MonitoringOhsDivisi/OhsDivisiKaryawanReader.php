<?php

declare(strict_types=1);

namespace App\Services\MonitoringOhsDivisi;

use App\Services\Dms\Roster\DmsRosterRfidReader;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Karyawan aktif dari departemen OHS Divisi (config monitoring_ohs_divisi.
 * departemen), dibaca dari view karyawan aktif kolom `departement`.
 *
 * View itu tidak boleh diberi WHERE langsung (lihat DmsRosterKaryawanAktif),
 * jadi dibaca lewat CTE MATERIALIZED lalu disaring. Hasil disimpan beberapa
 * jam; bila OLAP gagal dipakai hasil terakhir yang berhasil (7 hari), dan
 * kegagalan diingat 60 dtk supaya halaman tidak menunggu timeout tiap kali.
 */
final class OhsDivisiKaryawanReader
{
    private const KUNCI = 'monitoring_ohs_divisi:karyawan:v2:';

    private const KUNCI_TERAKHIR = 'monitoring_ohs_divisi:karyawan:terakhir:v2:';

    private const KUNCI_GAGAL = 'monitoring_ohs_divisi:karyawan:gagal:v2';

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * @return array<string, string>|null SID (huruf besar) => departemen; null bila belum pernah terbaca
     */
    public function sidDepartemen(): ?array
    {
        $karyawan = $this->karyawan();

        return $karyawan === null ? null : array_map(static fn (array $k): string => $k['departemen'], $karyawan);
    }

    /**
     * @return array<string, array{sid:string,nama:string,departemen:string,perusahaan:string,jabatan:string}>|null
     *         kunci = SID huruf besar; null bila belum pernah terbaca
     */
    public function karyawan(): ?array
    {
        $departemen = self::departemen();
        $sufiks = md5(implode('|', $departemen));
        if (is_array($ada = Cache::get(self::KUNCI.$sufiks))) {
            return $ada;
        }
        if (Cache::has(self::KUNCI_GAGAL)) {
            return Cache::get(self::KUNCI_TERAKHIR.$sufiks);
        }

        try {
            $rows = $this->olap->select(
                "WITH k AS MATERIALIZED (
                     SELECT upper(trim(kode_sid)) AS sid, trim(departement) AS dep, trim(nama) AS nama,
                            trim(nama_perusahaan) AS pt, trim(jabatan_struktural) AS jabatan
                     FROM {$this->view()}
                 )
                 SELECT DISTINCT ON (sid) sid, dep, nama, pt, jabatan FROM k
                 WHERE coalesce(sid, '') <> '' AND upper(coalesce(dep, '')) = ANY(?::text[])",
                [DmsRosterRfidReader::arrayPg($departemen)],
                (int) config('monitoring_ohs_divisi.timeout_ms', 20000),
            );
        } catch (Throwable $e) {
            Log::warning('Monitoring OHS Divisi: karyawan departemen gagal dibaca: '.$e->getMessage());
            Cache::put(self::KUNCI_GAGAL, true, 60);

            return Cache::get(self::KUNCI_TERAKHIR.$sufiks);
        }

        $hasil = [];
        foreach ($rows as $r) {
            $hasil[(string) $r->sid] = [
                'sid' => (string) $r->sid,
                'nama' => (string) ($r->nama ?? ''),
                'departemen' => (string) $r->dep,
                'perusahaan' => (string) ($r->pt ?? ''),
                'jabatan' => (string) ($r->jabatan ?? ''),
            ];
        }
        Cache::put(self::KUNCI.$sufiks, $hasil, (int) config('monitoring_ohs_divisi.cache_detik', 21600));
        Cache::put(self::KUNCI_TERAKHIR.$sufiks, $hasil, now()->addDays(7));

        return $hasil;
    }

    /**
     * Nama departemen dari config, huruf besar & tanpa spasi di ujung.
     *
     * @return list<string>
     */
    public static function departemen(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($d): string => mb_strtoupper(trim((string) $d)),
            (array) config('monitoring_ohs_divisi.departemen', []),
        ))));
    }

    private function view(): string
    {
        $view = (string) config('dms_roster.populasi.view_karyawan', 'bcsid.bep_vw_safety_karyawan_aktif');
        if (preg_match('/^[a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*$/i', $view) !== 1) {
            throw new \InvalidArgumentException('dms_roster.populasi.view_karyawan tidak valid.');
        }

        return $view;
    }
}
