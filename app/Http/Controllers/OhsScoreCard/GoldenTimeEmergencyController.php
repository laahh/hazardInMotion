<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Concerns\ServesDataTable;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Parameter Emergency Response "Tidak ada pelaporan melewati batas golden time".
 *
 *   lead_golden_time_emergency        -> persentase resmi, 78 baris
 *   detail_lead_golden_time_emergency -> satu baris per insiden, 102 baris
 *
 * Yang diukur: berapa persen insiden yang dilaporkan ke Control Room sebelum
 * batas golden time terlampaui. Makin tinggi makin baik, dan 100% berarti
 * tidak ada satu pun pelaporan yang telat -- persis bunyi nama parameternya.
 *
 * AMBANGNYA 10 MENIT, DAN ITU BUKAN TEBAKAN. Diturunkan dari datanya sendiri:
 * memakai batas "< 10 menit" tidak ada satu pun dari 102 baris rincian yang
 * bertentangan dengan pct_golden_time bawaan sumber, sedangkan batas "< 9
 * menit" menyisakan satu baris menyimpang. Lihat AMBANG_MENIT.
 *
 * GRAIN RINGKASAN ADA EMPAT KOLOM, bukan tiga. Selain site x perusahaan x
 * bulan, perusahaan_lead_investigasi ikut memecah baris: tiga pasangan tercatat
 * dua kali dengan lead investigasi berbeda. Karena itu ringkasan di-AVG per
 * site x perusahaan x bulan sebelum dipakai. Dengan AVG, seluruh 75 kunci
 * ringkasan cocok sempurna dengan rincian (rata-rata keseluruhan 71,58% vs
 * 71,57%); tanpa AVG, tiga pasangan itu akan terbaca salah.
 *
 * SATU BARIS BERMENIT NEGATIF. Insiden BMO 2 pada 31 Juli 2026 tercatat
 * -1430 menit karena jam kejadian 11:56 PM dan jam pelaporan 12:06 AM
 * diperlakukan sebagai hari yang sama di sumbernya; yang sebenarnya terjadi
 * adalah selisih 10 menit melewati tengah malam. Angkanya dibiarkan apa adanya
 * supaya halaman ini tidak diam-diam berbeda dari sumber, tetapi disebut di
 * catatan dan ikut dihitung sebagai "dalam golden time" mengikuti pct bawaan.
 *
 * TARGET & BAND mengikuti sistem penilaian OHS Score Card yang sama dengan
 * halaman lain (target 90%, band 98/90/80). Belum ada konfirmasi bahwa
 * parameter ini memakai band yang sama; kalau berbeda, ubah SCORE_BANDS dan
 * TARGET_PERCENT di bawah.
 */
final class GoldenTimeEmergencyController extends Controller
{
    use ServesDataTable;

    private const TABEL_RINGKASAN = 'lead_golden_time_emergency';
    private const TABEL_DETAIL = 'detail_lead_golden_time_emergency';

    /** Kolom yang ada di kedua tabel. */
    private const COL_SITE = 'site1';
    private const COL_PERUSAHAAN = 'perusahaan';
    private const COL_LEAD = 'perusahaan_lead_investigasi';
    private const COL_BULAN = 'month_of_ccr_waktu_insiden';
    private const COL_PERSEN = 'pct_golden_time';

    /** Kolom yang hanya ada di tabel rincian. */
    private const COL_KRONOLOGI = 'ccr_kronologi';
    private const COL_WAKTU_INSIDEN = 'second_of_ccr_waktu_insiden';
    private const COL_WAKTU_LAPOR = 'second_of_ccr_waktu_pelaporan';
    private const COL_MENIT = 'golden_time_dalam_menit';

    /**
     * Batas golden time dalam menit; pelaporan di bawah angka ini dianggap
     * tepat waktu. Diturunkan dari data, lihat docblock kelas.
     */
    private const AMBANG_MENIT = 10;

    /** Format waktu di sumber, misalnya "8/12/2026 11:50:00 PM". */
    private const FORMAT_WAKTU = 'n/j/Y g:i:s A';

    /** Kelompok lama pelaporan untuk panel sebaran; batas atas eksklusif. */
    private const KELOMPOK_MENIT = [
        ['Dalam golden time', null, self::AMBANG_MENIT],
        ['10 - 30 menit', self::AMBANG_MENIT, 31],
        ['31 - 60 menit', 31, 61],
        ['1 - 2 jam', 61, 121],
        ['Lebih dari 2 jam', 121, null],
    ];

    /** Batas baris yang dikirim ke modal rincian satu sel. */
    private const BATAS_BARIS_MODAL = 500;

    private const TARGET_PERCENT = 90.0;

    private const SCORE_BANDS = [
        [98.0, 4, '98% - 100%'],
        [90.0, 3, '90% - <98%'],
        [80.0, 2, '80% - <90%'],
        [0.0,  1, '<80%'],
    ];

    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /** Oktober masih berjalan saat data ini diambil, sejalan halaman lain. */
    private const EXCLUDED_MONTHS = [10];

