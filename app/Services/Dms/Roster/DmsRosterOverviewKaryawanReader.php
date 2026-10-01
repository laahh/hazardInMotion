<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pembaca data karyawan untuk halaman Ringkasan Roster: baris tabel
 * "Scorecard per Karyawan & Timeline" dan daftar "Karyawan Perlu Tindakan".
 *
 * Scorecard membaca dms_roster_pola x dms_roster_karyawan (hasil sinkronisasi
 * RFID) langsung, disaring ke base wajib_cek, lalu tiap pola dijalankan lewat
 * DmsRosterRuleEngine. Sengaja TIDAK lewat DmsRosterComplianceService: tabel
 * ini cuma butuh satu halaman (±10 baris), sedangkan service itu mengevaluasi
 * seluruh populasi untuk menyusun agregat yang tidak dipakai di sini.
 *
 * Sebaliknya daftar perlu tindakan MEMANG butuh peringkat seluruh populasi,
 * jadi bagian itu meminjam DmsRosterComplianceService::daftarWajibCuti() yang
 * hasil evaluasinya di-cache bersama kartu agregat halaman ini.
 *
 * Kedua metode mengembalikan null bila tabel kosong atau database tidak
 * terjangkau, supaya halaman bisa jatuh kembali ke data contoh tanpa gagal
 * render.
 */
final class DmsRosterOverviewKaryawanReader
{
    public function __construct(
        private readonly DmsRosterRuleEngine $engine,
        private readonly DmsRosterComplianceService $compliance,
    ) {}

    /**
     * Satu halaman baris scorecard.
     *
     * @return array<string, mixed>|null
     */
    public function ambil(int $halaman = 1, int $perPage = 10, ?int $tahun = null): ?array
    {
        $tahun ??= CarbonImmutable::now()->year;
        $perPage = max(1, $perPage);

        try {
            $meta = DB::table('dms_roster_pola as p')
                ->join('dms_roster_karyawan as k', 'k.id', '=', 'p.karyawan_id')
                ->where('p.tahun', $tahun)
                ->where('k.wajib_cek', true)
                ->selectRaw('COUNT(*) AS jumlah, MAX(p.hari_terakhir) AS hari_terakhir')
                ->first();

            if ($meta === null || (int) $meta->jumlah === 0) {
                return null;
            }

            $total = (int) $meta->jumlah;
            $totalHalaman = max(1, (int) ceil($total / $perPage));
            $halaman = min(max(1, $halaman), $totalHalaman);

            // Urut nama, dengan id sebagai pemecah seri: nama karyawan tidak
            // unik, dan tanpa kunci kedua urutannya bisa bergeser antar
            // halaman sehingga ada baris yang terlewat atau tampil dua kali.
            $rows = DB::table('dms_roster_pola as p')
                ->join('dms_roster_karyawan as k', 'k.id', '=', 'p.karyawan_id')
                ->where('p.tahun', $tahun)
                ->where('k.wajib_cek', true)
                ->select('k.kode_sid', 'k.nama', 'k.jabatan_struktural as jabatan', 'k.kategori',
                    'k.perusahaan', 'k.kode_pt', 'k.site', 'p.pola')
                ->orderBy('k.nama')
                ->orderBy('k.id')
                ->forPage($halaman, $perPage)
                ->get();
        } catch (Throwable $e) {
            Log::warning('DmsRoster overview karyawan gagal: '.$e->getMessage());

            return null;
        }

        if ($rows->isEmpty()) {
            return null;
        }

        $awal = CarbonImmutable::create($tahun, 1, 1)->startOfDay();
        $hariTerakhir = is_string($meta->hari_terakhir ?? null) && $meta->hari_terakhir !== ''
            ? CarbonImmutable::parse((string) $meta->hari_terakhir)
            : $awal;
        $panjang = (int) floor($awal->diffInDays($hariTerakhir, false)) + 1;

        $ambang = $this->engine->ambang();
        $baris = [];

        foreach ($rows as $i => $row) {
            $param = $this->paramPt((string) $row->perusahaan);
            $eval = $this->engine->evaluasi(
                substr((string) $row->pola, 0, max(1, $panjang)),
                (string) $row->kategori,
                $param['thr'],
                $param['map'],
            );

            $baris[] = [
                // Nomor urut lanjut antar halaman, bukan mulai 1 lagi.
                'no' => ($halaman - 1) * $perPage + $i + 1,
                'sid' => (string) $row->kode_sid,
                'pt' => (string) $row->kode_pt,
                'perusahaan' => (string) $row->perusahaan,
                'nama' => (string) $row->nama,
                'jabatan' => (string) ($row->jabatan ?? '—'),
                'kategori' => (string) $row->kategori,
                'site' => (string) ($row->site ?? '—'),
                'roster' => $eval->roster,
                'onsite' => $eval->hadir,
                'pagi' => $eval->pagi,
                'malam' => $eval->malam,
                'off' => $eval->off,
                'cuti' => $eval->cutiCur,
                'shiftMaks' => [
                    $eval->onNoOff.' hari beruntun',
                    $this->rentang($awal, $eval->onS, $eval->onE),
                    ! $eval->longgar && $eval->onNoOff > ($ambang['kerja_beruntun'] ?? 13),
                ],
                'onsiteMaks' => [
                    $eval->onAll.' hari',
                    $eval->onAll > 0 ? 'roster ke-'.$eval->onAllR.' · '.$this->rentang($awal, $eval->onAllS, $eval->onAllE) : '—',
                    ! $eval->longgar && $eval->onAll > ($ambang['onsite'] ?? 71),
                ],
                'cutiMin' => [
                    $eval->cutiMin > 0 ? $eval->cutiMin.' hari' : '—',
                    $eval->cutiMin > 0 ? 'roster ke-'.$eval->cutiMinR.' · '.$this->rentang($awal, $eval->cutiMinS, $eval->cutiMinE) : '—',
                    ! $eval->longgar && $eval->cutiMin > 0 && $eval->cutiMin < ($ambang['cuti_min'] ?? 12),
                ],
                'status' => $eval->status,
                'pelanggaran' => $eval->everRed,
                'longgar' => $eval->longgar,
                // Dipakai panel collapse untuk menggambar pola harian.
                'pola' => $eval->pola,
                'merah' => array_map(static fn (string $v): bool => $v !== '', $eval->red),
            ];
        }

        return [
            'tahun' => $tahun,
            'hari_terakhir' => $hariTerakhir->toDateString(),
            'panjang' => $panjang,
            'total' => $total,
            'halaman' => $halaman,
            'per_page' => $perPage,
            'total_halaman' => $totalHalaman,
            'dari' => ($halaman - 1) * $perPage + 1,
            'sampai' => ($halaman - 1) * $perPage + count($baris),
            'baris' => $baris,
        ];
    }

