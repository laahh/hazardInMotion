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
 * Parameter SOD "Blindspot TBC", untuk dua populasi PIC:
 *
 *   minecon -> lead_blindspot_tbc_month          + detail_lead_blindspot_tbc
 *   subcon  -> lead_blindspot_tbc_subcont_month  + detail_lead_subcont_blindspot_tbc
 *
 * Blindspot = temuan TBC di area sebuah perusahaan yang justru dilaporkan
 * pihak lain, bukan oleh pengawas perusahaan itu sendiri. Makin banyak
 * temuannya, makin banyak bahaya yang luput dari pengawasan si PIC.
 *
 * DUA UKURAN YANG BERBEDA, DAN SENGAJA TIDAK DICAMPUR:
 *   - Tabel detail memberi CACAH temuan. Itu yang mengisi matriks, kartu
 *     ringkasan, dan semua peringkat di tab Ringkasan.
 *   - Tabel bulanan memberi PERSENTASE resmi (Blindspot_TBC_dari_BC) per site
 *     x perusahaan x bulan. Itu tampil di panelnya sendiri.
 * Kolom pct_blindspot_tbc_dari_bc yang ikut di tabel detail tidak dipakai:
 * nilainya 100,00 di seluruh 63 baris yang ada, jadi tidak membedakan apa pun.
 *
 * KEADAAN DATA saat halaman ini dibuat (3 Oktober 2026): dari empat tabel di
 * atas hanya detail_lead_blindspot_tbc yang terisi, itu pun baru satu irisan
 * (63 temuan, seluruhnya SMO / PT Madhani Talatah Nusantara, Mei-September
 * 2026). Tiga tabel lain masih nol baris. Halaman ini tetap dibangun penuh dan
 * akan langsung hidup begitu tabelnya diisi; sementara itu tiap panel yang
 * sumbernya kosong menampilkan keterangan, bukan angka nol yang menyesatkan.
 */
final class BlindspotTbcController extends Controller
{
    use ServesDataTable;

    private const DATASETS = [
        'minecon' => [
            'label' => 'Minecon',
            'monthly' => 'lead_blindspot_tbc_month',
            'detail' => 'detail_lead_blindspot_tbc',
        ],
        'subcon' => [
            'label' => 'Subcon',
            'monthly' => 'lead_blindspot_tbc_subcont_month',
            'detail' => 'detail_lead_subcont_blindspot_tbc',
        ],
    ];

    private const DEFAULT_DATASET = 'minecon';

    /** Kolom tabel detail. */
    private const COL_SITE = 'site';
    private const COL_PIC_PERUSAHAAN = 'perusahaan_pic';
    private const COL_PIC_SID = 'sid_pic';
    private const COL_PIC_NAMA = 'pic';
    private const COL_PELAPOR_PERUSAHAAN = 'perusahaan_pelapor_all_karyawan';
    private const COL_PELAPOR_NAMA = 'pelapor_all_karyawan';
    private const COL_DESKRIPSI = 'deskripsi';
    private const COL_TASK = 'task_number';
    private const COL_BULAN = 'month_of_date_for_join';
    private const COL_TAHUN = 'year_of_date_for_join';

    /**
     * Kolom tabel bulanan. Namanya memakai huruf besar karena tabel itu hasil
     * scrape Tableau dan nama kolomnya mengikuti judul di sana apa adanya.
     */
    private const MON_SITE = 'site';
    private const MON_PERUSAHAAN = 'perusahaan_pic';
    private const MON_BULAN = 'Month_of_Date_for_Join';
    private const MON_PERSEN = 'Blindspot_TBC_dari_BC';

    /** Bulan tersimpan sebagai nama Inggris; dipetakan untuk urutan & label. */
    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /**
     * Bulan yang tidak ikut dihitung, sejalan dengan halaman Ratio TBC & GR:
     * Oktober masih berjalan saat data ini diambil.
     */
    private const EXCLUDED_MONTHS = ['October'];