    private const FILTERABLE = [
        'site' => self::COL_SITE,
        'mitra' => self::COL_PERUSAHAAN,
        'lead' => self::COL_LEAD,
    ];

    private const SEARCHABLE = [
        self::COL_SITE, self::COL_PERUSAHAAN, self::COL_LEAD,
        self::COL_BULAN, self::COL_KRONOLOGI,
    ];

    private const ORDERABLE = [
        0 => self::COL_SITE,
        1 => self::COL_PERUSAHAAN,
        2 => self::COL_LEAD,
        5 => self::COL_MENIT,
    ];

    public function index(): View
    {
        return view('ohs-score-card.golden-time-emergency.index', [
            'filterOptions' => [
                'site' => $this->gabungNilai(self::COL_SITE),
                'mitra' => $this->gabungNilai(self::COL_PERUSAHAAN),
                'lead' => $this->gabungNilai(self::COL_LEAD),
            ],
            'monthOptions' => $this->monthOptions(),
            'target' => self::TARGET_PERCENT,
            'ambang' => self::AMBANG_MENIT,
            'tabel_ringkasan' => self::TABEL_RINGKASAN,
            'tabel_detail' => self::TABEL_DETAIL,
        ]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request): JsonResponse
    {
        // AVG wajib di sini: lihat catatan "grain ringkasan ada empat kolom".
        $rows = $this->ringkasanQuery($request)
            ->selectRaw(
                self::COL_SITE . ' AS site, '
                . self::COL_PERUSAHAAN . ' AS mitra, '
                . self::COL_BULAN . ' AS bulan, '
                . 'AVG(' . self::COL_PERSEN . ') AS persen'
            )
            ->groupBy('site', 'mitra', 'bulan')
            ->get();

        $grid = [];
        $monthSeen = [];

        foreach ($rows as $row) {
            $monthNo = $this->nomorBulan((string) $row->bulan);

            if ($monthNo === 0 || in_array($monthNo, self::EXCLUDED_MONTHS, true)) {
                continue;
            }

            $monthSeen[$monthNo] = true;
            $site = trim((string) $row->site);
            $mitra = trim((string) $row->mitra);

            $grid[$site . '|' . $mitra]['site'] = $site;
            $grid[$site . '|' . $mitra]['mitra'] = $mitra;
            // NULL dibiarkan NULL: "tidak ada insiden bulan itu" bukan "nol persen".
            $grid[$site . '|' . $mitra]['bulan'][$monthNo] = $row->persen === null
                ? null
                : round((float) $row->persen, 2);
        }

        ksort($monthSeen);
        $months = array_keys($monthSeen);
        $matrix = $this->buildMatrix($grid, $months);
        $insiden = $this->ringkasInsiden($request);

        return response()->json([
            'months' => $this->monthHeadings($months),
            'kpi' => $this->buildKpi($matrix, $months, $insiden),
            'matrix' => $matrix,
            'per_site' => $this->ringkasPer($matrix, 'site'),
            'per_mitra' => $this->ringkasPer($matrix, 'mitra'),
            'terendah' => $this->buildTerendah($matrix),
            'monthly' => $this->buildMonthlySeries($matrix, $months),
            'sebaran' => $this->buildSebaran($request),
            'terlama' => $this->buildTerlama($request),
            'insiden' => $insiden,
            'catatan' => $this->catatan($request),
        ]);
    }

    /**
     * @param  array<string, mixed>  $grid
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    private function buildMatrix(array $grid, array $months): array
    {
        $out = [];

        foreach ($grid as $entry) {
            $cells = [];
            $terisi = [];

            foreach ($months as $month) {
                $value = $entry['bulan'][$month] ?? null;

                if ($value === null) {
                    $cells[] = null;
                    continue;
                }

                [, $nilai, $band] = $this->scoreBandFor($value);
                $cells[] = ['pct' => $value, 'nilai' => $nilai, 'nilai_band' => $band];
                $terisi[] = $value;
            }

            $average = $terisi !== [] ? round(array_sum($terisi) / count($terisi), 2) : null;
            [, $nilai, $band] = $this->scoreBandFor($average ?? 0.0);

            $out[] = [
                'site' => $entry['site'],
                'mitra' => $entry['mitra'],
                'cells' => $cells,
                'average' => $average,
                'terendah' => $terisi !== [] ? min($terisi) : null,
                'bulan_terisi' => count($terisi),
                'nilai' => $average === null ? null : $nilai,
                'nilai_band' => $average === null ? null : $band,
                'memenuhi_target' => $average !== null && $average >= self::TARGET_PERCENT,
                'trend' => $this->trendOf($terisi),
            ];
        }

        return $this->kelompokkanPerSite($out);
    }

    /**
     * Mengelompokkan baris per site supaya sel site-nya bisa digabung dengan
     * rowspan di tabel.
     *
     * Site diurutkan dari yang capaiannya paling rendah, dan di dalam tiap site
     * barisnya juga dari yang paling rendah, sehingga yang perlu ditangani
     * lebih dulu tetap berada di atas meski sudah dikelompokkan.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function kelompokkanPerSite(array $rows): array
    {
        $perSite = [];

        foreach ($rows as $row) {
            $perSite[$row['site']][] = $row;
        }

        $bobot = [];

        foreach ($perSite as $site => $baris) {
            $nilai = array_filter(
                array_column($baris, 'average'),
                static fn (?float $v): bool => $v !== null
            );
            // Baris tanpa angka didorong ke belakang lewat sentinel 101.
            $bobot[$site] = $nilai !== [] ? min($nilai) : 101.0;
        }

        asort($bobot);
        $out = [];

        foreach (array_keys($bobot) as $site) {
            $baris = $perSite[$site];
            usort(
                $baris,
                static fn (array $a, array $b): int => ($a['average'] ?? 101.0) <=> ($b['average'] ?? 101.0)
            );
            $out = array_merge($out, $baris);
        }

        return $out;
    }

    /** @param  array<int, float>  $terisi */
    private function trendOf(array $terisi): ?string
    {
        if (count($terisi) < 2) {
            return null;
        }

        $akhir = $terisi[count($terisi) - 1];
        $sebelum = $terisi[count($terisi) - 2];

        if ($akhir === $sebelum) {
            return 'flat';
        }

        return $akhir > $sebelum ? 'up' : 'down';
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrix
     * @param  array<int, int>  $months
     * @param  array<string, mixed>  $insiden
     * @return array<string, mixed>
     */
    private function buildKpi(array $matrix, array $months, array $insiden): array
    {
        $nilai = array_values(array_filter(
            array_column($matrix, 'average'),
            static fn (?float $v): bool => $v !== null
        ));

        $rata = $nilai !== [] ? round(array_sum($nilai) / count($nilai), 2) : null;
        [, $band, $bandLabel] = $this->scoreBandFor($rata ?? 0.0);

        $selKosong = 0;
        $selTerisi = 0;

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $cell) {
                $cell === null ? $selKosong++ : $selTerisi++;
            }
        }

        return [
            'rata' => $rata,
            'nilai' => $rata === null ? null : $band,
            'nilai_band' => $rata === null ? null : $bandLabel,
            'target' => self::TARGET_PERCENT,
            'ambang_menit' => self::AMBANG_MENIT,
            'memenuhi_target' => $rata !== null && $rata >= self::TARGET_PERCENT,
            'tertinggi' => $nilai !== [] ? max($nilai) : null,
            'terendah' => $nilai !== [] ? min($nilai) : null,
            'kombinasi' => count($nilai),
            'kombinasi_kosong' => count($matrix) - count($nilai),
            'memenuhi' => count(array_filter(
                $matrix,
                static fn (array $r): bool => $r['memenuhi_target']
            )),
            'site_count' => count(array_unique(array_column($matrix, 'site'))),
            'mitra_count' => count(array_unique(array_column($matrix, 'mitra'))),
            'bulan_count' => count($months),
            'sel_terisi' => $selTerisi,
            'sel_kosong' => $selKosong,
            'insiden_total' => $insiden['total'],
            'insiden_tepat' => $insiden['tepat'],
            'insiden_telat' => $insiden['telat'],
            'insiden_persen' => $insiden['persen'],
            'menit_median' => $insiden['median'],
            'menit_terlama' => $insiden['terlama'],
        ];
    }

