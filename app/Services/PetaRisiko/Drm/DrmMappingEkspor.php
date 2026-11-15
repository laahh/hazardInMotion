<?php

namespace App\Services\PetaRisiko\Drm;

use Carbon\CarbonImmutable;

/**
 * Excel "mapping bahaya-risiko": satu baris per bahaya-risiko HIRA (aktivitas – sub – bahaya) beserta
 * status identifikasi SAP, pemetaan ke label SAP (Master A) & teks Observasi/OAK (Master B), dan insiden.
 * Ditulis setiap sync ke storage/app/peta-risiko-drm/mapping-bahaya-risiko.xlsx.
 */
class DrmMappingEkspor
{
    public const NAMA_FILE = 'mapping-bahaya-risiko.xlsx';

    private const STATUS = [
        'G' => '! Teridentifikasi – ada gap',
        'K' => '✓ Teridentifikasi & terkontrol',
        'T' => '○ Tidak teridentifikasi (open risk)',
        'X' => '× Di luar baseline: tidak terpetakan ke SAP',
        '-' => '× Di luar baseline: tidak ada detail lokasi',
    ];

    /** [judul, lebar, dibungkus?, kunci baris] */
    private const KOLOM = [
        ['No', 6, false, null],
        ['Aktivitas', 30, true, 'aktivitas'],
        ['Sub aktivitas', 30, true, 'sub'],
        ['Bahaya / penyebab potensial', 40, true, 'bahaya'],
        ['Potensi insiden', 30, true, 'potensi'],
        ['Site (HIRA)', 18, true, 'sites'],
        ['Group Lokasi (aktivitas)', 22, true, 'grup'],
        ['Proses bisnis', 24, true, 'proses'],
        ['Faktor 4M1E', 12, false, 'faktor'],
        ['Risiko awal', 12, false, 'n1'],
        ['Kekerapan awal', 16, false, 'k1'],
        ['Konsekuensi awal', 16, false, 'c1'],
        ['Risiko residual', 12, false, 'n2'],
        ['Kekerapan residual', 16, false, 'k2'],
        ['Konsekuensi residual', 16, false, 'c2'],
        ['Pengendalian yang ada (HIRA)', 45, true, 'kontrol'],
        ['Status gabungan', 30, false, 'status'],
        ['Risiko final', 12, false, 'final'],
        ['Detail lokasi baseline', 11, false, 'dl_n'],
        ['DL terkontrol', 11, false, 'dl_k'],
        ['DL ada gap', 11, false, 'dl_g'],
        ['DL tidak teridentifikasi', 13, false, 'dl_t'],
        ['DL tidak terpetakan', 12, false, 'dl_x'],
        ['DL dgn temuan open', 12, false, 'dl_open'],
        ['DL temuan berulang', 12, false, 'dl_ulang'],
        ['DL OAK stop pekerjaan', 13, false, 'dl_stop'],
        ['Jumlah temuan open', 11, false, 'temuan_open'],
        ['Temuan dalam jendela', 11, false, 'temuan_jendela'],
        ['Laporan SAP terakhir', 13, false, 'terakhir'],
        ['Jml label SAP terpetakan (Master A)', 13, false, 'n_a'],
        ['Contoh label SAP (ketidaksesuaian › sub)', 55, true, 'label_a'],
        ['Jml teks Obs/OAK terpetakan (Master B)', 13, false, 'n_b'],
        ['Contoh teks aktivitas Obs/OAK', 45, true, 'teks_b'],
        ['DL dgn insiden 12 bln', 12, false, 'inc_dl'],
        ['Jumlah insiden terkait', 11, false, 'inc_n'],
        ['Insiden terakhir', 13, false, 'inc_tgl'],
        ['Risiko laten', 10, false, 'laten'],
    ];

