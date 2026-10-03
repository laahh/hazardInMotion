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
 * Parameter SOD "Blindspot TBC", untuk dua populasi PIC:
 *
 *   minecon -> lead_blindspot_tbc_month          + detail_lead_blindspot_tbc
 *   subcon  -> lead_blindspot_tbc_subcont_month  + detail_lead_subcont_blindspot_tbc
 *
 * Blindspot = temuan TBC di area sebuah perusahaan yang justru dilaporkan
 * pihak lain, bukan oleh pengawas perusahaan itu sendiri. Makin tinggi
 * persentasenya, makin banyak bahaya yang luput dari pengawasan si PIC.
 *
 * DUA UKURAN YANG BERBEDA, DAN SENGAJA TIDAK DICAMPUR:
 *   - Tabel bulanan memberi PERSENTASE resmi per site x perusahaan x bulan.
 *     Itu ukuran utamanya, dan mengisi matriks besar di tab Ringkasan.
 *   - Tabel detail memberi CACAH temuan beserta PIC, pelapor, dan deskripsinya.
 *     Itu yang menjawab "temuan apa saja", dan mengisi panel di bawahnya.
 * Kolom pct_blindspot_tbc_dari_bc yang ikut di tabel detail tidak dipakai
 * sebagai ukuran: nilainya 100,00 di seluruh baris yang ada, jadi tidak
 * membedakan apa pun.
 *
 * NAMA KOLOM TABEL BULANAN TIDAK SERAGAM. Versi lama hasil scrape Tableau
 * memakai Blindspot_TBC_dari_BC, versi yang sudah dirapikan memakai
 * pct_blindspot_tbc_dari_bc; saat tulisan ini dibuat tabel minecon sudah
 * memakai bentuk baru sedangkan subcon masih bentuk lama. Karena itu nama
 * kolomnya tidak ditulis mati, melainkan dibaca dari skema tabelnya lewat
 * monthlyColumns() sehingga kedua bentuk sama-sama jalan.
 *
 * KEADAAN DATA (3 Oktober 2026): lead_blindspot_tbc_month berisi 145 baris
 * (6 site, 7 perusahaan, Januari-September 2026) dan detail_lead_blindspot_tbc
 * 63 temuan, tetapi detailnya baru mencakup satu pasangan SMO / PT Madhani
 * Talatah Nusantara. Kedua tabel subcon masih kosong. Panel yang sumbernya
 * belum terisi menampilkan keterangan, bukan angka nol yang menyesatkan.
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
     * Calon nama kolom tabel bulanan, diurutkan dari bentuk yang dipakai
     * sekarang ke bentuk lama. Yang pertama cocok dengan skema tabel itulah
     * yang dipakai; lihat catatan di docblock kelas.
     */
    private const MONTHLY_CANDIDATES = [
        'site' => ['site'],
        'mitra' => ['perusahaan_pic'],
        'bulan' => ['month_of_date_for_join', 'Month_of_Date_for_Join'],
        'persen' => ['pct_blindspot_tbc_dari_bc', 'Blindspot_TBC_dari_BC'],
    ];

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

    /**
     * Berapa persen blindspot sudah pantas disebut tinggi.
     *
     * TEBAKAN, bukan angka resmi: sebarannya saat ini 0-50% dengan rata-rata
     * 1,85%, jadi 5% dipakai sebagai batas "perlu diperhatikan". Ubah di sini
     * begitu ambang yang sebenarnya diketahui.
     */
    private const AMBANG_PERSEN = 5.0;

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
            $monthly = $this->table($slug, 'monthly');
            $mon = $this->monthlyColumns($monthly);

            $datasets[$slug] = [
                'slug' => $slug,
                'label' => self::DATASETS[$slug]['label'],
                'monthly_table' => $monthly,
                'detail_table' => $detail,
                'ambang' => self::AMBANG_PERSEN,
                'filterOptions' => [
                    // Site & perusahaan diambil dari tabel bulanan karena
                    // cakupannya jauh lebih luas daripada tabel detail.
                    'site' => $this->gabungNilai([[$monthly, $mon['site']], [$detail, self::COL_SITE]]),
                    'mitra' => $this->gabungNilai([[$monthly, $mon['mitra']], [$detail, self::COL_PIC_PERUSAHAAN]]),
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

        $persen = $this->buildPersen($request, $dataset);
        $temuan = $this->buildTemuan($request, $dataset);

        return response()->json([
            'persen' => $persen,
            'temuan' => $temuan,
            'kpi' => $this->buildKpi($request, $dataset, $persen, $temuan),
            'per_site' => $this->buildPerSite($persen['rows']),
            'per_mitra' => $this->buildPerMitra($persen['rows']),
            'per_pic' => $this->buildPerPic($request, $dataset),
            'per_pelapor' => $this->buildPerPelapor($request, $dataset),
            'monthly' => $this->buildMonthlySeries($persen),
            'catatan' => $this->catatan($request, $dataset),
        ]);
    }

    /**
     * Ukuran utama: persentase blindspot per site x perusahaan x bulan.
     *
     * @return array<string, mixed>
     */
    private function buildPersen(Request $request, string $dataset): array
    {
        $table = $this->table($dataset, 'monthly');
        $mon = $this->monthlyColumns($table);

        $query = DB::table($table);

        if (self::EXCLUDED_MONTHS !== []) {
            $query->whereNotIn($mon['bulan'], self::EXCLUDED_MONTHS);
        }

        foreach (['site' => $mon['site'], 'mitra' => $mon['mitra']] as $parameter => $column) {
            $value = trim((string) $request->input($parameter, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $month = (int) $request->input('month', 0);

        if ($month >= 1 && $month <= 12) {
            $query->whereIn($mon['bulan'], $this->monthNames($month));
        }

        $rows = $query
            ->selectRaw(
                $mon['site'] . ' AS site, '
                . $mon['mitra'] . ' AS mitra, '
                . $mon['bulan'] . ' AS bulan, '
                . 'AVG(' . $mon['persen'] . ') AS persen'
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
                continue; // nama bulan tak dikenal: jangan diam-diam dianggap bulan lain
            }

            $monthSeen[$monthNo] = true;
            $site = trim((string) $row->site);
            $mitra = trim((string) $row->mitra);

            $grid[$site . '|' . $mitra]['site'] = $site;
            $grid[$site . '|' . $mitra]['mitra'] = $mitra;
            $grid[$site . '|' . $mitra]['bulan'][$monthNo] = round((float) $row->persen, 2);
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

            $average = $terisi !== [] ? round(array_sum($terisi) / count($terisi), 2) : null;

            $out[] = [
                'site' => $entry['site'],
                'mitra' => $entry['mitra'],
                'cells' => $cells,
                'average' => $average,
                'puncak' => $terisi !== [] ? max($terisi) : null,
                'trend' => $this->trendOf($terisi),
                'di_atas_ambang' => $average !== null && $average > self::AMBANG_PERSEN,
            ];
        }

        // Yang paling buruk di atas: itu yang perlu dibaca lebih dulu.
        usort($out, static fn (array $a, array $b): int => ($b['average'] ?? -1) <=> ($a['average'] ?? -1));

        return [
            'tersedia' => $out !== [],
            'tabel' => $table,
            'months' => $this->monthHeadings($months),
            'month_numbers' => $months,
            'rows' => $out,
        ];
    }

    /**
     * Ukuran pendamping: cacah temuan dari tabel detail.
     *
     * @return array<string, mixed>
     */
    private function buildTemuan(Request $request, string $dataset): array
    {
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

        $grid = [];
        $monthSeen = [];

        foreach ($rows as $row) {
            $monthNo = self::MONTH_MAP[$row->bulan][0] ?? 0;

            if ($monthNo === 0) {
                continue;
            }

            $monthSeen[$monthNo] = true;
            $site = trim((string) $row->site);
            $mitra = trim((string) $row->mitra);

            $grid[$site . '|' . $mitra]['site'] = $site;
            $grid[$site . '|' . $mitra]['mitra'] = $mitra;
            $grid[$site . '|' . $mitra]['bulan'][$monthNo] = (int) $row->jumlah;
        }

        ksort($monthSeen);
        ksort($grid);
        $months = array_keys($monthSeen);

        $out = [];

        foreach ($grid as $entry) {
            $cells = [];
            $total = 0;
            $terisi = [];

            foreach ($months as $monthNo) {
                $jumlah = $entry['bulan'][$monthNo] ?? null;
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
                'trend' => $this->trendOf($terisi),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'tersedia' => $out !== [],
            'tabel' => $this->table($dataset, 'detail'),
            'months' => $this->monthHeadings($months),
            'rows' => $out,
        ];
    }

    /**
     * Naik berarti memburuk untuk blindspot; arah itu dibalik saat diwarnai
     * di sisi tampilan.
     *
     * @param  array<int, float|int>  $terisi
     */
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
     * @param  array<string, mixed>  $persen
     * @param  array<string, mixed>  $temuan
     * @return array<string, mixed>
     */
    private function buildKpi(Request $request, string $dataset, array $persen, array $temuan): array
    {
        $nilai = array_values(array_filter(
            array_column($persen['rows'], 'average'),
            static fn (?float $v): bool => $v !== null
        ));

        $detail = $this->detailQueryFiltered($request, $dataset)
            ->selectRaw(
                'COUNT(*) AS temuan, '
                . 'COUNT(DISTINCT ' . self::COL_PIC_SID . ') AS pic, '
                . 'COUNT(DISTINCT ' . self::COL_PELAPOR_PERUSAHAAN . ') AS pelapor'
            )
            ->first();

        return [
            'rata_persen' => $nilai !== [] ? round(array_sum($nilai) / count($nilai), 2) : null,
            'puncak_persen' => $nilai !== [] ? max($nilai) : null,
            'kombinasi' => count($persen['rows']),
            'di_atas_ambang' => count(array_filter(
                $persen['rows'],
                static fn (array $r): bool => $r['di_atas_ambang']
            )),
            'ambang' => self::AMBANG_PERSEN,
            'site_count' => count(array_unique(array_column($persen['rows'], 'site'))),
            'mitra_count' => count(array_unique(array_column($persen['rows'], 'mitra'))),
            'bulan_count' => count($persen['months']),
            'temuan' => (int) ($detail->temuan ?? 0),
            'pic_count' => (int) ($detail->pic ?? 0),
            'pelapor_count' => (int) ($detail->pelapor ?? 0),
            'temuan_kombinasi' => count($temuan['rows']),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildPerSite(array $rows): array
    {
        return $this->rataPer($rows, 'site');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildPerMitra(array $rows): array
    {
        return array_slice($this->rataPer($rows, 'mitra'), 0, 12);
    }

    /**
     * Rata-rata persentase per satu dimensi.
     *
     * Rata-rata antar baris, bukan antar bulan: tiap pasangan site-perusahaan
     * dihitung sekali supaya yang datanya lengkap tidak berbobot lebih besar.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rataPer(array $rows, string $key): array
    {
        $kelompok = [];

        foreach ($rows as $row) {
            if ($row['average'] === null) {
                continue;
            }

            $kelompok[$row[$key]][] = $row['average'];
        }

        $out = [];

        foreach ($kelompok as $label => $nilai) {
            $rata = round(array_sum($nilai) / count($nilai), 2);

            $out[] = [
                $key => (string) $label,
                'percent' => $rata,
                'jumlah' => count($nilai),
                'puncak' => max($nilai),
                'di_atas_ambang' => $rata > self::AMBANG_PERSEN,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

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
     * Satu garis per perusahaan, dari persentase bulanan.
     *
     * @param  array<string, mixed>  $persen
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $persen): array
    {
        $months = $persen['month_numbers'];
        $perMitra = [];

        foreach ($persen['rows'] as $row) {
            foreach ($row['cells'] as $i => $value) {
                if ($value === null) {
                    continue;
                }

                $perMitra[$row['mitra']][$i][] = $value;
            }
        }

        // Garis dibatasi agar grafiknya terbaca; yang ditampilkan adalah
        // perusahaan dengan rata-rata tertinggi.
        $rata = [];

        foreach ($perMitra as $mitra => $perBulan) {
            $semua = array_merge(...array_values($perBulan));
            $rata[$mitra] = array_sum($semua) / count($semua);
        }

        arsort($rata);
        $terpilih = array_slice(array_keys($rata), 0, 8);

        $series = [];

        foreach ($terpilih as $mitra) {
            $data = [];

            foreach (array_keys($months) as $i) {
                $nilai = $perMitra[$mitra][$i] ?? null;
                // null, bukan 0: bulan tanpa data harus putus di grafik.
                $data[] = $nilai === null ? null : round(array_sum($nilai) / count($nilai), 2);
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

        if (! $adaDetail && ! $adaBulanan) {
            return 'Tabel ' . $monthly . ' dan ' . $detail . ' sama-sama masih kosong, '
                . 'jadi belum ada yang bisa ditampilkan di tab ini. Panel akan terisi sendiri '
                . 'begitu datanya masuk.';
        }

        if (! $adaBulanan) {
            return 'Tabel ' . $monthly . ' masih kosong, jadi matriks persentase dan kartu di atas '
                . 'belum terisi. Panel temuan tetap berjalan dari ' . $detail . '.';
        }

        if (! $adaDetail) {
            return 'Tabel ' . $detail . ' masih kosong, jadi daftar temuan, PIC, dan asal pelapor '
                . 'belum terisi. Matriks persentase tetap berjalan dari ' . $monthly . '.';
        }

        // Keduanya terisi, tapi detailnya bisa jauh lebih sempit daripada
        // tabel bulanan; itu perlu dikatakan supaya panel temuan yang kurus
        // tidak dikira berarti tidak ada temuan.
        $pasanganBulanan = $this->pasanganSiteMitra($monthly, $this->monthlyColumns($monthly));
        $pasanganDetail = $this->pasanganSiteMitra($detail, [
            'site' => self::COL_SITE,
            'mitra' => self::COL_PIC_PERUSAHAAN,
        ]);

        $kurang = count($pasanganBulanan) - count(array_intersect($pasanganBulanan, $pasanganDetail));

        if ($kurang <= 0) {
            return null;
        }

        return 'Tabel ' . $detail . ' baru mencakup ' . count($pasanganDetail) . ' dari '
            . count($pasanganBulanan) . ' pasangan site-perusahaan yang ada di ' . $monthly . '. '
            . 'Matriks persentase sudah lengkap, tetapi daftar temuan, PIC, dan asal pelapor '
            . 'hanya memuat pasangan yang sudah ada rinciannya.';
    }

    /**
     * @param  array<string, string>  $kolom
     * @return array<int, string>
     */
    private function pasanganSiteMitra(string $table, array $kolom): array
    {
        return DB::table($table)
            ->distinct()
            ->selectRaw("CONCAT(TRIM(" . $kolom['site'] . "), '|', TRIM(" . $kolom['mitra'] . ")) AS pasangan")
            ->pluck('pasangan')
            ->map(static fn ($v): string => (string) $v)
            ->all();
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
     * Nama kolom tabel bulanan menurut skema tabelnya sendiri.
     *
     * Tabel minecon dan subcon sedang berbeda bentuk, lihat catatan di
     * docblock kelas. Hasilnya di-cache per permintaan karena dipakai
     * beberapa kali dalam satu respons.
     *
     * @return array<string, string>
     */
    private function monthlyColumns(string $table): array
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $ada = [];

        foreach (Schema::getColumnListing($table) as $column) {
            $ada[mb_strtolower($column)] = $column;
        }

        $out = [];

        foreach (self::MONTHLY_CANDIDATES as $peran => $calon) {
            $out[$peran] = $calon[0];

            foreach ($calon as $nama) {
                if (isset($ada[mb_strtolower($nama)])) {
                    $out[$peran] = $ada[mb_strtolower($nama)];
                    break;
                }
            }
        }

        return $cache[$table] = $out;
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
     * Nilai gabungan dari beberapa tabel sekaligus, untuk isi dropdown.
     *
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
     * Tabel detail dan tabel bulanan bisa berbeda jangkauannya, jadi keduanya
     * digabung supaya dropdown yang sama berlaku untuk kedua tab.
     *
     * @return array<int, string>
     */
    private function monthOptions(string $dataset): array
    {
        $monthly = $this->table($dataset, 'monthly');
        $months = [];

        $sumber = [
            [$this->table($dataset, 'detail'), self::COL_BULAN],
            [$monthly, $this->monthlyColumns($monthly)['bulan']],
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