    /**
     * Rata-rata per site atau per perusahaan.
     *
     * Rata-rata antar baris, bukan antar bulan, supaya pasangan yang datanya
     * lengkap tidak berbobot lebih besar daripada yang datanya bolong.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function ringkasPer(array $matrix, string $key): array
    {
        $kelompok = [];

        foreach ($matrix as $row) {
            if ($row['average'] === null) {
                continue;
            }

            $kelompok[$row[$key]][] = $row['average'];
        }

        $out = [];

        foreach ($kelompok as $label => $nilai) {
            $rata = round(array_sum($nilai) / count($nilai), 2);
            [, $band] = $this->scoreBandFor($rata);

            $out[] = [
                $key => (string) $label,
                'percent' => $rata,
                'nilai' => $band,
                'jumlah' => count($nilai),
                'terendah' => min($nilai),
                'target' => self::TARGET_PERCENT,
                'memenuhi_target' => $rata >= self::TARGET_PERCENT,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $out;
    }

    /**
     * Lima pasangan dengan capaian terendah.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function buildTerendah(array $matrix): array
    {
        $rows = array_filter($matrix, static fn (array $r): bool => $r['average'] !== null);
        usort($rows, static fn (array $a, array $b): int => $a['average'] <=> $b['average']);

        return array_map(static fn (array $r): array => [
            'site' => $r['site'],
            'mitra' => $r['mitra'],
            'percent' => $r['average'],
            'nilai' => $r['nilai'],
            'terendah' => $r['terendah'],
        ], array_slice($rows, 0, 5));
    }

    /**
     * Satu garis per perusahaan.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $matrix, array $months): array
    {
        $perMitra = [];

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $i => $cell) {
                if ($cell === null) {
                    continue;
                }

                $perMitra[$row['mitra']][$i][] = $cell['pct'];
            }
        }

        ksort($perMitra);
        $series = [];

        foreach ($perMitra as $mitra => $perBulan) {
            $data = [];

            foreach (array_keys($months) as $i) {
                $nilai = $perBulan[$i] ?? null;
                // null, bukan 0: bulan tanpa insiden harus putus di grafik.
                $data[] = $nilai === null ? null : round(array_sum($nilai) / count($nilai), 2);
            }

            $series[] = ['name' => (string) $mitra, 'data' => $data];
        }

        return [
            'labels' => array_map(static fn (int $m): string => self::monthLabel($m), $months),
            'series' => $series,
        ];
    }

    // ======================================================================
    // Panel yang bersumber dari tabel rincian
    // ======================================================================

    /**
     * Cacah insiden, berapa yang tepat waktu, dan sebaran lamanya.
     *
     * @return array<string, mixed>
     */
    private function ringkasInsiden(Request $request): array
    {
        $menit = $this->detailQuery($request)
            ->whereNotNull(self::COL_MENIT)
            ->orderBy(self::COL_MENIT)
            ->pluck(self::COL_MENIT)
            ->map(static fn ($v): int => (int) $v)
            ->all();

        $total = count($menit);

        if ($total === 0) {
            return [
                'total' => 0, 'tepat' => 0, 'telat' => 0, 'persen' => null,
                'median' => null, 'terlama' => null, 'rata' => null,
            ];
        }

        $tepat = count(array_filter(
            $menit,
            static fn (int $m): bool => $m < self::AMBANG_MENIT
        ));

        $tengah = (int) floor(($total - 1) / 2);
        $median = $total % 2 === 1
            ? $menit[$tengah]
            : (int) round(($menit[$tengah] + $menit[$tengah + 1]) / 2);

        return [
            'total' => $total,
            'tepat' => $tepat,
            'telat' => $total - $tepat,
            'persen' => round($tepat / $total * 100, 2),
            'median' => $median,
            'terlama' => max($menit),
            'rata' => (int) round(array_sum($menit) / $total),
        ];
    }