    /**
     * @param  list<array<string, mixed>>  $baris  dari DrmSnapshotBuilder::susun()['ekspor']
     * @param  array<string, mixed>  $cfg
     */
    public function tulis(array $baris, CarbonImmutable $sampai, array $cfg, string $tujuan): void
    {
        $x = new DrmXlsxWriter();
        $x->tambahSheet(
            'Mapping bahaya-risiko',
            array_column(self::KOLOM, 0),
            $this->isi($baris),
            array_column(self::KOLOM, 1),
            array_keys(array_filter(array_column(self::KOLOM, 2))),
        );
        $x->tambahSheet('Ringkasan', ['Status gabungan', 'Jumlah bahaya-risiko', 'Keterangan'], $this->ringkasan($baris), [36, 18, 70], [2]);
        $x->tambahSheet('Keterangan', ['Hal', 'Penjelasan'], $this->keterangan($sampai, $cfg), [32, 110], [1]);
        $x->simpan($tujuan);
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return \Generator<list<string|int|bool>>
     */
    private function isi(array $baris): \Generator
    {
        foreach ($baris as $i => $r) {
            $isi = [];
            foreach (self::KOLOM as [, , , $kunci]) {
                $isi[] = match ($kunci) {
                    null => $i + 1,
                    'status' => self::STATUS[$r['status']] ?? $r['status'],
                    default => $r[$kunci] ?? '',
                };
            }
            yield $isi;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return list<list<string|int>>
     */
    private function ringkasan(array $baris): array
    {
        $n = array_count_values(array_column($baris, 'status'));
        $laten = count(array_filter($baris, fn ($r) => $r['laten']));
        $baseline = ($n['K'] ?? 0) + ($n['G'] ?? 0) + ($n['T'] ?? 0);

        return [
            ['Total bahaya-risiko HIRA aktif', count($baris), 'Unik per aktivitas – sub aktivitas – bahaya.'],
            ['BASELINE (terpetakan ke SAP + punya detail lokasi)', $baseline, 'Angka "Total bahaya-risiko unik (baseline)" di dashboard (Semua site). Hanya bahaya yang bisa diidentifikasi lewat SAP.'],
            [self::STATUS['K'], $n['K'] ?? 0, 'Ada sinyal SAP dalam jendela pada butir yang cocok dan tidak ada gap.'],
            [self::STATUS['G'], $n['G'] ?? 0, 'Temuan Hazard/Inspeksi masih open, berulang, atau OAK stop pekerjaan — di salah satu detail lokasi.'],
            [self::STATUS['T'], $n['T'] ?? 0, 'Terpetakan ke SAP tapi tidak ada laporan dalam jendela.'],
            [self::STATUS['X'], $n['X'] ?? 0, 'Tidak punya pasangan label SAP (Master A) maupun Observasi/OAK (Master B). Tidak dipaksakan, tidak dihitung di baseline.'],
            [self::STATUS['-'], $n['-'] ?? 0, 'Terpetakan ke SAP, tetapi aktivitasnya tidak cocok Group Lokasi di site-nya dan tidak ada laporan Obs/OAK-nya di lokasi mana pun.'],
            ['Risiko laten', $laten, 'Terkait insiden 12 bulan terakhir tapi tidak teridentifikasi SAP dalam jendela.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return list<list<string>>
     */
    private function keterangan(CarbonImmutable $sampai, array $cfg): array
    {
        $mulai = $sampai->subDays((int) $cfg['rentang_hari'] - 1)->toDateString();

        return [
            ['Data sampai', $sampai->toDateString().' (data SAP dibaca mulai '.$mulai.', '.$cfg['rentang_hari'].' hari)'],
            ['Jendela sinyal', $cfg['jendela_hari'].' hari terakhir untuk status terkontrol / ada gap.'],
            ['Satu baris', 'Satu bahaya-risiko HIRA aktif (mv_hira_master, JSON causes) per aktivitas – sub aktivitas – bahaya. Risiko awal/residual memakai baris terburuk bila ada duplikat.'],
            ['Baseline', 'Hanya bahaya yang BISA diidentifikasi lewat SAP (punya pasangan Master A atau Master B). Bahaya tersebut masuk detail lokasi bila site HIRA cocok DAN Group Lokasi aktivitas = Group Lokasi detail lokasi, ATAU aktivitasnya pernah dilaporkan lewat Observasi/OAK di detail lokasi itu.'],
            ['Status gabungan', 'Terburuk antar detail lokasi baseline: Ada gap > Terkontrol > Tidak teridentifikasi > Tidak terpetakan. Sama dengan angka "Semua site" di dashboard.'],
            ['Risiko final', 'Terkontrol → risiko residual HIRA; selain itu tetap risiko awal.'],
            ['DL …', 'Jumlah detail lokasi baseline per status (satu bahaya bisa berstatus berbeda di detail lokasi yang berbeda).'],
            ['Temuan berulang', '≥ '.$cfg['ambang_berulang'].' temuan Hazard/Inspeksi terpetakan dalam jendela di detail lokasi yang sama.'],
            ['Prinsip pemetaan', 'Tidak dipaksakan: bila kaitan teks SAP dengan bahaya tidak cukup kuat, bahaya dibiarkan "Belum terpetakan ke SAP" daripada dipasangkan keliru. Kata dibandingkan utuh; kata yang muncul di > '.($cfg['pemetaan']['kata_umum_persen'] * 100).'% teks HIRA dianggap kata umum; tema '.implode(', ', $cfg['pemetaan']['tema_umum']).' dianggap terlalu umum.'],
            ['Master A', 'Label SAP Hazard/Inspeksi (ketidaksesuaian › sub) ↔ bahaya HIRA (bahaya + potensi insiden): tema spesifik bersama (kamus 29 tema) DAN ≥'.$cfg['pemetaan']['kata_min'].' kata sama, minimal 1 di antaranya bukan kata umum.'],
            ['Master B', 'Teks aktivitas Observasi / aktivitas–sub OAK ↔ aktivitas–sub HIRA: ≥'.$cfg['master_b']['kata_min'].' kata sama (minimal 1 bukan kata umum), maks '.$cfg['master_b']['maks_per_teks'].' aktivitas HIRA terkuat per teks.'],
            ['Insiden terkait', 'Insiden BeInvestigasi berstatus investigasi (tanpa Golden Rules/PSPP) di detail lokasi baseline, teks kronologi + root cause cocok dengan bahaya (tema spesifik bersama + ≥'.$cfg['pemetaan']['kata_min'].' kata sama, minimal 1 bukan kata umum).'],
            ['Catatan', 'Pemetaan berbasis kata kunci dan belum diuji silang pakar. "Terkontrol" = menurut SAP, bukan bukti efektivitas lapangan. "Tidak teridentifikasi" bukan berarti aman.'],
        ];
    }
}