    /** Berapa temuan sebulan sudah pantas disebut banyak. */
    private const AMBANG_TEMUAN_BULANAN = 5;

    private const DETAIL_FILTERABLE = [
        'site' => self::COL_SITE,
        'mitra' => self::COL_PIC_PERUSAHAAN,
        'pelapor' => self::COL_PELAPOR_PERUSAHAAN,
    ];

    private const DETAIL_SEARCHABLE = [
        self::COL_SITE, self::COL_PIC_PERUSAHAAN, self::COL_PIC_SID, self::COL_PIC_NAMA,
        self::COL_PELAPOR_PERUSAHAAN, self::COL_PELAPOR_NAMA, self::COL_DESKRIPSI,
    ];

    private const DETAIL_ORDERABLE = [
        0 => self::COL_SITE,
        1 => self::COL_PIC_PERUSAHAAN,
        2 => self::COL_PIC_NAMA,
        3 => self::COL_PELAPOR_NAMA,
        5 => self::COL_BULAN,
        6 => self::COL_TASK,
    ];

    public function index(): View
    {
        $datasets = [];

        foreach (array_keys(self::DATASETS) as $slug) {
            $detail = $this->table($slug, 'detail');

            $datasets[$slug] = [
                'slug' => $slug,
                'label' => self::DATASETS[$slug]['label'],
                'monthly_table' => $this->table($slug, 'monthly'),
                'detail_table' => $detail,
                'filterOptions' => [
                    'site' => $this->distinctValues($detail, self::COL_SITE),
                    'mitra' => $this->distinctValues($detail, self::COL_PIC_PERUSAHAAN),
                    'pelapor' => $this->distinctValues($detail, self::COL_PELAPOR_PERUSAHAAN),
                ],
                'monthOptions' => $this->monthOptions($slug),
            ];
        }

        return view('ohs-score-card.blindspot-tbc.index', ['datasets' => $datasets]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request, string $dataset = self::DEFAULT_DATASET): JsonResponse
    {
        $dataset = $this->dataset($dataset);

        $rows = $this->detailQueryFiltered($request, $dataset)
            ->selectRaw(
                self::COL_SITE . ' AS site, '
                . self::COL_PIC_PERUSAHAAN . ' AS mitra, '
                . self::COL_BULAN . ' AS bulan, '
                . 'COUNT(*) AS jumlah'
            )
            ->groupBy('site', 'mitra', 'bulan')
            ->orderBy('site')
            ->orderBy('mitra')
            ->get();

        $matrix = [];
        $monthSeen = [];
        $perMitra = [];

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
            $matrix[$key]['bulan'][$monthNo] = ($matrix[$key]['bulan'][$monthNo] ?? 0) + (int) $row->jumlah;

            $perMitra[$mitra] = ($perMitra[$mitra] ?? 0) + (int) $row->jumlah;
        }

        ksort($monthSeen);
        ksort($matrix);
        $months = array_keys($monthSeen);
        $matrixRows = $this->buildMatrix($matrix, $months);

        return response()->json([
            'months' => $this->monthHeadings($months),
            'kpi' => $this->buildKpi($request, $dataset, $matrixRows, $months),
            'matrix' => $matrixRows,
            'per_mitra' => $this->buildRanking($perMitra),
            'per_site' => $this->buildPerSite($matrixRows),
            'per_pic' => $this->buildPerPic($request, $dataset),
            'per_pelapor' => $this->buildPerPelapor($request, $dataset),
            'monthly' => $this->buildMonthlySeries($matrix, $months),
            'resmi' => $this->buildPersenResmi($request, $dataset),
            'catatan' => $this->catatan($request, $dataset),
        ]);
    }