    /**
     * Sebaran lama pelaporan menurut KELOMPOK_MENIT.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSebaran(Request $request): array
    {
        $menit = $this->detailQuery($request)
            ->whereNotNull(self::COL_MENIT)
            ->pluck(self::COL_MENIT)
            ->map(static fn ($v): int => (int) $v)
            ->all();

        $total = count($menit);
        $out = [];

        foreach (self::KELOMPOK_MENIT as [$label, $bawah, $atas]) {
            $jumlah = count(array_filter(
                $menit,
                static fn (int $m): bool => ($bawah === null || $m >= $bawah)
                    && ($atas === null || $m < $atas)
            ));

            $out[] = [
                'label' => $label,
                'jumlah' => $jumlah,
                'persen' => $total > 0 ? round($jumlah / $total * 100, 2) : 0.0,
                'tepat_waktu' => $atas === self::AMBANG_MENIT,
            ];
        }

        return $out;
    }

    /**
     * Sepuluh insiden dengan pelaporan paling lambat.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildTerlama(Request $request): array
    {
        return $this->detailQuery($request)
            ->whereNotNull(self::COL_MENIT)
            ->where(self::COL_MENIT, '>=', self::AMBANG_MENIT)
            ->select($this->detailColumns())
            ->orderByDesc(self::COL_MENIT)
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => $this->present($row))
            ->all();
    }

    /** Keterangan agar matriks berlubang dan baris janggal tidak disalahbaca. */
    private function catatan(Request $request): ?string
    {
        if (! DB::table(self::TABEL_RINGKASAN)->exists()) {
            return 'Tabel ' . self::TABEL_RINGKASAN . ' masih kosong, jadi belum ada yang bisa ditampilkan.';
        }

        $pesan = [];

        $kosong = (clone $this->ringkasanQuery($request))->whereNull(self::COL_PERSEN)->count();

        if ($kosong > 0) {
            $total = (clone $this->ringkasanQuery($request))->count();
            $pesan[] = $kosong . ' dari ' . $total . ' baris di ' . self::TABEL_RINGKASAN
                . ' belum berisi persentase; sel kosong ditandai strip, bukan nol, '
                . 'dan tidak ikut dihitung dalam rata-rata.';
        }

        $negatif = (clone $this->detailQuery($request))->where(self::COL_MENIT, '<', 0)->count();

        if ($negatif > 0) {
            $pesan[] = $negatif . ' insiden tercatat bermenit negatif karena jam kejadian dan jam '
                . 'pelaporan melewati tengah malam tetapi diperlakukan sebagai hari yang sama di '
                . 'sumbernya; angkanya dibiarkan apa adanya dan tetap terhitung sebagai pelaporan '
                . 'tepat waktu, mengikuti pct_golden_time bawaan.';
        }

        $pesan[] = 'Batas golden time yang dipakai ' . self::AMBANG_MENIT . ' menit, diturunkan '
            . 'dari kecocokan penuh antara golden_time_dalam_menit dan pct_golden_time di '
            . self::TABEL_DETAIL . '.';

        // Dua angka persentase tampil berdampingan di kartu ringkasan dan
        // nilainya memang berbeda; sebabnya disebutkan supaya tidak dikira
        // salah hitung.
        $insiden = $this->ringkasInsiden($request);

        if ($insiden['persen'] !== null) {
            $pesan[] = 'Persentase besar di kartu pertama dirata-ratakan antar pasangan site dan '
                . 'perusahaan, jadi pasangan dengan satu insiden berbobot sama dengan yang '
                . 'berinsiden banyak; dihitung per insiden tanpa pembobotan itu, angkanya '
                . number_format($insiden['persen'], 2, ',', '.') . '%.';
        }

        return implode(' ', $pesan);
    }

