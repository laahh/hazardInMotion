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
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Parameter SOD "Ratio TBC & GR", untuk dua populasi pengawas:
 *
 *   minecon -> lead_ratio_pelapor_tbc          + detail_lead_ratio_pelapor_tbc
 *   subcon  -> lead_subcont_ratio_pelapor_tbc  + detail_lead_subcont_ratio_pelapor_tbc
 *
 * Rasionya = pengawas yang membuat laporan TBC dibagi pengawas yang tercatat
 * hadir lewat RFID. Rekap di sini selalu menjumlahkan pembilang & penyebut
 * lalu membaginya sendiri, bukan merata-ratakan persentase antar baris, supaya
 * perusahaan berisi 2 pengawas tidak berbobot sama dengan yang berisi 150.
 *
 * BARIS OFFSITE. Di tabel detail, baris ber-status_offsite punya
 * countd_pengawas_rfid = 0 tetapi sebagian ber-countd_pengawas_tbc = 1: orang
 * itu memang melapor, hanya saja tidak masuk basis RFID. Laporannya karena itu
 * dikeluarkan dari pembilang (lihat SQL_TBC). Tanpa aturan ini rekap detail
 * meleset dari tabel ringkasan; dengan aturan ini kecocokan minecon naik dari
 * 73 ke 90 dari 105 kombinasi, dan subcon dari 20 ke 52 dari 54.
 *
 * SELISIH YANG TERSISA adalah beda waktu ambil data antar tabel, bukan bug di
 * halaman ini: bulan Oktober dan site HO hanya ada di ringkasan subcon,
 * sedangkan tabel detailnya baru sampai September.
 */
final class RatioTbcGrController extends Controller
{
    use ServesDataTable;

    /**
     * Dua populasi dengan bentuk sumber yang berbeda.
     *
     * Ringkasan minecon menyimpan site x perusahaan x bulan lengkap dengan
     * cacah pengawas, sedangkan ringkasan subcon hanya site x bulan dan hanya
     * berisi persentase. Karena itu matriks subcon dibaca langsung sebagai
     * persen, dan panel yang butuh cacah pengawas mengambil dari tabel detail.
     */
    private const DATASETS = [
        'minecon' => [
            'label' => 'Minecon',
            'summary' => 'lead_ratio_pelapor_tbc',
            'detail' => 'detail_lead_ratio_pelapor_tbc',
            'summary_has_counts' => true,
        ],
        'subcon' => [
            'label' => 'Subcon',
            'summary' => 'lead_subcont_ratio_pelapor_tbc',
            'detail' => 'detail_lead_subcont_ratio_pelapor_tbc',
            'summary_has_counts' => false,
        ],
    ];

    private const DEFAULT_DATASET = 'minecon';

    /**
     * Batas baris yang dikirim ke modal rincian. Sel terpadat berisi 663
     * baris (BMO 2 x Pamapersada), jadi batas ini tidak pernah terpakai pada
     * data sekarang; dipasang supaya sumber yang membengkak tidak diam-diam
     * mengirim puluhan ribu baris ke browser.
     */
    private const BATAS_BARIS_MODAL = 1000;

    /** Nama kolom di sumber panjang-panjang; dipendekkan lewat alias. */
    private const COL_SITE = 'site_dedicated_pelapor_all_karyawan';
    private const COL_PERUSAHAAN = 'perusahaan_pelapor_all_karyawan';
    private const COL_BULAN = 'month_of_date_time';
    private const COL_RFID = 'countd_pengawas_rfid';
    private const COL_TBC = 'countd_pengawas_tbc';
    private const COL_PCT = 'pct_ratio_pelapor_tbc';
    private const COL_SID = 'sid_pelapor_all_karyawan';
    private const COL_NAMA = 'pelapor_all_karyawan';
    private const COL_JAB_FUNGSIONAL = 'jabatan_fungsional_pelapor_all_karyawan';
    private const COL_JAB_STRUKTURAL = 'jabatan_struktural_pelapor_all_karyawan';
    private const COL_OFFSITE = 'status_offsite';

    /** Penyebut & pembilang rasio pada tabel detail. Lihat catatan OFFSITE. */
    private const SQL_RFID = 'SUM(' . self::COL_RFID . ')';
    private const SQL_TBC = 'SUM(CASE WHEN ' . self::COL_RFID . ' > 0 THEN ' . self::COL_TBC . ' ELSE 0 END)';

    private const TARGET_PERCENT = 90.0;

    /** Bulan tersimpan sebagai nama Inggris; dipetakan untuk urutan & label. */
    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /**
     * Bulan yang tidak ikut dihitung di mana pun.
     *
     * Oktober masih berjalan saat data ini diambil, jadi angkanya jauh di
     * bawah bulan penuh (minecon 53,70% berbanding 95,18% di September) dan
     * akan menyeret turun rata-rata, matriks, serta Nilai. Dikecualikan di
     * applyDimensionFilters supaya berlaku untuk tabel ringkasan maupun
     * detail, dan di monthOptions supaya tidak muncul di dropdown.
     */
    private const EXCLUDED_MONTHS = ['October'];

    private const SCORE_BANDS = [
        [98.0, 4, '98% - 100%'],
        [90.0, 3, '90% - <98%'],
        [80.0, 2, '80% - <90%'],
        [0.0,  1, '<80%'],
    ];

