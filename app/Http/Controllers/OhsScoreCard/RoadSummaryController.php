<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Parameter "Jalan sesuai standar" — tabel app_mixer.road_summary.
 *
 * Isinya hasil evaluasi per segmen jalan (grade, lebar, superelevasi).
 * Tabelnya besar (±140 ribu baris), jadi DataTable-nya server-side:
 * paging, sorting, search, dan filter semuanya dikerjakan di SQL.
 */
final class RoadSummaryController extends Controller
{
    private const TABLE = 'road_summary';

    private const DEFAULT_PAGE_LENGTH = 25;
    private const MAX_PAGE_LENGTH = 200;

    /**
     * Batas baris untuk format .xlsx. Diturunkan dari pengukuran nyata di
     * mesin ini (memory_limit 512 MB): 10 rb baris ~130 MB, 50 rb ~480 MB.
     * 30 rb memberi ruang aman; di atas itu arahkan pengguna ke CSV.
     */
    private const MAX_XLSX_ROWS = 30000;

    /** Opsi dropdown filter di-cache, query DISTINCT-nya mahal di tabel sebesar ini. */
    private const FILTER_CACHE_TTL = 600;

    /**
     * Batas contoh segmen yang dikirim ke modal rincian sel.
     *
     * Sel terpadat berisi 12 ribu segmen (GMO/PAMA Januari), jadi daftar
     * mentah tidak pernah jadi pilihan: modal menyajikan pecahan ringkas
     * (per minggu, per pit, per jenis cek) lalu hanya contoh segmen yang
     * paling banyak gagal ceknya sebanyak batas ini.
     */
    private const BATAS_BARIS_MODAL = 500;

    /** Kolom yang boleh difilter persis (exact match) dari query string. */
    private const FILTERABLE = [
        'site', 'pit', 'mitra', 'year', 'week', 'grade_stat', 'road_width', 'supereleva',
    ];

    /**
     * Ekspresi SQL "segmen memenuhi standar" — satu sumber kebenaran yang dipakai
     * bertiga: nilai kolom Kesimpulan, filter Kesimpulan, dan agregat ringkasan.
     *
     * Aturannya:
     *  - grade_stat, road_width, supereleva WAJIB 'ACCEPT' (selalu dinilai).
     *  - junction_1 / junction_s hanya ikut dinilai bila segmen tersebut memang
     *    titik pertemuan. Nilai '-' (atau kosong/NULL) berarti tidak berlaku,
     *    jadi tidak menggugurkan. Kalau terisi, nilainya harus 'ACCEPT'.
     *
     * Nilai junction selain '-' dan 'ACCEPT' otomatis dianggap tidak lolos,
     * jadi aman walau nanti muncul status baru yang belum dikenal.
     */
    private const STANDARD_SQL = "("
        . "grade_stat = 'ACCEPT' AND road_width = 'ACCEPT' AND supereleva = 'ACCEPT'"
        . " AND (junction_1 IS NULL OR junction_1 IN ('-', '', 'ACCEPT'))"
        . " AND (junction_s IS NULL OR junction_s IN ('-', '', 'ACCEPT'))"
        . ")";

    /**
     * Jenis cek yang bisa menggugurkan sebuah segmen: [label, ekspresi gagal].
     *
     * Satu daftar untuk Pareto di dashboard dan untuk pecahan penyebab di
     * modal rincian sel, supaya keduanya tidak bisa menghitung hal berbeda.
     * Junction hanya dihitung gagal bila nilainya 'REJECT' — '-' berarti
     * segmen itu memang bukan titik pertemuan, jadi ceknya tidak berlaku.
     */
    private const JENIS_CEK = [
        'gagal_lebar' => ['Lebar Jalan', "road_width <> 'ACCEPT'"],
        'gagal_super' => ['Superelevasi', "supereleva <> 'ACCEPT'"],
        'gagal_grade' => ['Grade', "grade_stat <> 'ACCEPT'"],
        'gagal_junction_1' => ['Junction 1', "junction_1 = 'REJECT'"],
        'gagal_junction_s' => ['Junction S', "junction_s = 'REJECT'"],
    ];

    /** Nilai yang diterima filter Kesimpulan. */
    private const CONCLUSION_STANDARD = 'standar';
    private const CONCLUSION_NOT_STANDARD = 'tidak-standar';