    // ======================================================================
    // Modal rincian satu sel matriks bulanan
    // ======================================================================

    /**
     * Isi satu sel "Capaian per Bulan", yaitu site x perusahaan x bulan.
     *
     * YANG DITONJOLKAN JEDANYA, BUKAN CACAHNYA. Persentase di sel hanya
     * memberi tahu berapa banyak yang telat; yang tidak terbaca dari matriks
     * adalah seberapa telat. Tabel rincian menyimpan jam kejadian, jam
     * pelaporan, dan golden_time_dalam_menit, jadi tiap insiden bisa
     * ditampilkan dengan jeda lapornya sendiri, diurutkan dari yang terlama,
     * dan ditandai apakah melewati AMBANG_MENIT.
     *
     * PERSENNYA DIAMBIL DARI RINGKASAN, BUKAN DIHITUNG ULANG, persis seperti
     * overview(): AVG(pct_golden_time) atas baris ringkasan yang jatuh di sel
     * ini. Dengan begitu angka di kartu tidak mungkin berbeda dari angka di
     * sel. Hasil hitung ulang dari rincian tetap dikirim terpisah
     * ('persen_rincian') supaya pembaca bisa melihat keduanya; pada data saat
     * ini keduanya cocok di seluruh 75 sel.
     *
     * SATU SEL BISA PUNYA LEBIH DARI SATU BARIS RINGKASAN karena
     * perusahaan_lead_investigasi ikut memecah grain di tabel ringkasan. Tiga
     * sel seperti itu, dan dua di antaranya berisi 100% dan 0% sehingga selnya
     * menjadi 50%. Cacah baris dan daftar lead investigasinya ikut dikirim
     * supaya angka 50% itu bisa diterangkan, bukan terbaca sebagai salah hitung.
     */
    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));

        // monthHeadings() halaman ini mengirim nomor 1-12, tapi kode
        // tahun*100+bulan tetap diterima supaya tidak pecah kalau penomoran
        // heading berubah.
        $kode = (int) $request->input('month', 0);
        $bulan = $kode > 9999 ? $kode % 100 : $kode;

        if ($site === '' || $bulan < 1 || $bulan > 12) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Site dan bulan wajib diisi untuk membuka rincian.',
            ]);
        }

        if (in_array($bulan, self::EXCLUDED_MONTHS, true)) {
            return response()->json([
                'ok' => false,
                'pesan' => self::monthLabel($bulan) . ' tidak ikut ditampilkan di halaman ini '
                    . 'karena datanya belum lengkap.',
            ]);
        }

        /** Satu kueri dasar untuk kedua tabel; kolom koordinatnya senama. */
        $dasar = function (string $tabel) use ($site, $mitra, $bulan): Builder {
            $query = DB::table($tabel)
                ->where(self::COL_SITE, $site)
                ->whereIn(self::COL_BULAN, $this->namaBulan($bulan));

            if ($mitra !== '') {
                $query->where(self::COL_PERUSAHAAN, $mitra);
            }

            return $query;
        };

        $baris = $dasar(self::TABEL_DETAIL)
            ->select($this->detailColumns())
            // Terlama di atas: yang paling jauh melewati golden time itu yang
            // perlu dibaca lebih dulu.
            ->orderByDesc(self::COL_MENIT)
            ->orderBy('id')
            ->limit(self::BATAS_BARIS_MODAL + 1)
            ->get()
            ->map(fn (object $row): array => $this->present($row))
            ->all();

        $terpotong = count($baris) > self::BATAS_BARIS_MODAL;

        if ($terpotong) {
            $baris = array_slice($baris, 0, self::BATAS_BARIS_MODAL);
        }

        return response()->json([
            'ok' => true,
            'judul' => [
                'site' => $site,
                'mitra' => $mitra,
                'bulan' => self::monthLabel($bulan),
            ],
            'ambang' => self::AMBANG_MENIT,
            'ringkas' => $this->ringkasSel($dasar(self::TABEL_DETAIL)),
            'ringkasan' => $this->ringkasanSel($dasar(self::TABEL_RINGKASAN)),
            'sebaran' => $this->sebaranSel($dasar(self::TABEL_DETAIL)),
            'terpotong' => $terpotong,
            'batas' => self::BATAS_BARIS_MODAL,
            'baris' => $baris,
        ]);
    }

    /**
     * Cacah dan sebaran jeda lapor untuk satu sel, dari tabel rincian.
     *
     * @return array<string, mixed>
     */
    private function ringkasSel(Builder $query): array
    {
        $menit = (clone $query)
            ->whereNotNull(self::COL_MENIT)
            ->orderBy(self::COL_MENIT)
            ->pluck(self::COL_MENIT)
            ->map(static fn ($v): int => (int) $v)
            ->all();

        $insiden = (clone $query)->count();
        $total = count($menit);

        if ($total === 0) {
            return [
                'insiden' => $insiden,
                'bermenit' => 0,
                'tepat' => 0,
                'telat' => 0,
                'persen_rincian' => null,
                'median' => null,
                'terlama' => null,
                'tercepat' => null,
                'rata' => null,
                'negatif' => 0,
            ];
        }

        $tepat = count(array_filter($menit, static fn (int $m): bool => $m < self::AMBANG_MENIT));
        $tengah = (int) floor(($total - 1) / 2);

        return [
            'insiden' => $insiden,
            // Dipisah dari 'insiden' supaya baris tanpa menit tidak diam-diam
            // hilang dari penyebut tanpa penjelasan.
            'bermenit' => $total,
            'tepat' => $tepat,
            'telat' => $total - $tepat,
            'persen_rincian' => round($tepat / $total * 100, 2),
            'median' => $total % 2 === 1
                ? $menit[$tengah]
                : (int) round(($menit[$tengah] + $menit[$tengah + 1]) / 2),
            'terlama' => max($menit),
            'tercepat' => min($menit),
            'rata' => (int) round(array_sum($menit) / $total),
            'negatif' => count(array_filter($menit, static fn (int $m): bool => $m < 0)),
        ];
    }

    /**
     * Angka sel menurut tabel ringkasan, beserta baris-baris pembentuknya.
     *
     * AVG-nya sama persis dengan overview(), jadi kartu di modal tidak mungkin
     * menyimpang dari sel yang baru saja diklik.
     *
     * @return array<string, mixed>
     */
    private function ringkasanSel(Builder $query): array
    {
        $baris = (clone $query)
            ->select([
                self::COL_LEAD . ' AS lead_investigasi',
                self::COL_PERSEN . ' AS persen',
            ])
            ->orderByDesc(self::COL_PERSEN)
            ->get();

        $nilai = $baris
            ->pluck('persen')
            ->filter(static fn ($v): bool => $v !== null)
            ->map(static fn ($v): float => (float) $v)
            ->all();

        return [
            'persen' => $nilai === []
                ? null
                : round(array_sum($nilai) / count($nilai), 2),
            'baris' => $baris->map(static function (object $r): array {
                $lead = trim((string) ($r->lead_investigasi ?? ''));

                return [
                    'lead' => $lead === '' ? '-' : $lead,
                    'persen' => $r->persen === null ? null : round((float) $r->persen, 2),
                ];
            })->all(),
        ];
    }

    /**
     * Sebaran jeda lapor di sel ini, memakai KELOMPOK_MENIT yang sama dengan
     * panel sebaran di tab Ringkasan supaya kelompoknya tidak berbeda arti.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sebaranSel(Builder $query): array
    {
        $menit = (clone $query)
            ->whereNotNull(self::COL_MENIT)
            ->pluck(self::COL_MENIT)
            ->map(static fn ($v): int => (int) $v)
            ->all();

        $total = count($menit);
        $out = [];

        foreach (self::KELOMPOK_MENIT as [$label, $bawah, $atas]) {
            $jumlah = count(array_filter(
                $menit,
                static fn (int $m): bool => ($bawah === null || $m >= $bawah)
                    && ($atas === null || $m < $atas)
            ));

            // Kelompok kosong dibuang: di satu sel yang isinya beberapa insiden
            // saja, deretan nol hanya menambah panjang tanpa menambah arti.
            if ($jumlah === 0) {
                continue;
            }

            $out[] = [
                'label' => $label,
                'jumlah' => $jumlah,
                'persen' => $total > 0 ? round($jumlah / $total * 100, 2) : 0.0,
                'tepat_waktu' => $atas === self::AMBANG_MENIT,
            ];
        }

        return $out;
    }

    // ======================================================================
    // Tab Data
    // ======================================================================

    public function data(Request $request): JsonResponse
    {
        $query = $this->dataQuery($request);

        $rows = (clone $query)
            ->select($this->detailColumns())
            ->orderBy(
                $this->dtOrderColumn($request, self::ORDERABLE, self::COL_SITE),
                $this->dtDirection($request, 'asc')
            )
            ->orderBy('id')
            ->forPage($this->dtPage($request), $this->dtPageLength($request))
            ->get()
            ->map(fn (object $row): array => $this->present($row))
            ->all();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $this->baseCount(),
            'recordsFiltered' => (clone $query)->count(),
            'data' => $rows,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->dataQuery($request)
            ->select($this->detailColumns())
            ->orderBy(self::COL_SITE)
            ->orderBy(self::COL_PERUSAHAAN)
            ->orderBy('id');

        return $this->dtExport(
            $request,
            $query,
            [
                'Site', 'Perusahaan', 'Lead Investigasi', 'Bulan', 'Waktu Insiden',
                'Waktu Pelaporan', 'Golden Time (menit)', 'Status', 'Kronologi',
            ],
            function (object $row): array {
                $p = $this->present($row);

                return [
                    $p['site'], $p['mitra'], $p['lead'], $p['bulan'],
                    $p['waktu_insiden'], $p['waktu_lapor'],
                    $p['menit'] ?? '', $p['status'], $p['kronologi'],
                ];
            },
            'golden-time-emergency'
        );
    }

    /** @return array<int, string> */
    private function detailColumns(): array
    {
        return [
            'id',
            self::COL_SITE . ' AS site',
            self::COL_PERUSAHAAN . ' AS mitra',
            self::COL_LEAD . ' AS lead_investigasi',
            self::COL_BULAN . ' AS bulan_sumber',
            self::COL_WAKTU_INSIDEN . ' AS waktu_insiden',
            self::COL_WAKTU_LAPOR . ' AS waktu_lapor',
            self::COL_MENIT . ' AS menit',
            self::COL_KRONOLOGI . ' AS kronologi',
            self::COL_PERSEN . ' AS persen',
        ];
    }

    private function dataQuery(Request $request): Builder
    {
        $query = $this->detailQuery($request);

        $status = trim((string) $request->input('status', ''));

        if ($status === 'tepat') {
            $query->where(self::COL_MENIT, '<', self::AMBANG_MENIT);
        } elseif ($status === 'telat') {
            $query->where(self::COL_MENIT, '>=', self::AMBANG_MENIT);
        }

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            self::SEARCHABLE
        );

        return $query;
    }

    // ======================================================================
    // Kueri dasar
    // ======================================================================

    /** Tabel ringkasan dengan filter dimensi & bulan terpasang. */
    private function ringkasanQuery(Request $request): Builder
    {
        return $this->terapkanFilter(DB::table(self::TABEL_RINGKASAN), $request);
    }

    /** Tabel rincian dengan filter dimensi & bulan terpasang. */
    private function detailQuery(Request $request): Builder
    {
        return $this->terapkanFilter(DB::table(self::TABEL_DETAIL), $request);
    }

    /**
     * Filter yang sama untuk kedua tabel; kolomnya kebetulan senama, jadi
     * cukup satu jalur dan keduanya dijamin tidak pernah tersaring berbeda.
     */
    private function terapkanFilter(Builder $query, Request $request): Builder
    {
        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn(self::COL_BULAN, $this->namaBulan($nomor));
        }

        foreach (self::FILTERABLE as $parameter => $column) {
            $value = trim((string) $request->input($parameter, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $month = (int) $request->input('month', 0);

        if ($month >= 1 && $month <= 12) {
            $query->whereIn(self::COL_BULAN, $this->namaBulan($month));
        }

        return $query;
    }

    /**
     * Satu baris rincian dalam bentuk siap tampil.
     *
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        $menit = $row->menit === null ? null : (int) $row->menit;
        $tepat = $menit !== null && $menit < self::AMBANG_MENIT;
        $monthNo = $this->nomorBulan((string) $row->bulan_sumber);
        $lead = trim((string) ($row->lead_investigasi ?? ''));

        return [
            'site' => trim((string) $row->site),
            'mitra' => trim((string) $row->mitra),
            // Lead investigasi kosong di sebagian baris sumber; dibiarkan
            // bertanda strip, bukan diisi nama perusahaan, karena keduanya
            // tidak selalu sama (21 dari 102 baris berbeda).
            'lead' => $lead === '' ? '-' : $lead,
            'bulan' => $monthNo === 0 ? trim((string) $row->bulan_sumber) : self::monthLabel($monthNo),
            'waktu_insiden' => $this->waktuTampil((string) $row->waktu_insiden),
            'waktu_lapor' => $this->waktuTampil((string) $row->waktu_lapor),
            'menit' => $menit,
            'menit_label' => $menit === null ? '-' : $this->lamaTampil($menit),
            'tepat_waktu' => $tepat,
            'status' => $menit === null
                ? 'Tidak ada waktu'
                : ($tepat ? 'Dalam golden time' : 'Melewati batas'),
            'kronologi' => trim((string) ($row->kronologi ?? '')),
        ];
    }

    // ======================================================================
    // Utilitas
    // ======================================================================

    /** Waktu sumber "8/12/2026 11:50:00 PM" menjadi "12/08/2026 23:50". */
    private function waktuTampil(string $nilai): string
    {
        $nilai = trim($nilai);

        if ($nilai === '') {
            return '-';
        }

        $waktu = DateTimeImmutable::createFromFormat(self::FORMAT_WAKTU, $nilai);

        return $waktu === false ? $nilai : $waktu->format('d/m/Y H:i');
    }

    /** Lama pelaporan dalam satuan yang enak dibaca. */
    private function lamaTampil(int $menit): string
    {
        if ($menit < 0) {
            return $menit . ' mnt';
        }

        if ($menit < 60) {
            return $menit . ' mnt';
        }

        if ($menit < 1440) {
            return intdiv($menit, 60) . ' jam ' . ($menit % 60) . ' mnt';
        }

        return intdiv($menit, 1440) . ' hari ' . intdiv($menit % 1440, 60) . ' jam';
    }

    /**
     * Nomor bulan dari dua bentuk penulisan yang dipakai sumber-sumber ini:
     * nama bulan Inggris maupun "M01".."M12". 0 bila tidak dikenali.
     */
    private function nomorBulan(string $nilai): int
    {
        $nilai = trim($nilai);

        if (preg_match('/^M(\d{1,2})$/i', $nilai, $cocok) === 1) {
            $nomor = (int) $cocok[1];

            return $nomor >= 1 && $nomor <= 12 ? $nomor : 0;
        }

        return self::MONTH_MAP[$nilai][0] ?? 0;
    }

    /**
     * Semua cara penulisan satu nomor bulan, untuk dipakai di whereIn.
     *
     * @return array<int, string>
     */
    private function namaBulan(int $nomor): array
    {
        $out = [sprintf('M%02d', $nomor), 'M' . $nomor];

        foreach (self::MONTH_MAP as $inggris => [$no]) {
            if ($no === $nomor) {
                $out[] = $inggris;
            }
        }

        return $out;
    }

    private function baseCount(): int
    {
        $query = DB::table(self::TABEL_DETAIL);

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn(self::COL_BULAN, $this->namaBulan($nomor));
        }

        return $query->count();
    }

    /**
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    private function monthHeadings(array $months): array
    {
        return array_map(static fn (int $m): array => [
            'number' => $m,
            'label' => mb_strtoupper(mb_substr(self::monthLabel($m), 0, 3)),
        ], $months);
    }

    /**
     * Nilai unik satu kolom dari kedua tabel sekaligus, supaya pilihan filter
     * tidak kehilangan perusahaan yang hanya muncul di salah satunya.
     *
     * @return array<int, string>
     */
    private function gabungNilai(string $column): array
    {
        $nilai = [];

        foreach ([self::TABEL_RINGKASAN, self::TABEL_DETAIL] as $tabel) {
            foreach ($this->distinctValues($tabel, $column) as $v) {
                $nilai[$v] = true;
            }
        }

        $out = array_keys($nilai);
        sort($out);

        return $out;
    }

    /** @return array<int, string> */
    private function distinctValues(string $table, string $column): array
    {
        return DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(static fn ($v): string => trim((string) $v))
            ->filter(static fn (string $v): bool => $v !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Bulan yang benar-benar ada di sumber, diurutkan kalender.
     *
     * @return array<int, string>
     */
    private function monthOptions(): array
    {
        $months = [];

        foreach ($this->gabungNilai(self::COL_BULAN) as $nilai) {
            $nomor = $this->nomorBulan($nilai);

            if ($nomor === 0 || in_array($nomor, self::EXCLUDED_MONTHS, true)) {
                continue;
            }

            $months[$nomor] = self::monthLabel($nomor);
        }

        ksort($months);

        return $months;
    }

    private static function monthLabel(int $number): string
    {
        foreach (self::MONTH_MAP as [$no, $label]) {
            if ($no === $number) {
                return $label;
            }
        }

        return '-';
    }

    /** @return array{0: float, 1: int, 2: string} */
    private function scoreBandFor(float $percent): array
    {
        foreach (self::SCORE_BANDS as $band) {
            if ($percent >= $band[0]) {
                return $band;
            }
        }

        return [0.0, 1, '<80%'];
    }
}
