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
 * REG-1/2/3 per HARI INI, dihitung mundur langsung dari scan RFID
 * (bcsid.mv_checkinout_rfid) — TIDAK memakai dms_roster_pola, yang hanya
 * untuk analisis historis dan sengaja berhenti di kemarin.
 *
 * Satu query menarik hari ber-CHECK IN (status PASSED) per SID selama
 * jendela N hari terakhir (default 180, ±5 dtk untuk ±24 rb SID). Dari situ
 * dirakit deret kerja/off per karyawan lalu dinilai dengan aturan yang sama
 * dengan rule engine (off > 5 hari = cuti, ambang dari config).
 *
 * Hari ini diperlakukan khusus: scan hari ini masih berjalan (dan materialized
 * view-nya di-refresh berkala), jadi karyawan yang BELUM scan hari ini dinilai
 * sampai kemarin — tidak dianggap off, supaya run kerjanya tidak terputus palsu.
 *
 * Master karyawan (nama, jabatan, kategori, populasi wajib_cek) dibaca dari
 * dms_roster_karyawan.
 */
final class DmsRosterRegLiveService
{
    public function __construct(
        private readonly DmsRosterRuleEngine $engine,
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * @return array{per:string,diambil:string,jendela:int,reg:array<string,list<array<string,mixed>>>}|null
     *         null bila RFID tidak terjangkau / query gagal.
     */
    public function ambil(): ?array
    {
        $hariIni = CarbonImmutable::now()->startOfDay();
        $key = 'dms_roster:reg_live:v1:'.$hariIni->toDateString();

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
     * @param  list<int>  $hariScan  Offset hari ber-CHECK IN; 0 = hari pertama jendela.
     * @param  int  $panjang  Panjang jendela; offset $panjang - 1 = hari ini.
     * @return array<string, array{hari:int,mulai:int,selesai:int,terpotong:bool}|null>
     */
    public function nilai(array $hariScan, int $panjang, string $kategori): array
    {
        $deret = str_repeat('o', $panjang);
        foreach ($hariScan as $i) {
            if ($i >= 0 && $i < $panjang) {
                $deret[$i] = 'P';
            }
        }

        // Belum scan hari ini = belum diketahui, bukan off.
        if ($deret[$panjang - 1] === 'o') {
            $deret = substr($deret, 0, -1);
        }

        $kini = $this->engine->pelanggaranTerkini(
            $this->engine->evaluasi($deret, $kategori, (int) config('dms_roster.thr_default', 7), 'block'),
        );

        $hasil = [];
        foreach ($kini as $kode => $run) {
            // Run yang menyentuh awal jendela panjang aslinya tidak diketahui:
            // REG-1/2 tetap pelanggaran (minimal sepanjang jendela), sedangkan
            // cuti terpotong tidak bisa dinilai "pendek", jadi REG-3 dilewati.
            $terpotong = $run !== null && $run['mulai'] === 0;
            $hasil[$kode] = $run === null || ($kode === 'reg3' && $terpotong)
                ? null
                : $run + ['terpotong' => $terpotong];
        }

        return $hasil;
    }

    /**
     * @return array{per:string,diambil:string,jendela:int,reg:array<string,list<array<string,mixed>>>}|null
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
            Log::warning('DmsRoster REG live gagal: '.$e->getMessage());

            return null;
        }

        $reg = ['reg1' => [], 'reg2' => [], 'reg3' => []];
        $tanggal = static fn (int $i): string => $awal->addDays($i)->translatedFormat('j M Y');

        foreach ($karyawan as $k) {
            $hariScan = $scan[(string) $k->kode_sid] ?? [];
            if ($hariScan === []) {
                continue;
            }

            foreach ($this->nilai($hariScan, $panjang, (string) $k->kategori) as $kode => $run) {
                if ($run === null) {
                    continue;
                }

                $reg[$kode][] = [
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
                    'scan_terakhir' => $tanggal(max($hariScan)),
                ];
            }
        }

        $panjangDulu = static fn (array $a, array $b): int => $b['hari'] <=> $a['hari'];
        usort($reg['reg1'], $panjangDulu);
        usort($reg['reg2'], $panjangDulu);
        usort($reg['reg3'], static fn (array $a, array $b): int => $a['hari'] <=> $b['hari']);

        return [
            'per' => $hariIni->translatedFormat('j M Y'),
            'diambil' => CarbonImmutable::now()->translatedFormat('j M Y H:i'),
            'jendela' => $panjang,
            'reg' => $reg,
        ];
    }

    /**
     * Hari ber-CHECK IN per SID sebagai offset dari $awal. Agregasi dikerjakan
     * di Postgres supaya yang dikirim cuma ±24 rb baris, bukan jutaan scan.
     *
     * @return array<string, list<int>>
     */
    private function tarikScan(CarbonImmutable $awal, CarbonImmutable $hariIni): array
    {
        $rows = $this->olap->select(
            "SELECT s.kode_sid,
                    string_agg(DISTINCT (s.tanggal_checkinout::date - ?::date)::text, ',') AS hari
             FROM bcsid.mv_checkinout_rfid s
             WHERE upper(trim(coalesce(s.status_lolos, ''))) = ?
               AND s.jenis_checkinout = 'CHECK IN'
               AND s.tanggal_checkinout >= ?
               AND s.tanggal_checkinout < ?
               AND s.kode_sid IS NOT NULL
               AND trim(s.kode_sid) <> ''
             GROUP BY s.kode_sid",
            [
                $awal->toDateString(),
                strtoupper((string) config('dms_roster.populasi.status_lolos_scan', 'PASSED')),
                $awal->toDateTimeString(),
                $hariIni->addDay()->toDateTimeString(),
            ],
            (int) config('dms_roster.reg_live.timeout_ms', 20000),
        );

        $out = [];
        foreach ($rows as $row) {
            $out[trim((string) $row->kode_sid)] = array_map('intval', explode(',', (string) $row->hari));
        }

        return $out;
    }
}