    /**
     * Karyawan yang paling perlu ditindak: peringkat wajib cuti, on-site
     * berjalan terpanjang lebih dulu.
     *
     * @return array{total:int,baris:list<array<string,mixed>>}|null
     */
    public function perluTindakan(int $limit = 12, ?int $tahun = null): ?array
    {
        $tahun ??= CarbonImmutable::now()->year;

        try {
            $meta = $this->compliance->meta($tahun);
            if ($meta['jumlah'] === 0) {
                return null;
            }

            $wajib = $this->compliance->daftarWajibCuti(['tahun' => $tahun]);
        } catch (Throwable $e) {
            Log::warning('DmsRoster perlu tindakan gagal: '.$e->getMessage());

            return null;
        }

        if ($wajib === []) {
            return ['total' => 0, 'baris' => []];
        }

        $akhir = CarbonImmutable::parse($meta['hari_terakhir']);
        $baris = [];

        foreach (array_slice($wajib, 0, max(1, $limit)) as $r) {
            $hari = (int) $r['onCur'];
            // onCur dihitung mundur dari hari terakhir data, jadi awal blok
            // on-site yang sedang berjalan = hari terakhir - (onCur - 1).
            $mulai = $akhir->subDays(max(0, $hari - 1));

            $baris[] = [
                'sid' => (string) $r['sid'],
                'nama' => (string) $r['nama'],
                'pt' => (string) $r['kode_pt'],
                'site' => (string) ($r['site'] !== '' ? $r['site'] : '—'),
                'hari' => $hari,
                'sejak' => $mulai->translatedFormat('j M Y'),
            ];
        }

        return ['total' => count($wajib), 'baris' => $baris];
    }

    /**
     * @return array{thr:int,map:string}
     */
    private function paramPt(string $perusahaan): array
    {
        /** @var array<string, array<string, mixed>> $daftar */
        $daftar = config('dms_roster.perusahaan', []);
        $p = $daftar[$perusahaan] ?? [];

        return [
            'thr' => (int) ($p['thr'] ?? config('dms_roster.thr_default', 7)),
            'map' => (string) ($p['map'] ?? config('dms_roster.map_default', 'block')),
        ];
    }

    private function rentang(CarbonImmutable $awal, int $mulai, int $selesai): string
    {
        if ($mulai < 0 || $selesai < 0) {
            return '—';
        }

        return $awal->addDays($mulai)->translatedFormat('j M')
            .'–'.$awal->addDays($selesai)->translatedFormat('j M');
    }
}
