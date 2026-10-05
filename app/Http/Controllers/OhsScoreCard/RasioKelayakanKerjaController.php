<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Concerns\ServesDataTable;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Parameter wellbeing "Rasio Kelayakan Kerja", untuk dua populasi pekerja:
 *
 *   minecon -> lead_ratio_kelayakan_kerja         + detail_lead_ratio_kelayakan_kerja
 *   subcon  -> lead_subcont_ratio_kelayakan_kerja (tanpa tabel rincian)
 *
 * Ukurannya persentase pekerja yang hasil MCU-nya Fit. Makin tinggi makin baik.
 *
 * DUA ANGKA YANG BERBEDA, DAN KEDUANYA DITAMPILKAN. Tabel bulanan memberi
 * persentase per bulan; tabel rincian memberi hasil MCU per karyawan. Keduanya
 * tidak sama, dan selisihnya bisa besar: BMO 2 / PT Pamapersada tercatat
 * 67,89% bila merata-ratakan sembilan angka bulanan, tetapi 95,86% bila
 * dihitung dari 1.593 karyawannya. Penyebabnya rata-rata antar bulan memberi
 * bobot sama pada bulan berisi 2 orang dan bulan berisi 500 orang. Karena itu
 * kartu KPI memakai angka dari rincian (berbobot jumlah karyawan) sementara
 * matriks tetap menampilkan angka bulanan apa adanya, dan selisihnya disebut
 * di keterangan.
 *
 * RINCIAN TIDAK PUNYA KOLOM BULAN, hanya tahun. Jadi panel hasil MCU bersifat
 * setahun penuh dan tidak ikut berubah saat filter bulan dipakai; itu
 * dinyatakan di subjudul panelnya.
 *
 * SUBCON TIDAK PUNYA DIMENSI PERUSAHAAN maupun tabel rincian, jadi matriksnya
 * site x bulan dan tab Data-nya menampilkan baris bulanan, bukan karyawan.
 * Bentuk kolom tab Data karena itu ditentukan per kumpulan data.
 */
final class RasioKelayakanKerjaController extends Controller
{
    use ServesDataTable;

    /**
     * Dua populasi dengan bentuk sumber yang berbeda.
     *
     * 'detail' bernilai null bila populasi itu belum punya tabel rincian.
     */
    private const DATASETS = [
        'minecon' => [
            'label' => 'Minecon',
            'summary' => 'lead_ratio_kelayakan_kerja',
            'detail' => 'detail_lead_ratio_kelayakan_kerja',
            'has_mitra' => true,
        ],
        'subcon' => [
            'label' => 'Subcon',
            'summary' => 'lead_subcont_ratio_kelayakan_kerja',
            'detail' => null,
            'has_mitra' => false,
        ],
    ];

    private const DEFAULT_DATASET = 'minecon';

    /** Calon nama kolom tabel bulanan; yang pertama cocok dipakai. */
    private const KOLOM_BULANAN = [
        'site' => ['site_dedicated', 'Site_Dedicated'],
        'mitra' => ['nama_perusahaan', 'perusahaan'],
        'bulan' => ['month_of_tanggal_pelaksanaan_mcu', 'Month_of_Tanggal_Pelaksanaan_Mcu',
                    'ISO_Month_of_Tanggal_Pelaksanaan_Mcu'],
        'tahun' => ['iso_year_of_tanggal_pelaksanaan_mcu', 'ISO_Year_of_Tanggal_Pelaksanaan_Mcu'],
        'persen' => ['pct_mcu_fit', 'MCU_Fit', 'Persen_MCU_Fit'],
    ];

    /** Kolom tabel rincian. */
    private const COL_SID = 'sid_karyawan';
    private const COL_NAMA = 'nama_karyawan';
    private const COL_HASIL = 'hasil_mcu_fin';

    private const TARGET_PERCENT = 95.0;

    /** Batas baris karyawan yang dikirim ke modal rincian sel. */
    private const BATAS_BARIS_MODAL = 500;

    /**
     * Band penilaian resmi: [batas bawah, batas atas, nilai dasar, label].
     *
     * URUTANNYA TERBAIK DULU, dan itu bukan sekadar gaya: dataQuery()
     * menyaring tab Data lewat SCORE_BANDS[4 - $nilai][0], jadi elemen
     * pertama tiap baris harus tetap batas bawah dan urutannya tidak
     * boleh dibalik.
     *
     * NILAINYA BERKOMA: di dalam satu band nilai melandai mengikuti posisi
     * capaian di antara kedua batasnya, jadi angkanya tidak meloncat.
     */
    private const SCORE_BANDS = [
        [95.0, 100.0, 4, '95% - 100%'],
        [90.0, 95.0, 3, '90% - <95%'],
        [85.0, 90.0, 2, '85% - <90%'],
        [0.0, 85.0, 1, '<85%'],
    ];

    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /** Oktober masih berjalan saat data ini diambil, sejalan halaman lain. */
    private const EXCLUDED_MONTHS = [10];

    public function index(): View
    {
        $datasets = [];

        foreach (array_keys(self::DATASETS) as $slug) {
            $kolom = $this->kolomBulanan($slug);

            $datasets[$slug] = [
                'slug' => $slug,
                'label' => self::DATASETS[$slug]['label'],
                'summary_table' => $this->table($slug, 'summary'),
                'detail_table' => self::DATASETS[$slug]['detail'],
                'has_mitra' => self::DATASETS[$slug]['has_mitra'],
                'filterOptions' => [
                    'site' => $this->distinctValues($this->table($slug, 'summary'), $kolom['site']),
                    'mitra' => self::DATASETS[$slug]['has_mitra']
                        ? $this->distinctValues($this->table($slug, 'summary'), $kolom['mitra'])
                        : [],
                ],
                'monthOptions' => $this->monthOptions($slug),
                'dataColumns' => $this->dataColumns($slug),
            ];
        }

        return view('ohs-score-card.rasio-kelayakan-kerja.index', [
            'datasets' => $datasets,
            'target' => self::TARGET_PERCENT,
        ]);
    }

