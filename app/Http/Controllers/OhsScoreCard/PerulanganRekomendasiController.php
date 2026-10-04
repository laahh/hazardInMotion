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
 * Parameter SIRC "Perulangan rekomendasi hasil investigasi".
 *
 *   lead_perulangan_rekomendasi        -> cacah rekomendasi yang terulang
 *   detail_lead_perulangan_rekomendasi -> rincian tiap perulangan
 *
 * Yang dihitung: rekomendasi hasil investigasi insiden yang ternyata terulang
 * lagi, artinya tindakan perbaikannya tidak berjalan. TARGETNYA NOL, jadi sel
 * matriks yang tidak punya baris dikirim sebagai 0 dan diwarnai hijau, bukan
 * strip abu-abu yang berarti "datanya belum ada".
 *
 * KEDUA TABEL HAMPIR REKONSILIASI. Ringkasan mencatat 20 perulangan di 13
 * baris (satu baris bisa bernilai 1, 2, atau 4), detail mencatat 19 baris
 * masing-masing bernilai 1. Dibandingkan per site-perusahaan-bulan, 12 dari 13
 * kombinasi cocok persis; satu kombinasi (BMO 1 / PT Madhani Talatah Nusantara
 * / Mei, 1 perulangan) ada di ringkasan tetapi belum punya rincian. Karena itu
 * matriks dan kartu memakai tabel ringkasan, tab Data memakai tabel detail,
 * dan selisihnya disebut di keterangan alih-alih ditutup-tutupi.
 *
 * DUA KOLOM SENGAJA TIDAK DIBUATKAN PANEL. activity_layer1 bernilai sama untuk
 * seluruh 19 baris ("12. Incident Investigation & Reporting"), dan
 * perusahaan_lead_investigasi selalu sama dengan perusahaan kecuali satu baris
 * yang kosong. Grafik dari kolom yang tidak pernah berbeda hanya menambah
 * ruang tanpa menambah informasi; keduanya tetap ikut di tab Data dan ekspor.
 *
 * NAMA KOLOMNYA BERBEDA dari parameter lain: site1, month_of_ccr_waktu_insiden,
 * count_of_layer_tindakan_perbaikan1.
 */
final class PerulanganRekomendasiController extends Controller
{
    use ServesDataTable;

    private const TABEL_RINGKASAN = 'lead_perulangan_rekomendasi';
    private const TABEL_DETAIL = 'detail_lead_perulangan_rekomendasi';

    private const COL_SITE = 'site1';
    private const COL_PERUSAHAAN = 'perusahaan';
    private const COL_LEAD = 'perusahaan_lead_investigasi';
    private const COL_BULAN = 'month_of_ccr_waktu_insiden';
    private const COL_JUMLAH = 'count_of_layer_tindakan_perbaikan1';

    /** Kolom yang hanya ada di tabel detail. */
    private const COL_ACTIVITY = 'activity_layer1';
    private const COL_KETERANGAN = 'keterangan_layer';
    private const COL_TINDAKAN = 'tindakan_perbaikan_pencegahan';

    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /** Oktober masih berjalan saat data ini diambil, sejalan halaman lain. */
    private const EXCLUDED_MONTHS = [10];

    private const DETAIL_FILTERABLE = [
        'site' => self::COL_SITE,
        'mitra' => self::COL_PERUSAHAAN,
    ];

    private const DETAIL_SEARCHABLE = [
        self::COL_SITE, self::COL_PERUSAHAAN, self::COL_LEAD,
        self::COL_ACTIVITY, self::COL_KETERANGAN, self::COL_TINDAKAN,
    ];

    private const DETAIL_ORDERABLE = [
        0 => self::COL_SITE,
        1 => self::COL_PERUSAHAAN,
    ];