    /**
     * Persentase resmi dari tabel bulanan hasil scrape Tableau.
     *
     * Dipisah dari matriks temuan karena ukurannya lain: yang satu cacah
     * temuan, yang satu bagian temuan yang datang dari luar. Mencampurnya
     * dalam satu tabel akan membuat angka di sel tidak jelas artinya.
     *
     * @return array<string, mixed>
     */
    private function buildPersenResmi(Request $request, string $dataset): array
    {
        $table = $this->table($dataset, 'monthly');

        $query = DB::table($table);

        if (self::EXCLUDED_MONTHS !== []) {
            $query->whereNotIn(self::MON_BULAN, self::EXCLUDED_MONTHS);
        }

        foreach (['site' => self::MON_SITE, 'mitra' => self::MON_PERUSAHAAN] as $parameter => $column) {
            $value = trim((string) $request->input($parameter, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $month = (int) $request->input('month', 0);

        if ($month >= 1 && $month <= 12) {
            $query->whereIn(self::MON_BULAN, $this->monthNames($month));
        }

        $rows = $query
            ->selectRaw(
                self::MON_SITE . ' AS site, '
                . self::MON_PERUSAHAAN . ' AS mitra, '
                . self::MON_BULAN . ' AS bulan, '
                . 'AVG(' . self::MON_PERSEN . ') AS persen'
            )
            ->groupBy('site', 'mitra', 'bulan')
            ->orderBy('site')
            ->orderBy('mitra')
            ->get();

        $grid = [];
        $monthSeen = [];

        foreach ($rows as $row) {
            $monthNo = self::MONTH_MAP[$row->bulan][0] ?? 0;

            if ($monthNo === 0) {
                continue;
            }

            $monthSeen[$monthNo] = true;
            $key = trim((string) $row->site) . '|' . trim((string) $row->mitra);

            $grid[$key]['site'] = trim((string) $row->site);
            $grid[$key]['mitra'] = trim((string) $row->mitra);
            $grid[$key]['bulan'][$monthNo] = round((float) $row->persen, 2);
        }

        ksort($monthSeen);
        ksort($grid);
        $months = array_keys($monthSeen);

        $out = [];

        foreach ($grid as $entry) {
            $cells = [];
            $terisi = [];

            foreach ($months as $monthNo) {
                $value = $entry['bulan'][$monthNo] ?? null;
                $cells[] = $value;

                if ($value !== null) {
                    $terisi[] = $value;
                }
            }

            $out[] = [
                'site' => $entry['site'],
                'mitra' => $entry['mitra'],
                'cells' => $cells,
                'average' => $terisi !== [] ? round(array_sum($terisi) / count($terisi), 2) : null,
            ];
        }

        return [
            'tersedia' => $out !== [],
            'tabel' => $table,
            'months' => $this->monthHeadings($months),
            'rows' => $out,
        ];
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
            ->orderBy(self::COL_PIC_PERUSAHAAN)
            ->orderBy('id');

        return $this->dtExport(
            $request,
            $query,
            [
                'Site', 'Perusahaan PIC', 'SID PIC', 'Nama PIC', 'Perusahaan Pelapor',
                'Nama Pelapor', 'Tahun', 'Bulan', 'Nomor Task', 'Deskripsi Temuan',
            ],
            function (object $row): array {
                $p = $this->presentDetail($row);

                return [
                    $p['site'], $p['mitra'], $p['sid_pic'], $p['pic'],
                    $p['pelapor_perusahaan'], $p['pelapor'], $p['tahun'], $p['bulan'],
                    $p['task'], $p['deskripsi'],
                ];
            },
            'blindspot-tbc-' . $dataset
        );
    }

    /** @return array<int, string> */
    private function detailColumns(): array
    {
        return [
            self::COL_SITE . ' AS site',
            self::COL_PIC_PERUSAHAAN . ' AS mitra',
            self::COL_PIC_SID . ' AS sid_pic',
            self::COL_PIC_NAMA . ' AS pic',
            self::COL_PELAPOR_PERUSAHAAN . ' AS pelapor_perusahaan',
            self::COL_PELAPOR_NAMA . ' AS pelapor',
            self::COL_TAHUN . ' AS tahun',
            self::COL_BULAN . ' AS bulan_sumber',
            self::COL_TASK . ' AS task',
            self::COL_DESKRIPSI . ' AS deskripsi',
        ];
    }

    private function detailQuery(Request $request, string $dataset): Builder
    {
        $query = $this->detailQueryFiltered($request, $dataset, self::DETAIL_FILTERABLE);

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
    private function detailQueryFiltered(Request $request, string $dataset, ?array $map = null): Builder
    {
        $query = DB::table($this->table($dataset, 'detail'));

        if (self::EXCLUDED_MONTHS !== []) {
            $query->whereNotIn(self::COL_BULAN, self::EXCLUDED_MONTHS);
        }

        foreach ($map ?? ['site' => self::COL_SITE, 'mitra' => self::COL_PIC_PERUSAHAAN] as $parameter => $column) {
            $value = trim((string) $request->input($parameter, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $month = (int) $request->input('month', 0);

        if ($month >= 1 && $month <= 12) {
            $query->whereIn(self::COL_BULAN, $this->monthNames($month));
        }

        return $query;
    }

    /**
     * Satu temuan dalam bentuk siap tampil.
     *
     * @return array<string, mixed>
     */
    private function presentDetail(object $row): array
    {
        $teks = static fn ($value): string => trim((string) $value);

        return [
            'site' => $teks($row->site),
            'mitra' => $teks($row->mitra),
            'sid_pic' => $teks($row->sid_pic),
            'pic' => $teks($row->pic),
            'pelapor_perusahaan' => $teks($row->pelapor_perusahaan),
            'pelapor' => $teks($row->pelapor),
            'tahun' => (int) $row->tahun,
            'bulan' => self::MONTH_MAP[$row->bulan_sumber][1] ?? $teks($row->bulan_sumber),
            'task' => (string) $row->task,
            'deskripsi' => $teks($row->deskripsi),
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
            $total = 0;
            $terisi = [];

            foreach ($months as $month) {
                $jumlah = $entry['bulan'][$month] ?? null;
                $cells[] = $jumlah;

                if ($jumlah !== null) {
                    $total += $jumlah;
                    $terisi[] = $jumlah;
                }
            }

            $out[] = [
                'site' => $entry['site'],
                'mitra' => $entry['mitra'],
                'cells' => $cells,
                'total' => $total,
                'rata' => $terisi !== [] ? round(array_sum($terisi) / count($terisi), 1) : 0.0,
                'puncak' => $terisi !== [] ? max($terisi) : 0,
                // Untuk blindspot, naik berarti memburuk; arah ini dibalik saat
                // diwarnai di sisi tampilan.
                'trend' => $this->trendOf($terisi),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return $out;
    }

    /** @param  array<int, int>  $terisi */
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
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildKpi(Request $request, string $dataset, array $matrixRows, array $months): array
    {
        $row = $this->detailQueryFiltered($request, $dataset)
            ->selectRaw(
                'COUNT(*) AS temuan, '
                . 'COUNT(DISTINCT ' . self::COL_PIC_PERUSAHAAN . ') AS mitra, '
                . 'COUNT(DISTINCT ' . self::COL_PIC_SID . ') AS pic, '
                . 'COUNT(DISTINCT ' . self::COL_SITE . ') AS site, '
                . 'COUNT(DISTINCT ' . self::COL_PELAPOR_PERUSAHAAN . ') AS pelapor'
            )
            ->first();

        $temuan = (int) ($row->temuan ?? 0);
        $jumlahBulan = count($months);

        return [
            'temuan' => $temuan,
            'mitra_count' => (int) ($row->mitra ?? 0),
            'pic_count' => (int) ($row->pic ?? 0),
            'site_count' => (int) ($row->site ?? 0),
            'pelapor_count' => (int) ($row->pelapor ?? 0),
            'bulan_count' => $jumlahBulan,
            'rata_per_bulan' => $jumlahBulan > 0 ? round($temuan / $jumlahBulan, 1) : 0.0,
            'ambang' => self::AMBANG_TEMUAN_BULANAN,
            // Kombinasi site+perusahaan yang rata-rata bulanannya sudah di atas
            // ambang; itulah yang perlu ditindak lebih dulu.
            'di_atas_ambang' => count(array_filter(
                $matrixRows,
                static fn (array $r): bool => $r['rata'] > self::AMBANG_TEMUAN_BULANAN
            )),
            'kombinasi' => count($matrixRows),
        ];
    }

    /**
     * @param  array<string, int>  $perMitra
     * @return array<int, array<string, mixed>>
     */
    private function buildRanking(array $perMitra): array
    {
        $grand = array_sum($perMitra);
        $out = [];

        foreach ($perMitra as $mitra => $jumlah) {
            $out[] = [
                'mitra' => (string) $mitra,
                'jumlah' => $jumlah,
                'percent' => $grand > 0 ? round($jumlah / $grand * 100, 2) : 0.0,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['jumlah'] <=> $a['jumlah']);

        return array_slice($out, 0, 12);
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @return array<int, array<string, mixed>>
     */
    private function buildPerSite(array $matrixRows): array
    {
        $perSite = [];

        foreach ($matrixRows as $row) {
            $perSite[$row['site']]['jumlah'] = ($perSite[$row['site']]['jumlah'] ?? 0) + $row['total'];
            $perSite[$row['site']]['mitra'] = ($perSite[$row['site']]['mitra'] ?? 0) + 1;
        }

        $grand = array_sum(array_column($perSite, 'jumlah'));
        ksort($perSite);
        $out = [];

        foreach ($perSite as $site => $agg) {
            $out[] = [
                'site' => (string) $site,
                'jumlah' => $agg['jumlah'],
                'mitra' => $agg['mitra'],
                'percent' => $grand > 0 ? round($agg['jumlah'] / $grand * 100, 2) : 0.0,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['jumlah'] <=> $a['jumlah']);

        return $out;
    }

    /**
     * PIC dengan temuan terbanyak: siapa yang areanya paling sering
     * ketahuan orang lain.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPerPic(Request $request, string $dataset): array
    {
        $rows = $this->detailQueryFiltered($request, $dataset)
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(" . self::COL_PIC_NAMA . "), ''), '(Tanpa Nama)') AS nama, "
                . self::COL_PIC_SID . ' AS sid, '
                . self::COL_PIC_PERUSAHAAN . ' AS mitra, '
                . self::COL_SITE . ' AS site, '
                . 'COUNT(*) AS jumlah'
            )
            ->groupBy('nama', 'sid', 'mitra', 'site')
            ->orderByDesc('jumlah')
            ->limit(10)
            ->get();

        return $rows->map(static fn (object $r): array => [
            'label' => trim((string) $r->nama),
            'sid' => trim((string) $r->sid),
            'mitra' => trim((string) $r->mitra),
            'site' => trim((string) $r->site),
            'jumlah' => (int) $r->jumlah,
        ])->all();
    }

    /**
     * Dari mana temuannya datang. Inti blindspot: makin besar porsi pelapor
     * dari luar, makin banyak yang luput dari pengawas perusahaan sendiri.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPerPelapor(Request $request, string $dataset): array
    {
        $rows = $this->detailQueryFiltered($request, $dataset)
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(" . self::COL_PELAPOR_PERUSAHAAN . "), ''), '(Tanpa Nama)') AS label, "
                . 'COUNT(*) AS jumlah'
            )
            ->groupBy('label')
            ->orderByDesc('jumlah')
            ->get();

        $out = $rows->map(static fn (object $r): array => [
            'label' => (string) $r->label,
            'jumlah' => (int) $r->jumlah,
        ])->all();

        if (count($out) <= 10) {
            return $out;
        }

        $kepala = array_slice($out, 0, 9);
        $ekor = array_slice($out, 9);

        $kepala[] = [
            'label' => count($ekor) . ' pelapor lainnya',
            'jumlah' => array_sum(array_column($ekor, 'jumlah')),
        ];

        return $kepala;
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $matrix, array $months): array
    {
        $perMitra = [];

        foreach ($matrix as $entry) {
            foreach ($months as $month) {
                $perMitra[$entry['mitra']][$month] = ($perMitra[$entry['mitra']][$month] ?? 0)
                    + ($entry['bulan'][$month] ?? 0);
            }
        }

        // Garis dibatasi agar grafiknya terbaca; yang ditampilkan adalah
        // perusahaan dengan temuan terbanyak.
        uasort($perMitra, static fn (array $a, array $b): int => array_sum($b) <=> array_sum($a));
        $perMitra = array_slice($perMitra, 0, 8, true);

        $series = [];

        foreach ($perMitra as $mitra => $perMonth) {
            $data = [];

            foreach ($months as $month) {
                $data[] = $perMonth[$month] ?? 0;
            }

            $series[] = ['name' => (string) $mitra, 'data' => $data];
        }

        return [
            'labels' => array_map(static fn (int $m): string => self::monthLabel($m), $months),
            'series' => $series,
        ];
    }

    /**
     * Keterangan ketika sumbernya belum terisi, supaya panel kosong tidak
     * terbaca sebagai "tidak ada blindspot".
     */
    private function catatan(Request $request, string $dataset): ?string
    {
        $detail = $this->table($dataset, 'detail');
        $monthly = $this->table($dataset, 'monthly');

        $adaDetail = DB::table($detail)->exists();
        $adaBulanan = DB::table($monthly)->exists();

        if ($adaDetail && $adaBulanan) {
            return null;
        }

        if (! $adaDetail && ! $adaBulanan) {
            return 'Tabel ' . $detail . ' dan ' . $monthly . ' sama-sama masih kosong, '
                . 'jadi belum ada yang bisa ditampilkan di tab ini. Panel akan terisi sendiri '
                . 'begitu datanya masuk.';
        }

        if (! $adaDetail) {
            return 'Tabel ' . $detail . ' masih kosong, jadi kartu ringkasan, matriks temuan, '
                . 'dan tab Data belum berisi apa pun. Yang tersedia baru persentase resmi dari '
                . $monthly . '.';
        }

        return 'Tabel ' . $monthly . ' masih kosong, jadi panel persentase resmi belum terisi. '
            . 'Kartu ringkasan, matriks temuan, dan tab Data tetap berjalan dari ' . $detail . '.';
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

    private function detailBaseCount(string $dataset): int
    {
        $query = DB::table($this->table($dataset, 'detail'));

        if (self::EXCLUDED_MONTHS !== []) {
            $query->whereNotIn(self::COL_BULAN, self::EXCLUDED_MONTHS);
        }

        return $query->count();
    }

    /**
     * Nama bulan Inggris untuk satu nomor bulan.
     *
     * @return array<int, string>
     */
    private function monthNames(int $month): array
    {
        $names = array_keys(array_filter(
            self::MONTH_MAP,
            static fn (array $v): bool => $v[0] === $month
        ));

        return $names ?: ['__tidak_ada__'];
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
     * Tabel detail dan tabel bulanan bisa berbeda jangkauannya, jadi keduanya
     * digabung supaya dropdown yang sama berlaku untuk kedua tab.
     *
     * @return array<int, string>
     */
    private function monthOptions(string $dataset): array
    {
        $months = [];

        $sumber = [
            [$this->table($dataset, 'detail'), self::COL_BULAN],
            [$this->table($dataset, 'monthly'), self::MON_BULAN],
        ];

        foreach ($sumber as [$table, $column]) {
            foreach ($this->distinctValues($table, $column) as $name) {
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
}