    /**
     * Kolom tab Data, berbeda bentuk antar kumpulan data: minecon menampilkan
     * karyawan, subcon menampilkan baris bulanan.
     *
     * @return array<int, array<string, string>>
     */
    private function dataColumns(string $slug): array
    {
        if (self::DATASETS[$slug]['detail'] === null) {
            return [
                ['key' => 'site', 'label' => 'Site'],
                ['key' => 'bulan', 'label' => 'Bulan'],
                ['key' => 'tahun', 'label' => 'Tahun'],
                ['key' => 'persen', 'label' => 'MCU Fit', 'class' => 'text-end', 'tipe' => 'persen'],
                ['key' => 'nilai', 'label' => 'Nilai', 'class' => 'text-center', 'tipe' => 'nilai'],
            ];
        }

        return [
            ['key' => 'site', 'label' => 'Site'],
            ['key' => 'mitra', 'label' => 'Perusahaan'],
            ['key' => 'sid', 'label' => 'SID'],
            ['key' => 'nama', 'label' => 'Nama Karyawan'],
            ['key' => 'hasil', 'label' => 'Hasil MCU'],
            ['key' => 'status', 'label' => 'Status', 'class' => 'text-center', 'tipe' => 'status'],
        ];
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request, string $dataset = self::DEFAULT_DATASET): JsonResponse
    {
        $dataset = $this->dataset($dataset);
        $kolom = $this->kolomBulanan($dataset);
        $punyaMitra = self::DATASETS[$dataset]['has_mitra'];

        $pilih = $kolom['site'] . ' AS site, ' . $kolom['bulan'] . ' AS bulan, '
            . 'AVG(' . $kolom['persen'] . ') AS persen'
            . ($punyaMitra ? ', ' . $kolom['mitra'] . ' AS mitra' : '');

        $query = $this->summaryQuery($request, $dataset)->selectRaw($pilih)->groupBy('site', 'bulan');

        if ($punyaMitra) {
            $query->groupBy('mitra');
        }

        $grid = [];
        $monthSeen = [];

        foreach ($query->get() as $row) {
            $monthNo = $this->nomorBulan((string) $row->bulan);

            if ($monthNo === 0) {
                continue; // nama bulan tak dikenal: jangan diam-diam dianggap bulan lain
            }

            $monthSeen[$monthNo] = true;
            $site = trim((string) $row->site);
            $mitra = $punyaMitra ? trim((string) $row->mitra) : null;
            $key = $site . '|' . ($mitra ?? '');

            $grid[$key]['site'] = $site;
            $grid[$key]['mitra'] = $mitra;
            // NULL dibiarkan NULL: "belum ada MCU" bukan "tidak ada yang Fit".
            $grid[$key]['bulan'][$monthNo] = $row->persen === null
                ? null
                : round((float) $row->persen, 2);
        }

        ksort($monthSeen);
        ksort($grid);
        $months = array_keys($monthSeen);
        $matrix = $this->buildMatrix($grid, $months, $punyaMitra);

        return response()->json([
            'has_mitra' => $punyaMitra,
            'months' => $this->monthHeadings($months),
            'kpi' => $this->buildKpi($request, $dataset, $matrix, $months),
            'matrix' => $matrix,
            'per_site' => $this->ringkasPer($matrix, 'site'),
            'per_mitra' => $punyaMitra ? $this->ringkasPer($matrix, 'mitra') : [],
            'terendah' => $this->buildTerendah($matrix, $punyaMitra),
            'hasil_mcu' => $this->buildHasilMcu($request, $dataset),
            'monthly' => $this->buildMonthlySeries($matrix, $months, $punyaMitra),
            'catatan' => $this->catatan($request, $dataset, $matrix),
        ]);
    }

