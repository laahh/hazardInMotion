<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Concerns\ServesDataTable;
use App\Services\OhsScoreCard\MineconRelasi;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Parameter SOD "% Blindspot TBC dengan PIC Subcontractor".
 *
 * Sumbernya satu tabel, detail_lead_subcont_blindspot_tbc_pic_subcont: satu
 * baris per temuan, lengkap dengan PIC, pelapor, dan deskripsinya. Tidak ada
 * tabel ringkasan bulanannya.
 *
 * YANG DIUKUR CACAH TEMUAN, BUKAN PERSENTASE, meskipun nama parameternya
 * diawali "%". Kolom pct_blindspot_tbc_dari_bc di tabel ini bernilai 100,00
 * untuk seluruh 123 baris sehingga tidak membedakan apa pun, dan tidak ada
 * penyebut di mana pun untuk menghitung persentase yang sebenarnya. Memaksakan
 * angka persen di sini hanya akan mengarang. TARGETNYA NOL: sel matriks yang
 * tidak punya baris dikirim sebagai 0 dan diwarnai hijau, bukan strip abu-abu
 * yang berarti "datanya belum ada".
 *
 * BERIRISAN DENGAN TAB SUBCON DI HALAMAN BLINDSPOT TBC. Dari 123 task di sini,
 * 122 juga ada di detail_lead_subcont_blindspot_tbc yang dipakai halaman itu.
 * Bedanya, tabel sana memuat tiap temuan dua kali (244 baris untuk 122 task)
 * sedangkan tabel ini bersih dan punya satu task tambahan. Jadi keduanya
 * menggambarkan temuan yang sama dengan sudut pandang berbeda; kalau ternyata
 * tabel ini yang dianggap sahih, tab Subcon di sana tinggal diarahkan ke sini.
 */
final class BlindspotTbcPicSubcontController extends Controller
{
    use ServesDataTable;

    private const TABLE = 'detail_lead_subcont_blindspot_tbc_pic_subcont';

    private const COL_SITE = 'site';
    private const COL_PERUSAHAAN = 'perusahaan_pic';
    private const COL_SID = 'sid_pic';
    private const COL_PIC = 'pic';
    private const COL_PELAPOR_PERUSAHAAN = 'perusahaan_pelapor_all_karyawan';
    private const COL_PELAPOR_NAMA = 'pelapor_all_karyawan';
    private const COL_DESKRIPSI = 'deskripsi';
    private const COL_TASK = 'task_number';
    private const COL_BULAN = 'month_of_date_for_join';
    private const COL_TAHUN = 'year_of_date_for_join';

    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /** Oktober masih berjalan saat data ini diambil, sejalan halaman lain. */
    private const EXCLUDED_MONTHS = [10];

    /** Pagar supaya satu sel yang sangat ramai tidak menjatuhkan modal. */
    private const BATAS_BARIS_MODAL = 500;

    private const FILTERABLE = [
        'site' => self::COL_SITE,
        'mitra' => self::COL_PERUSAHAAN,
        'pelapor' => self::COL_PELAPOR_PERUSAHAAN,
    ];

    private const SEARCHABLE = [
        self::COL_SITE, self::COL_PERUSAHAAN, self::COL_SID, self::COL_PIC,
        self::COL_PELAPOR_PERUSAHAAN, self::COL_PELAPOR_NAMA, self::COL_DESKRIPSI,
    ];

    private const ORDERABLE = [
        0 => self::COL_SITE,
        1 => self::COL_PERUSAHAAN,
        2 => self::COL_PIC,
        3 => self::COL_PELAPOR_NAMA,
        5 => self::COL_BULAN,
        6 => self::COL_TASK,
    ];

    public function __construct(
        private readonly MineconRelasi $minecon,
    ) {}

