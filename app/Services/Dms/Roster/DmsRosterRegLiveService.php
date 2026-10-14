<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pelanggaran regulasi (REG-1/2/3) dan ketidaksesuaian mapping roster
 * (MAP-1/2/3) per HARI INI, dihitung mundur langsung dari scan RFID
 * (bcsid.mv_checkinout_rfid) — TIDAK memakai dms_roster_pola, yang hanya
 * untuk analisis historis dan sengaja berhenti di kemarin.
 *
 * Satu query menarik check-in pertama per SID per hari selama jendela N hari
 * terakhir (default 180, ±5 dtk untuk ±24 rb SID) sebagai kode P/M. Dari situ
 * dirakit deret P/M/o per karyawan lalu dinilai oleh DmsRosterRuleEngine
 * (off > 5 hari = cuti, ambang dan parameter PT dari config).
 *
 * Hari ini diperlakukan khusus: scan hari ini masih berjalan (dan materialized
 * view-nya di-refresh berkala), jadi karyawan yang BELUM scan hari ini dinilai
 * sampai kemarin — tidak dianggap off, supaya run kerjanya tidak terputus palsu.
 *
 * Master karyawan (nama, jabatan, kategori, perusahaan, populasi wajib_cek)
 * dibaca dari dms_roster_karyawan.
 */
final class DmsRosterRegLiveService
{
    public const KODE = ['reg1', 'reg2', 'reg3', 'map1', 'map2', 'map3'];

