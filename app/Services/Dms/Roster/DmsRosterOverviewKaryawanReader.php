<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pembaca baris tabel "Scorecard per Karyawan & Timeline" di halaman
 * Ringkasan Roster.
 *
 * Filter & urutan butuh hasil evaluasi seluruh populasi, jadi pembaca ini
 * meminjam DmsRosterComplianceService yang hasil evaluasinya di-cache bersama
 * kartu agregat halaman ini. Pola harian lengkap (panel collapse) hanya
 * dievaluasi ulang untuk baris yang tampil. Mengembalikan null bila tabel
 * kosong atau database tidak terjangkau, supaya halaman jatuh ke data contoh
 * tanpa gagal render.
 *
 * (Daftar "Karyawan Perlu Tindakan" kini dari DmsRosterRegLiveService —
 * aturan banned periode kerja > 70 hari langsung dari RFID.)
 */
final class DmsRosterOverviewKaryawanReader
{
    public function __construct(
        private readonly DmsRosterRuleEngine $engine,
        private readonly DmsRosterComplianceService $compliance,
    ) {}

    /**
     * Satu halaman baris scorecard sesuai filter tabel (cari, status, notes,
     * roster, kategori, perusahaan, site, periode, urutan).
     *
     * Penyaringan, pengurutan, dan paginasi dikerjakan
     * DmsRosterComplianceService::dashboard() atas hasil evaluasi seluruh
     * populasi yang sudah di-cache (dipakai bersama kartu agregat halaman
     * ini), jadi filter tidak menambah evaluasi baru. Pola harian lengkap
     * untuk panel collapse hanya dievaluasi ulang untuk baris yang tampil.
     *
     * @param  array<string, mixed>  $filters  Hasil RosterComplianceController::filters().
     * @return array<string, mixed>|null
     */
    public function ambil(array $filters, int $perPage = 10): ?array
    {
        try {
            $payload = $this->compliance->dashboard($filters + ['per_page' => max(1, $perPage)]);
            if (! ($payload['up'] ?? false)) {
                return null;
            }

            $polaHalaman = $this->polaUntuk(array_column($payload['baris'], 'sid'), (int) $payload['tahun']);
        } catch (Throwable $e) {
            Log::warning('DmsRoster overview karyawan gagal: '.$e->getMessage());

            return null;
        }

        $ambang = $this->engine->ambang();
        $pg = $payload['paginasi'];
        $panjang = (int) $payload['meta']['panjang'];
        $baris = [];

        foreach ($payload['baris'] as $i => $r) {
            $longgar = (bool) $r['longgar'];
            $pola = $polaHalaman[$r['sid']] ?? null;
            $eval = $pola === null ? null : $this->engine->evaluasi(
                substr($pola['pola'], 0, max(1, $panjang)),
                $pola['kategori'],
                $this->paramPt($pola['perusahaan'])['thr'],
                $this->paramPt($pola['perusahaan'])['map'],
            );

            $baris[] = [
                // Nomor urut lanjut antar halaman, bukan mulai 1 lagi.
                'no' => ($pg['halaman'] - 1) * $pg['per_page'] + $i + 1,
                'sid' => (string) $r['sid'],
                'pt' => (string) $r['kode_pt'],
                'perusahaan' => (string) $r['perusahaan'],
                'nama' => (string) $r['nama'],
                'jabatan' => $r['jabatan'] !== '' ? (string) $r['jabatan'] : '—',
                'kategori' => (string) $r['kategori'],
                'site' => $r['site'] !== '' ? (string) $r['site'] : '—',
                'roster' => (int) $r['roster'],
                'onsite' => (int) $r['hadir'],
                'pagi' => (int) $r['pagi'],
                'malam' => (int) $r['malam'],
                'off' => (int) $r['off'],
                'cuti' => (int) $r['cutiCur'],
                'shiftMaks' => [
                    $r['onNoOff'].' hari beruntun',
                    $r['rentang_onNoOff'] !== '' ? $r['rentang_onNoOff'] : '—',
                    ! $longgar && $r['onNoOff'] > ($ambang['kerja_beruntun'] ?? 13),
                ],
                'onsiteMaks' => [
                    $r['onAll'].' hari',
                    $r['onAll'] > 0 ? 'roster ke-'.$r['onAllR'].' · '.$r['rentang_onAll'] : '—',
                    ! $longgar && $r['onAll'] > ($ambang['onsite'] ?? 71),
                ],
                'cutiMin' => [
                    $r['cutiMin'] > 0 ? $r['cutiMin'].' hari' : '—',
                    $r['cutiMin'] > 0 ? 'roster ke-'.$r['cutiMinR'].' · '.$r['rentang_cutiMin'] : '—',
                    ! $longgar && $r['cutiMin'] > 0 && $r['cutiMin'] < ($ambang['cuti_min'] ?? 12),
                ],
                'status' => (string) $r['status'],
                'pelanggaran' => (bool) $r['everRed'],
                'longgar' => $longgar,
                // Dipakai panel collapse untuk menggambar pola harian.
                'pola' => $eval?->pola ?? '',
                'merah' => $eval === null ? [] : array_map(static fn (string $v): bool => $v !== '', $eval->red),
                // Daftar kejadian pelanggaran (rentang tanggal) di bawah kalender.
                'riwayat' => $eval === null ? [] : $this->riwayatPelanggaran($eval, (int) $payload['tahun']),
            ];
        }

        return [
            'tahun' => (int) $payload['tahun'],
            'hari_terakhir' => (string) $payload['meta']['hari_terakhir'],
            'panjang' => $panjang,
            'total' => (int) $pg['total_baris'],
            'populasi' => (int) $payload['agregat']['total'],
            'halaman' => (int) $pg['halaman'],
            'per_page' => (int) $pg['per_page'],
            'total_halaman' => (int) $pg['total_halaman'],
            'dari' => (int) $pg['dari'],
            'sampai' => (int) $pg['sampai'],
            'baris' => $baris,
            // Isi pilihan dropdown filter & keadaannya saat ini.
            'filters' => $payload['filters'],
            'periode' => $payload['periode'],
            'opsi' => [
                'status' => $payload['statusTersedia'],
                'kategori' => $payload['kategoriTersedia'],
                'roster' => $payload['rosterTersedia'],
                'site' => $payload['siteTersedia'],
                'pt' => array_map(
                    static fn (array $p): array => ['kode' => $p['kode'], 'nama' => $p['perusahaan']],
                    $payload['perPt'],
                ),
            ],
        ];
    }

