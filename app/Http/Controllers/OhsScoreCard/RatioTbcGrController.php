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
 * Parameter SOD "Ratio TBC & GR".
 *
 * Dua tabel dipakai sesuai peruntukannya:
 *   - lead_ratio_pelapor_tbc         -> tab Ringkasan (105 baris agregat)
 *   - detail_lead_ratio_pelapor_tbc  -> tab Data (15.767 baris per pengawas)
 *
 * Rasionya = countd_pengawas_tbc / countd_pengawas_rfid: dari sekian pengawas
 * yang tercatat hadir lewat RFID, berapa yang membuat laporan TBC. Kolom
 * pct_ratio_pelapor_tbc di tabel sudah berisi hasil bagi itu dan terbukti
 * cocok, tetapi rekap di sini tetap menjumlahkan pembilang & penyebut lalu
 * membaginya sendiri, karena merata-ratakan persentase antar baris akan
 * memberi bobot sama pada perusahaan berisi 2 pengawas dan 150 pengawas.
 *
 * CATATAN DATA: kedua tabel tidak rekonsiliasi persis. Total ringkasan
 * rfid 15.398 / tbc 13.410, sedangkan detail 15.389 / 13.480 (13.412 bila
 * baris OFFSITE dikecualikan; 378 baris OFFSITE semuanya ber-rfid 0). 73 dari
 * 105 kombinasi site-perusahaan-bulan cocok, 32 berbeda tipis. Karena itu
 * angka tab Ringkasan dan tab Data bisa berselisih sedikit, dan itu bukan bug
 * di halaman ini melainkan selisih di sumbernya.
 */
final class RatioTbcGrController extends Controller
{
    use ServesDataTable;

    private const SUMMARY_TABLE = 'lead_ratio_pelapor_tbc';
    private const DETAIL_TABLE = 'detail_lead_ratio_pelapor_tbc';

    /** Nama kolom di sumber panjang-panjang; dipendekkan lewat alias. */
    private const COL_SITE = 'site_dedicated_pelapor_all_karyawan';
    private const COL_PERUSAHAAN = 'perusahaan_pelapor_all_karyawan';
    private const COL_BULAN = 'month_of_date_time';
    private const COL_RFID = 'countd_pengawas_rfid';
    private const COL_TBC = 'countd_pengawas_tbc';
    private const COL_SID = 'sid_pelapor_all_karyawan';
    private const COL_NAMA = 'pelapor_all_karyawan';
    private const COL_JAB_FUNGSIONAL = 'jabatan_fungsional_pelapor_all_karyawan';
    private const COL_JAB_STRUKTURAL = 'jabatan_struktural_pelapor_all_karyawan';
    private const COL_OFFSITE = 'status_offsite';

    private const TARGET_PERCENT = 90.0;

    /** Bulan tersimpan sebagai nama Inggris; dipetakan untuk urutan & label. */
    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

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
        return view('ohs-score-card.ratio-tbc-gr.index', [
            'filterOptions' => [
                'site' => $this->distinctValues(self::SUMMARY_TABLE, self::COL_SITE),
                'mitra' => $this->distinctValues(self::SUMMARY_TABLE, self::COL_PERUSAHAAN),
                'jabatan' => $this->distinctValues(self::DETAIL_TABLE, self::COL_JAB_FUNGSIONAL),
            ],
            'monthOptions' => $this->monthOptions(),
        ]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request): JsonResponse
    {
        $rows = $this->summaryQuery($request)
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
            $key = $row->site . '|' . $row->mitra;

            $matrix[$key]['site'] = (string) $row->site;
            $matrix[$key]['mitra'] = (string) $row->mitra;
            $matrix[$key]['bulan'][$monthNo]['total'] = ($matrix[$key]['bulan'][$monthNo]['total'] ?? 0) + (int) $row->rfid;
            $matrix[$key]['bulan'][$monthNo]['standar'] = ($matrix[$key]['bulan'][$monthNo]['standar'] ?? 0) + (int) $row->tbc;

            $perusahaan[(string) $row->mitra]['total'] = ($perusahaan[(string) $row->mitra]['total'] ?? 0) + (int) $row->rfid;
            $perusahaan[(string) $row->mitra]['standar'] = ($perusahaan[(string) $row->mitra]['standar'] ?? 0) + (int) $row->tbc;
        }

        ksort($monthSeen);
        ksort($matrix);
        $months = array_keys($monthSeen);
        $matrixRows = $this->buildMatrix($matrix, $months);