    /**
     * Tabel ini tidak punya kolom bulan/tanggal — hanya `year` + `week`
     * (kolom tanggal di road_datasets adalah waktu ingest, bukan periode
     * yang diukur, jadi tidak dipakai). Bulan karena itu diturunkan dari
     * nomor minggu memakai aturan ISO 8601: satu minggu dimiliki oleh bulan
     * tempat hari KAMIS-nya jatuh.
     *
     * Dipakai aturan Kamis, bukan Senin, karena minggu bisa membelah dua
     * bulan — mis. 2026 minggu 1 mulai Senin 29 Des 2025; dengan aturan
     * Kamis (1 Jan 2026) minggu itu benar masuk Januari 2026, bukan
     * Desember 2025. Pemetaan ini sudah dicocokkan dengan MySQL
     * MONTH(STR_TO_DATE(... '%x%v %W') + 3 hari) untuk seluruh data.
     */
    /**
     * Konversi persentase segmen standar menjadi Nilai 1–4.
     *
     * Bentuk: [ambang bawah, nilai, label]. Dibaca dari atas; band pertama
     * yang ambangnya <= persentase dipakai.
     *
     * CATATAN: ambang yang diberikan berhenti di "98% <= X < 100%", sehingga
     * X = 100% tepat tidak tercakup. Di sini 100% ikut Nilai 4 karena itu
     * capaian terbaik. Kalau ternyata 100% seharusnya punya nilai sendiri
     * (mis. Nilai 5), cukup tambahkan band baru di paling atas.
     */
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
        [100.0, 100.0, 4, '100%'],
        [98.0, 100.0, 3, '98% - <100%'],
        [95.0, 98.0, 2, '95% - <98%'],
        [0.0, 95.0, 1, '<95%'],
    ];

    /** Target kepatuhan jalan yang dipakai di dashboard Overview. */
    private const TARGET_PERCENT = 100.0;

    private const MONTH_LABELS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** Index kolom DataTable -> kolom SQL. Whitelist, supaya order tidak bisa diinjeksi. */
    private const ORDERABLE = [
        0 => 'site',
        1 => 'pit',
        2 => 'mitra',
        3 => 'year',
        4 => 'week',
        // Kolom Bulan diturunkan dari week, jadi urut minggu = urut bulan.
        5 => 'week',
        6 => 'nama_jalan',
        7 => 'segment',
        8 => 'grade_stat',
        9 => 'road_width',
        10 => 'supereleva',
        11 => 'junction_1',
        12 => 'junction_s',
        // 13 = kolom Kesimpulan, ditangani khusus karena hasil hitungan (lihat applyOrder()).
    ];

    /** Index kolom DataTable untuk kolom Kesimpulan. */
    private const CONCLUSION_COLUMN_INDEX = 13;

    /** Kolom yang ikut kena kotak search bebas. */
    private const SEARCHABLE = [
        'site', 'pit', 'mitra', 'nama_jalan', 'grade_stat', 'road_width', 'supereleva',
    ];

    public function index(): View
    {
        return view('ohs-score-card.jalan-sesuai-standar.index', [
            'filterOptions' => $this->filterOptions(),
            'monthOptions' => $this->monthOptions(),
            'totalSegments' => $this->totalCount(),
            'maxXlsxRows' => self::MAX_XLSX_ROWS,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);
        $search = (string) $request->input('search.value', '');

        $recordsTotal = $this->totalCount();

        $filtered = $this->buildFilteredQuery($request);

        // Tanpa filter & search, hasilnya pasti sama dengan seluruh tabel —
        // hindari dua full scan (count + agregat ringkasan) di tiap request.
        $isUnfiltered = $this->activeFilterCount($request) === 0 && trim($search) === '';

        if ($isUnfiltered) {
            $recordsFiltered = $recordsTotal;
            $summary = $this->totalSummary();
        } else {
            // count() meniadakan order by, jadi hitung dulu sebelum paging dipasang.
            $recordsFiltered = (clone $filtered)->count();
            $summary = $this->summarise(clone $filtered);
        }

        $filtered
            ->select([
                'site', 'pit', 'mitra', 'year', 'week', 'nama_jalan',
                'segment', 'grade_stat', 'road_width', 'supereleva',
                'junction_1', 'junction_s',
            ])
            ->selectRaw(self::STANDARD_SQL . ' AS is_standar');

        $this->applyOrder($filtered, $request);

        $rows = $filtered
            ->orderBy('id') // tie-breaker: paging stabil saat nilai kolom sort kembar
            ->forPage($this->page($request), $this->pageLength($request))
            ->get()
            ->map(function (object $row): object {
                // Cast eksplisit: MySQL mengembalikan 1/0, pastikan JSON-nya boolean.
                $row->is_standar = (bool) $row->is_standar;

                // Bulan dihitung di PHP, bukan SQL, supaya query tetap sargable.
                $month = $this->monthOfIsoWeek((int) $row->year, (int) $row->week);
                $row->bulan = self::MONTH_LABELS[$month] ?? '–';

                return $row;
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
            'summary' => $summary,
        ]);
    }

    /**
     * Unduh hasil filter saat ini.
     *
     * Dua format, karena keduanya punya batasan berbeda:
     *  - xlsx : rapi & langsung jadi file Excel, tapi PhpSpreadsheet menahan
     *           seluruh sheet di memori. Diukur di mesin ini: 10 rb baris
     *           ~13 dtk / 130 MB, 50 rb baris ~115 dtk / 480 MB — padahal
     *           memory_limit 512 MB. Karena itu dibatasi MAX_XLSX_ROWS.
     *  - csv  : ditulis mengalir per potongan, memori nyaris tetap, sanggup
     *           seluruh tabel. Dibuka langsung oleh Excel (pakai BOM UTF-8).
     */
    /**
     * Data tab "Overview Dashboard".
     *
     * Semua angka berasal dari SATU query agregat: GROUP BY site, mitra,
     * year, week. Hasilnya kecil (± 9 kombinasi site-mitra x 40 minggu),
     * jadi rekap bulanan, tren, dan ringkasan per perusahaan dihitung di PHP.
     *
     * Sengaja TIDAK mengelompokkan per bulan di SQL: ekspresi bulan harus
     * diturunkan dari nomor minggu lewat fungsi tanggal, yang membuat MySQL
     * memindai penuh 140 ribu baris. Mengelompokkan per minggu memakai kolom
     * apa adanya, lalu minggu dipetakan ke bulan di sini — sekalian memberi
     * data mingguan untuk grafik tanpa query kedua.
     */
    public function overview(Request $request): JsonResponse
    {
        $rows = $this->applyOverviewFilters($this->baseQuery(), $request)
            ->selectRaw(
                'site, mitra, year, week, COUNT(*) AS total, SUM(' . self::STANDARD_SQL . ') AS standar'
            )
            ->groupBy('site', 'mitra', 'year', 'week')
            ->orderBy('site')
            ->orderBy('mitra')
            ->orderBy('year')
            ->orderBy('week')
            ->get();

        $matrix = [];       // [site|mitra][bulan] => [total, standar]
        $weekBuckets = [];  // [mitra][year-week] => [total, standar]
        $monthSeen = [];
        $weekSeen = [];
        $perusahaan = [];

        foreach ($rows as $row) {
            $site = (string) $row->site;
            $mitra = (string) $row->mitra;
            $year = (int) $row->year;
            $week = (int) $row->week;
            $total = (int) $row->total;
            $standar = (int) $row->standar;
            $month = $this->monthOfIsoWeek($year, $week);

            if ($month > 0) {
                $monthSeen[$month] = true;
                $key = $site . '|' . $mitra;
                $matrix[$key]['site'] = $site;
                $matrix[$key]['mitra'] = $mitra;
                $matrix[$key]['bulan'][$month]['total'] = ($matrix[$key]['bulan'][$month]['total'] ?? 0) + $total;
                $matrix[$key]['bulan'][$month]['standar'] = ($matrix[$key]['bulan'][$month]['standar'] ?? 0) + $standar;
            }

            $weekKey = sprintf('%04d-%02d', $year, $week);
            $weekSeen[$weekKey] = true;
            $weekBuckets[$mitra][$weekKey]['total'] = ($weekBuckets[$mitra][$weekKey]['total'] ?? 0) + $total;
            $weekBuckets[$mitra][$weekKey]['standar'] = ($weekBuckets[$mitra][$weekKey]['standar'] ?? 0) + $standar;

            $perusahaan[$mitra]['total'] = ($perusahaan[$mitra]['total'] ?? 0) + $total;
            $perusahaan[$mitra]['standar'] = ($perusahaan[$mitra]['standar'] ?? 0) + $standar;
        }

        ksort($monthSeen);
        ksort($weekSeen);
        $months = array_keys($monthSeen);
        $weeks = array_keys($weekSeen);

        $matrixRows = $this->buildOverviewMatrix($matrix, $months);
        $sites = array_unique(array_column($matrixRows, 'site'));
        $mitras = array_unique(array_column($matrixRows, 'mitra'));
        $paretoArea = $this->buildParetoDanArea($request);

        return response()->json([
            'months' => array_map(fn (int $m): array => [
                'number' => $m,
                'label' => mb_strtoupper(mb_substr(self::MONTH_LABELS[$m] ?? '-', 0, 3)),
            ], $months),
            'kpi' => $this->buildKpi($matrix, $months, count($sites), count($mitras)),
            'matrix' => $matrixRows,
            'perusahaan' => $this->buildOverviewPerusahaan($perusahaan),
            'site_vs_target' => $this->buildSiteVsTarget($matrix, $months),
            'top_terendah' => $this->buildTopTerendah($matrixRows),
            'pareto' => $paretoArea['pareto'],
            'per_area' => $paretoArea['per_area'],
            'monthly' => $this->buildMonthlySeries($matrix, $months),
            'weekly' => $this->buildWeeklySeries($weekBuckets, $weeks),
        ]);
    }

    /**
     * Ringkasan angka besar di kepala dashboard, termasuk perubahan terhadap
     * bulan sebelumnya.
     *
     * @param  array<string, mixed>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildKpi(array $matrix, array $months, int $siteCount, int $mitraCount): array
    {
        $total = 0;
        $standar = 0;

        foreach ($matrix as $entry) {
            foreach ($months as $month) {
                $total += $entry['bulan'][$month]['total'] ?? 0;
                $standar += $entry['bulan'][$month]['standar'] ?? 0;
            }
        }

        $pct = $total > 0 ? round($standar / $total * 100, 2) : 0.0;
        [, $nilai, $band] = $this->scoreBandFor($pct);

        $lastLabel = null;

        if (count($months) >= 1) {
            $lastMonth = $months[count($months) - 1];
            $lastLabel = self::MONTH_LABELS[$lastMonth] ?? '-';
        }

        return [
            'total' => $total,
            'standar' => $standar,
            'tidak_sesuai' => $total - $standar,
            'standar_pct' => $pct,
            'tidak_sesuai_pct' => $total > 0 ? round(($total - $standar) / $total * 100, 2) : 0.0,
            'nilai' => $nilai,
            'nilai_band' => $band,
            'target' => self::TARGET_PERCENT,
            'memenuhi_target' => $pct >= self::TARGET_PERCENT,
            'site_count' => $siteCount,
            'mitra_count' => $mitraCount,
            'bulan_terakhir' => $lastLabel,
        ];
    }

    /**
     * Capaian tiap site dibanding target.
     *
     * @param  array<string, mixed>  $matrix
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    private function buildSiteVsTarget(array $matrix, array $months): array
    {
        $perSite = [];

        foreach ($matrix as $entry) {
            foreach ($months as $month) {
                $perSite[$entry['site']]['total'] = ($perSite[$entry['site']]['total'] ?? 0)
                    + ($entry['bulan'][$month]['total'] ?? 0);
                $perSite[$entry['site']]['standar'] = ($perSite[$entry['site']]['standar'] ?? 0)
                    + ($entry['bulan'][$month]['standar'] ?? 0);
            }
        }

        ksort($perSite);
        $out = [];

        foreach ($perSite as $site => $agg) {
            $pct = $agg['total'] > 0 ? round($agg['standar'] / $agg['total'] * 100, 2) : 0.0;
            [, $nilai] = $this->scoreBandFor($pct);

            $out[] = [
                'site' => (string) $site,
                'percent' => $pct,
                'target' => self::TARGET_PERCENT,
                'nilai' => $nilai,
                'total' => $agg['total'],
                'tidak_sesuai' => $agg['total'] - $agg['standar'],
            ];
        }

        return $out;
    }

    /**
     * Lima kombinasi site/perusahaan dengan capaian terendah.
     *
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @return array<int, array<string, mixed>>
     */
    private function buildTopTerendah(array $matrixRows): array
    {
        $rows = $matrixRows;

        usort($rows, static fn (array $a, array $b): int => $a['average'] <=> $b['average']);

        return array_map(static fn (array $r): array => [
            'site' => $r['site'],
            'mitra' => $r['mitra'],
            'percent' => $r['average'],
            'nilai' => $r['nilai'],
            'total' => $r['total'],
            'tidak_sesuai' => $r['tidak_sesuai'],
        ], array_slice($rows, 0, 5));
    }

    /**
     * Rincian jenis ketidaksesuaian (Pareto) dan sebaran per area/pit.
     *
     * Keduanya diambil dari SATU query: dikelompokkan per pit dengan
     * penjumlahan bersyarat tiap jenis cek, lalu Pareto adalah totalnya.
     *
     * Catatan: satu segmen bisa gagal di lebih dari satu jenis cek, jadi
     * jumlah seluruh batang Pareto wajar melebihi jumlah segmen tidak sesuai.
     *
     * @return array{pareto: array<int, array<string, mixed>>, per_area: array<int, array<string, mixed>>}
     */
    private function buildParetoDanArea(Request $request): array
    {
        $rows = $this->applyOverviewFilters($this->baseQuery(), $request)
            ->selectRaw(
                'pit,'
                . ' COUNT(*) AS total,'
                . $this->jenisCekSelect()
                . ' SUM(NOT ' . self::STANDARD_SQL . ') AS tidak_sesuai'
            )
            ->groupBy('pit')
            ->get();

        $jenis = array_map(static fn (array $j): string => $j[0], self::JENIS_CEK);

        $totalJenis = array_fill_keys(array_keys($jenis), 0);
        $perArea = [];
        $totalTidakSesuai = 0;

        foreach ($rows as $row) {
            foreach (array_keys($jenis) as $key) {
                $totalJenis[$key] += (int) $row->$key;
            }

            $tidak = (int) $row->tidak_sesuai;
            $totalTidakSesuai += $tidak;

            $perArea[] = [
                'area' => (string) ($row->pit ?? '-'),
                'total' => (int) $row->total,
                'tidak_sesuai' => $tidak,
                'percent' => (int) $row->total > 0 ? round($tidak / (int) $row->total * 100, 2) : 0.0,
            ];
        }

        // Pareto: urut terbanyak, lalu persentase kumulatif.
        arsort($totalJenis);
        $grandJenis = array_sum($totalJenis);
        $pareto = [];
        $kumulatif = 0;

        foreach ($totalJenis as $key => $jumlah) {
            $kumulatif += $jumlah;
            $pareto[] = [
                'label' => $jenis[$key],
                'jumlah' => $jumlah,
                'percent' => $grandJenis > 0 ? round($jumlah / $grandJenis * 100, 1) : 0.0,
                'kumulatif' => $grandJenis > 0 ? round($kumulatif / $grandJenis * 100, 1) : 0.0,
            ];
        }

        // Area diurutkan dari yang paling banyak tidak sesuai, ambil 8 teratas
        // supaya grafiknya tetap terbaca (ada 31 pit).
        usort($perArea, static fn (array $a, array $b): int => $b['tidak_sesuai'] <=> $a['tidak_sesuai']);
        $top = array_slice($perArea, 0, 8);
        $sisa = array_slice($perArea, 8);

        if ($sisa !== []) {
            $top[] = [
                'area' => 'Lainnya (' . count($sisa) . ' area)',
                'total' => array_sum(array_column($sisa, 'total')),
                'tidak_sesuai' => array_sum(array_column($sisa, 'tidak_sesuai')),
                'percent' => 0.0,
            ];
        }

        return [
            'pareto' => $pareto,
            'pareto_total_segmen' => $totalTidakSesuai,
            'per_area' => $top,
        ];
    }

    /** Overview hanya memakai filter yang masuk akal untuk rekap. */
    private function applyOverviewFilters(Builder $query, Request $request): Builder
    {
        foreach (['site', 'mitra', 'pit', 'year'] as $column) {
            $value = trim((string) $request->input($column, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $this->applyMonthFilter($query, $request);

        return $query;
    }

    /**
     * Baris matriks: satu baris per site+mitra, berisi persentase tiap bulan,
     * rata-rata, Nilai, dan arah tren bulan terakhir vs bulan sebelumnya.
     *
     * @param  array<string, mixed>  $matrix
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    private function buildOverviewMatrix(array $matrix, array $months): array
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
                    $cells[] = null; // bulan tanpa data: sel dibiarkan kosong
                    continue;
                }

                $standar = $entry['bulan'][$month]['standar'] ?? 0;
                $pct = round($standar / $total * 100, 2);

                // Nilai per sel dihitung di sini, bukan di JavaScript, supaya
                // ambangnya selalu sama dengan SCORE_BANDS — satu sumber
                // kebenaran untuk kartu Nilai, matriks, dan ringkasan.
                [, $cellNilai, $cellBand] = $this->scoreBandFor($pct);

                $cells[] = [
                    'pct' => $pct,
                    'total' => $total,
                    'standar' => $standar,
                    'nilai' => $cellNilai,
                    'nilai_band' => $cellBand,
                ];
                $filled[] = $pct;
                $grandTotal += $total;
                $grandStandar += $standar;
            }

            $avg = $grandTotal > 0 ? round($grandStandar / $grandTotal * 100, 2) : 0.0;
            [, $nilai, $band] = $this->scoreBandFor($avg);

            $trend = null;
            if (count($filled) >= 2) {
                $last = $filled[count($filled) - 1];
                $prev = $filled[count($filled) - 2];
                $trend = $last >= $prev ? 'up' : 'down';
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
     * @param  array<string, array{total: int, standar: int}>  $perusahaan
     * @return array<int, array<string, mixed>>
     */
    private function buildOverviewPerusahaan(array $perusahaan): array
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
     * Seri bulanan per mitra untuk grafik perbandingan.
     *
     * @param  array<string, mixed>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $matrix, array $months): array
    {
        $byMitra = [];

        foreach ($matrix as $entry) {
            foreach ($months as $month) {
                $total = $entry['bulan'][$month]['total'] ?? 0;
                $byMitra[$entry['mitra']][$month]['total'] = ($byMitra[$entry['mitra']][$month]['total'] ?? 0) + $total;
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
                // null, bukan 0: bulan tanpa data harus putus di grafik,
                // bukan terbaca sebagai capaian 0%.
                $data[] = $total > 0 ? round($perMonth[$month]['standar'] / $total * 100, 2) : null;
            }

            $series[] = ['name' => (string) $mitra, 'data' => $data];
        }

        return [
            'labels' => array_map(fn (int $m): string => self::MONTH_LABELS[$m] ?? '-', $months),
            'series' => $series,
        ];
    }

    /**
     * Seri mingguan per mitra untuk grafik perbandingan.
     *
     * @param  array<string, mixed>  $weekBuckets
     * @param  array<int, string>  $weeks
     * @return array<string, mixed>
     */
    private function buildWeeklySeries(array $weekBuckets, array $weeks): array
    {
        ksort($weekBuckets);
        $series = [];

        foreach ($weekBuckets as $mitra => $perWeek) {
            $data = [];

            foreach ($weeks as $week) {
                $total = $perWeek[$week]['total'] ?? 0;
                $data[] = $total > 0 ? round($perWeek[$week]['standar'] / $total * 100, 2) : null;
            }

            $series[] = ['name' => (string) $mitra, 'data' => $data];
        }

        // Label minggu diberi tahun HANYA bila datanya memuat lebih dari satu
        // tahun. Tanpa ini, baris ber-year 2029 (141 baris, kemungkinan salah
        // ketik) muncul sebagai "W29" setelah "W39" dan terlihat seperti
        // sumbu yang kacau, padahal urutannya memang kronologis.
        $years = array_unique(array_map(static fn (string $w): string => substr($w, 0, 4), $weeks));
        $withYear = count($years) > 1;

        return [
            'labels' => array_map(
                static fn (string $w): string => $withYear
                    ? 'W' . (int) substr($w, 5) . " '" . substr($w, 2, 2)
                    : 'W' . (int) substr($w, 5),
                $weeks
            ),
            'series' => $series,
        ];
    }


    /**
     * Potongan SELECT penjumlahan bersyarat untuk tiap jenis cek.
     * Diakhiri koma, karena selalu dipakai di tengah daftar kolom.
     */
    private function jenisCekSelect(): string
    {
        $parts = [];

        foreach (self::JENIS_CEK as $key => [, $sql]) {
            $parts[] = ' SUM(' . $sql . ') AS ' . $key . ',';
        }

        return implode('', $parts);
    }

    // ======================================================================
    // Rincian satu sel matriks "Capaian per Bulan"
    // ======================================================================

    /**
     * Isi modal untuk satu sel matriks bulanan (site x perusahaan x bulan).
     *
     * Ukuran selnya adalah PERSENTASE segmen yang memenuhi standar, dengan
     * penyebut jumlah segmen yang dievaluasi di sel itu. Keduanya nyata
     * tersimpan per baris, jadi band Nilai di modal memakai scoreBandFor()
     * yang sama dengan sel — tidak ada angka yang dikarang.
     *
     * Dua hal yang menentukan bentuk jawaban ini:
     *
     *  1. Butiran tabelnya MINGGU, bukan bulan. overview() mengelompokkan
     *     per (site, mitra, year, week) lalu menurunkan bulan dari nomor
     *     minggu dengan aturan Kamis (monthOfIsoWeek()). Endpoint ini memakai
     *     weeksOfMonth() — helper yang sama yang kini dipakai filter Bulan —
     *     sehingga sel dan modal mustahil menjaring minggu yang berbeda.
     *     Itu penting: 8 dari 40 minggu di data ini membelah dua bulan.
     *
     *  2. Satu sel bisa berisi belasan ribu segmen (terpadat: GMO/PAMA
     *     Januari, 12.236 segmen). Daftar mentah karena itu tidak dikirim.
     *     Yang dikirim pecahan ringkas — per minggu, per pit, per jenis cek —
     *     ditambah paling banyak BATAS_BARIS_MODAL contoh segmen yang paling
     *     banyak gagal ceknya.
     */
    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));
        $pit = trim((string) $request->input('pit', ''));

        // Matriks halaman ini mengirim nomor bulan 1-12, tapi halaman lain
        // memakai kode tahun*100+bulan. Diterima keduanya supaya pemanggil
        // tidak perlu tahu bentuk mana yang dipakai.
        $kode = (int) $request->input('month', 0);
        $bulan = $kode > 9999 ? $kode % 100 : $kode;
        $tahun = $kode > 9999 ? intdiv($kode, 100) : (int) $request->input('year', 0);

        if ($site === '' || $bulan < 1 || $bulan > 12) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Site dan bulan wajib diisi untuk membuka rincian.',
            ]);
        }

        $weeksByYear = $this->weeksOfMonth($bulan, $tahun > 0 ? $tahun : null);

        if ($weeksByYear === []) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Tidak ada minggu yang jatuh di ' . (self::MONTH_LABELS[$bulan] ?? '-')
                    . ' pada data yang tersimpan.',
            ]);
        }

        $dasar = function () use ($site, $mitra, $pit, $weeksByYear): Builder {
            $query = $this->baseQuery()->where('site', $site);

            if ($mitra !== '') {
                $query->where('mitra', $mitra);
            }

            // Filter pit di dashboard ikut membentuk sel, jadi ikut dibawa
            // ke sini — kalau tidak, modal memecah sel yang lebih besar
            // daripada yang diklik.
            if ($pit !== '') {
                $query->where('pit', $pit);
            }

            $this->whereYearWeeks($query, $weeksByYear);

            return $query;
        };

        $perMinggu = $this->rincianPerMinggu($dasar());
        $total = array_sum(array_column($perMinggu, 'total'));
        $standar = array_sum(array_column($perMinggu, 'standar'));

        if ($total === 0) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Tidak ada segmen terevaluasi untuk kombinasi ini.',
            ]);
        }

        [$perPit, $perJenis] = $this->rincianPerPitDanJenis($dasar());

        $pct = round($standar / $total * 100, 2);
        [, $nilai, $band] = $this->scoreBandFor($pct);

        $tidakSesuai = $total - $standar;
        $contoh = $tidakSesuai > 0 ? $this->contohSegmen($dasar()) : [];
        $terpotong = count($contoh) > self::BATAS_BARIS_MODAL;

        if ($terpotong) {
            $contoh = array_slice($contoh, 0, self::BATAS_BARIS_MODAL);
        }

        return response()->json([
            'ok' => true,
            'judul' => [
                'site' => $site,
                'mitra' => $mitra,
                'pit' => $pit,
                'bulan' => self::MONTH_LABELS[$bulan] ?? '-',
            ],
            'ringkas' => [
                'total' => $total,
                'standar' => $standar,
                'tidak_sesuai' => $tidakSesuai,
                'percent' => $pct,
                'tidak_sesuai_percent' => round($tidakSesuai / $total * 100, 2),
                'nilai' => $nilai,
                'nilai_band' => $band,
                'target' => self::TARGET_PERCENT,
                'memenuhi_target' => $pct >= self::TARGET_PERCENT,
                'pit' => count($perPit),
                'ruas' => (int) ($dasar()->distinct()->count('nama_jalan')),
                'minggu' => count($perMinggu),
            ],
            'per_minggu' => $perMinggu,
            'per_pit' => $perPit,
            'per_jenis' => $perJenis,
            'terpotong' => $terpotong,
            'batas' => self::BATAS_BARIS_MODAL,
            'baris' => $contoh,
        ]);
    }

    /**
     * Minggu-minggu penyusun sel ini.
     *
     * Agregatnya persis seperti overview(): COUNT(*) dan SUM(STANDARD_SQL)
     * atas pengelompokan year+week yang sama, jadi penjumlahannya di PHP
     * menghasilkan angka yang identik dengan sel.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rincianPerMinggu(Builder $query): array
    {
        return $query
            ->selectRaw('year, week, COUNT(*) AS total, SUM(' . self::STANDARD_SQL . ') AS standar')
            ->groupBy('year', 'week')
            ->orderBy('year')
            ->orderBy('week')
            ->get()
            ->map(function (object $row): array {
                $total = (int) $row->total;
                $standar = (int) $row->standar;

                return [
                    'tahun' => (int) $row->year,
                    'minggu' => (int) $row->week,
                    'total' => $total,
                    'standar' => $standar,
                    'tidak_sesuai' => $total - $standar,
                    'percent' => $total > 0 ? round($standar / $total * 100, 2) : 0.0,
                ];
            })
            ->all();
    }

    /**
     * Sebaran per pit sekaligus pecahan jenis ketidaksesuaian, dari satu query.
     *
     * Catatan kejujuran: satu segmen bisa gagal di lebih dari satu cek, jadi
     * jumlah seluruh batang "per jenis" wajar melebihi cacah segmen tidak
     * sesuai. Itu dinyatakan di modal, bukan disembunyikan.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function rincianPerPitDanJenis(Builder $query): array
    {
        $rows = $query
            ->selectRaw(
                'pit,'
                . ' COUNT(*) AS total,'
                . ' COUNT(DISTINCT nama_jalan) AS ruas,'
                . $this->jenisCekSelect()
                . ' SUM(' . self::STANDARD_SQL . ') AS standar'
            )
            ->groupBy('pit')
            ->get();

        $totalJenis = array_fill_keys(array_keys(self::JENIS_CEK), 0);
        $perPit = [];

        foreach ($rows as $row) {
            foreach (array_keys(self::JENIS_CEK) as $key) {
                $totalJenis[$key] += (int) $row->$key;
            }

            $total = (int) $row->total;
            $standar = (int) $row->standar;

            $perPit[] = [
                'pit' => trim((string) ($row->pit ?? '')) ?: '-',
                'total' => $total,
                'standar' => $standar,
                'tidak_sesuai' => $total - $standar,
                'ruas' => (int) $row->ruas,
                'percent' => $total > 0 ? round($standar / $total * 100, 2) : 0.0,
            ];
        }

        // Pit yang paling banyak menyumbang segmen tidak sesuai di atas:
        // itu yang perlu ditindaklanjuti, bukan yang kebetulan terbesar.
        usort($perPit, static fn (array $a, array $b): int => $b['tidak_sesuai'] <=> $a['tidak_sesuai']);

        arsort($totalJenis);
        $perJenis = [];

        foreach ($totalJenis as $key => $jumlah) {
            $perJenis[] = [
                'label' => self::JENIS_CEK[$key][0],
                'jumlah' => $jumlah,
            ];
        }

        return [$perPit, $perJenis];
    }

    /**
     * Contoh segmen tidak sesuai, yang paling banyak gagal ceknya di atas.
     *
     * Hanya segmen tidak sesuai yang didaftar: segmen standar tidak punya
     * yang perlu dibaca satu per satu, dan sel terbesar berisi belasan ribu
     * baris. Diambil satu lebih banyak dari batas supaya pemanggil tahu
     * daftarnya terpotong.
     *
     * @return array<int, array<string, mixed>>
     */
    private function contohSegmen(Builder $query): array
    {
        $bobot = implode(' + ', array_map(
            static fn (array $j): string => '(' . $j[1] . ')',
            array_values(self::JENIS_CEK)
        ));

        return $query
            ->whereRaw('NOT ' . self::STANDARD_SQL)
            ->select([
                'pit', 'mitra', 'year', 'week', 'nama_jalan', 'segment',
                'grade_stat', 'road_width', 'supereleva', 'junction_1', 'junction_s',
            ])
            ->selectRaw('(' . $bobot . ') AS gagal')
            ->orderByRaw('(' . $bobot . ') DESC')
            ->orderBy('pit')
            ->orderBy('nama_jalan')
            ->orderBy('segment')
            ->limit(self::BATAS_BARIS_MODAL + 1)
            ->get()
            ->map(static function (object $row): array {
                $teks = static fn ($value): string => trim((string) $value);

                return [
                    'pit' => $teks($row->pit) ?: '-',
                    'mitra' => $teks($row->mitra) ?: '-',
                    'tahun' => (int) $row->year,
                    'minggu' => (int) $row->week,
                    'nama_jalan' => $teks($row->nama_jalan) ?: '-',
                    'segment' => (int) $row->segment,
                    'grade_stat' => $teks($row->grade_stat) ?: '-',
                    'road_width' => $teks($row->road_width) ?: '-',
                    'supereleva' => $teks($row->supereleva) ?: '-',
                    'junction_1' => $teks($row->junction_1) ?: '-',
                    'junction_s' => $teks($row->junction_s) ?: '-',
                    'gagal' => (int) $row->gagal,
                ];
            })
            ->all();
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $format = strtolower(trim((string) $request->input('format', 'xlsx')));
        $format = $format === 'csv' ? 'csv' : 'xlsx';

        $query = $this->buildFilteredQuery($request);
        $total = (clone $query)->count();

        if ($format === 'xlsx' && $total > self::MAX_XLSX_ROWS) {
            return response()->json([
                'message' => sprintf(
                    'Hasil filter %s baris, melebihi batas %s baris untuk format Excel (.xlsx). '
                    . 'Persempit filter — misalnya pilih satu bulan atau satu site — atau unduh sebagai CSV.',
                    number_format($total, 0, ',', '.'),
                    number_format(self::MAX_XLSX_ROWS, 0, ',', '.')
                ),
                'total' => $total,
                'max' => self::MAX_XLSX_ROWS,
            ], 422);
        }

        $query
            ->select([
                'site', 'pit', 'mitra', 'year', 'week', 'nama_jalan',
                'segment', 'grade_stat', 'road_width', 'supereleva',
                'junction_1', 'junction_s',
            ])
            ->selectRaw(self::STANDARD_SQL . ' AS is_standar')
            ->orderBy('site')
            ->orderBy('year')
            ->orderBy('week')
            ->orderBy('nama_jalan')
            ->orderBy('segment')
            ->orderBy('id');

        $filename = 'jalan-sesuai-standar-' . now()->format('Ymd-His') . '.' . $format;

        return $format === 'csv'
            ? $this->streamCsv($query, $filename)
            : $this->streamXlsx($query, $filename);
    }

    /** @return array<int, string> */
    private function exportHeaders(): array
    {
        return [
            'Site', 'Pit', 'Mitra', 'Tahun', 'Minggu', 'Bulan', 'Nama Jalan', 'Segmen',
            'Grade', 'Lebar Jalan', 'Superelevasi', 'Junction 1', 'Junction S', 'Kesimpulan',
        ];
    }

    /**
     * Satu baris database -> satu baris file.
     *
     * @return array<int, string|int>
     */
    private function exportRow(object $row): array
    {
        $month = $this->monthOfIsoWeek((int) $row->year, (int) $row->week);

        return [
            (string) $row->site,
            (string) $row->pit,
            (string) $row->mitra,
            (int) $row->year,
            (int) $row->week,
            self::MONTH_LABELS[$month] ?? '-',
            (string) $row->nama_jalan,
            (int) $row->segment,
            (string) $row->grade_stat,
            (string) $row->road_width,
            (string) $row->supereleva,
            (string) $row->junction_1,
            (string) $row->junction_s,
            $row->is_standar ? 'STANDAR' : 'TIDAK STANDAR',
        ];
    }

    /**
     * Sheet kosong berisi header bergaya.
     *
     * Sengaja TIDAK memakai SpreadsheetExporter::createSheetWithHeaders(),
     * karena helper itu menyalakan setAutoSize(true) untuk tiap kolom.
     * Auto-size memaksa PhpSpreadsheet mengukur lebar teks tiap sel, dan pada
     * ekspor sebesar ini biayanya sekitar 3x lipat (12 rb baris: 41 dtk dengan
     * auto-size vs ~13 dtk tanpa). Lebar kolom di sini dipatok manual.
     */
    /**
     * Naikkan memory_limit ke $megabytes bila saat ini lebih rendah.
     * Tidak pernah menurunkan, dan membiarkan konfigurasi tak terbatas (-1).
     */
    private function raiseMemoryLimitTo(int $megabytes): void
    {
        $current = trim((string) ini_get('memory_limit'));

        if ($current === '-1') {
            return;
        }

        $unit = strtolower(substr($current, -1));
        $value = (int) $current;
        $currentMb = match ($unit) {
            'g' => $value * 1024,
            'm' => $value,
            'k' => intdiv($value, 1024),
            default => intdiv($value, 1048576),
        };

        if ($currentMb < $megabytes) {
            ini_set('memory_limit', $megabytes . 'M');
        }
    }

    private function newExportSpreadsheet(): Spreadsheet
    {
        $widths = [10, 14, 10, 8, 9, 12, 26, 9, 13, 13, 14, 12, 12, 16];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jalan Sesuai Standar');
        $sheet->fromArray($this->exportHeaders(), null, 'A1');

        $lastColumn = Coordinate::stringFromColumnIndex(count($this->exportHeaders()));

        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }

        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    private function streamCsv(Builder $query, string $filename): StreamedResponse
    {
        return response()->stream(
            function () use ($query): void {
                // Seluruh tabel (±140 rb baris) butuh lebih dari 30 dtk default,
                // walau memorinya datar karena ditulis per potongan.
                set_time_limit(0);

                $out = fopen('php://output', 'wb');

                // BOM UTF-8: tanpa ini Excel di Windows merusak karakter non-ASCII.
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $this->exportHeaders(), ';');

                // cursor(), BUKAN chunk(). chunk() memakai LIMIT/OFFSET sehingga
                // MySQL mengulang ORDER BY atas seluruh hasil di setiap potongan
                // — pada 140 rb baris itu puluhan kali filesort dan praktis
                // menggantung. cursor() menjalankan satu query lalu menarik baris
                // satu per satu.
                $written = 0;
                foreach ($query->cursor() as $row) {
                    fputcsv($out, $this->exportRow($row), ';');

                    if ((++$written % 5000) === 0) {
                        flush();
                    }
                }

                fclose($out);
            },
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store, no-cache',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }

    private function streamXlsx(Builder $query, string $filename): StreamedResponse
    {
        return response()->stream(
            function () use ($query): void {
                // Membangun .xlsx itu mahal: diukur di data ini, ~24 dtk/176 MB
                // untuk 12 rb baris dan ~58 dtk/258 MB untuk 20 rb baris
                // (satu bulan penuh). Header respons sudah terkirim duluan,
                // jadi yang perlu dilonggarkan tinggal batas waktu & memori
                // proses — bukan menurunkan batas baris sampai sebulan penuh
                // tidak bisa diunduh.
                set_time_limit(0);
                $this->raiseMemoryLimitTo(768);

                $spreadsheet = $this->newExportSpreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                // cursor() dengan alasan yang sama seperti di streamCsv():
                // chunk() akan memaksa MySQL mengulang ORDER BY tiap potongan.
                // Penulisan tetap dikumpulkan per 2000 baris, karena fromArray()
                // sekali-banyak jauh lebih murah daripada setCellValue() per sel.
                $rowNumber = 2;
                $buffer = [];

                foreach ($query->cursor() as $row) {
                    $buffer[] = $this->exportRow($row);

                    if (count($buffer) === 2000) {
                        $sheet->fromArray($buffer, null, 'A' . $rowNumber);
                        $rowNumber += count($buffer);
                        $buffer = [];
                    }
                }

                if ($buffer !== []) {
                    $sheet->fromArray($buffer, null, 'A' . $rowNumber);
                }

                $writer = new Xlsx($spreadsheet);
                $writer->setPreCalculateFormulas(false);
                $writer->save('php://output');

                $spreadsheet->disconnectWorksheets();
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store, no-cache',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }

    private function baseQuery(): Builder
    {
        return DB::table(self::TABLE);
    }

    /** Jumlah seluruh baris; berubah hanya saat ada ingest baru, jadi aman di-cache. */
    private function totalCount(): int
    {
        return (int) Cache::remember(
            'ohs-score-card.road-summary.total',
            self::FILTER_CACHE_TTL,
            fn (): int => $this->baseQuery()->count()
        );
    }

    /**
     * Ringkasan untuk kondisi tanpa filter — sama untuk semua pengguna, jadi di-cache.
     *
     * @return array<string, int|float>
     */
    private function totalSummary(): array
    {
        return Cache::remember(
            'ohs-score-card.road-summary.summary',
            self::FILTER_CACHE_TTL,
            fn (): array => $this->summarise($this->baseQuery())
        );
    }

    /**
     * Berapa dropdown filter yang sedang terisi — termasuk Kesimpulan, yang
     * bukan kolom fisik. Kalau Kesimpulan tidak ikut dihitung, jalur cepat
     * "tanpa filter" akan keliru menyajikan total & ringkasan seluruh tabel.
     */
    private function activeFilterCount(Request $request): int
    {
        $count = 0;

        foreach (self::FILTERABLE as $column) {
            if (trim((string) $request->input($column, '')) !== '') {
                $count++;
            }
        }

        if (in_array(
            trim((string) $request->input('kesimpulan', '')),
            [self::CONCLUSION_STANDARD, self::CONCLUSION_NOT_STANDARD],
            true
        )) {
            $count++;
        }

        $month = (int) $request->input('month', 0);

        if ($month >= 1 && $month <= 12) {
            $count++;
        }

        return $count;
    }

    /**
     * Query dengan seluruh filter terpasang. Dipakai bersama oleh data() dan
     * export(), supaya isi file unduhan dijamin sama persis dengan yang
     * sedang tampil di tabel.
     */
    private function buildFilteredQuery(Request $request): Builder
    {
        $query = $this->applyFilters($this->baseQuery(), $request);
        $this->applyMonthFilter($query, $request);
        $this->applyConclusionFilter($query, $request);
        $this->applySearch($query, (string) $request->input('search.value', $request->input('search', '')));

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        foreach (self::FILTERABLE as $column) {
            $value = trim((string) $request->input($column, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        return $query;
    }

    /**
     * Band Nilai untuk sebuah persentase.
     *
     * @return array{0: float, 1: int, 2: string} [ambang, nilai, label]
     */
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

        return [0.0, 1.0, '<95%'];
    }

    /**
     * Bulan pemilik sebuah minggu ISO: bulan tempat hari Kamis-nya jatuh.
     * Mengembalikan 0 bila nomor minggu tidak masuk akal.
     */
    private function monthOfIsoWeek(int $year, int $week): int
    {
        if ($week < 1 || $week > 53 || $year < 1970) {
            return 0;
        }

        // Hari ke-4 pada minggu ISO = Kamis.
        return (int) (new \DateTimeImmutable())->setISODate($year, $week, 4)->format('n');
    }

    /**
     * Pasangan (year, week) yang benar-benar ada di data. Dipakai untuk
     * menyusun opsi dropdown Bulan sekaligus menerjemahkan filter bulan
     * menjadi daftar minggu.
     *
     * @return array<int, array{year: int, week: int}>
     */
    private function yearWeekPairs(): array
    {
        return Cache::remember(
            'ohs-score-card.road-summary.year-weeks',
            self::FILTER_CACHE_TTL,
            fn (): array => $this->baseQuery()
                ->select('year', 'week')
                ->whereNotNull('year')
                ->whereNotNull('week')
                ->distinct()
                ->orderBy('year')
                ->orderBy('week')
                ->get()
                ->map(static fn (object $row): array => [
                    'year' => (int) $row->year,
                    'week' => (int) $row->week,
                ])
                ->all()
        );
    }

    /**
     * Bulan yang ada datanya, untuk mengisi dropdown.
     *
     * @return array<int, string> [nomor bulan => label]
     */
    private function monthOptions(): array
    {
        $months = [];

        foreach ($this->yearWeekPairs() as $pair) {
            $month = $this->monthOfIsoWeek($pair['year'], $pair['week']);

            if ($month > 0) {
                $months[$month] = self::MONTH_LABELS[$month];
            }
        }

        ksort($months);

        return $months;
    }

    /**
     * Filter bulan. Diterjemahkan jadi daftar minggu per tahun, bukan fungsi
     * tanggal di WHERE — supaya MySQL tetap bisa memakai index pada year/week
     * dan tidak memaksa full scan di 140 ribu baris.
     */
    private function applyMonthFilter(Builder $query, Request $request): void
    {
        $month = (int) $request->input('month', 0);

        if ($month < 1 || $month > 12) {
            return;
        }

        $weeksByYear = $this->weeksOfMonth($month);

        if ($weeksByYear === []) {
            $query->whereRaw('1 = 0'); // bulan dipilih tapi tak ada datanya

            return;
        }

        $this->whereYearWeeks($query, $weeksByYear);
    }

    /**
     * Minggu mana saja yang dimiliki sebuah bulan, menurut aturan Kamis di
     * monthOfIsoWeek().
     *
     * Dipisahkan menjadi helper karena dipakai dua tempat yang WAJIB sepakat:
     * filter Bulan di tabel dan rincian satu sel matriks. Kalau keduanya
     * memakai penurunan minggu->bulan yang berbeda, modal akan menjaring
     * minggu yang tidak sama dengan selnya dan angkanya tidak akan cocok.
     *
     * @return array<int, array<int, int>> [tahun => daftar minggu]
     */
    private function weeksOfMonth(int $month, ?int $year = null): array
    {
        $weeksByYear = [];

        foreach ($this->yearWeekPairs() as $pair) {
            if ($year !== null && $pair['year'] !== $year) {
                continue;
            }

            if ($this->monthOfIsoWeek($pair['year'], $pair['week']) === $month) {
                $weeksByYear[$pair['year']][] = $pair['week'];
            }
        }

        return $weeksByYear;
    }

    /**
     * (year, week) yang diizinkan, ditulis sebagai OR per tahun supaya kolom
     * year/week dipakai apa adanya — index idx_road_summary_periode
     * (site, year, week, mitra) tetap terpakai, tidak ada fungsi tanggal di WHERE.
     *
     * @param  array<int, array<int, int>>  $weeksByYear
     */
    private function whereYearWeeks(Builder $query, array $weeksByYear): void
    {
        $query->where(function (Builder $outer) use ($weeksByYear): void {
            foreach ($weeksByYear as $year => $weeks) {
                $outer->orWhere(function (Builder $inner) use ($year, $weeks): void {
                    $inner->where('year', $year)->whereIn('week', $weeks);
                });
            }
        });
    }

    /**
     * Filter kolom Kesimpulan. Bukan kolom fisik, jadi dipakaikan ekspresi
     * STANDARD_SQL yang sama dengan yang menghasilkan nilainya.
     */
    private function applyConclusionFilter(Builder $query, Request $request): void
    {
        $value = trim((string) $request->input('kesimpulan', ''));

        if ($value === self::CONCLUSION_STANDARD) {
            $query->whereRaw(self::STANDARD_SQL . ' = 1');

            return;
        }

        if ($value === self::CONCLUSION_NOT_STANDARD) {
            $query->whereRaw(self::STANDARD_SQL . ' = 0');
        }
    }

    private function applyOrder(Builder $query, Request $request): void
    {
        $index = (int) data_get($request->input('order'), '0.column', 0);
        $direction = $this->orderDirection($request);

        if ($index === self::CONCLUSION_COLUMN_INDEX) {
            $query->orderByRaw(self::STANDARD_SQL . ' ' . $direction);

            return;
        }

        $query->orderBy(self::ORDERABLE[$index] ?? 'site', $direction);
    }

    private function applySearch(Builder $query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        // Escape wildcard LIKE supaya "%" / "_" dari user diperlakukan sebagai teks biasa.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);

        $query->where(function (Builder $inner) use ($escaped): void {
            foreach (self::SEARCHABLE as $column) {
                $inner->orWhere($column, 'like', '%' . $escaped . '%');
            }
        });
    }

    /**
     * Ringkasan kepatuhan atas hasil filter saat ini (bukan cuma halaman aktif).
     *
     * @return array<string, int|float>
     */
    private function summarise(Builder $query): array
    {
        $row = $query->selectRaw(
            'COUNT(*) AS total,'
            . " SUM(grade_stat = 'ACCEPT') AS grade_ok,"
            . " SUM(road_width = 'ACCEPT') AS width_ok,"
            . " SUM(supereleva = 'ACCEPT') AS super_ok,"
            . ' SUM(' . self::STANDARD_SQL . ') AS standar_ok'
        )->first();

        $total = (int) ($row->total ?? 0);
        $percent = static fn (int $ok): float => $total > 0 ? round($ok / $total * 100, 2) : 0.0;

        $gradeOk = (int) ($row->grade_ok ?? 0);
        $widthOk = (int) ($row->width_ok ?? 0);
        $superOk = (int) ($row->super_ok ?? 0);
        $standarOk = (int) ($row->standar_ok ?? 0);
        $standarPct = $percent($standarOk);

        // Tanpa baris sama sekali, persentasenya 0 — tapi itu "tidak ada data",
        // bukan capaian 0%. Jangan dilaporkan sebagai Nilai 1.
        if ($total === 0) {
            $nilai = 0;
            $nilaiBand = 'tidak ada data';
        } else {
            [, $nilai, $nilaiBand] = $this->scoreBandFor($standarPct);
        }

        return [
            'total' => $total,
            'grade_ok' => $gradeOk,
            'width_ok' => $widthOk,
            'super_ok' => $superOk,
            'standar_ok' => $standarOk,
            'grade_pct' => $percent($gradeOk),
            'width_pct' => $percent($widthOk),
            'super_pct' => $percent($superOk),
            'standar_pct' => $standarPct,
            'nilai' => $nilai,
            'nilai_band' => $nilaiBand,
        ];
    }

    /**
     * Nilai unik tiap kolom filter, untuk mengisi dropdown.
     *
     * @return array<string, array<int, string>>
     */
    private function filterOptions(): array
    {
        return Cache::remember('ohs-score-card.road-summary.filters', self::FILTER_CACHE_TTL, function (): array {
            $options = [];

            foreach (self::FILTERABLE as $column) {
                $options[$column] = $this->baseQuery()
                    ->select($column)
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->distinct()
                    ->orderBy($column)
                    ->pluck($column)
                    ->map(static fn ($value): string => (string) $value)
                    ->all();
            }

            return $options;
        });
    }

    private function orderDirection(Request $request): string
    {
        $dir = strtolower((string) data_get($request->input('order'), '0.dir', 'asc'));

        return $dir === 'desc' ? 'desc' : 'asc';
    }

    private function pageLength(Request $request): int
    {
        $length = (int) $request->input('length', self::DEFAULT_PAGE_LENGTH);

        if ($length < 1) {
            return self::DEFAULT_PAGE_LENGTH;
        }

        return min($length, self::MAX_PAGE_LENGTH);
    }

    private function page(Request $request): int
    {
        $start = max(0, (int) $request->input('start', 0));

        return (int) floor($start / $this->pageLength($request)) + 1;
    }
}