    public function index(): View
    {
        return view('ohs-score-card.blindspot-tbc-pic-subcont.index', [
            'filterOptions' => [
                'site' => $this->distinctValues(self::COL_SITE),
                'mitra' => $this->distinctValues(self::COL_PERUSAHAAN),
                'pelapor' => $this->distinctValues(self::COL_PELAPOR_PERUSAHAAN),
                'minecon' => $this->daftarMinecon(),
            ],
            'monthOptions' => $this->monthOptions(),
            'tabel' => self::TABLE,
        ]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request): JsonResponse
    {
        $rows = $this->baseQuery($request)
            ->selectRaw(
                self::COL_SITE . ' AS site, '
                . self::COL_PERUSAHAAN . ' AS mitra, '
                . self::COL_BULAN . ' AS bulan, '
                . 'COUNT(*) AS jumlah'
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
        // baiknya, dan menghilangkannya membuat bulan tanpa temuan tak terlihat.
        $months = $this->rentangBulan(array_keys($monthSeen));
        $matrix = $this->buildMatrix($grid, $months);

        return response()->json([
            'months' => $this->monthHeadings($months),
            'kpi' => $this->buildKpi($request, $matrix, $months),
            'matrix' => $matrix,
            'per_site' => $this->ringkasPer($matrix, 'site'),
            'per_mitra' => $this->ringkasPer($matrix, 'mitra'),
            'per_pic' => $this->buildPerPic($request),
            'per_pelapor' => $this->buildPerPelapor($request),
            'monthly' => $this->buildMonthlySeries($matrix, $months),
            'catatan' => $this->catatan(),
        ]);
    }

    /**
     * Bulan yang ditampilkan: dari yang paling awal sampai paling akhir ada
     * temuan, termasuk bulan di antaranya yang bersih.
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
                // 0, bukan null: tidak adanya baris berarti tidak ada temuan,
                // dan itu kabar baik yang harus kelihatan.
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
     * rowspan di tabel, site dengan temuan terbanyak di atas.
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

        // Naik berarti memburuk: temuan bertambah.
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

        $cacah = $this->baseQuery($request)
            ->selectRaw(
                'COUNT(DISTINCT ' . self::COL_SID . ') AS pic, '
                . 'COUNT(DISTINCT ' . self::COL_PELAPOR_PERUSAHAAN . ') AS pelapor'
            )
            ->first();

        return [
            'temuan' => $total,
            'kombinasi' => count($matrix),
            'site_count' => count(array_unique(array_column($matrix, 'site'))),
            'mitra_count' => count(array_unique(array_column($matrix, 'mitra'))),
            'pic_count' => (int) ($cacah->pic ?? 0),
            'pelapor_count' => (int) ($cacah->pelapor ?? 0),
            'bulan_count' => count($months),
            'bulan_bersih' => $bulanBersih,
            'rata_per_bulan' => $months === [] ? 0.0 : round($total / count($months), 1),
            'bulan_terburuk' => $indeksTerbanyak === false || $terbanyak === 0
                ? null
                : self::monthLabel($months[$indeksTerbanyak]),
            'temuan_terburuk' => $terbanyak,
        ];
    }

    /**
     * Cacah temuan per site atau per perusahaan PIC.
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
                // Untuk site berarti jumlah perusahaan yang kedapatan, dan
                // sebaliknya.
                'lawan' => count($agg['lawan']),
                'percent' => $grand > 0 ? round($agg['jumlah'] / $grand * 100, 2) : 0.0,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['jumlah'] <=> $a['jumlah']);

        return array_slice($out, 0, $key === 'mitra' ? 12 : count($out));
    }

    /**
     * PIC dengan temuan terbanyak: siapa yang areanya paling sering ketahuan
     * pihak lain.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPerPic(Request $request): array
    {
        $rows = $this->baseQuery($request)
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(" . self::COL_PIC . "), ''), '(Tanpa Nama)') AS nama, "
                . self::COL_SID . ' AS sid, '
                . self::COL_PERUSAHAAN . ' AS mitra, '
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
     * Dari mana temuannya datang. Inti blindspot: tidak satu pun dilaporkan
     * oleh perusahaan PIC-nya sendiri.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPerPelapor(Request $request): array
    {
        $rows = $this->baseQuery($request)
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(" . self::COL_PELAPOR_PERUSAHAAN . "), ''), '(Tanpa Nama)') AS label, "
                . 'COUNT(*) AS jumlah'
            )
            ->groupBy('label')
            ->orderByDesc('jumlah')
            ->get();

        $grand = $rows->sum('jumlah');

        return $rows->map(static fn (object $r): array => [
            'label' => (string) $r->label,
            'jumlah' => (int) $r->jumlah,
            'percent' => $grand > 0 ? round((int) $r->jumlah / $grand * 100, 2) : 0.0,
        ])->all();
    }

    /**
     * Satu garis total temuan per bulan, ditambah garis per site teratas.
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

    private function catatan(): ?string
    {
        if (! DB::table(self::TABLE)->exists()) {
            return 'Tabel ' . self::TABLE . ' masih kosong. Untuk parameter ini tabel kosong '
                . 'justru berarti tidak ada temuan sama sekali.';
        }

        return 'Tabel ' . self::TABLE . ' hanya memuat site, perusahaan, dan bulan yang kedapatan '
            . 'temuan, jadi sel bernilai 0 berarti tidak ada temuan pada bulan itu, bukan datanya '
            . 'belum masuk. Yang dihitung cacah temuan, bukan persentase: kolom '
            . 'pct_blindspot_tbc_dari_bc bernilai 100,00 di seluruh baris sehingga tidak bisa '
            . 'dipakai sebagai ukuran.';
    }

    // ======================================================================
    // Tab Data
    // ======================================================================

    public function data(Request $request): JsonResponse
    {
        $query = $this->dataQuery($request);

        $rows = (clone $query)
            ->select($this->columns())
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
            ->select($this->columns())
            ->orderBy(self::COL_SITE)
            ->orderBy(self::COL_PERUSAHAAN)
            ->orderBy('id');

        return $this->dtExport(
            $request,
            $query,
            ['Site', 'Perusahaan Minecon', 'Perusahaan PIC (Subkon)', 'SID PIC', 'Nama PIC',
             'Perusahaan Pelapor', 'Nama Pelapor', 'Tahun', 'Bulan', 'Nomor Task', 'Deskripsi Temuan'],
            function (object $row): array {
                $p = $this->present($row);

                return [
                    $p['site'], $p['minecon'], $p['mitra'], $p['sid_pic'], $p['pic'],
                    $p['pelapor_perusahaan'], $p['pelapor'], $p['tahun'], $p['bulan'],
                    $p['task'], $p['deskripsi'],
                ];
            },
            'blindspot-tbc-pic-subcont'
        );
    }

    /** @return array<int, string> */
    private function columns(): array
    {
        return [
            self::COL_SITE . ' AS site',
            self::COL_PERUSAHAAN . ' AS mitra',
            self::COL_SID . ' AS sid_pic',
            self::COL_PIC . ' AS pic',
            self::COL_PELAPOR_PERUSAHAAN . ' AS pelapor_perusahaan',
            self::COL_PELAPOR_NAMA . ' AS pelapor',
            self::COL_TAHUN . ' AS tahun',
            self::COL_BULAN . ' AS bulan_sumber',
            self::COL_TASK . ' AS task',
            self::COL_DESKRIPSI . ' AS deskripsi',
        ];
    }

    private function dataQuery(Request $request): Builder
    {
        $query = $this->baseQuery($request, self::FILTERABLE);

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            self::SEARCHABLE
        );

        return $query;
    }

    /**
     * Tabel dengan filter dimensi & bulan terpasang.
     *
     * @param  array<string, string>|null  $map  null memakai filter tab Ringkasan
     */
    private function baseQuery(Request $request, ?array $map = null): Builder
    {
        $query = DB::table(self::TABLE);

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn(self::COL_BULAN, $this->namaBulan($nomor));
        }

        foreach ($map ?? ['site' => self::COL_SITE, 'mitra' => self::COL_PERUSAHAAN] as $parameter => $column) {
            $value = trim((string) $request->input($parameter, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $month = (int) $request->input('month', 0);

        if ($month >= 1 && $month <= 12) {
            $query->whereIn(self::COL_BULAN, $this->namaBulan($month));
        }

        $this->saringMinecon($query, trim((string) $request->input('minecon', '')));

        return $query;
    }

    /**
     * Menyaring menurut perusahaan minecon.
     *
     * Minecon TIDAK ADA DI TABEL INI -- diturunkan dari view relasi di
     * database lain -- jadi tidak bisa ditulis sebagai WHERE biasa. Caranya:
     * pasangan site/subkon yang bermuara ke minecon itu dikumpulkan dulu di
     * PHP, lalu dipakai sebagai daftar pasangan yang diizinkan.
     */
    private function saringMinecon(Builder $query, string $minecon): void
    {
        if ($minecon === '') {
            return;
        }

        $pasangan = [];

        foreach ($this->pasanganSiteMitra() as [$site, $mitra]) {
            if ($this->minecon->label($site, $mitra) === $minecon) {
                $pasangan[] = [$site, $mitra];
            }
        }

        if ($pasangan === []) {
            // Tidak ada yang cocok: jangan diam-diam menampilkan semuanya.
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $q) use ($pasangan): void {
            foreach ($pasangan as [$site, $mitra]) {
                $q->orWhere(function (Builder $w) use ($site, $mitra): void {
                    $w->where(self::COL_SITE, $site)->where(self::COL_PERUSAHAAN, $mitra);
                });
            }
        });
    }

    /**
     * Seluruh pasangan site/subkon yang ada di tabel ini.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function pasanganSiteMitra(): array
    {
        return DB::table(self::TABLE)
            ->selectRaw(self::COL_SITE . ' AS s, ' . self::COL_PERUSAHAAN . ' AS m')
            ->distinct()
            ->get()
            ->map(static fn (object $r): array => [trim((string) $r->s), trim((string) $r->m)])
            ->all();
    }

    /**
     * Daftar minecon yang benar-benar muncul, untuk isi penyaring.
     *
     * @return array<int, string>
     */
    private function daftarMinecon(): array
    {
        $out = [];

        foreach ($this->pasanganSiteMitra() as [$site, $mitra]) {
            $out[] = $this->minecon->label($site, $mitra);
        }

        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * Satu temuan dalam bentuk siap tampil.
     *
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        $teks = static fn ($value): string => trim((string) $value);
        $monthNo = $this->nomorBulan((string) $row->bulan_sumber);

        $site = $teks($row->site);
        $mitra = $teks($row->mitra);
        $relasi = $this->minecon->untuk($site, $mitra);

        return [
            'site' => $site,
            'mitra' => $mitra,
            // Perusahaan minecon di atas subkon ini; lihat MineconRelasi untuk
            // aturan ketika relasinya ganda atau tidak ketemu.
            'minecon' => $relasi['minecon'] ?? $this->minecon->label($site, $mitra),
            'minecon_pasti' => $relasi['minecon'] !== null,
            'minecon_status' => $relasi['status'],
            'sid_pic' => $teks($row->sid_pic),
            'pic' => $teks($row->pic),
            'pelapor_perusahaan' => $teks($row->pelapor_perusahaan),
            'pelapor' => $teks($row->pelapor),
            'tahun' => (int) $row->tahun,
            'bulan' => $monthNo === 0 ? $teks($row->bulan_sumber) : self::monthLabel($monthNo),
            'task' => (string) $row->task,
            // Delapan deskripsi memuat baris baru; diratakan agar tidak merusak
            // tinggi baris tabel maupun memecah sel CSV.
            'deskripsi' => trim(preg_replace('/\s*\R\s*/u', ' ', (string) $row->deskripsi) ?? ''),
        ];
    }

    // ======================================================================
    // Modal rincian satu sel matriks
    // ======================================================================

    /**
     * Isi satu sel matriks "Temuan per Bulan", untuk modal rincian.
     *
     * Halaman ini hanya punya satu matriks dan sumbernya tabel rincian itu
     * sendiri, jadi modal mencacah dengan COUNT(*) atas tabel yang sama persis
     * seperti overview(). Selama keduanya tidak menyaring apa pun lagi, sel dan
     * modal tidak mungkin berselisih.
     *
     * TIDAK ADA DEDUPE DI SINI, DAN ITU HASIL PEMERIKSAAN, BUKAN KELALAIAN.
     * Tabel saudaranya detail_lead_subcont_blindspot_tbc memuat tiap temuan
     * tepat dua kali (244 baris untuk 122 temuan) sehingga halaman Blindspot
     * TBC wajib membuang kembarannya lewat detailTanpaKembar(). Tabel ini
     * diperiksa dengan kunci yang sama -- site, perusahaan_pic,
     * month_of_date_for_join, task_number -- dan hasilnya 123 baris, 123 kunci
     * unik, 123 task unik: nol kembaran. Memasang dedupe di sini justru akan
     * membuat modal berbeda dari overview() yang mencacah mentah.
     *
     * YANG DITONJOLKAN PELAPORNYA. Blindspot berarti temuan di area sebuah
     * subkontraktor yang justru ditemukan pihak lain, jadi yang ingin diketahui
     * pembaca siapa yang menangkapnya, bukan sekadar berapa banyak. Di seluruh
     * 123 baris tidak satu pun perusahaan pelapornya sama dengan perusahaan
     * PIC-nya, sehingga sisi "ditemukan pihak lain" memang selalu benar.
     */
    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));