    /** Kolom detail yang boleh difilter persis. */
    private const DETAIL_FILTERABLE = [
        'site' => self::COL_SITE,
        'mitra' => self::COL_PERUSAHAAN,
        'jabatan' => self::COL_JAB_FUNGSIONAL,
    ];

    private const DETAIL_SEARCHABLE = [
        self::COL_SID, self::COL_NAMA, self::COL_SITE,
        self::COL_PERUSAHAAN, self::COL_JAB_FUNGSIONAL, self::COL_JAB_STRUKTURAL,
    ];

    private const DETAIL_ORDERABLE = [
        0 => self::COL_SITE,
        1 => self::COL_PERUSAHAAN,
        2 => self::COL_SID,
        3 => self::COL_NAMA,
        4 => self::COL_JAB_FUNGSIONAL,
        5 => self::COL_BULAN,
        6 => self::COL_RFID,
        7 => self::COL_TBC,
    ];

    public function index(): View
    {
        $datasets = [];

        foreach (array_keys(self::DATASETS) as $slug) {
            $detail = $this->table($slug, 'detail');

            $datasets[$slug] = [
                'slug' => $slug,
                'label' => self::DATASETS[$slug]['label'],
                'summary_table' => self::DATASETS[$slug]['summary'],
                'detail_table' => $detail,
                'summary_has_mitra' => self::DATASETS[$slug]['summary_has_counts'],
                'filterOptions' => [
                    'site' => $this->distinctValues($this->table($slug, 'summary'), self::COL_SITE),
                    'mitra' => $this->distinctValues($detail, self::COL_PERUSAHAAN),
                    'jabatan' => $this->distinctValues($detail, self::COL_JAB_FUNGSIONAL),
                ],
                'monthOptions' => $this->monthOptions($slug),
            ];
        }

        return view('ohs-score-card.ratio-tbc-gr.index', ['datasets' => $datasets]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request, string $dataset = self::DEFAULT_DATASET): JsonResponse
    {
        $dataset = $this->dataset($dataset);

        return response()->json(
            self::DATASETS[$dataset]['summary_has_counts']
                ? $this->overviewFromCounts($request, $dataset)
                : $this->overviewFromPercent($request, $dataset)
        );
    }

    /**
     * Ringkasan minecon: tabel ringkasannya menyimpan cacah pengawas per
     * site x perusahaan x bulan, jadi seluruh panel bisa dihitung dari sana.
     *
     * @return array<string, mixed>
     */
    private function overviewFromCounts(Request $request, string $dataset): array
    {
        $rows = $this->summaryQuery($request, $dataset)
            ->select([])
            ->selectRaw(
                self::COL_SITE . ' AS site, '
                . self::COL_PERUSAHAAN . ' AS mitra, '
                . self::COL_BULAN . ' AS bulan, '
                . 'SUM(' . self::COL_RFID . ') AS rfid, '
                . 'SUM(' . self::COL_TBC . ') AS tbc'
            )
            ->groupBy('site', 'mitra', 'bulan')
            ->orderBy('site')
            ->orderBy('mitra')
            ->get();

        $matrix = [];
        $monthSeen = [];
        $perusahaan = [];

        foreach ($rows as $row) {
            $monthNo = self::MONTH_MAP[$row->bulan][0] ?? 0;

            if ($monthNo === 0) {
                continue; // nama bulan tak dikenal: jangan diam-diam dianggap bulan lain
            }

            $monthSeen[$monthNo] = true;
            $site = trim((string) $row->site);
            $mitra = trim((string) $row->mitra);
            $key = $site . '|' . $mitra;

            $matrix[$key]['site'] = $site;
            $matrix[$key]['mitra'] = $mitra;
            $matrix[$key]['bulan'][$monthNo]['total'] = ($matrix[$key]['bulan'][$monthNo]['total'] ?? 0) + (int) $row->rfid;
            $matrix[$key]['bulan'][$monthNo]['standar'] = ($matrix[$key]['bulan'][$monthNo]['standar'] ?? 0) + (int) $row->tbc;

            $perusahaan[$mitra]['total'] = ($perusahaan[$mitra]['total'] ?? 0) + (int) $row->rfid;
            $perusahaan[$mitra]['standar'] = ($perusahaan[$mitra]['standar'] ?? 0) + (int) $row->tbc;
        }

        ksort($monthSeen);
        ksort($matrix);
        $months = array_keys($monthSeen);
        $matrixRows = $this->buildMatrix($matrix, $months);

        return [
            'months' => $this->monthHeadings($months),
            'kpi' => $this->buildKpi($matrixRows),
            'matrix' => $matrixRows,
            'matrix_has_mitra' => true,
            'catatan' => null,
            'perusahaan' => $this->buildPerusahaan($perusahaan),
            'site_vs_target' => $this->buildSiteVsTarget($matrixRows),
            'top_terendah' => $this->buildTopTerendah($matrixRows),
            'pareto' => $this->buildPerJabatan($request, $dataset),
            'per_area' => $this->buildBelumPerPerusahaan($perusahaan),
            'monthly' => $this->buildMonthlySeries($matrix, $months, 'mitra'),
        ];
    }

    /**
     * Ringkasan subcon: tabel ringkasannya hanya site x bulan dan hanya
     * menyimpan persentase, tanpa perusahaan dan tanpa cacah pengawas.
     *
     * Matriks, capaian per site, dan tren bulanan karena itu dibaca langsung
     * dari persentase resmi tersebut, sementara panel yang butuh cacah
     * pengawas (kartu KPI, peringkat perusahaan, jabatan) dihitung dari tabel
     * detail. Keduanya cocok untuk 52 dari 54 pasangan site-bulan yang
     * beririsan, jadi angkanya sejalan walau sumbernya berbeda.
     *
     * @return array<string, mixed>
     */
    private function overviewFromPercent(Request $request, string $dataset): array
    {
        $rows = $this->summaryQuery($request, $dataset)
            ->select([])
            ->selectRaw(
                self::COL_SITE . ' AS site, '
                . self::COL_BULAN . ' AS bulan, '
                . 'AVG(' . self::COL_PCT . ') AS pct'
            )
            ->groupBy('site', 'bulan')
            ->orderBy('site')
            ->get();

        $matrix = [];
        $monthSeen = [];

        foreach ($rows as $row) {
            $monthNo = self::MONTH_MAP[$row->bulan][0] ?? 0;

            if ($monthNo === 0) {
                continue;
            }

            $monthSeen[$monthNo] = true;
            $site = trim((string) $row->site);

            $matrix[$site]['site'] = $site;
            $matrix[$site]['mitra'] = null;
            $matrix[$site]['bulan'][$monthNo]['pct'] = round((float) $row->pct, 2);
        }

        ksort($monthSeen);
        ksort($matrix);
        $months = array_keys($monthSeen);
        $matrixRows = $this->buildMatrixFromPercent($matrix, $months);

        $perusahaan = $this->detailAggregate($request, $dataset, self::COL_PERUSAHAAN);

        return [
            'months' => $this->monthHeadings($months),
            'kpi' => $this->buildKpiFromDetail($request, $dataset),
            'matrix' => $matrixRows,
            'matrix_has_mitra' => false,
            'catatan' => $this->catatanCakupan($request, $dataset, $matrixRows, $months),
            'perusahaan' => $this->buildPerusahaan($perusahaan),
            'site_vs_target' => $this->buildSiteVsTargetFromPercent($matrixRows),
            'top_terendah' => $this->buildTopTerendahFromDetail($request, $dataset),
            'pareto' => $this->buildPerJabatan($request, $dataset),
            'per_area' => $this->buildBelumPerPerusahaan($perusahaan),
            'monthly' => $this->buildMonthlySeries($matrix, $months, 'site'),
        ];
    }

    /**
     * Peringatan ketika tabel detail belum menjangkau apa yang sudah ada di
     * tabel ringkasan.
     *
     * Tanpa ini, memfilter ke site HO atau bulan Oktober membuat kartu KPI dan
     * panel per perusahaan menampilkan nol begitu saja, seolah tidak ada yang
     * melapor, padahal yang terjadi adalah tabel detailnya belum diperbarui.
     *
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @param  array<int, int>  $months
     */
    private function catatanCakupan(Request $request, string $dataset, array $matrixRows, array $months): ?string
    {
        if ($matrixRows === []) {
            return null;
        }

        $ada = $this->detailFilterQuery($request, $dataset)
            ->distinct()
            ->select([self::COL_SITE . ' AS site', self::COL_BULAN . ' AS bulan'])
            ->get();

        $ekor = ' Matriks, capaian per site, dan tren bulanan tetap memakai persentase resmi dari '
            . $this->table($dataset, 'summary') . ', sedangkan kartu di atas, peringkat perusahaan, '
            . 'dan tab Data hanya mencakup periode yang sudah ada di tabel detail.';

        // Tidak ada satu baris pun: memerinci bulan dan site yang hilang cuma
        // mengulang isi filter, jadi cukup dinyatakan sekali.
        if ($ada->isEmpty()) {
            return 'Tabel ' . $this->table($dataset, 'detail')
                . ' belum memuat pilihan ini sama sekali.' . $ekor;
        }

        $siteAda = $ada->map(static fn (object $r): string => trim((string) $r->site))->unique()->all();
        $bulanAda = $ada
            ->map(static fn (object $r): int => self::MONTH_MAP[$r->bulan][0] ?? 0)
            ->unique()
            ->all();

        $siteKurang = array_values(array_diff(array_column($matrixRows, 'site'), $siteAda));
        $bulanKurang = array_values(array_diff($months, $bulanAda));

        if ($siteKurang === [] && $bulanKurang === []) {
            return null;
        }

        $bagian = [];

        if ($bulanKurang !== []) {
            $bagian[] = 'bulan ' . implode(', ', array_map(
                static fn (int $m): string => self::monthLabel($m),
                $bulanKurang
            ));
        }

        if ($siteKurang !== []) {
            $bagian[] = 'site ' . implode(', ', $siteKurang);
        }

        return 'Tabel ' . $this->table($dataset, 'detail') . ' belum memuat '
            . implode(' dan ', $bagian) . '.' . $ekor;
    }

    /** Query ringkasan dengan filter terpasang. */
    private function summaryQuery(Request $request, string $dataset): Builder
    {
        $query = DB::table($this->table($dataset, 'summary'));

        $map = ['site' => self::COL_SITE];

        // Ringkasan subcon tidak punya kolom perusahaan, jadi filter itu
        // memang tidak ditawarkan pada tab Ringkasan-nya.
        if (self::DATASETS[$dataset]['summary_has_counts']) {
            $map['mitra'] = self::COL_PERUSAHAAN;
        }

        $this->applyDimensionFilters($query, $request, $map);

        return $query;
    }

    /**
     * Filter dimensi + bulan. Bulan datang sebagai angka dari dropdown, lalu
     * diterjemahkan balik ke nama Inggris sesuai isi kolomnya.
     *
     * @param  array<string, string>  $map
     */
    private function applyDimensionFilters(Builder $query, Request $request, array $map): void
    {
        if (self::EXCLUDED_MONTHS !== []) {
            $query->whereNotIn(self::COL_BULAN, self::EXCLUDED_MONTHS);
        }

        foreach ($map as $parameter => $column) {
            $value = trim((string) $request->input($parameter, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $month = (int) $request->input('month', 0);

        if ($month >= 1 && $month <= 12) {
            $names = array_keys(array_filter(
                self::MONTH_MAP,
                static fn (array $v): bool => $v[0] === $month
            ));

            $query->whereIn(self::COL_BULAN, $names ?: ['__tidak_ada__']);
        }
    }

    /**
     * Rekap tabel detail per satu dimensi, memakai aturan OFFSITE.
     *
     * @return array<string, array{total: int, standar: int}>
     */
    private function detailAggregate(Request $request, string $dataset, string $column): array
    {
        $rows = $this->detailFilterQuery($request, $dataset)
            ->selectRaw(
                "COALESCE(NULLIF(TRIM($column), ''), '(Tanpa Nama)') AS dimensi, "
                . self::SQL_RFID . ' AS rfid, '
                . self::SQL_TBC . ' AS tbc'
            )
            ->groupBy('dimensi')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[(string) $row->dimensi] = [
                'total' => (int) $row->rfid,
                'standar' => (int) $row->tbc,
            ];
        }

        return $out;
    }

    /** Tabel detail dengan filter dimensi tab Ringkasan terpasang. */
    private function detailFilterQuery(Request $request, string $dataset): Builder
    {
        $query = DB::table($this->table($dataset, 'detail'));

        $this->applyDimensionFilters($query, $request, [
            'site' => self::COL_SITE,
            'mitra' => self::COL_PERUSAHAAN,
        ]);

        return $query;
    }

    /**
     * Rasio per jabatan fungsional, dari tabel detail.
     *
     * Menggantikan panel Pareto jenis ketidaksesuaian: sumber ini tidak punya
     * rincian jenis temuan, yang ada justru jenjang jabatan pelapor, dan itu
     * yang berguna untuk tahu jenjang mana yang pelaporannya paling tertinggal.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPerJabatan(Request $request, string $dataset): array
    {
        $agg = $this->detailAggregate($request, $dataset, self::COL_JAB_FUNGSIONAL);
        $out = [];

        foreach ($agg as $jabatan => $row) {
            $out[] = [
                'label' => (string) $jabatan,
                'jumlah' => max(0, $row['total'] - $row['standar']),
                'rfid' => $row['total'],
                'tbc' => $row['standar'],
                'rasio' => $row['total'] > 0 ? round($row['standar'] / $row['total'] * 100, 2) : 0.0,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['jumlah'] <=> $a['jumlah']);

        $grand = array_sum(array_column($out, 'jumlah'));
        $kumulatif = 0;

        foreach ($out as $i => $row) {
            $kumulatif += $row['jumlah'];
            $out[$i]['percent'] = $grand > 0 ? round($row['jumlah'] / $grand * 100, 1) : 0.0;
            $out[$i]['kumulatif'] = $grand > 0 ? round($kumulatif / $grand * 100, 1) : 0.0;
        }

        return $out;
    }

    /**
     * Pengawas yang belum melapor, dipecah per perusahaan.
     *
     * @param  array<string, array{total: int, standar: int}>  $perusahaan
     * @return array<int, array<string, mixed>>
     */
    private function buildBelumPerPerusahaan(array $perusahaan): array
    {
        $out = [];

        foreach ($perusahaan as $mitra => $agg) {
            $belum = max(0, $agg['total'] - $agg['standar']);

            if ($belum === 0) {
                continue;
            }

            $out[] = [
                'area' => (string) $mitra,
                'total' => $agg['total'],
                'tidak_sesuai' => $belum,
                'percent' => $agg['total'] > 0 ? round($belum / $agg['total'] * 100, 2) : 0.0,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['tidak_sesuai'] <=> $a['tidak_sesuai']);

        // Subcon punya 138 perusahaan; donat sebanyak itu tidak terbaca.
        return $this->capSlices($out, 12);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function capSlices(array $rows, int $max): array
    {
        if (count($rows) <= $max) {
            return $rows;
        }

        $head = array_slice($rows, 0, $max - 1);
        $tail = array_slice($rows, $max - 1);

        $head[] = [
            'area' => count($tail) . ' perusahaan lainnya',
            'total' => array_sum(array_column($tail, 'total')),
            'tidak_sesuai' => array_sum(array_column($tail, 'tidak_sesuai')),
            'percent' => 0.0,
        ];

        return $head;
    }

    // ======================================================================
    // Tab Data (tabel detail)
    // ======================================================================

    public function data(Request $request, string $dataset = self::DEFAULT_DATASET): JsonResponse
    {
        $dataset = $this->dataset($dataset);
        $query = $this->detailQuery($request, $dataset);

        $rows = (clone $query)
            ->select($this->detailColumns())
            ->orderBy(
                $this->dtOrderColumn($request, self::DETAIL_ORDERABLE, self::COL_SITE),
                $this->dtDirection($request, 'asc')
            )
            ->orderBy('id')
            ->forPage($this->dtPage($request), $this->dtPageLength($request))
            ->get()
            ->map(fn (object $row): array => $this->presentDetail($row))
            ->all();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            // Basisnya tabel tanpa bulan yang dikecualikan, bukan tabel utuh:
            // kalau tidak, DataTables melaporkan "disaring dari 15.767" padahal
            // pengguna belum memasang filter apa pun.
            'recordsTotal' => $this->detailBaseCount($dataset),
            'recordsFiltered' => (clone $query)->count(),
            'data' => $rows,
        ]);
    }

    public function export(Request $request, string $dataset = self::DEFAULT_DATASET): StreamedResponse
    {
        $dataset = $this->dataset($dataset);

        $query = $this->detailQuery($request, $dataset)
            ->select($this->detailColumns())
            ->orderBy(self::COL_SITE)
            ->orderBy(self::COL_PERUSAHAAN)
            ->orderBy('id');

        return $this->dtExport(
            $request,
            $query,
            [
                'Site', 'Perusahaan', 'SID', 'Nama Pelapor', 'Jabatan Fungsional',
                'Jabatan Struktural', 'Status Offsite', 'Bulan',
                'Tercatat RFID', 'Melapor TBC', 'Status',
            ],
            function (object $row): array {
                $p = $this->presentDetail($row);

                return [
                    $p['site'], $p['mitra'], $p['sid'], $p['nama'], $p['jabatan'],
                    $p['jabatan_struktural'], $p['offsite'], $p['bulan'],
                    $p['rfid'], $p['tbc'], $p['status'],
                ];
            },
            'ratio-tbc-gr-' . $dataset
        );
    }

    private function detailBaseCount(string $dataset): int
    {
        $query = DB::table($this->table($dataset, 'detail'));

        if (self::EXCLUDED_MONTHS !== []) {
            $query->whereNotIn(self::COL_BULAN, self::EXCLUDED_MONTHS);
        }

        return $query->count();
    }

    /** @return array<int, string> */
    private function detailColumns(): array
    {
        return [
            self::COL_SITE . ' AS site',
            self::COL_PERUSAHAAN . ' AS mitra',
            self::COL_SID . ' AS sid',
            self::COL_NAMA . ' AS nama',
            self::COL_JAB_FUNGSIONAL . ' AS jabatan',
            self::COL_JAB_STRUKTURAL . ' AS jabatan_struktural',
            self::COL_OFFSITE . ' AS offsite',
            self::COL_BULAN . ' AS bulan_sumber',
            self::COL_RFID . ' AS rfid',
            self::COL_TBC . ' AS tbc',
        ];
    }

    private function detailQuery(Request $request, string $dataset): Builder
    {
        $query = DB::table($this->table($dataset, 'detail'));

        $this->applyDimensionFilters($query, $request, self::DETAIL_FILTERABLE);

        $status = trim((string) $request->input('status', ''));

        if ($status === 'melapor') {
            $query->where(self::COL_RFID, '>', 0)->where(self::COL_TBC, '>', 0);
        } elseif ($status === 'belum') {
            $query->where(self::COL_RFID, '>', 0)->where(self::COL_TBC, '<=', 0);
        } elseif ($status === 'offsite') {
            $query->where(self::COL_RFID, '<=', 0);
        }

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            self::DETAIL_SEARCHABLE
        );

        return $query;
    }

    /**
     * Isi satu sel matriks Pemenuhan per Bulan, untuk modal rincian.
     *
     * Dipanggil saat satu sel diklik. Site dan bulan selalu dikirim; mitra
     * hanya ada di minecon, karena ringkasan subcon bergrain site x bulan saja
     * sehingga selnya memang mewakili SELURUH perusahaan di site itu. Tanpa
     * mitra, filternya cukup site + bulan dan modalnya menampilkan kolom
     * perusahaan supaya terlihat siapa saja yang ada di balik angka itu.
     *
     * CACAHNYA DIHITUNG ULANG DI SINI, bukan diambil dari ringkasan, dengan
     * aturan yang sama persis dengan presentDetail(): pengawas di luar basis
     * RFID tidak ikut jadi penyebut. Kalau tidak, angka di modal bisa berbeda
     * dari persentase di selnya dan tidak ada yang bisa menjelaskan kenapa.
     */
    public function detailBulan(Request $request, string $dataset = self::DEFAULT_DATASET): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));
        $bulan = (int) $request->input('month', 0);