    public function __construct(
        private readonly DmsRosterRuleEngine $engine,
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * @return array{per:string,diambil:string,jendela:int,daftar:array<string,list<array<string,mixed>>>}|null
     *         null bila RFID tidak terjangkau / query gagal.
     */
    public function ambil(): ?array
    {
        $hariIni = CarbonImmutable::now()->startOfDay();
        $key = 'dms_roster:reg_live:v2:'.$hariIni->toDateString();

        $tersimpan = Cache::get($key);
        if (is_array($tersimpan)) {
            return $tersimpan;
        }

        $hasil = $this->hitung($hariIni);
        // Kegagalan tidak di-cache supaya percobaan berikutnya langsung ke RFID lagi.
        if ($hasil !== null) {
            Cache::put($key, $hasil, (int) config('dms_roster.reg_live.cache_ttl', 900));
        }

        return $hasil;
    }

    /**
     * Penilaian satu karyawan — murni, tanpa DB.
     *
     * @param  array<int, string>  $hariScan  offset hari => 'P'|'M'; 0 = hari pertama jendela.
     * @param  int  $panjang  Panjang jendela; offset $panjang - 1 = hari ini.
     * @return array<string, array<string, mixed>|null>  Kunci sesuai self::KODE.
     */
    public function nilai(array $hariScan, int $panjang, string $kategori, string $perusahaan = ''): array
    {
        $deret = str_repeat('o', $panjang);
        foreach ($hariScan as $i => $kode) {
            if ($i >= 0 && $i < $panjang) {
                $deret[$i] = $kode === 'M' ? 'M' : 'P';
            }
        }

        // Belum scan hari ini = belum diketahui, bukan off.
        if ($deret[$panjang - 1] === 'o') {
            $deret = substr($deret, 0, -1);
        }

        $param = $this->engine->paramPerusahaan($perusahaan);
        $eval = $this->engine->evaluasi($deret, $kategori, $param['thr'], $param['map']);
        $hasil = [];

        foreach ($this->engine->pelanggaranTerkini($eval) as $kode => $run) {
            // Run yang menyentuh awal jendela panjang aslinya tidak diketahui:
            // REG-1/2 tetap pelanggaran (minimal sepanjang jendela), sedangkan
            // cuti terpotong tidak bisa dinilai "pendek", jadi REG-3 dilewati.
            $terpotong = $run !== null && $run['mulai'] === 0;
            $hasil[$kode] = $run === null || ($kode === 'reg3' && $terpotong)
                ? null
                : $run + ['terpotong' => $terpotong];
        }

        foreach ($this->engine->mappingTerkini($eval, $param['thr'], $param['map']) as $kode => $run) {
            $hasil[$kode] = $run === null ? null : $run + ['terpotong' => false, 'blok' => $param['thr'] - 1];
        }

        return $hasil;
    }

    /**
     * @return array{per:string,diambil:string,jendela:int,daftar:array<string,list<array<string,mixed>>>}|null
     */
    private function hitung(CarbonImmutable $hariIni): ?array
    {
        $panjang = max(30, (int) config('dms_roster.reg_live.jendela_hari', 180));
        $awal = $hariIni->subDays($panjang - 1);

        try {
            $scan = $this->tarikScan($awal, $hariIni);
            $karyawan = DB::table('dms_roster_karyawan')
                ->where('wajib_cek', true)
                ->select('kode_sid', 'nama', 'jabatan_struktural as jabatan', 'kategori', 'perusahaan', 'kode_pt', 'site')
                ->get();
        } catch (Throwable $e) {
            Log::warning('DmsRoster REG/MAP live gagal: '.$e->getMessage());

            return null;
        }

        $daftar = array_fill_keys(self::KODE, []);
        $tanggal = static fn (int $i): string => $awal->addDays($i)->translatedFormat('j M Y');

        foreach ($karyawan as $k) {
            $hariScan = $scan[(string) $k->kode_sid] ?? [];
            if ($hariScan === []) {
                continue;
            }

            foreach ($this->nilai($hariScan, $panjang, (string) $k->kategori, (string) $k->perusahaan) as $kode => $run) {
                if ($run === null) {
                    continue;
                }

                $daftar[$kode][] = [
                    'sid' => (string) $k->kode_sid,
                    'nama' => (string) $k->nama,
                    'jabatan' => (string) ($k->jabatan ?: '—'),
                    'kategori' => (string) $k->kategori,
                    'perusahaan' => (string) $k->perusahaan,
                    'pt' => (string) $k->kode_pt,
                    'site' => (string) ($k->site ?: '—'),
                    'hari' => $run['hari'],
                    'terpotong' => $run['terpotong'],
                    'mulai' => $tanggal($run['mulai']),
                    'selesai' => $tanggal($run['selesai']),
                    'keterangan' => $this->keterangan($kode, $run, $tanggal),
                    'scan_terakhir' => $tanggal(max(array_keys($hariScan))),
                ];
            }
        }

        // REG-3 dari cuti terpendek; sisanya dari run/blok terpanjang.
        foreach ($daftar as $kode => &$baris) {
            usort($baris, $kode === 'reg3'
                ? static fn (array $a, array $b): int => $a['hari'] <=> $b['hari']
                : static fn (array $a, array $b): int => $b['hari'] <=> $a['hari']);
        }
        unset($baris);

        return [
            'per' => $hariIni->translatedFormat('j M Y'),
            'diambil' => CarbonImmutable::now()->translatedFormat('j M Y H:i'),
            'jendela' => $panjang,
            'daftar' => $daftar,
        ];
    }

    /**
     * @param  array<string, mixed>  $run
     * @param  callable(int): string  $tanggal
     */
    private function keterangan(string $kode, array $run, callable $tanggal): string
    {
        return match ($kode) {
            'map1' => 'Blok roster PT maks '.$run['blok'].' hari; terlewati sejak '.$tanggal($run['pada']),
            'map2' => 'Ganti shift Pagi↔Malam tanpa off pada '.$tanggal($run['pada']),
            'map3' => 'Shift Pagi setelah Malam pada '.$tanggal($run['pada']),
            default => '',
        };
    }

    /**
     * Kode shift per hari per SID ("offset+P/M", dipisah koma). Agregasi
     * dikerjakan di Postgres supaya yang dikirim cuma ±24 rb baris, bukan
     * jutaan scan. Batas Pagi/Malam sama dengan sinkronisasi pola (menit 720).
     *
     * @return array<string, array<int, string>>
     */
    private function tarikScan(CarbonImmutable $awal, CarbonImmutable $hariIni): array
    {
        $rows = $this->olap->select(
            "SELECT d.kode_sid,
                    string_agg(d.o::text || CASE WHEN d.m < ? THEN 'P' ELSE 'M' END, ',') AS hari
             FROM (
                 SELECT trim(s.kode_sid) AS kode_sid,
                        s.tanggal_checkinout::date - ?::date AS o,
                        MIN(extract(hour FROM s.tanggal_checkinout) * 60
                            + extract(minute FROM s.tanggal_checkinout)) AS m
                 FROM bcsid.mv_checkinout_rfid s
                 WHERE upper(trim(coalesce(s.status_lolos, ''))) = ?
                   AND s.jenis_checkinout = 'CHECK IN'
                   AND s.tanggal_checkinout >= ?
                   AND s.tanggal_checkinout < ?
                   AND s.kode_sid IS NOT NULL
                   AND trim(s.kode_sid) <> ''
                 GROUP BY 1, 2
             ) d
             GROUP BY d.kode_sid",
            [
                (int) config('dms_roster.populasi.batas_menit_pagi', 720),
                $awal->toDateString(),
                strtoupper((string) config('dms_roster.populasi.status_lolos_scan', 'PASSED')),
                $awal->toDateTimeString(),
                $hariIni->addDay()->toDateTimeString(),
            ],
            (int) config('dms_roster.reg_live.timeout_ms', 20000),
        );

        $out = [];
        foreach ($rows as $row) {
            $hari = [];
            foreach (explode(',', (string) $row->hari) as $sel) {
                $hari[(int) substr($sel, 0, -1)] = substr($sel, -1);
            }
            $out[(string) $row->kode_sid] = $hari;
        }

        return $out;
    }
}