    /**
     * @param  array<string, mixed>  $grid
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    private function buildMatrix(array $grid, array $months, bool $punyaMitra): array
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

        return $punyaMitra ? $this->kelompokkanPerSite($out) : $this->urutkanTerendah($out);
    }

    /**
     * Mengelompokkan baris per site supaya sel site-nya bisa digabung dengan
     * rowspan; site dengan capaian terendah di atas, begitu pula di dalamnya.
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
            $out = array_merge($out, $this->urutkanTerendah($perSite[$site]));
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function urutkanTerendah(array $rows): array
    {
        usort(
            $rows,
            static fn (array $a, array $b): int => ($a['average'] ?? 101.0) <=> ($b['average'] ?? 101.0)
        );

        return $rows;
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
     * @return array<string, mixed>
     */
    private function buildKpi(Request $request, string $dataset, array $matrix, array $months): array
    {
        $nilai = array_values(array_filter(
            array_column($matrix, 'average'),
            static fn (?float $v): bool => $v !== null
        ));

        $rataBulanan = $nilai !== [] ? round(array_sum($nilai) / count($nilai), 2) : null;

        // Angka utama diambil dari rincian bila ada, karena berbobot jumlah
        // karyawan; lihat catatan di docblock kelas.
        $rincian = $this->cacahRincian($request, $dataset);
        $rata = $rincian['total'] > 0
            ? round($rincian['fit'] / $rincian['total'] * 100, 2)
            : $rataBulanan;

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
            'rata_bulanan' => $rataBulanan,
            'dari_rincian' => $rincian['total'] > 0,
            'karyawan' => $rincian['total'],
            'fit' => $rincian['fit'],
            'unfit' => $rincian['total'] - $rincian['fit'],
            'nilai' => $rata === null ? null : $band,
            'nilai_band' => $rata === null ? null : $bandLabel,
            'target' => self::TARGET_PERCENT,
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
            'mitra_count' => count(array_unique(array_filter(array_column($matrix, 'mitra')))),
            'bulan_count' => count($months),
            'sel_terisi' => $selTerisi,
            'sel_kosong' => $selKosong,
        ];
    }

    /**
     * Cacah karyawan & yang Fit dari tabel rincian.
     *
     * @return array{total: int, fit: int}
     */
    private function cacahRincian(Request $request, string $dataset): array
    {
        $query = $this->detailQuery($request, $dataset);

        if ($query === null) {
            return ['total' => 0, 'fit' => 0];
        }

        $kolom = $this->kolomBulanan($dataset);
        $row = $query->selectRaw(
            'COUNT(*) AS total, SUM(' . $kolom['persen'] . ' > 0) AS fit'
        )->first();

        return ['total' => (int) ($row->total ?? 0), 'fit' => (int) ($row->fit ?? 0)];
    }

    /**
     * Sebaran hasil MCU dari tabel rincian.
     *
     * @return array<string, mixed>
     */
    private function buildHasilMcu(Request $request, string $dataset): array
    {
        $query = $this->detailQuery($request, $dataset);

        if ($query === null) {
            return ['tersedia' => false, 'rows' => []];
        }

        $rows = $query
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(" . self::COL_HASIL . "), ''), '(Tidak Diisi)') AS label, "
                . 'COUNT(*) AS jumlah, '
                . 'SUM(' . $this->kolomBulanan($dataset)['persen'] . ' > 0) AS fit'
            )
            ->groupBy('label')
            ->orderByDesc('jumlah')
            ->get();

        $grand = $rows->sum('jumlah');

        return [
            'tersedia' => $rows->isNotEmpty(),
            'rows' => $rows->map(static fn (object $r): array => [
                'label' => (string) $r->label,
                'jumlah' => (int) $r->jumlah,
                'fit' => (int) $r->fit > 0,
                'percent' => $grand > 0 ? round((int) $r->jumlah / $grand * 100, 2) : 0.0,
            ])->all(),
        ];
    }

    /**
     * Rata-rata per site atau per perusahaan, dari angka bulanan.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function ringkasPer(array $matrix, string $key): array
    {
        $kelompok = [];

        foreach ($matrix as $row) {
            if ($row['average'] === null || $row[$key] === null) {
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
     * Lima capaian terendah.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function buildTerendah(array $matrix, bool $punyaMitra): array
    {
        $rows = array_values(array_filter($matrix, static fn (array $r): bool => $r['average'] !== null));
        usort($rows, static fn (array $a, array $b): int => $a['average'] <=> $b['average']);

        return array_map(static fn (array $r): array => [
            'site' => $r['site'],
            'mitra' => $punyaMitra ? $r['mitra'] : null,
            'percent' => $r['average'],
            'nilai' => $r['nilai'],
            'terendah' => $r['terendah'],
        ], array_slice($rows, 0, 5));
    }

    /**
     * Satu garis per perusahaan (minecon) atau per site (subcon).
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $matrix, array $months, bool $punyaMitra): array
    {
        $kunci = $punyaMitra ? 'mitra' : 'site';
        $kelompok = [];

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $i => $cell) {
                if ($cell === null) {
                    continue;
                }

                $kelompok[$row[$kunci]][$i][] = $cell['pct'];
            }
        }

        ksort($kelompok);
        $series = [];

        foreach ($kelompok as $nama => $perBulan) {
            $data = [];

            foreach (array_keys($months) as $i) {
                $nilai = $perBulan[$i] ?? null;
                // null, bukan 0: bulan tanpa data harus putus di grafik.
                $data[] = $nilai === null ? null : round(array_sum($nilai) / count($nilai), 2);
            }

            $series[] = ['name' => (string) $nama, 'data' => $data];
        }

        return [
            'labels' => array_map(static fn (int $m): string => self::monthLabel($m), $months),
            'series' => $series,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrix
     */
    private function catatan(Request $request, string $dataset, array $matrix): ?string
    {
        $tabel = $this->table($dataset, 'summary');

        if (! DB::table($tabel)->exists()) {
            return 'Tabel ' . $tabel . ' masih kosong, jadi belum ada yang bisa ditampilkan.';
        }

        $bagian = [];

        $kolom = $this->kolomBulanan($dataset);
        $kosong = (clone $this->summaryQuery($request, $dataset))->whereNull($kolom['persen'])->count();

        if ($kosong > 0) {
            $total = (clone $this->summaryQuery($request, $dataset))->count();
            $bagian[] = $kosong . ' dari ' . $total . ' baris di ' . $tabel . ' belum berisi '
                . 'persentase; sel kosong ditandai strip dan tidak ikut dihitung';
        }

        $rincian = $this->cacahRincian($request, $dataset);

        if ($rincian['total'] > 0) {
            $nilai = array_values(array_filter(
                array_column($matrix, 'average'),
                static fn (?float $v): bool => $v !== null
            ));
            $rataBulanan = $nilai !== [] ? round(array_sum($nilai) / count($nilai), 2) : null;
            $rataRincian = round($rincian['fit'] / $rincian['total'] * 100, 2);

            if ($rataBulanan !== null && abs($rataBulanan - $rataRincian) >= 1.0) {
                $bagian[] = 'Kartu di atas memakai ' . number_format($rataRincian, 2) . '% dari '
                    . number_format($rincian['total']) . ' karyawan di '
                    . self::DATASETS[$dataset]['detail'] . ', sedangkan merata-ratakan angka bulanan '
                    . 'di matriks menghasilkan ' . number_format($rataBulanan, 2) . '%. Keduanya benar: '
                    . 'rata-rata bulanan memberi bobot sama pada bulan berisi sedikit dan banyak '
                    . 'karyawan';
            }

            $bagian[] = 'Panel Hasil MCU dihitung setahun penuh karena tabel rincian tidak punya '
                . 'kolom bulan, jadi tidak ikut berubah saat filter bulan dipakai';
        }

        return $bagian === [] ? null : implode('. ', $bagian) . '.';
    }

    // ======================================================================
    // Tab Data
    // ======================================================================

    public function data(Request $request, string $dataset = self::DEFAULT_DATASET): JsonResponse
    {
        $dataset = $this->dataset($dataset);
        $punyaRincian = self::DATASETS[$dataset]['detail'] !== null;

        $query = $punyaRincian
            ? $this->detailQuery($request, $dataset, true)
            : $this->summaryQuery($request, $dataset);

        $kolom = $this->kolomBulanan($dataset);
        $urut = $punyaRincian ? $kolom['site'] : $kolom['site'];

        $rows = (clone $query)
            ->select($this->selectColumns($dataset))
            ->orderBy($urut)
            ->orderBy('id')
            ->forPage($this->dtPage($request), $this->dtPageLength($request))
            ->get()
            ->map(fn (object $row): array => $this->present($row, $dataset))
            ->all();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $this->baseCount($dataset),
            'recordsFiltered' => (clone $query)->count(),
            'data' => $rows,
        ]);
    }

    public function export(Request $request, string $dataset = self::DEFAULT_DATASET): StreamedResponse
    {
        $dataset = $this->dataset($dataset);
        $punyaRincian = self::DATASETS[$dataset]['detail'] !== null;
        $kolom = $this->kolomBulanan($dataset);

        $query = ($punyaRincian
            ? $this->detailQuery($request, $dataset, true)
            : $this->summaryQuery($request, $dataset))
            ->select($this->selectColumns($dataset))
            ->orderBy($kolom['site'])
            ->orderBy('id');

        $judul = $punyaRincian
            ? ['Site', 'Perusahaan', 'SID', 'Nama Karyawan', 'Hasil MCU', 'Status', 'Tahun']
            : ['Site', 'Bulan', 'Tahun', 'MCU Fit (%)', 'Nilai', 'Keterangan'];

        return $this->dtExport(
            $request,
            $query,
            $judul,
            function (object $row) use ($dataset, $punyaRincian): array {
                $p = $this->present($row, $dataset);

                return $punyaRincian
                    ? [$p['site'], $p['mitra'], $p['sid'], $p['nama'], $p['hasil'], $p['status'], $p['tahun']]
                    : [$p['site'], $p['bulan'], $p['tahun'], $p['persen'] ?? '', $p['nilai'] ?? '', $p['keterangan']];
            },
            'rasio-kelayakan-kerja-' . $dataset
        );
    }

    /** @return array<int, string> */
    private function selectColumns(string $dataset): array
    {
        $kolom = $this->kolomBulanan($dataset);

        if (self::DATASETS[$dataset]['detail'] === null) {
            return [
                $kolom['site'] . ' AS site',
                $kolom['bulan'] . ' AS bulan_sumber',
                $kolom['tahun'] . ' AS tahun',
                $kolom['persen'] . ' AS persen',
            ];
        }

        return [
            $kolom['site'] . ' AS site',
            $kolom['mitra'] . ' AS mitra',
            self::COL_SID . ' AS sid',
            self::COL_NAMA . ' AS nama',
            self::COL_HASIL . ' AS hasil',
            $kolom['tahun'] . ' AS tahun',
            $kolom['persen'] . ' AS persen',
        ];
    }

    /**
     * Satu baris dalam bentuk siap tampil.
     *
     * @return array<string, mixed>
     */
    private function present(object $row, string $dataset): array
    {
        $teks = static fn ($v): string => trim((string) $v);
        $persen = $row->persen === null ? null : round((float) $row->persen, 2);

        if (self::DATASETS[$dataset]['detail'] !== null) {
            return [
                'site' => $teks($row->site),
                'mitra' => $teks($row->mitra),
                'sid' => $teks($row->sid),
                'nama' => $teks($row->nama),
                'hasil' => $teks($row->hasil) === '' ? '(Tidak Diisi)' : $teks($row->hasil),
                'tahun' => (string) $row->tahun,
                // Status diturunkan dari persentase, bukan dari teks hasilnya:
                // "fit with note with follow up" tetap terhitung Fit.
                'status' => $persen !== null && $persen > 0 ? 'Fit' : 'Tidak Fit',
            ];
        }

        [, $nilai, $band] = $this->scoreBandFor($persen ?? 0.0);
        $monthNo = $this->nomorBulan((string) $row->bulan_sumber);

        return [
            'site' => $teks($row->site),
            'bulan' => $monthNo === 0 ? $teks($row->bulan_sumber) : self::monthLabel($monthNo),
            'tahun' => (string) $row->tahun,
            'persen' => $persen,
            'nilai' => $persen === null ? null : $nilai,
            'nilai_band' => $persen === null ? null : $band,
            'memenuhi_target' => $persen !== null && $persen >= self::TARGET_PERCENT,
            'keterangan' => $persen === null
                ? 'Belum ada data'
                : ($persen >= self::TARGET_PERCENT ? 'Memenuhi target' : 'Di bawah target'),
        ];
    }

    // ======================================================================
    // Modal rincian satu sel "Capaian per Bulan"
    // ======================================================================

    /**
     * Rincian satu sel matriks bulanan.
     *
     * DUA ARKETIPE DALAM SATU ENDPOINT, karena dua kumpulan data halaman ini
     * bentuk sumbernya berbeda: minecon punya tabel rincian sehingga selnya
     * bisa dipecah jadi daftar karyawan, subcon tidak punya sehingga modalnya
     * hanya bisa menyajikan konteks di sekeliling sel. Yang membedakan cuma
     * ada/tidaknya DATASETS[...]['detail'], jadi kedua bentuk itu dilayani
     * jalur yang sama dan bedanya dinyatakan lewat 'punya_rincian'.
     *
     * TIGA HAL YANG MEMANG TIDAK BISA DITURUNKAN DARI SUMBER. Ketiganya
     * dikatakan apa adanya ke modal, bukan dikarang:
     *
     * 1. Tabel bulanan hanya menyimpan persentasenya, bukan pembilang dan
     *    penyebutnya. "95,92% di Juli" karena itu tidak bisa dipecah jadi
     *    "47 dari 49 karyawan", dan modal tidak menampilkan penyebut untuk sel.
     * 2. Tabel rincian tidak punya kolom bulan, hanya tahun. Daftar karyawan
     *    MUSTAHIL disaring ke bulan yang diklik; ia dikirim sebagai angka
     *    setahun penuh, ditandai 'setahun' => true, dan panelnya menyebut itu
     *    terus terang — sejalan dengan panel Hasil MCU di tab Ringkasan yang
     *    sudah memakai pendekatan yang sama.
     * 3. Persentase bulanan dan persentase dari rincian bukan ukuran yang
     *    sama. BMO 2 / PT Pamapersada Nusantara: 67,89% bila delapan angka
     *    bulanannya dirata-ratakan, 95,86% bila dihitung dari 1.593
     *    karyawannya, karena rata-rata antar bulan memberi bobot sama pada
     *    bulan berisi 2 orang dan bulan berisi 500 orang. Selisih itu dikirim
     *    sebagai muatan tersendiri supaya modal wajib menerangkannya, bukan
     *    menaruh kedua angka berdampingan tanpa keterangan.
     */
    public function detailBulan(Request $request, string $dataset = self::DEFAULT_DATASET): JsonResponse
    {
        $dataset = $this->dataset($dataset);
        $punyaMitra = self::DATASETS[$dataset]['has_mitra'];
        $kolom = $this->kolomBulanan($dataset);

        $site = trim((string) $request->input('site', ''));
        $mitra = $punyaMitra ? trim((string) $request->input('mitra', '')) : '';

        // monthHeadings() halaman ini mengirim nomor 1-12 karena sumbernya
        // cuma bernama bulan. Kode tahun*100+bulan tetap diterima supaya
        // tautan dari halaman bertahun tidak patah.
        $kode = (int) $request->input('month', 0);
        $bulan = $kode > 9999 ? $kode % 100 : $kode;
        $tahun = $kode > 9999 ? intdiv($kode, 100) : null;

        if ($site === '' || $bulan < 1 || $bulan > 12) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Site dan bulan wajib diisi untuk membuka rincian.',
            ]);
        }

        if ($punyaMitra && $mitra === '') {
            return response()->json([
                'ok' => false,
                'pesan' => 'Perusahaan wajib diisi: satu baris matriks minecon adalah '
                    . 'gabungan site dan perusahaan, bukan site saja.',
            ]);
        }

        // Dihormati persis seperti overview(): Oktober tidak pernah muncul di
        // matriks, jadi tidak boleh bisa dibuka lewat endpoint ini juga.
        if (in_array($bulan, self::EXCLUDED_MONTHS, true)) {
            return response()->json([
                'ok' => false,
                'pesan' => self::monthLabel($bulan) . ' tidak ditampilkan di halaman ini '
                    . 'karena bulannya masih berjalan saat data diambil.',
            ]);
        }

        // AVG yang sama persis dengan overview(), supaya angka sel dan angka
        // modal tidak mungkin berbeda.
        $sel = $this->agregatPersen(
            DB::table($this->table($dataset, 'summary'))
                ->where($kolom['site'], $site)
                ->whereIn($kolom['bulan'], $this->namaBulan($bulan))
                ->when($tahun !== null, fn (Builder $q): Builder => $q->where($kolom['tahun'], $tahun))
                ->when($punyaMitra, fn (Builder $q): Builder => $q->where($kolom['mitra'], $mitra)),
            $kolom['persen']
        );

        $riwayat = $this->riwayatBaris($dataset, $site, $mitra, $tahun);
        $baris = $this->rataBaris($riwayat);
        $rincian = $this->rincianKaryawan($dataset, $site, $mitra);

        return response()->json([
            'ok' => true,
            'dataset' => $dataset,
            'punya_rincian' => $rincian !== null,
            'punya_mitra' => $punyaMitra,
            'judul' => [
                'dataset' => self::DATASETS[$dataset]['label'],
                'site' => $site,
                'mitra' => $punyaMitra ? $mitra : null,
                'bulan' => self::monthLabel($bulan),
                'tahun' => $tahun,
            ],
            'target' => self::TARGET_PERCENT,
            'sel' => $sel,
            'baris' => $baris,
            'riwayat' => $this->tandaiBulan($riwayat, $bulan),
            // Minecon: perusahaan lain di site yang sama. Subcon tidak punya
            // dimensi perusahaan, jadi tetangga terdekatnya adalah site lain.
            'tetangga' => $punyaMitra
                ? $this->tandaiLabel(
                    $this->tetanggaBulan($dataset, $bulan, $tahun, 'mitra', $site),
                    $mitra
                )
                : $this->tandaiLabel(
                    $this->tetanggaBulan($dataset, $bulan, $tahun, 'site'),
                    $site
                ),
            // Sumbu ketiga, hanya bermakna untuk minecon: perusahaan yang sama
            // di site lain pada bulan ini -- masalah site atau masalah mitra?
            'lintas_site' => $punyaMitra
                ? $this->tandaiLabel(
                    $this->tetanggaBulan($dataset, $bulan, $tahun, 'site', null, $mitra),
                    $site
                )
                : [],
            'rincian' => $rincian,
            'selisih' => $this->selisihUkuran($baris, $rincian),
            'catatan' => $this->catatanModal($dataset, $sel, $rincian),
        ]);
    }

    /**
     * Persentase sekumpulan baris bulanan, dengan AVG yang sama seperti
     * overview(). 'baris' ikut dikirim karena nol baris berarti sel itu tidak
     * ada di tabel, bukan nol persen.
     *
     * @return array<string, mixed>
     */
    private function agregatPersen(Builder $query, string $kolomPersen): array
    {
        $row = $query
            ->selectRaw('AVG(' . $kolomPersen . ') AS persen, COUNT(*) AS baris')
            ->first();

        $persen = ($row->persen ?? null) === null ? null : round((float) $row->persen, 2);
        [, $nilai, $band] = $this->scoreBandFor($persen ?? 0.0);

        return [
            'persen' => $persen,
            'baris' => (int) ($row->baris ?? 0),
            'nilai' => $persen === null ? null : $nilai,
            'nilai_band' => $persen === null ? null : $band,
            'memenuhi_target' => $persen !== null && $persen >= self::TARGET_PERCENT,
        ];
    }

    /**
     * Baris matriks yang sama sepanjang bulan: capaian sel ini kronis atau
     * sesaat?
     *
     * Tiap bulan diagregasi lalu dibulatkan dua angka sebelum dipakai, persis
     * seperti buildMatrix(), supaya rata-ratanya sama dengan kolom RATA.
     *
     * @return array<int, array<string, mixed>>
     */
    private function riwayatBaris(string $dataset, string $site, string $mitra, ?int $tahun): array
    {
        $kolom = $this->kolomBulanan($dataset);

        $rows = DB::table($this->table($dataset, 'summary'))
            ->where($kolom['site'], $site)
            ->when(
                self::DATASETS[$dataset]['has_mitra'],
                fn (Builder $q): Builder => $q->where($kolom['mitra'], $mitra)
            )
            ->when($tahun !== null, fn (Builder $q): Builder => $q->where($kolom['tahun'], $tahun))
            ->selectRaw($kolom['bulan'] . ' AS bulan, AVG(' . $kolom['persen'] . ') AS persen')
            ->groupBy('bulan')
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $nomor = $this->nomorBulan((string) $r->bulan);

            if ($nomor === 0 || in_array($nomor, self::EXCLUDED_MONTHS, true)) {
                continue; // bulan tak dikenal atau sengaja dikecualikan
            }

            $persen = $r->persen === null ? null : round((float) $r->persen, 2);
            [, $nilai, $band] = $this->scoreBandFor($persen ?? 0.0);

            $out[] = [
                'nomor' => $nomor,
                'bulan' => self::monthLabel($nomor),
                'persen' => $persen,
                'nilai' => $persen === null ? null : $nilai,
                'nilai_band' => $persen === null ? null : $band,
                'memenuhi_target' => $persen !== null && $persen >= self::TARGET_PERCENT,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['nomor'] <=> $b['nomor']);

        return $out;
    }

    /**
     * Rata-rata baris dari riwayatnya, sehingga angkanya dijamin sama dengan
     * kolom RATA di matriks tanpa query tambahan.
     *
     * @param  array<int, array<string, mixed>>  $riwayat
     * @return array<string, mixed>
     */
    private function rataBaris(array $riwayat): array
    {
        $nilai = array_values(array_filter(
            array_column($riwayat, 'persen'),
            static fn (?float $v): bool => $v !== null
        ));

        $rata = $nilai !== [] ? round(array_sum($nilai) / count($nilai), 2) : null;
        [, $band, $bandLabel] = $this->scoreBandFor($rata ?? 0.0);

        return [
            'persen' => $rata,
            'nilai' => $rata === null ? null : $band,
            'nilai_band' => $rata === null ? null : $bandLabel,
            'bulan_terisi' => count($nilai),
            'terendah' => $nilai !== [] ? min($nilai) : null,
            'tertinggi' => $nilai !== [] ? max($nilai) : null,
            'memenuhi_target' => $rata !== null && $rata >= self::TARGET_PERCENT,
        ];
    }

    /**
     * Baris lain pada bulan yang sama, dikelompokkan menurut $peran ('mitra'
     * atau 'site').
     *
     * Satu helper untuk tiga panel: perusahaan lain di site ini, site lain di
     * bulan ini, dan perusahaan yang sama di site lain. Filter halaman sengaja
     * tidak diteruskan ke sini — gunanya justru memperlihatkan sekeliling sel,
     * yang akan hilang kalau ikut dipersempit.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tetanggaBulan(
        string $dataset,
        int $bulan,
        ?int $tahun,
        string $peran,
        ?string $site = null,
        ?string $mitra = null
    ): array {
        $kolom = $this->kolomBulanan($dataset);

        $rows = DB::table($this->table($dataset, 'summary'))
            ->whereIn($kolom['bulan'], $this->namaBulan($bulan))
            ->when($tahun !== null, fn (Builder $q): Builder => $q->where($kolom['tahun'], $tahun))
            ->when($site !== null, fn (Builder $q): Builder => $q->where($kolom['site'], $site))
            ->when($mitra !== null, fn (Builder $q): Builder => $q->where($kolom['mitra'], $mitra))
            ->selectRaw($kolom[$peran] . ' AS label, AVG(' . $kolom['persen'] . ') AS persen')
            ->groupBy('label')
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $persen = $r->persen === null ? null : round((float) $r->persen, 2);
            [, $nilai, $band] = $this->scoreBandFor($persen ?? 0.0);

            $out[] = [
                'label' => trim((string) $r->label),
                'persen' => $persen,
                'nilai' => $persen === null ? null : $nilai,
                'nilai_band' => $persen === null ? null : $band,
                'memenuhi_target' => $persen !== null && $persen >= self::TARGET_PERCENT,
            ];
        }

        // Terendah di atas: yang perlu perhatian lebih dulu terlihat.
        usort($out, static fn (array $a, array $b): int => ($a['persen'] ?? 101.0) <=> ($b['persen'] ?? 101.0));

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function tandaiBulan(array $rows, int $bulan): array
    {
        return array_map(static function (array $row) use ($bulan): array {
            $row['ini'] = $row['nomor'] === $bulan;

            return $row;
        }, $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function tandaiLabel(array $rows, string $label): array
    {
        return array_map(static function (array $row) use ($label): array {
            $row['ini'] = $row['label'] === $label;

            return $row;
        }, $rows);
    }

    /**
     * Karyawan di balik baris ini, dari tabel rincian. null bila kumpulan data
     * ini memang tidak punya tabel rincian (subcon).
     *
     * SETAHUN PENUH, BUKAN BULAN YANG DIKLIK. Tabel rincian tidak punya kolom
     * bulan, jadi penyaringan ke bulan mustahil. Menyajikannya seolah tersaring
     * akan membuat modal berbohong; yang dilakukan justru menandainya dengan
     * 'setahun' => true dan mengirim daftar tahun yang tercakup, supaya modal
     * bisa mengatakannya.
     *
     * @return array<string, mixed>|null
     */
    private function rincianKaryawan(string $dataset, string $site, string $mitra): ?array
    {
        $tabel = self::DATASETS[$dataset]['detail'];

        if ($tabel === null || ! Schema::hasTable($tabel)) {
            return null;
        }

        $kolom = $this->kolomBulanan($dataset);

        $dasar = fn (): Builder => DB::table($tabel)
            ->where($kolom['site'], $site)
            ->where($kolom['mitra'], $mitra);

        $cacah = $dasar()
            ->selectRaw('COUNT(*) AS total, SUM(' . $kolom['persen'] . ' > 0) AS fit')
            ->first();

        $total = (int) ($cacah->total ?? 0);
        $fit = (int) ($cacah->fit ?? 0);
        $persen = $total > 0 ? round($fit / $total * 100, 2) : null;
        [, $nilai, $band] = $this->scoreBandFor($persen ?? 0.0);

        $baris = $dasar()
            ->select($this->selectColumns($dataset))
            ->orderBy($kolom['persen'])
            ->orderBy(self::COL_NAMA)
            ->limit(self::BATAS_BARIS_MODAL + 1)
            ->get()
            ->map(fn (object $row): array => $this->present($row, $dataset))
            ->all();

        $terpotong = count($baris) > self::BATAS_BARIS_MODAL;

        if ($terpotong) {
            $baris = array_slice($baris, 0, self::BATAS_BARIS_MODAL);
        }

        return [
            'tabel' => $tabel,
            'setahun' => true,
            'tahun' => $dasar()
                ->distinct()
                ->orderBy($kolom['tahun'])
                ->pluck($kolom['tahun'])
                ->map(static fn ($v): int => (int) $v)
                ->all(),
            'total' => $total,
            'fit' => $fit,
            'unfit' => $total - $fit,
            'persen' => $persen,
            'nilai' => $persen === null ? null : $nilai,
            'nilai_band' => $persen === null ? null : $band,
            'hasil' => $this->sebaranHasil($dasar(), $kolom['persen'], $total),
            'baris' => $baris,
            'terpotong' => $terpotong,
            'batas' => self::BATAS_BARIS_MODAL,
        ];
    }

    /**
     * Sebaran hasil_mcu_fin untuk baris ini. Fit/tidaknya diturunkan dari
     * persentase, bukan dari teks hasilnya, persis seperti present():
     * "fit with note with follow up" tetap terhitung Fit.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sebaranHasil(Builder $query, string $kolomPersen, int $total): array
    {
        return $query
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(" . self::COL_HASIL . "), ''), '(Tidak Diisi)') AS label, "
                . 'COUNT(*) AS jumlah, '
                . 'SUM(' . $kolomPersen . ' > 0) AS fit'
            )
            ->groupBy('label')
            ->orderByDesc('jumlah')
            ->get()
            ->map(static fn (object $r): array => [
                'label' => (string) $r->label,
                'jumlah' => (int) $r->jumlah,
                'fit' => (int) $r->fit > 0,
                'percent' => $total > 0 ? round((int) $r->jumlah / $total * 100, 2) : 0.0,
            ])
            ->all();
    }

    /**
     * Dua angka yang sama-sama benar tetapi berbeda ukurannya, beserta
     * sebabnya. Dikirim terpisah supaya modal tidak boleh menaruh keduanya
     * berdampingan tanpa keterangan; lihat docblock detailBulan().
     *
     * @param  array<string, mixed>  $baris
     * @param  array<string, mixed>|null  $rincian
     * @return array<string, mixed>|null
     */
    private function selisihUkuran(array $baris, ?array $rincian): ?array
    {
        if ($rincian === null || $rincian['persen'] === null || $baris['persen'] === null) {
            return null;
        }

        $delta = round($rincian['persen'] - $baris['persen'], 2);

        return [
            'bulanan' => $baris['persen'],
            'bulan_terisi' => $baris['bulan_terisi'],
            'rincian' => $rincian['persen'],
            'karyawan' => $rincian['total'],
            'delta' => $delta,
            // Di bawah satu poin keduanya praktis sama; menerangkan selisih
            // sebesar itu justru membingungkan.
            'besar' => abs($delta) >= 1.0,
        ];
    }

    /**
     * Kalimat kejujuran yang bergantung pada data, dikirim ke modal supaya
     * tidak ada angka yang berdiri tanpa keterangan.
     *
     * @param  array<string, mixed>  $sel
     * @param  array<string, mixed>|null  $rincian
     * @return array<int, string>
     */
    private function catatanModal(string $dataset, array $sel, ?array $rincian): array
    {
        $out = [];

        if ($sel['baris'] === 0) {
            $out[] = 'Kombinasi ini tidak punya baris di ' . $this->table($dataset, 'summary')
                . ' untuk bulan tersebut.';
        }

        // Penyebutnya memang tidak tersimpan; lebih baik dikatakan daripada
        // pembaca mengira angkanya bisa ditelusuri ke jumlah orang.
        $out[] = 'Tabel ' . $this->table($dataset, 'summary') . ' hanya menyimpan persentasenya, '
            . 'bukan berapa karyawan yang diperiksa di bulan itu, jadi persentase sel tidak bisa '
            . 'dipecah menjadi jumlah orang.';

        if ($rincian === null) {
            $out[] = 'Kumpulan data ' . self::DATASETS[$dataset]['label'] . ' belum punya tabel '
                . 'rincian, jadi daftar karyawan di balik angka ini memang tidak tersedia — bukan '
                . 'kosong karena filter.';

            return $out;
        }

        $out[] = 'Daftar karyawan diambil dari ' . $rincian['tabel'] . ', yang tidak punya kolom '
            . 'bulan. Isinya setahun penuh dan sama untuk bulan mana pun di baris ini.';

        return $out;
    }

    // ======================================================================
    // Query
    // ======================================================================

    /** Tabel bulanan dengan filter terpasang. */
    private function summaryQuery(Request $request, string $dataset): Builder
    {
        $kolom = $this->kolomBulanan($dataset);
        $query = DB::table($this->table($dataset, 'summary'));

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn($kolom['bulan'], $this->namaBulan($nomor));
        }

        $this->filterDimensi($query, $request, $dataset, true);

        $month = (int) $request->input('month', 0);

        if ($month >= 1 && $month <= 12) {
            $query->whereIn($kolom['bulan'], $this->namaBulan($month));
        }

        return $query;
    }

    /**
     * Tabel rincian dengan filter terpasang, atau null bila populasi ini belum
     * punya tabel rincian.
     *
     * Filter bulan sengaja tidak diteruskan: tabel rincian tidak punya kolom
     * bulan, hanya tahun.
     */
    private function detailQuery(Request $request, string $dataset, bool $denganCari = false): ?Builder
    {
        $tabel = self::DATASETS[$dataset]['detail'];

        if ($tabel === null || ! Schema::hasTable($tabel)) {
            return null;
        }

        $query = DB::table($tabel);
        $this->filterDimensi($query, $request, $dataset, false);

        if ($denganCari) {
            $kolom = $this->kolomBulanan($dataset);
            $this->dtApplySearch(
                $query,
                (string) $request->input('search.value', $request->input('search', '')),
                [$kolom['site'], $kolom['mitra'], self::COL_SID, self::COL_NAMA, self::COL_HASIL]
            );
        }

        return $query;
    }

    private function filterDimensi(Builder $query, Request $request, string $dataset, bool $bulanan): void
    {
        $kolom = $this->kolomBulanan($dataset);

        $site = trim((string) $request->input('site', ''));

        if ($site !== '') {
            $query->where($kolom['site'], $site);
        }

        if (! self::DATASETS[$dataset]['has_mitra']) {
            return;
        }

        $mitra = trim((string) $request->input('mitra', ''));

        if ($mitra !== '') {
            $query->where($kolom['mitra'], $mitra);
        }
    }

    // ======================================================================
    // Utilitas
    // ======================================================================

    private function dataset(string $slug): string
    {
        return isset(self::DATASETS[$slug]) ? $slug : self::DEFAULT_DATASET;
    }

    private function table(string $dataset, string $kind): string
    {
        return (string) self::DATASETS[$this->dataset($dataset)][$kind];
    }

    /**
     * Nama kolom tabel bulanan menurut skemanya sendiri.
     *
     * @return array<string, string>
     */
    private function kolomBulanan(string $dataset): array
    {
        static $peta = [];

        if (isset($peta[$dataset])) {
            return $peta[$dataset];
        }

        $ada = [];

        foreach (Schema::getColumnListing($this->table($dataset, 'summary')) as $column) {
            $ada[mb_strtolower($column)] = $column;
        }

        $out = [];

        foreach (self::KOLOM_BULANAN as $peran => $calon) {
            $out[$peran] = $calon[0];

            foreach ($calon as $nama) {
                if (isset($ada[mb_strtolower($nama)])) {
                    $out[$peran] = $ada[mb_strtolower($nama)];
                    break;
                }
            }
        }

        return $peta[$dataset] = $out;
    }

    private function baseCount(string $dataset): int
    {
        if (self::DATASETS[$dataset]['detail'] !== null) {
            return DB::table(self::DATASETS[$dataset]['detail'])->count();
        }

        $kolom = $this->kolomBulanan($dataset);
        $query = DB::table($this->table($dataset, 'summary'));

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn($kolom['bulan'], $this->namaBulan($nomor));
        }

        return $query->count();
    }

    /**
     * Nomor bulan dari dua bentuk penulisan: nama bulan Inggris maupun
     * "M01".."M12". 0 bila tidak dikenali.
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

    /** @return array<int, string> */
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

    /** @return array<int, string> */
    private function monthOptions(string $dataset): array
    {
        $months = [];
        $kolom = $this->kolomBulanan($dataset);

        foreach ($this->distinctValues($this->table($dataset, 'summary'), $kolom['bulan']) as $nilai) {
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
        foreach (self::SCORE_BANDS as [$bawah, $atas, $dasar, $label]) {
            if ($percent < $bawah) {
                continue;
            }

            if ($dasar >= 4) {
                return [$bawah, 4.0, $label];
            }

            $rentang = $atas - $bawah;
            $nilai = $rentang > 0
                ? $dasar + ($percent - $bawah) / $rentang
                : (float) $dasar;

            // Tidak boleh menyentuh angka band berikutnya, supaya angka dan
            // label band di layar tidak pernah bertentangan.
            $nilai = min($nilai, $dasar + 0.99);

            return [$bawah, round(max(1.0, min(4.0, $nilai)), 2), $label];
        }

        return [0.0, 1.0, '<85%'];
    }
}