        if ($site === '' || $bulan < 1 || $bulan > 12) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Site dan bulan wajib diisi untuk membuka rincian.',
            ]);
        }

        $query = DB::table($this->table($dataset, 'detail'))
            ->where(self::COL_SITE, $site)
            ->whereIn(self::COL_BULAN, $this->nilaiBulan($bulan));

        if ($mitra !== '') {
            $query->where(self::COL_PERUSAHAAN, $mitra);
        }

        $baris = (clone $query)
            ->select($this->detailColumns())
            // Yang belum melapor didahulukan: itu yang perlu ditindaklanjuti.
            ->orderByRaw('CASE WHEN ' . self::COL_RFID . ' <= 0 THEN 2'
                . ' WHEN ' . self::COL_TBC . ' > 0 THEN 1 ELSE 0 END')
            ->orderBy(self::COL_PERUSAHAAN)
            ->orderBy(self::COL_NAMA)
            ->limit(self::BATAS_BARIS_MODAL + 1)
            ->get()
            ->map(fn (object $row): array => $this->presentDetail($row))
            ->all();

        $terpotong = count($baris) > self::BATAS_BARIS_MODAL;

        if ($terpotong) {
            $baris = array_slice($baris, 0, self::BATAS_BARIS_MODAL);
        }

        $cacah = (clone $query)
            ->selectRaw(
                'COUNT(*) AS semua, '
                . 'SUM(CASE WHEN ' . self::COL_RFID . ' > 0 THEN 1 ELSE 0 END) AS dasar, '
                . 'SUM(CASE WHEN ' . self::COL_RFID . ' > 0 AND ' . self::COL_TBC . ' > 0 THEN 1 ELSE 0 END) AS melapor'
            )
            ->first();

        $dasar = (int) ($cacah->dasar ?? 0);
        $melapor = (int) ($cacah->melapor ?? 0);

        return response()->json([
            'ok' => true,
            'judul' => [
                'dataset' => self::DATASETS[$dataset]['label'] ?? $dataset,
                'site' => $site,
                'mitra' => $mitra,
                'bulan' => self::monthLabel($bulan),
            ],
            // Target ikut dikirim supaya pewarnaan kartu persentase di modal
            // memakai ambang yang sama dengan sisa halaman, bukan angka yang
            // dipatok ulang di JS dan bisa menyimpang kalau ambangnya berubah.
            'target' => self::TARGET_PERCENT,
            'ringkas' => [
                'semua' => (int) ($cacah->semua ?? 0),
                'dasar' => $dasar,
                'melapor' => $melapor,
                'belum' => $dasar - $melapor,
                'offsite' => (int) ($cacah->semua ?? 0) - $dasar,
                'persen' => $dasar > 0 ? round($melapor / $dasar * 100, 2) : null,
            ],
            'terpotong' => $terpotong,
            'batas' => self::BATAS_BARIS_MODAL,
            'baris' => $baris,
        ]);
    }

    /**
     * Semua cara penulisan satu nomor bulan di kolom sumber.
     *
     * @return array<int, string>
     */
    private function nilaiBulan(int $nomor): array
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
     * Satu baris tabel detail dalam bentuk siap tampil.
     *
     * @return array<string, mixed>
     */
    private function presentDetail(object $row): array
    {
        $rfid = (int) $row->rfid;
        $tbc = (int) $row->tbc;

        // Beberapa kolom sumber bertipe CHAR, jadi nilainya datang dengan
        // spasi padding di belakang.
        $teks = static fn ($value): string => trim((string) $value);

        return [
            'site' => $teks($row->site),
            'mitra' => $teks($row->mitra),
            'sid' => $teks($row->sid),
            'nama' => $teks($row->nama),
            'jabatan' => $teks($row->jabatan),
            'jabatan_struktural' => $teks($row->jabatan_struktural),
            'offsite' => $teks($row->offsite),
            'bulan' => self::MONTH_MAP[$row->bulan_sumber][1] ?? $teks($row->bulan_sumber),
            'rfid' => $rfid,
            'tbc' => $tbc,
            // Di luar basis RFID: orangnya mungkin melapor, tapi tidak ikut
            // menentukan rasio, jadi tidak boleh dihitung sebagai "Melapor".
            'status' => $rfid <= 0 ? 'Di Luar RFID' : ($tbc > 0 ? 'Melapor' : 'Belum Melapor'),
        ];
    }

    // ======================================================================
    // Penyusun panel
    // ======================================================================

    /**
     * @param  array<string, mixed>  $matrix
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    private function buildMatrix(array $matrix, array $months): array
    {
        $out = [];

        foreach ($matrix as $entry) {
            $cells = [];
            $grandTotal = 0;
            $grandStandar = 0;
            $filled = [];

            foreach ($months as $month) {
                $total = $entry['bulan'][$month]['total'] ?? 0;

                if ($total === 0) {
                    $cells[] = null;
                    continue;
                }

                $standar = $entry['bulan'][$month]['standar'] ?? 0;
                $pct = round($standar / $total * 100, 2);
                [, $nilai, $band] = $this->scoreBandFor($pct);

                $cells[] = [
                    'pct' => $pct, 'total' => $total, 'standar' => $standar,
                    'nilai' => $nilai, 'nilai_band' => $band,
                ];
                $filled[] = $pct;
                $grandTotal += $total;
                $grandStandar += $standar;
            }

            $avg = $grandTotal > 0 ? round($grandStandar / $grandTotal * 100, 2) : 0.0;
            [, $nilai, $band] = $this->scoreBandFor($avg);

            $out[] = [
                'site' => $entry['site'],
                'mitra' => $entry['mitra'],
                'cells' => $cells,
                'average' => $avg,
                'total' => $grandTotal,
                'tidak_sesuai' => $grandTotal - $grandStandar,
                'nilai' => $nilai,
                'nilai_band' => $band,
                'trend' => $this->trendOf($filled),
            ];
        }

        return $out;
    }

    /**
     * Matriks subcon: sumbernya hanya persentase, jadi rata-rata barisnya
     * adalah rata-rata antar bulan tanpa pembobotan cacah pengawas.
     *
     * @param  array<string, mixed>  $matrix
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    private function buildMatrixFromPercent(array $matrix, array $months): array
    {
        $out = [];

        foreach ($matrix as $entry) {
            $cells = [];
            $filled = [];

            foreach ($months as $month) {
                $pct = $entry['bulan'][$month]['pct'] ?? null;

                if ($pct === null) {
                    $cells[] = null;
                    continue;
                }

                [, $nilai, $band] = $this->scoreBandFor((float) $pct);

                $cells[] = [
                    'pct' => (float) $pct, 'total' => null, 'standar' => null,
                    'nilai' => $nilai, 'nilai_band' => $band,
                ];
                $filled[] = (float) $pct;
            }

            $avg = $filled !== [] ? round(array_sum($filled) / count($filled), 2) : 0.0;
            [, $nilai, $band] = $this->scoreBandFor($avg);

            $out[] = [
                'site' => $entry['site'],
                'mitra' => $entry['mitra'],
                'cells' => $cells,
                'average' => $avg,
                'total' => null,
                'tidak_sesuai' => null,
                'nilai' => $nilai,
                'nilai_band' => $band,
                'trend' => $this->trendOf($filled),
            ];
        }

        return $out;
    }

    /** @param  array<int, float>  $filled */
    private function trendOf(array $filled): ?string
    {
        if (count($filled) < 2) {
            return null;
        }

        return $filled[count($filled) - 1] >= $filled[count($filled) - 2] ? 'up' : 'down';
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @return array<string, mixed>
     */
    private function buildKpi(array $matrixRows): array
    {
        $total = array_sum(array_column($matrixRows, 'total'));
        $belum = array_sum(array_column($matrixRows, 'tidak_sesuai'));

        return $this->kpiPayload(
            $total,
            $total - $belum,
            count(array_unique(array_column($matrixRows, 'site'))),
            count(array_unique(array_filter(array_column($matrixRows, 'mitra'))))
        );
    }

    /**
     * KPI subcon: cacah pengawas hanya ada di tabel detail.
     *
     * @return array<string, mixed>
     */
    private function buildKpiFromDetail(Request $request, string $dataset): array
    {
        $row = $this->detailFilterQuery($request, $dataset)
            ->selectRaw(
                self::SQL_RFID . ' AS rfid, '
                . self::SQL_TBC . ' AS tbc, '
                . 'COUNT(DISTINCT ' . self::COL_SITE . ') AS sites, '
                . 'COUNT(DISTINCT ' . self::COL_PERUSAHAAN . ') AS mitras'
            )
            ->first();

        return $this->kpiPayload(
            (int) ($row->rfid ?? 0),
            (int) ($row->tbc ?? 0),
            (int) ($row->sites ?? 0),
            (int) ($row->mitras ?? 0)
        );
    }

    /** @return array<string, mixed> */
    private function kpiPayload(int $total, int $melapor, int $siteCount, int $mitraCount): array
    {
        $belum = max(0, $total - $melapor);
        $pct = $total > 0 ? round($melapor / $total * 100, 2) : 0.0;
        [, $nilai, $band] = $this->scoreBandFor($pct);

        return [
            'total' => $total,
            'standar' => $melapor,
            'tidak_sesuai' => $belum,
            'standar_pct' => $pct,
            'tidak_sesuai_pct' => $total > 0 ? round($belum / $total * 100, 2) : 0.0,
            'nilai' => $nilai,
            'nilai_band' => $band,
            'target' => self::TARGET_PERCENT,
            'memenuhi_target' => $pct >= self::TARGET_PERCENT,
            'site_count' => $siteCount,
            'mitra_count' => $mitraCount,
            'bulan_terakhir' => null,
        ];
    }

    /**
     * Peringkat perusahaan.
     *
     * Subcon punya 138 perusahaan dan sebagian hanya berisi 4-6 pengawas,
     * sehingga mengurutkan murni berdasarkan persentase akan menaruh mereka di
     * puncak dan menenggelamkan yang berisi ratusan orang. Karena itu yang
     * ditampilkan adalah $max perusahaan dengan pengawas terbanyak, baru
     * diurutkan persentasenya di antara mereka.
     *
     * @param  array<string, array{total: int, standar: int}>  $perusahaan
     * @return array<int, array<string, mixed>>
     */
    private function buildPerusahaan(array $perusahaan, int $max = 12): array
    {
        $out = [];

        foreach ($perusahaan as $mitra => $agg) {
            $pct = $agg['total'] > 0 ? round($agg['standar'] / $agg['total'] * 100, 2) : 0.0;
            [, $nilai, $band] = $this->scoreBandFor($pct);

            $out[] = [
                'mitra' => (string) $mitra,
                'total' => $agg['total'],
                'standar' => $agg['standar'],
                'percent' => $pct,
                'nilai' => $nilai,
                'nilai_band' => $band,
            ];
        }

        if (count($out) > $max) {
            usort($out, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
            $out = array_slice($out, 0, $max);
        }

        usort($out, static fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @return array<int, array<string, mixed>>
     */
    private function buildSiteVsTarget(array $matrixRows): array
    {
        $perSite = [];

        foreach ($matrixRows as $row) {
            $perSite[$row['site']]['total'] = ($perSite[$row['site']]['total'] ?? 0) + $row['total'];
            $perSite[$row['site']]['belum'] = ($perSite[$row['site']]['belum'] ?? 0) + $row['tidak_sesuai'];
        }

        ksort($perSite);
        $out = [];

        foreach ($perSite as $site => $agg) {
            $pct = $agg['total'] > 0 ? round(($agg['total'] - $agg['belum']) / $agg['total'] * 100, 2) : 0.0;
            [, $nilai] = $this->scoreBandFor($pct);

            $out[] = [
                'site' => (string) $site,
                'percent' => $pct,
                'target' => self::TARGET_PERCENT,
                'nilai' => $nilai,
                'total' => $agg['total'],
                'tidak_sesuai' => $agg['belum'],
            ];
        }

        return $out;
    }

    /**
     * Subcon: matriksnya sudah per site, jadi rata-rata barisnya langsung
     * dipakai dan tidak perlu dijumlah ulang.
     *
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @return array<int, array<string, mixed>>
     */
    private function buildSiteVsTargetFromPercent(array $matrixRows): array
    {
        return array_map(static fn (array $row): array => [
            'site' => $row['site'],
            'percent' => $row['average'],
            'target' => self::TARGET_PERCENT,
            'nilai' => $row['nilai'],
            'total' => null,
            'tidak_sesuai' => null,
        ], $matrixRows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @return array<int, array<string, mixed>>
     */
    private function buildTopTerendah(array $matrixRows): array
    {
        $rows = $matrixRows;
        usort($rows, static fn (array $a, array $b): int => $a['average'] <=> $b['average']);

        return array_map(static fn (array $r): array => [
            'site' => $r['site'], 'mitra' => $r['mitra'],
            'percent' => $r['average'], 'nilai' => $r['nilai'],
            'total' => $r['total'], 'tidak_sesuai' => $r['tidak_sesuai'],
        ], array_slice($rows, 0, 5));
    }

    /**
     * Subcon: lima pasangan site-perusahaan dengan pengawas belum melapor
     * terbanyak. Dasarnya cacah, bukan persentase, supaya perusahaan berisi
     * 2 orang tidak menutupi yang berisi 200.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildTopTerendahFromDetail(Request $request, string $dataset): array
    {
        $rows = $this->detailFilterQuery($request, $dataset)
            ->selectRaw(
                self::COL_SITE . ' AS site, '
                . self::COL_PERUSAHAAN . ' AS mitra, '
                . self::SQL_RFID . ' AS rfid, '
                . self::SQL_TBC . ' AS tbc'
            )
            ->groupBy('site', 'mitra')
            ->havingRaw(self::SQL_RFID . ' > ' . self::SQL_TBC)
            ->orderByRaw('(' . self::SQL_RFID . ' - ' . self::SQL_TBC . ') DESC')
            ->limit(5)
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $total = (int) $row->rfid;
            $pct = $total > 0 ? round((int) $row->tbc / $total * 100, 2) : 0.0;
            [, $nilai] = $this->scoreBandFor($pct);

            $out[] = [
                'site' => trim((string) $row->site),
                'mitra' => trim((string) $row->mitra),
                'percent' => $pct,
                'nilai' => $nilai,
                'total' => $total,
                'tidak_sesuai' => $total - (int) $row->tbc,
            ];
        }

        return $out;
    }

    /**
     * Satu garis per perusahaan (minecon) atau per site (subcon).
     *
     * @param  array<string, mixed>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $matrix, array $months, string $by): array
    {
        $grouped = [];

        foreach ($matrix as $entry) {
            $name = (string) ($entry[$by] ?? '-');

            foreach ($months as $month) {
                $cell = $entry['bulan'][$month] ?? null;

                if ($cell === null) {
                    continue;
                }

                if (array_key_exists('pct', $cell)) {
                    // Sumber persentase: satu site hanya punya satu nilai per
                    // bulan, jadi tidak ada yang perlu dijumlahkan.
                    $grouped[$name][$month]['pct'] = $cell['pct'];
                    continue;
                }

                $grouped[$name][$month]['total'] = ($grouped[$name][$month]['total'] ?? 0) + $cell['total'];
                $grouped[$name][$month]['standar'] = ($grouped[$name][$month]['standar'] ?? 0) + $cell['standar'];
            }
        }

        ksort($grouped);
        $series = [];

        foreach ($grouped as $name => $perMonth) {
            $data = [];

            foreach ($months as $month) {
                $cell = $perMonth[$month] ?? null;

                if ($cell === null) {
                    $data[] = null; // null, bukan 0: bulan tanpa data harus putus di grafik
                    continue;
                }

                if (array_key_exists('pct', $cell)) {
                    $data[] = $cell['pct'];
                    continue;
                }

                $data[] = $cell['total'] > 0 ? round($cell['standar'] / $cell['total'] * 100, 2) : null;
            }

            $series[] = ['name' => (string) $name, 'data' => $data];
        }

        return [
            'labels' => array_map(static fn (int $m): string => self::monthLabel($m), $months),
            'series' => $series,
        ];
    }

    // ======================================================================
    // Utilitas
    // ======================================================================

    /** Nama dataset yang sah; yang lain jatuh ke bawaan. */
    private function dataset(string $slug): string
    {
        return isset(self::DATASETS[$slug]) ? $slug : self::DEFAULT_DATASET;
    }

    private function table(string $dataset, string $kind): string
    {
        return self::DATASETS[$this->dataset($dataset)][$kind];
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

    /**
     * Bulan yang benar-benar ada di sumber, diurutkan kalender.
     *
     * Ringkasan dan detail bisa berbeda jangkauan bulannya, jadi keduanya
     * digabung supaya dropdown yang sama berlaku untuk kedua tab.
     *
     * @return array<int, string>
     */
    private function monthOptions(string $dataset): array
    {
        $months = [];

        foreach (['summary', 'detail'] as $kind) {
            foreach ($this->distinctValues($this->table($dataset, $kind), self::COL_BULAN) as $name) {
                if (in_array($name, self::EXCLUDED_MONTHS, true)) {
                    continue;
                }

                if (isset(self::MONTH_MAP[$name])) {
                    $months[self::MONTH_MAP[$name][0]] = self::MONTH_MAP[$name][1];
                }
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