    public function index(): View
    {
        return view('ohs-score-card.perulangan-rekomendasi.index', [
            'filterOptions' => [
                'site' => $this->gabungNilai([
                    [self::TABEL_RINGKASAN, self::COL_SITE],
                    [self::TABEL_DETAIL, self::COL_SITE],
                ]),
                'mitra' => $this->gabungNilai([
                    [self::TABEL_RINGKASAN, self::COL_PERUSAHAAN],
                    [self::TABEL_DETAIL, self::COL_PERUSAHAAN],
                ]),
            ],
            'monthOptions' => $this->monthOptions(),
            'tabel_ringkasan' => self::TABEL_RINGKASAN,
            'tabel_detail' => self::TABEL_DETAIL,
        ]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request): JsonResponse
    {
        $rows = $this->ringkasanQuery($request)
            ->selectRaw(
                self::COL_SITE . ' AS site, '
                . self::COL_PERUSAHAAN . ' AS mitra, '
                . self::COL_BULAN . ' AS bulan, '
                . 'SUM(' . self::COL_JUMLAH . ') AS jumlah'
            )
            ->groupBy('site', 'mitra', 'bulan')
            ->get();

        $grid = [];
        $monthSeen = [];

        foreach ($rows as $row) {
            $monthNo = $this->nomorBulan((string) $row->bulan);

            if ($monthNo === 0) {
                continue; // nama bulan tak dikenal: jangan diam-diam dianggap bulan lain
            }

            $monthSeen[$monthNo] = true;
            $site = trim((string) $row->site);
            $mitra = trim((string) $row->mitra);

            $grid[$site . '|' . $mitra]['site'] = $site;
            $grid[$site . '|' . $mitra]['mitra'] = $mitra;
            $grid[$site . '|' . $mitra]['bulan'][$monthNo] = (int) $row->jumlah;
        }

        // Bulan bersih di tengah rentang tetap jadi kolom: justru itu kabar
        // baiknya, dan menghilangkannya membuat bulan tanpa perulangan tak
        // terlihat.
        $months = $this->rentangBulan(array_keys($monthSeen));
        $matrix = $this->buildMatrix($grid, $months);

        return response()->json([
            'months' => $this->monthHeadings($months),
            'kpi' => $this->buildKpi($request, $matrix, $months),
            'matrix' => $matrix,
            'per_site' => $this->ringkasPer($matrix, 'site'),
            'per_mitra' => $this->ringkasPer($matrix, 'mitra'),
            'monthly' => $this->buildMonthlySeries($matrix, $months),
            'catatan' => $this->catatan($request),
        ]);
    }

    /**
     * Bulan yang ditampilkan: dari yang paling awal sampai paling akhir ada
     * perulangan, termasuk bulan di antaranya yang bersih.
     *
     * @param  array<int, int>  $ada
     * @return array<int, int>
     */
    private function rentangBulan(array $ada): array
    {
        if ($ada === []) {
            return [];
        }

        sort($ada);
        $out = [];

        for ($m = $ada[0]; $m <= $ada[count($ada) - 1]; $m++) {
            if (! in_array($m, self::EXCLUDED_MONTHS, true)) {
                $out[] = $m;
            }
        }

        return $out;
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
            $total = 0;
            $bersih = 0;

            foreach ($months as $month) {
                // 0, bukan null: tidak adanya baris berarti tidak ada
                // perulangan, dan itu kabar baik yang harus kelihatan.
                $jumlah = $entry['bulan'][$month] ?? 0;
                $cells[] = $jumlah;
                $total += $jumlah;

                if ($jumlah === 0) {
                    $bersih++;
                }
            }

            $out[] = [
                'site' => $entry['site'],
                'mitra' => $entry['mitra'],
                'cells' => $cells,
                'total' => $total,
                'puncak' => $cells === [] ? 0 : max($cells),
                'bulan_bersih' => $bersih,
                'bulan_kena' => count($months) - $bersih,
                'trend' => $this->trendOf($cells),
            ];
        }

        return $this->kelompokkanPerSite($out);
    }

    /**
     * Mengelompokkan baris per site supaya sel site-nya bisa digabung dengan
     * rowspan di tabel, site dengan perulangan terbanyak di atas.
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
            $bobot[$site] = array_sum(array_column($baris, 'total'));
        }

        arsort($bobot);
        $out = [];

        foreach (array_keys($bobot) as $site) {
            $baris = $perSite[$site];
            usort($baris, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
            $out = array_merge($out, $baris);
        }

        return $out;
    }

    /** @param  array<int, int>  $cells */
    private function trendOf(array $cells): ?string
    {
        if (count($cells) < 2) {
            return null;
        }

        $akhir = $cells[count($cells) - 1];
        $sebelum = $cells[count($cells) - 2];

        if ($akhir === $sebelum) {
            return 'flat';
        }

        // Naik berarti memburuk: perulangan bertambah.
        return $akhir > $sebelum ? 'up' : 'down';
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildKpi(Request $request, array $matrix, array $months): array
    {
        $total = array_sum(array_column($matrix, 'total'));
        $perBulan = array_fill(0, count($months), 0);

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $i => $jumlah) {
                $perBulan[$i] += $jumlah;
            }
        }

        $bulanBersih = count(array_filter($perBulan, static fn (int $n): bool => $n === 0));
        $terbanyak = $perBulan === [] ? 0 : max($perBulan);
        $indeksTerbanyak = array_search($terbanyak, $perBulan, true);

