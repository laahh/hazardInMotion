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
 * Parameter wellbeing "Rasio Kelayakan Kerja".
 *
 * Ukurannya persentase pekerja yang hasil MCU-nya Fit, per site per bulan.
 * Makin tinggi makin baik.
 *
 * SUMBERNYA DIPILIH SAAT JALAN. Parameter ini punya beberapa tabel dengan isi
 * yang sama tetapi bentuk berbeda: yang berakhiran _month masih berbentuk hasil
 * scrape Tableau (Site_Dedicated, MCU_Fit), sedangkan yang tanpa akhiran itu
 * sudah dirapikan (site_dedicated, pct_mcu_fit). Saat tulisan ini dibuat yang
 * terisi justru yang sudah dirapikan, dan pola serupa pernah berbalik di
 * parameter Blindspot TBC. Karena itu tabel dan nama kolomnya tidak ditulis
 * mati: sumberQuery() memakai tabel pertama yang ada isinya, dan kolomnya
 * dipetakan dari skema lewat petaKolom().
 *
 * BELUM ADA DIMENSI PERUSAHAAN. Sumber subcon hanya menyimpan site, jadi
 * matriksnya site x bulan. Sumber minecon (lead_ratio_kelayakan_kerja_month)
 * punya nama_perusahaan tetapi masih kosong; begitu terisi, tinggal
 * ditambahkan ke SUMBER dan dimensinya menyusul.
 *
 * SKALA ANGKA dideteksi sekali per permintaan: tabel yang sudah dirapikan
 * menyimpan persen (0-100), sedangkan hasil scrape Tableau untuk parameter
 * sejenis menyimpan pecahan (0-1). Lihat skalaPersen().
 */
final class RasioKelayakanKerjaController extends Controller
{
    use ServesDataTable;

    /**
     * Calon tabel sumber, diurutkan dari yang paling diutamakan.
     *
     * @var array<int, string>
     */
    private const SUMBER = [
        'lead_subcont_ratio_kelayakan_kerja_month',
        'lead_subcont_ratio_kelayakan_kerja',
    ];

    /**
     * Calon nama kolom untuk tiap peran.
     *
     * @var array<string, array<int, string>>
     */
    private const KOLOM = [
        'site' => ['site_dedicated', 'Site_Dedicated'],
        'bulan' => ['month_of_tanggal_pelaksanaan_mcu', 'Month_of_Tanggal_Pelaksanaan_Mcu',
                    'ISO_Month_of_Tanggal_Pelaksanaan_Mcu'],
        'tahun' => ['iso_year_of_tanggal_pelaksanaan_mcu', 'ISO_Year_of_Tanggal_Pelaksanaan_Mcu'],
        'persen' => ['pct_mcu_fit', 'MCU_Fit', 'Persen_MCU_Fit'],
    ];

    private const TARGET_PERCENT = 90.0;

    private const SCORE_BANDS = [
        [98.0, 4, '98% - 100%'],
        [90.0, 3, '90% - <98%'],
        [80.0, 2, '80% - <90%'],
        [0.0,  1, '<80%'],
    ];

    /** Bulan tersimpan sebagai nama Inggris; dipetakan untuk urutan & label. */
    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /**
     * Bulan yang tidak ikut dihitung, sejalan dengan halaman parameter
     * lainnya: Oktober masih berjalan saat data ini diambil.
     */
    private const EXCLUDED_MONTHS = ['October'];