        return response()->json([
            'months' => array_map(static fn (int $m): array => [
                'number' => $m,
                'label' => mb_strtoupper(mb_substr(self::monthLabel($m), 0, 3)),
            ], $months),
            'kpi' => $this->buildKpi($matrixRows),
            'matrix' => $matrixRows,
            'perusahaan' => $this->buildPerusahaan($perusahaan),
            'site_vs_target' => $this->buildSiteVsTarget($matrixRows),
            'top_terendah' => $this->buildTopTerendah($matrixRows),
            'pareto' => $this->buildPerJabatan($request),
            'per_area' => $this->buildBelumPerPerusahaan($perusahaan),
            'monthly' => $this->buildMonthlySeries($matrix, $months),
        ]);
    }

    /** Query ringkasan dengan filter terpasang. */
    private function summaryQuery(Request $request): Builder
    {
        $query = DB::table(self::SUMMARY_TABLE);

        $this->applyDimensionFilters($query, $request, [
            'site' => self::COL_SITE,
            'mitra' => self::COL_PERUSAHAAN,
        ]);

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
     * Rasio per jabatan fungsional, dari tabel detail.
     *
     * Menggantikan panel Pareto jenis ketidaksesuaian: sumber ini tidak punya
     * rincian jenis temuan, yang ada justru jenjang jabatan pelapor, dan itu
     * yang berguna untuk tahu jenjang mana yang pelaporannya paling tertinggal.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPerJabatan(Request $request): array
    {
        $query = DB::table(self::DETAIL_TABLE);

        $this->applyDimensionFilters($query, $request, [
            'site' => self::COL_SITE,
            'mitra' => self::COL_PERUSAHAAN,
        ]);

        $rows = $query
            ->selectRaw(
                'COALESCE(NULLIF(TRIM(' . self::COL_JAB_FUNGSIONAL . "), ''), '(Tanpa Jabatan)') AS jabatan, "
                . 'SUM(' . self::COL_RFID . ') AS rfid, '
                . 'SUM(' . self::COL_TBC . ') AS tbc'
            )
            ->groupBy('jabatan')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $rfid = (int) $row->rfid;
            $belum = max(0, $rfid - (int) $row->tbc);

            $out[] = [
                'label' => (string) $row->jabatan,
                'jumlah' => $belum,
                'rfid' => $rfid,
                'tbc' => (int) $row->tbc,
                'rasio' => $rfid > 0 ? round((int) $row->tbc / $rfid * 100, 2) : 0.0,
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

        return $out;
    }

    // ======================================================================
    // Tab Data (tabel detail)
    // ======================================================================

    public function data(Request $request): JsonResponse
    {
        $query = $this->detailQuery($request);

        $rows = (clone $query)
            ->select([
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
            ])
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
            'recordsTotal' => DB::table(self::DETAIL_TABLE)->count(),
            'recordsFiltered' => (clone $query)->count(),
            'data' => $rows,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->detailQuery($request)
            ->select([
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
            ])
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
            'ratio-tbc-gr'
        );
    }

    private function detailQuery(Request $request): Builder
    {
        $query = DB::table(self::DETAIL_TABLE);

        $this->applyDimensionFilters($query, $request, self::DETAIL_FILTERABLE);

        $status = trim((string) $request->input('status', ''));

        if ($status === 'melapor') {
            $query->where(self::COL_TBC, '>', 0);
        } elseif ($status === 'belum') {
            $query->where(self::COL_TBC, '<=', 0);
        }

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            self::DETAIL_SEARCHABLE
        );

        return $query;
    }

    /**
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
            'bulan' => self::MONTH_MAP[$row->bulan_sumber][1] ?? (string) $row->bulan_sumber,
            'rfid' => $rfid,
            'tbc' => $tbc,
            'status' => $tbc > 0 ? 'Melapor' : 'Belum Melapor',
        ];
    }

    // ======================================================================
    // Rekap bersama
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

            $trend = null;
            if (count($filled) >= 2) {
                $trend = $filled[count($filled) - 1] >= $filled[count($filled) - 2] ? 'up' : 'down';
            }

            $out[] = [
                'site' => $entry['site'],
                'mitra' => $entry['mitra'],
                'cells' => $cells,
                'average' => $avg,
                'total' => $grandTotal,
                'tidak_sesuai' => $grandTotal - $grandStandar,
                'nilai' => $nilai,
                'nilai_band' => $band,
                'trend' => $trend,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @return array<string, mixed>
     */
    private function buildKpi(array $matrixRows): array
    {
        $total = array_sum(array_column($matrixRows, 'total'));
        $belum = array_sum(array_column($matrixRows, 'tidak_sesuai'));
        $melapor = $total - $belum;
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
            'site_count' => count(array_unique(array_column($matrixRows, 'site'))),
            'mitra_count' => count(array_unique(array_column($matrixRows, 'mitra'))),
            'bulan_terakhir' => null,
        ];
    }

    /**
     * @param  array<string, array{total: int, standar: int}>  $perusahaan
     * @return array<int, array<string, mixed>>
     */
    private function buildPerusahaan(array $perusahaan): array
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
     * @param  array<string, mixed>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $matrix, array $months): array
    {
        $byMitra = [];

        foreach ($matrix as $entry) {
            foreach ($months as $month) {
                $byMitra[$entry['mitra']][$month]['total'] = ($byMitra[$entry['mitra']][$month]['total'] ?? 0)
                    + ($entry['bulan'][$month]['total'] ?? 0);
                $byMitra[$entry['mitra']][$month]['standar'] = ($byMitra[$entry['mitra']][$month]['standar'] ?? 0)
                    + ($entry['bulan'][$month]['standar'] ?? 0);
            }
        }

        ksort($byMitra);
        $series = [];

        foreach ($byMitra as $mitra => $perMonth) {
            $data = [];

            foreach ($months as $month) {
                $total = $perMonth[$month]['total'] ?? 0;
                // null, bukan 0: bulan tanpa data harus putus di grafik.
                $data[] = $total > 0 ? round($perMonth[$month]['standar'] / $total * 100, 2) : null;
            }

            $series[] = ['name' => (string) $mitra, 'data' => $data];
        }

        return [
            'labels' => array_map(static fn (int $m): string => self::monthLabel($m), $months),
            'series' => $series,
        ];
    }

    // ======================================================================
    // Utilitas
    // ======================================================================

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

        foreach ($this->distinctValues(self::SUMMARY_TABLE, self::COL_BULAN) as $name) {
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