        return [
            'perulangan' => $total,
            'rincian' => $this->detailQueryFiltered($request)->count(),
            'kombinasi' => count($matrix),
            'site_count' => count(array_unique(array_column($matrix, 'site'))),
            'mitra_count' => count(array_unique(array_column($matrix, 'mitra'))),
            'bulan_count' => count($months),
            'bulan_bersih' => $bulanBersih,
            'rata_per_bulan' => $months === [] ? 0.0 : round($total / count($months), 1),
            'bulan_terburuk' => $indeksTerbanyak === false || $terbanyak === 0
                ? null
                : self::monthLabel($months[$indeksTerbanyak]),
            'perulangan_terburuk' => $terbanyak,
        ];
    }

    /**
     * Cacah perulangan per site atau per perusahaan.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function ringkasPer(array $matrix, string $key): array
    {
        $kelompok = [];

        foreach ($matrix as $row) {
            $lawan = $key === 'site' ? $row['mitra'] : $row['site'];
            $kelompok[$row[$key]]['jumlah'] = ($kelompok[$row[$key]]['jumlah'] ?? 0) + $row['total'];
            $kelompok[$row[$key]]['lawan'][$lawan] = true;
        }

        $grand = array_sum(array_column($kelompok, 'jumlah'));
        $out = [];

        foreach ($kelompok as $label => $agg) {
            $out[] = [
                $key => (string) $label,
                'jumlah' => $agg['jumlah'],
                // Untuk site berarti jumlah perusahaan yang terlibat, dan
                // sebaliknya.
                'lawan' => count($agg['lawan']),
                'percent' => $grand > 0 ? round($agg['jumlah'] / $grand * 100, 2) : 0.0,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['jumlah'] <=> $a['jumlah']);

        return $out;
    }

    /**
     * Satu garis total perulangan per bulan, ditambah garis per site teratas.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $matrix, array $months): array
    {
        $perSite = [];
        $total = array_fill(0, count($months), 0);

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $i => $jumlah) {
                $perSite[$row['site']][$i] = ($perSite[$row['site']][$i] ?? 0) + $jumlah;
                $total[$i] += $jumlah;
            }
        }

        uasort($perSite, static fn (array $a, array $b): int => array_sum($b) <=> array_sum($a));
        $series = [['name' => 'Semua site', 'data' => array_values($total)]];

        foreach (array_slice($perSite, 0, 6, true) as $site => $perBulan) {
            $data = [];

            foreach (array_keys($months) as $i) {
                $data[] = $perBulan[$i] ?? 0;
            }

            $series[] = ['name' => (string) $site, 'data' => $data];
        }

        return [
            'labels' => array_map(static fn (int $m): string => self::monthLabel($m), $months),
            'series' => $series,
        ];
    }

    private function catatan(Request $request): ?string
    {
        if (! DB::table(self::TABEL_RINGKASAN)->exists()) {
            return 'Tabel ' . self::TABEL_RINGKASAN . ' masih kosong. Untuk parameter ini tabel '
                . 'kosong justru berarti tidak ada rekomendasi yang terulang.';
        }

        $dasar = 'Sel bernilai 0 berarti tidak ada rekomendasi yang terulang pada bulan itu, '
            . 'bukan datanya belum masuk.';

        $perulangan = (int) (clone $this->ringkasanQuery($request))->sum(self::COL_JUMLAH);
        $rincian = $this->detailQueryFiltered($request)->count();

        if ($rincian === $perulangan) {
            return $dasar;
        }

        return $dasar . ' Tercatat ' . $perulangan . ' perulangan di ' . self::TABEL_RINGKASAN
            . ' tetapi baru ' . $rincian . ' yang punya rincian di ' . self::TABEL_DETAIL
            . ', jadi tab Data memuat lebih sedikit daripada angka di kartu.';
    }

    // ======================================================================
    // Tab Data (tabel detail)
    // ======================================================================

    public function data(Request $request): JsonResponse
    {
        $query = $this->detailQuery($request);

        $rows = (clone $query)
            ->select($this->detailColumns())
            ->orderBy(
                $this->dtOrderColumn($request, self::DETAIL_ORDERABLE, self::COL_SITE),
                $this->dtDirection($request, 'asc')
            )
            ->orderBy('id')
            ->forPage($this->dtPage($request), $this->dtPageLength($request))
            ->get()
            ->map(fn (object $row): array => $this->present($row))
            ->all();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $this->detailBaseCount(),
            'recordsFiltered' => (clone $query)->count(),
            'data' => $rows,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->detailQuery($request)
            ->select($this->detailColumns())
            ->orderBy(self::COL_SITE)
            ->orderBy(self::COL_PERUSAHAAN)
            ->orderBy('id');

        return $this->dtExport(
            $request,
            $query,
            ['Site', 'Perusahaan', 'Perusahaan Lead Investigasi', 'Bulan', 'Activity Layer',
             'Keterangan', 'Tindakan Perbaikan & Pencegahan', 'Jumlah'],
            function (object $row): array {
                $p = $this->present($row);

                return [
                    $p['site'], $p['mitra'], $p['lead'], $p['bulan'], $p['activity'],
                    $p['keterangan'], $p['tindakan'], $p['jumlah'],
                ];
            },
            'perulangan-rekomendasi'
        );
    }

    /** @return array<int, string> */
    private function detailColumns(): array
    {
        return [
            self::COL_SITE . ' AS site',
            self::COL_PERUSAHAAN . ' AS mitra',
            self::COL_LEAD . ' AS lead',
            self::COL_BULAN . ' AS bulan_sumber',
            self::COL_ACTIVITY . ' AS activity',
            self::COL_KETERANGAN . ' AS keterangan',
            self::COL_TINDAKAN . ' AS tindakan',
            self::COL_JUMLAH . ' AS jumlah',
        ];
    }

    private function detailQuery(Request $request): Builder
    {
        $query = $this->detailQueryFiltered($request, self::DETAIL_FILTERABLE);

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            self::DETAIL_SEARCHABLE
        );

        return $query;
    }

    /**
     * Tabel detail dengan filter dimensi terpasang.
     *
     * @param  array<string, string>|null  $map  null memakai filter tab Ringkasan
     */
    private function detailQueryFiltered(Request $request, ?array $map = null): Builder
    {
        return $this->terapkanFilter(
            DB::table(self::TABEL_DETAIL),
            $request,
            $map ?? ['site' => self::COL_SITE, 'mitra' => self::COL_PERUSAHAAN]
        );
    }

    /** Tabel ringkasan dengan filter dimensi terpasang. */
    private function ringkasanQuery(Request $request): Builder
    {
        return $this->terapkanFilter(
            DB::table(self::TABEL_RINGKASAN),
            $request,
            ['site' => self::COL_SITE, 'mitra' => self::COL_PERUSAHAAN]
        );
    }

    /** @param  array<string, string>  $map */
    private function terapkanFilter(Builder $query, Request $request, array $map): Builder
    {
        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn(self::COL_BULAN, $this->namaBulan($nomor));
        }

        foreach ($map as $parameter => $column) {
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
        $monthNo = $this->nomorBulan((string) $row->bulan_sumber);

        // Keterangan dan tindakan memuat baris baru serta penomoran manual;
        // diratakan jadi satu baris agar tidak merusak tinggi baris tabel
        // maupun memecah sel CSV.
        $rata = static fn ($v): string => trim(preg_replace('/\s*\R\s*/u', ' ', (string) $v) ?? '');

        return [
            'site' => trim((string) $row->site),
            'mitra' => trim((string) $row->mitra),
            'lead' => trim((string) $row->lead),
            'bulan' => $monthNo === 0 ? trim((string) $row->bulan_sumber) : self::monthLabel($monthNo),
            'activity' => trim((string) $row->activity),
            'keterangan' => $rata($row->keterangan),
            'tindakan' => $rata($row->tindakan),
            'jumlah' => (int) $row->jumlah,
        ];
    }

    // ======================================================================
    // Utilitas
    // ======================================================================

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

    /**
     * Semua ejaan satu nomor bulan, untuk dipakai di whereIn.
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

    private function detailBaseCount(): int
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
     * @param  array<int, array{0: string, 1: string}>  $sumber
     * @return array<int, string>
     */
    private function gabungNilai(array $sumber): array
    {
        $out = [];

        foreach ($sumber as [$table, $column]) {
            $out = array_merge($out, $this->distinctValues($table, $column));
        }

        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * Bulan yang benar-benar ada di sumber, diurutkan kalender.
     *
     * @return array<int, string>
     */
    private function monthOptions(): array
    {
        $months = [];

        $sumber = [
            [self::TABEL_RINGKASAN, self::COL_BULAN],
            [self::TABEL_DETAIL, self::COL_BULAN],
        ];

        foreach ($sumber as [$table, $column]) {
            foreach ($this->distinctValues($table, $column) as $nilai) {
                $nomor = $this->nomorBulan($nilai);

                if ($nomor === 0 || in_array($nomor, self::EXCLUDED_MONTHS, true)) {
                    continue;
                }

                $months[$nomor] = self::monthLabel($nomor);
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
}