    /**
     * Kejadian pelanggaran sepanjang tahun, satu entri per run yang melanggar,
     * urut tanggal. Memakai ambang rule engine yang sama dengan titik merah di
     * kalender dan kolom tabel, jadi ketiganya selalu sepakat. Run kerja
     * beruntun yang berada DI DALAM periode on-site panjang dicatat sebagai
     * kejadian sendiri — keduanya pelanggaran yang berbeda.
     *
     * @return list<array{jenis:string,teks:string,mulai:string,selesai:string,hari:int}>
     */
    public function riwayatPelanggaran(DmsRosterEvaluation $e, int $tahun): array
    {
        if ($e->longgar || $e->pola === '') {
            return [];
        }

        $ambang = $this->engine->ambang();
        $awal = CarbonImmutable::create($tahun, 1, 1)->startOfDay();
        $p = $e->pola;
        $n = strlen($p);
        $out = [];

        $aturan = [
            // [cocok-karakter, apakah run melanggar, jenis, teks]
            [
                static fn (string $c): bool => $c !== 'c',
                static fn (int $len): bool => $len > ($ambang['onsite'] ?? 71),
                'Periode kerja',
                static fn (int $len): string => $len.' hari on-site tanpa cuti',
            ],
            [
                static fn (string $c): bool => $c === 'P' || $c === 'M',
                static fn (int $len): bool => $len > ($ambang['kerja_beruntun'] ?? 13),
                'Bekerja berturut-turut',
                static fn (int $len): string => $len.' hari tanpa off',
            ],
            [
                static fn (string $c): bool => $c === 'c',
                static fn (int $len): bool => $len < ($ambang['cuti_min'] ?? 12),
                'Cuti terlalu pendek',
                static fn (int $len): string => 'cuti hanya '.$len.' hari',
            ],
        ];

        foreach ($aturan as [$cocok, $melanggar, $jenis, $teks]) {
            $i = 0;
            while ($i < $n) {
                if (! $cocok($p[$i])) {
                    $i++;

                    continue;
                }
                $j = $i;
                while ($j < $n && $cocok($p[$j])) {
                    $j++;
                }
                if ($melanggar($j - $i)) {
                    $out[] = [
                        'jenis' => $jenis,
                        'teks' => $teks($j - $i),
                        'mulai' => $awal->addDays($i)->translatedFormat('j M'),
                        'selesai' => $awal->addDays($j - 1)->translatedFormat('j M'),
                        'hari' => $i,
                    ];
                }
                $i = $j;
            }
        }

        usort($out, static fn (array $a, array $b): int => $a['hari'] <=> $b['hari']);

        return $out;
    }

    /**
     * Pola mentah untuk sekumpulan SID (satu halaman tabel).
     *
     * @param  list<string>  $sids
     * @return array<string, array{pola:string,kategori:string,perusahaan:string}>
     */
    private function polaUntuk(array $sids, int $tahun): array
    {
        if ($sids === []) {
            return [];
        }

        return DB::table('dms_roster_pola as p')
            ->join('dms_roster_karyawan as k', 'k.id', '=', 'p.karyawan_id')
            ->where('p.tahun', $tahun)
            ->whereIn('k.kode_sid', $sids)
            ->get(['k.kode_sid', 'p.pola', 'k.kategori', 'k.perusahaan'])
            ->mapWithKeys(static fn (object $r): array => [(string) $r->kode_sid => [
                'pola' => (string) $r->pola,
                'kategori' => (string) $r->kategori,
                'perusahaan' => (string) $r->perusahaan,
            ]])
            ->all();
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
}