        // monthHeadings() halaman ini mengirim 1-12, tapi bentuk tahun*100+bulan
        // tetap diterima supaya modal tidak pecah kalau heading berubah. Bagian
        // tahunnya sengaja tidak dipakai menyaring: overview() pun tidak
        // mengelompokkan per tahun, dan menambahkannya hanya di sini akan
        // membuat modal berbeda dari selnya.
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
                'pesan' => self::monthLabel($bulan) . ' tidak ikut ditampilkan di parameter ini '
                    . 'karena bulannya masih berjalan saat data diambil.',
            ]);
        }

        $dasar = fn (): Builder => DB::table(self::TABLE)
            ->where(self::COL_SITE, $site)
            ->whereIn(self::COL_BULAN, $this->namaBulan($bulan))
            ->when($mitra !== '', fn (Builder $q): Builder => $q->where(self::COL_PERUSAHAAN, $mitra));

        $baris = $dasar()
            ->select($this->columns())
            ->orderBy(self::COL_PELAPOR_PERUSAHAAN)
            ->orderBy(self::COL_TASK)
            ->limit(self::BATAS_BARIS_MODAL + 1)
            ->get()
            ->map(fn (object $row): array => $this->present($row))
            ->all();

        $terpotong = count($baris) > self::BATAS_BARIS_MODAL;

        if ($terpotong) {
            $baris = array_slice($baris, 0, self::BATAS_BARIS_MODAL);
        }

        $cacah = $dasar()
            ->selectRaw(
                'COUNT(*) AS temuan, '
                . 'COUNT(DISTINCT ' . self::COL_SID . ') AS pic, '
                . 'COUNT(DISTINCT ' . self::COL_PELAPOR_NAMA . ') AS pelapor, '
                . 'COUNT(DISTINCT ' . self::COL_PELAPOR_PERUSAHAAN . ') AS perusahaan_pelapor'
            )
            ->first();

        return response()->json([
            'ok' => true,
            'judul' => [
                'site' => $site,
                'mitra' => $mitra,
                'bulan' => self::monthLabel($bulan),
            ],
            'ringkas' => [
                'temuan' => (int) ($cacah->temuan ?? 0),
                'pic' => (int) ($cacah->pic ?? 0),
                'pelapor' => (int) ($cacah->pelapor ?? 0),
                'perusahaan_pelapor' => (int) ($cacah->perusahaan_pelapor ?? 0),
            ],
            'per_pelapor' => $this->pelaporTerbanyak($dasar()),
            'per_pic' => $this->picTerbanyak($dasar()),
            'terpotong' => $terpotong,
            'batas' => self::BATAS_BARIS_MODAL,
            'baris' => $baris,
        ]);
    }

    /**
     * Perusahaan yang menangkap temuan di sel ini, terbanyak di atas.
     *
     * Tidak dipotong: perusahaan pelapor di seluruh tabel cuma empat, jadi
     * daftarnya selalu pendek dan memotongnya hanya akan menyembunyikan
     * informasi.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pelaporTerbanyak(Builder $query): array
    {
        return $query
            ->selectRaw(self::COL_PELAPOR_PERUSAHAAN . ' AS perusahaan, COUNT(*) AS n')
            ->groupBy('perusahaan')
            ->orderByDesc('n')
            ->get()
            ->map(static fn (object $r): array => [
                'perusahaan' => trim((string) $r->perusahaan) ?: '-',
                'n' => (int) $r->n,
            ])
            ->all();
    }

    /**
     * PIC yang areanya paling sering kecolongan di sel ini.
     *
     * @return array<int, array<string, mixed>>
     */
    private function picTerbanyak(Builder $query): array
    {
        return $query
            ->selectRaw(
                self::COL_PIC . ' AS pic, '
                . self::COL_SID . ' AS sid, COUNT(*) AS n'
            )
            ->groupBy('pic', 'sid')
            ->orderByDesc('n')
            ->limit(10)
            ->get()
            ->map(static fn (object $r): array => [
                'pic' => trim((string) $r->pic) ?: '-',
                'sid' => trim((string) $r->sid) ?: '-',
                'n' => (int) $r->n,
            ])
            ->all();
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

    private function baseCount(): int
    {
        $query = DB::table(self::TABLE);

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
    private function distinctValues(string $column): array
    {
        return DB::table(self::TABLE)
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

        foreach ($this->distinctValues(self::COL_BULAN) as $nilai) {
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
}
