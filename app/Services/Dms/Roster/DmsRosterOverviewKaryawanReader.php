<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Baris karyawan asli untuk tabel "Scorecard per Karyawan & Timeline" di
 * halaman Ringkasan Roster.
 *
 * Sumbernya tabel lokal dms_roster_patterns (hasil sinkronisasi RFID), lalu
 * tiap pola dijalankan lewat DmsRosterRuleEngine untuk mendapat metrik roster
 * dan array merah/kuning per hari.
 *
 * Sengaja TIDAK memanggil DmsRosterComplianceService: halaman ini hanya butuh
 * beberapa baris teratas, sedangkan service itu mengevaluasi seluruh populasi
 * (±8 ribu orang, ±9 detik saat cache dingin) untuk menyusun agregat yang
 * tidak dipakai di sini.
 *
 * Mengembalikan null bila tabel kosong atau database tidak terjangkau, supaya
 * halaman bisa jatuh kembali ke baris contoh tanpa gagal render.
 */
final class DmsRosterOverviewKaryawanReader
{
    public function __construct(
        private readonly DmsRosterRuleEngine $engine,
    ) {}

    /**
     * @return array{tahun:int,hari_terakhir:string,panjang:int,baris:list<array<string,mixed>>}|null
     */
    public function ambil(int $limit = 10, ?int $tahun = null): ?array
    {
        $tahun ??= CarbonImmutable::now()->year;

        try {
            $meta = DB::table('dms_roster_patterns')
                ->where('tahun', $tahun)
                ->selectRaw('COUNT(*) AS jumlah, MAX(hari_terakhir) AS hari_terakhir')
                ->first();

            if ($meta === null || (int) $meta->jumlah === 0) {
                return null;
            }

            // Prioritaskan yang metriknya paling menarik dilihat: on-site
            // terpanjang lebih dulu, lalu nama supaya urutannya stabil.
            $rows = DB::table('dms_roster_patterns')
                ->where('tahun', $tahun)
                ->select('kode_sid', 'nama', 'jabatan', 'kategori', 'perusahaan', 'kode_pt', 'site', 'pola')
                ->orderBy('nama')
                ->limit(max(1, $limit))
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

        foreach ($rows as $row) {
            $param = $this->paramPt((string) $row->perusahaan);
            $eval = $this->engine->evaluasi(
                substr((string) $row->pola, 0, max(1, $panjang)),
                (string) $row->kategori,
                $param['thr'],
                $param['map'],
            );

            $baris[] = [
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
            'baris' => $baris,
        ];
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