    public function index(): View
    {
        $kolom = $this->petaKolom();

        return view('ohs-score-card.rasio-kelayakan-kerja.index', [
            'filterOptions' => [
                'site' => $this->distinctValues($kolom['site']),
            ],
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->distinctValues($kolom['tahun']),
            'target' => self::TARGET_PERCENT,
            'tabel' => $this->sumber(),
        ]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request): JsonResponse
    {
        $kolom = $this->petaKolom();
        $skala = $this->skalaPersen();

        $rows = $this->baseQuery($request)
            ->selectRaw(
                '`' . $kolom['site'] . '` AS site, '
                . '`' . $kolom['bulan'] . '` AS bulan, '
                . 'AVG(`' . $kolom['persen'] . '`) AS persen'
            )
            ->groupBy('site', 'bulan')
            ->get();

        $grid = [];
        $monthSeen = [];

        foreach ($rows as $row) {
            $monthNo = self::MONTH_MAP[$row->bulan][0] ?? 0;

            if ($monthNo === 0) {
                continue; // nama bulan tak dikenal: jangan diam-diam dianggap bulan lain
            }

            $monthSeen[$monthNo] = true;
            $site = trim((string) $row->site);

            $grid[$site]['site'] = $site;
            // NULL dibiarkan NULL: "belum ada MCU" bukan "tidak ada yang Fit".
            $grid[$site]['bulan'][$monthNo] = $row->persen === null
                ? null
                : round((float) $row->persen * $skala, 2);
        }

        ksort($monthSeen);
        $months = array_keys($monthSeen);
        $matrix = $this->buildMatrix($grid, $months);

        return response()->json([
            'months' => $this->monthHeadings($months),
            'kpi' => $this->buildKpi($matrix, $months),
            'matrix' => $matrix,
            'per_site' => $this->ringkasPerSite($matrix),
            'terendah' => $this->buildTerendah($matrix),
            'monthly' => $this->buildMonthlySeries($matrix, $months),
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

        // Yang paling rendah di atas: itu yang perlu dibaca lebih dulu. Tidak
        // ada pengelompokan rowspan di sini karena tiap baris sudah satu site.
        usort($out, static fn (array $a, array $b): int => ($a['average'] ?? 101.0) <=> ($b['average'] ?? 101.0));

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
     * @return array<string, mixed>
     */
    private function buildKpi(array $matrix, array $months): array
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
            'memenuhi_target' => $rata !== null && $rata >= self::TARGET_PERCENT,
            'tertinggi' => $nilai !== [] ? max($nilai) : null,
            'terendah' => $nilai !== [] ? min($nilai) : null,
            // Penyebutnya hanya site yang punya angka.
            'kombinasi' => count($nilai),
            'kombinasi_kosong' => count($matrix) - count($nilai),
            'memenuhi' => count(array_filter(
                $matrix,
                static fn (array $r): bool => $r['memenuhi_target']
            )),
            'site_count' => count($matrix),
            'bulan_count' => count($months),
            'sel_terisi' => $selTerisi,
            'sel_kosong' => $selKosong,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function ringkasPerSite(array $matrix): array
    {
        $out = [];

        foreach ($matrix as $row) {
            if ($row['average'] === null) {
                continue;
            }

            $out[] = [
                'site' => $row['site'],
                'percent' => $row['average'],
                'nilai' => $row['nilai'],
                'jumlah' => $row['bulan_terisi'],
                'terendah' => $row['terendah'],
                'target' => self::TARGET_PERCENT,
                'memenuhi_target' => $row['memenuhi_target'],
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $out;
    }

    /**
     * Lima site dengan capaian terendah.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function buildTerendah(array $matrix): array
    {
        $rows = array_values(array_filter($matrix, static fn (array $r): bool => $r['average'] !== null));

        return array_map(static fn (array $r): array => [
            'site' => $r['site'],
            'percent' => $r['average'],
            'nilai' => $r['nilai'],
            'terendah' => $r['terendah'],
        ], array_slice($rows, 0, 5));
    }

    /**
     * Satu garis per site.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $matrix, array $months): array
    {
        $series = [];
        $urut = $matrix;
        usort($urut, static fn (array $a, array $b): int => strcmp($a['site'], $b['site']));

        foreach ($urut as $row) {
            $data = [];

            foreach ($row['cells'] as $cell) {
                // null, bukan 0: bulan tanpa data harus putus di grafik.
                $data[] = $cell === null ? null : $cell['pct'];
            }

            $series[] = ['name' => $row['site'], 'data' => $data];
        }

        return [
            'labels' => array_map(static fn (int $m): string => self::monthLabel($m), $months),
            'series' => $series,
        ];
    }

    /**
     * Keterangan ketika sumbernya belum terisi atau masih bolong, supaya
     * matriks berlubang tidak dikira capaiannya nol.
     */
    private function catatan(Request $request): ?string
    {
        $tabel = $this->sumber();
        $kolom = $this->petaKolom();

        if (! DB::table($tabel)->exists()) {
            return 'Tabel ' . $tabel . ' masih kosong, jadi belum ada yang bisa ditampilkan. '
                . 'Panel akan terisi sendiri begitu datanya masuk.';
        }

        $kosong = (clone $this->baseQuery($request))->whereNull($kolom['persen'])->count();

        if ($kosong === 0) {
            return null;
        }

        $total = (clone $this->baseQuery($request))->count();

        return $kosong . ' dari ' . $total . ' baris di ' . $tabel . ' belum berisi persentase. '
            . 'Sel yang kosong ditandai strip, bukan nol, dan tidak ikut dihitung dalam rata-rata.';
    }

    // ======================================================================
    // Tab Data
    // ======================================================================

    public function data(Request $request): JsonResponse
    {
        $query = $this->dataQuery($request);
        $skala = $this->skalaPersen();
        $kolom = $this->petaKolom();

        $rows = (clone $query)
            ->select($this->columns())
            ->orderBy(
                $this->dtOrderColumn($request, $this->orderable(), $kolom['site']),
                $this->dtDirection($request, 'asc')
            )
            ->orderBy('id')
            ->forPage($this->dtPage($request), $this->dtPageLength($request))
            ->get()
            ->map(fn (object $row): array => $this->present($row, $skala))
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
        $skala = $this->skalaPersen();
        $kolom = $this->petaKolom();

        $query = $this->dataQuery($request)
            ->select($this->columns())
            ->orderBy($kolom['site'])
            ->orderBy('id');

        return $this->dtExport(
            $request,
            $query,
            ['Site', 'Tahun', 'Bulan', 'MCU Fit (%)', 'Nilai', 'Keterangan'],
            function (object $row) use ($skala): array {
                $p = $this->present($row, $skala);

                return [
                    $p['site'], $p['tahun'], $p['bulan'],
                    $p['persen'] ?? '', $p['nilai'] ?? '', $p['keterangan'],
                ];
            },
            'rasio-kelayakan-kerja'
        );
    }

    /** @return array<int, string> */
    private function columns(): array
    {
        $kolom = $this->petaKolom();

        // Tanpa backtick: select() sudah mengutip sendiri.
        return [
            $kolom['site'] . ' AS site',
            $kolom['tahun'] . ' AS tahun',
            $kolom['bulan'] . ' AS bulan_sumber',
            $kolom['persen'] . ' AS persen',
        ];
    }

    /** @return array<int, string> */
    private function orderable(): array
    {
        $kolom = $this->petaKolom();

        return [
            0 => $kolom['site'],
            1 => $kolom['tahun'],
            3 => $kolom['persen'],
        ];
    }

    private function dataQuery(Request $request): Builder
    {
        $query = $this->baseQuery($request);
        $kolom = $this->petaKolom();
        $skala = $this->skalaPersen();

        $nilai = (int) $request->input('nilai', 0);

        if ($nilai >= 1 && $nilai <= 4) {
            [$batas] = self::SCORE_BANDS[4 - $nilai];
            $query->whereNotNull($kolom['persen'])
                ->where($kolom['persen'], '>=', $batas / $skala);

            // Band teratas sengaja tanpa batas atas, supaya angka di atas 100
            // tidak lenyap dari semua filter sekaligus.
            if ($nilai < 4) {
                $atas = self::SCORE_BANDS[3 - $nilai][0];
                $query->where($kolom['persen'], '<', $atas / $skala);
            }
        } elseif (trim((string) $request->input('nilai', '')) === 'kosong') {
            $query->whereNull($kolom['persen']);
        }

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            [$kolom['site'], $kolom['bulan']]
        );

        return $query;
    }

    /** Tabel dengan filter dimensi, bulan, dan tahun terpasang. */
    private function baseQuery(Request $request): Builder
    {
        $kolom = $this->petaKolom();
        $query = DB::table($this->sumber());

        if (self::EXCLUDED_MONTHS !== []) {
            $query->whereNotIn($kolom['bulan'], self::EXCLUDED_MONTHS);
        }

        $site = trim((string) $request->input('site', ''));

        if ($site !== '') {
            $query->where($kolom['site'], $site);
        }

        $tahun = trim((string) $request->input('tahun', ''));

        if ($tahun !== '') {
            $query->where($kolom['tahun'], $tahun);
        }

        $month = (int) $request->input('month', 0);

        if ($month >= 1 && $month <= 12) {
            $names = array_keys(array_filter(
                self::MONTH_MAP,
                static fn (array $v): bool => $v[0] === $month
            ));

            $query->whereIn($kolom['bulan'], $names ?: ['__tidak_ada__']);
        }

        return $query;
    }

    /**
     * Satu baris dalam bentuk siap tampil.
     *
     * @return array<string, mixed>
     */
    private function present(object $row, float $skala): array
    {
        $persen = $row->persen === null ? null : round((float) $row->persen * $skala, 2);
        [, $nilai, $band] = $this->scoreBandFor($persen ?? 0.0);

        return [
            'site' => trim((string) $row->site),
            'tahun' => (string) $row->tahun,
            'bulan' => self::MONTH_MAP[$row->bulan_sumber][1] ?? trim((string) $row->bulan_sumber),
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
    // Utilitas
    // ======================================================================

    /**
     * Tabel sumber yang dipakai: calon pertama yang ada isinya, atau calon
     * pertama yang tabelnya ada bila semuanya kosong.
     */
    private function sumber(): string
    {
        static $sumber = null;

        if ($sumber !== null) {
            return $sumber;
        }

        $pertamaAda = null;

        foreach (self::SUMBER as $tabel) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            $pertamaAda ??= $tabel;

            if (DB::table($tabel)->exists()) {
                return $sumber = $tabel;
            }
        }

        return $sumber = $pertamaAda ?? self::SUMBER[0];
    }

    /**
     * Nama kolom menurut skema tabel yang sedang dipakai.
     *
     * @return array<string, string>
     */
    private function petaKolom(): array
    {
        static $peta = null;

        if ($peta !== null) {
            return $peta;
        }

        $ada = [];

        foreach (Schema::getColumnListing($this->sumber()) as $column) {
            $ada[mb_strtolower($column)] = $column;
        }

        $out = [];

        foreach (self::KOLOM as $peran => $calon) {
            $out[$peran] = $calon[0];

            foreach ($calon as $nama) {
                if (isset($ada[mb_strtolower($nama)])) {
                    $out[$peran] = $ada[mb_strtolower($nama)];
                    break;
                }
            }
        }

        return $peta = $out;
    }

    /**
     * Pengali agar nilainya menjadi persen.
     *
     * Tabel yang sudah dirapikan menyimpan persen (0-100), hasil scrape
     * Tableau menyimpan pecahan (0-1). Diperiksa dari nilai tertinggi seluruh
     * tabel, bukan per baris, supaya satu baris bernilai 1% tidak salah dikira
     * pecahan.
     */
    private function skalaPersen(): float
    {
        static $skala = null;

        if ($skala !== null) {
            return $skala;
        }

        $max = DB::table($this->sumber())->max($this->petaKolom()['persen']);

        return $skala = ($max !== null && (float) $max <= 1.0) ? 100.0 : 1.0;
    }

    private function baseCount(): int
    {
        $query = DB::table($this->sumber());

        if (self::EXCLUDED_MONTHS !== []) {
            $query->whereNotIn($this->petaKolom()['bulan'], self::EXCLUDED_MONTHS);
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

    /** @return array<int, string> */
    private function distinctValues(string $column): array
    {
        return DB::table($this->sumber())
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

        foreach ($this->distinctValues($this->petaKolom()['bulan']) as $name) {
            if (in_array($name, self::EXCLUDED_MONTHS, true)) {
                continue;
            }

            if (isset(self::MONTH_MAP[$name])) {
                $months[self::MONTH_MAP[$name][0]] = self::MONTH_MAP[$name][1];
            }
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
