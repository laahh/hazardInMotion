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
 * Parameter SIRM "Deviasi Rekayasa Engineering Overspeed".
 *
 * Sumbernya lead_pelanggaran_overspeed: 47 baris, satu baris per site x PIC
 * approval x perusahaan x bulan yang KEDAPATAN pelanggaran, berisi cacah
 * pelanggarnya. Tidak ada tabel rinciannya.
 *
 * YANG DICACAH ORANG, BUKAN KEJADIAN. Kolomnya
 * distinct_count_of_kode_sid_bep_vw_berecord, yaitu banyaknya SID karyawan
 * berbeda yang kedapatan, jadi satu orang yang melanggar berkali-kali dalam
 * satu bulan tetap terhitung satu. Angka di halaman ini karena itu dibaca
 * "berapa pelanggar", bukan "berapa pelanggaran".
 *
 * CACAH, BUKAN PERSENTASE, seperti halaman Penggunaan HP dan GR Seatbelt:
 *
 *   - Tidak ada penyebut, jadi tidak ada band Nilai 1-4. Memaksakannya hanya
 *     akan mengarang angka.
 *   - TARGETNYA NOL. Yang baik adalah tidak muncul sama sekali di tabel ini.
 *   - SEL KOSONG BERARTI BAIK. Sel tanpa baris dikirim sebagai 0, bukan null,
 *     dan diwarnai hijau. Lihat buildMatrix().
 *
 * PIC APPROVAL IKUT JADI DIMENSI, dan ini yang membedakannya dari halaman
 * cacah lain. Sembilan PIC, masing-masing terikat tepat satu site -- sudah
 * diperiksa, tidak ada PIC yang muncul di dua site -- sehingga per-PIC
 * sebetulnya rincian di dalam site, bukan dimensi yang memotongnya. Karena itu
 * PIC tidak dipakai sebagai baris matriks, melainkan panel tersendiri.
 *
 * BARIS MATRIKS HANYA PASANGAN YANG PERNAH KEDAPATAN, sejalan halaman cacah
 * lain: memuat seluruh 18 perusahaan x 5 site hanya menghasilkan baris nol
 * yang tidak terbaca.
 */
final class PelanggaranOverspeedController extends Controller
{
    use ServesDataTable;

    /**
     * Band resmi parameter berbasis cacah: [batas atas, nilai, label].
     *
     *   X = 0        -> Nilai 4      1 <= X <= 3  -> Nilai 3
     *   4 <= X <= 5  -> Nilai 2      X > 5        -> Nilai 1
     *
     * NILAINYA SENGAJA BULAT, tanpa koma seperti parameter persentase: yang
     * diukur adalah banyaknya kejadian, jadi tidak ada posisi "di antara" dua
     * batas yang bermakna. Batas ditulis sebagai batas ATAS supaya rata-rata
     * bulanan yang berupa pecahan tetap jatuh di band yang masuk akal.
     */
    private const SCORE_BANDS = [
        [0.0, 4, 'tidak ada pelanggar'],
        [3.0, 3, '1 - 3 pelanggar'],
        [5.0, 2, '4 - 5 pelanggar'],
    ];

    private const TABLE = 'lead_pelanggaran_overspeed';

    private const COL_SITE = 'site_by_approval';
    private const COL_PIC = 'pic_approval';
    private const COL_PERUSAHAAN = 'perusahaan';
    private const COL_BULAN = 'month_of_start_date_be_record';
    private const COL_JUMLAH = 'distinct_count_of_kode_sid_bep_vw_berecord';

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
        'pic' => self::COL_PIC,
        'mitra' => self::COL_PERUSAHAAN,
    ];

    private const SEARCHABLE = [
        self::COL_SITE, self::COL_PIC, self::COL_PERUSAHAAN, self::COL_BULAN,
    ];

    private const ORDERABLE = [
        0 => self::COL_SITE,
        1 => self::COL_PIC,
        2 => self::COL_PERUSAHAAN,
        4 => self::COL_JUMLAH,
    ];

    public function index(): View
    {
        return view('ohs-score-card.pelanggaran-overspeed.index', [
            'filterOptions' => [
                'site' => $this->distinctValues(self::COL_SITE),
                'pic' => $this->distinctValues(self::COL_PIC),
                'mitra' => $this->distinctValues(self::COL_PERUSAHAAN),
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

        // Bulan yang bersih sama sekali tetap jadi kolom: justru itu kabar
        // baiknya, dan menghilangkannya membuat bulan tanpa pelanggar tak terlihat.
        $months = $this->rentangBulan(array_keys($monthSeen));
        $matrix = $this->buildMatrix($grid, $months);

        return response()->json([
            'months' => $this->monthHeadings($months),
            'kpi' => $this->buildKpi($matrix, $months, $request),
            'matrix' => $matrix,
            'per_site' => $this->ringkasPer($matrix, 'site'),
            'per_mitra' => $this->ringkasPer($matrix, 'mitra'),
            'per_pic' => $this->buildPerPic($request),
            'monthly' => $this->buildMonthlySeries($matrix, $months),
            'catatan' => $this->catatan(),
        ]);
    }

    /**
     * Bulan yang ditampilkan: dari yang paling awal sampai paling akhir ada
     * pelanggar, termasuk bulan di antaranya yang bersih.
     *
     * @param  array<int, int>  $adaTemuan
     * @return array<int, int>
     */
    private function rentangBulan(array $adaTemuan): array
    {
        if ($adaTemuan === []) {
            return [];
        }

        sort($adaTemuan);
        $out = [];

        for ($m = $adaTemuan[0]; $m <= $adaTemuan[count($adaTemuan) - 1]; $m++) {
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
    /**
     * Nilai 1-4 untuk sebuah cacah. Menerima pecahan supaya rata-rata bulanan
     * sebuah baris bisa dinilai dengan aturan yang sama.
     *
     * @return array{0: int, 1: string}
     */
    private function nilaiUntukCacah(float $jumlah): array
    {
        foreach (self::SCORE_BANDS as [$batasAtas, $nilai, $label]) {
            if ($jumlah <= $batasAtas) {
                return [$nilai, $label];
            }
        }

        return [1, 'lebih dari 5 pelanggar'];
    }

    private function buildMatrix(array $grid, array $months): array
    {
        $out = [];

        foreach ($grid as $entry) {
            $cells = [];
            $total = 0;
            $bersih = 0;

            foreach ($months as $month) {
                // 0, bukan null: tidak adanya baris di sini berarti tidak ada
                // pelanggar, dan itu kabar baik yang harus kelihatan.
                $jumlah = $entry['bulan'][$month] ?? 0;
                $cells[] = $jumlah;
                $total += $jumlah;

                if ($jumlah === 0) {
                    $bersih++;
                }
            }

            // Nilai dihitung di sini, bukan di layar, supaya matriks, kartu,
            // dan modal tidak mungkin berselisih angka.
            $rata = $months === [] ? 0.0 : $total / count($months);
            [$nilaiBaris, $bandBaris] = $this->nilaiUntukCacah($rata);

            $out[] = [
                'site' => $entry['site'],
                'mitra' => $entry['mitra'],
                'cells' => $cells,
                'total' => $total,
                'puncak' => $cells === [] ? 0 : max($cells),
                'bulan_bersih' => $bersih,
                'bulan_kena' => count($months) - $bersih,
                'trend' => $this->trendOf($cells),
                'nilai_cells' => array_map(
                    fn (int $n): int => $this->nilaiUntukCacah((float) $n)[0],
                    $cells
                ),
                'rata' => round($rata, 2),
                'nilai' => $nilaiBaris,
                'nilai_band' => $bandBaris,
            ];
        }

        return $this->kelompokkanPerSite($out);
    }

    /**
     * Mengelompokkan baris per site supaya sel site-nya bisa digabung dengan
     * rowspan di tabel, site dengan pelanggar terbanyak di atas.
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

        // Naik berarti memburuk: pelanggar bertambah.
        return $akhir > $sebelum ? 'up' : 'down';
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildKpi(array $matrix, array $months, Request $request): array
    {
        $total = array_sum(array_column($matrix, 'total'));

        // Bulan yang sama sekali tidak ada pelanggar di seluruh site & perusahaan.
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
            'temuan' => $total,
            'kombinasi' => count($matrix),
            'baris' => (clone $this->baseQuery($request))->count(),
            'site_count' => count(array_unique(array_column($matrix, 'site'))),
            'mitra_count' => count(array_unique(array_column($matrix, 'mitra'))),
            'pic_count' => (clone $this->baseQuery($request))->distinct()->count(self::COL_PIC),
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
     * Cacah pelanggar per site atau per perusahaan.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function ringkasPer(array $matrix, string $key): array
    {
        $kelompok = [];

        foreach ($matrix as $row) {
            $kelompok[$row[$key]]['jumlah'] = ($kelompok[$row[$key]]['jumlah'] ?? 0) + $row['total'];
            $kelompok[$row[$key]]['lawan'][$row[$key] === $row['site'] ? $row['mitra'] : $row['site']] = true;
        }

        $grand = array_sum(array_column($kelompok, 'jumlah'));
        $out = [];

        foreach ($kelompok as $label => $agg) {
            $out[] = [
                $key => (string) $label,
                'jumlah' => $agg['jumlah'],
                // Berapa banyak lawan dimensinya: untuk site berarti jumlah
                // perusahaan yang kedapatan, dan sebaliknya.
                'lawan' => count($agg['lawan']),
                'percent' => $grand > 0 ? round($agg['jumlah'] / $grand * 100, 2) : 0.0,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['jumlah'] <=> $a['jumlah']);

        return $out;
    }

    /**
     * Cacah pelanggar per PIC approval, beserta site tempatnya bertugas.
     *
     * Dikueri langsung dari tabel, bukan diturunkan dari matriks, karena PIC
     * bukan salah satu dimensi matriks.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPerPic(Request $request): array
    {
        $rows = $this->baseQuery($request)
            ->selectRaw(
                self::COL_PIC . ' AS pic, '
                . 'MIN(' . self::COL_SITE . ') AS site, '
                . 'SUM(' . self::COL_JUMLAH . ') AS jumlah, '
                . 'COUNT(DISTINCT ' . self::COL_PERUSAHAAN . ') AS mitra_count, '
                . 'COUNT(DISTINCT ' . self::COL_BULAN . ') AS bulan_count'
            )
            ->groupBy('pic')
            ->get();

        $grand = (int) $rows->sum('jumlah');

        return $rows
            ->map(static fn (object $r): array => [
                'pic' => trim((string) $r->pic),
                'site' => trim((string) $r->site),
                'jumlah' => (int) $r->jumlah,
                'mitra_count' => (int) $r->mitra_count,
                'bulan_count' => (int) $r->bulan_count,
                'percent' => $grand > 0 ? round((int) $r->jumlah / $grand * 100, 2) : 0.0,
            ])
            ->sortByDesc('jumlah')
            ->values()
            ->all();
    }

    /**
     * Satu garis total pelanggar per bulan, ditambah garis per site teratas.
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
                . 'justru berarti tidak ada pelanggaran sama sekali.';
        }

        return 'Angkanya cacah KARYAWAN BERBEDA yang kedapatan (SID unik), bukan cacah kejadian, '
            . 'jadi satu orang yang melanggar berkali-kali dalam sebulan tetap terhitung satu. '
            . 'Tabel ' . self::TABLE . ' hanya memuat site, PIC, perusahaan, dan bulan yang '
            . 'kedapatan; sel bernilai 0 berarti tidak ada pelanggar pada bulan itu, bukan datanya '
            . 'belum masuk, dan perusahaan yang tidak pernah muncul memang tidak punya pelanggar.';
    }

    // ======================================================================
    // Rincian satu sel matriks
    // ======================================================================

    /**
     * Rincian satu sel matriks "Pelanggar per Bulan".
     *
     * PARAMETERNYA SITE + PERUSAHAAN + BULAN, persis grain baris matriks di
     * overview(): di sana pengelompokannya site x perusahaan x bulan dan
     * seluruh PIC di dalamnya DIJUMLAHKAN. Menambahkan pic sebagai parameter
     * karena itu akan menjaring lebih sempit daripada selnya sendiri.
     *
     * Sudah diperiksa ke sumber: tidak ada satu pun (site, perusahaan, bulan)
     * yang ditangani lebih dari satu PIC, jadi untuk data sekarang SUM selalu
     * atas satu baris. Penjumlahannya tetap ditulis supaya tetap sama dengan
     * overview() seandainya kelak ada.
     *
     * SEL NOL TETAP DIBUKA. Nol di halaman ini bukan "data belum masuk"
     * melainkan "tidak ada pelanggar", dan itu kabar baik yang pantas dibaca
     * lengkap dengan konteksnya.
     *
     * YANG DIJUMLAHKAN TETAP ORANG, BUKAN KEJADIAN, dan hanya di dalam satu
     * baris sumber. Menjumlahkan antar bulan bisa menghitung orang yang sama
     * lebih dari sekali; SID-nya tidak tersimpan sehingga pengulangan itu tidak
     * mungkin dikurangkan. Peringatannya ikut dikirim dan ditampilkan di modal.
     */
    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));

        // Bulan di sumber tidak bertahun ("July", bukan "July 2026"), jadi
        // monthHeadings() mengirim 1-12. Kode tahun*100+bulan tetap diterima
        // supaya tautan dari halaman lain tidak patah; tahunnya diabaikan
        // karena memang tidak ada yang bisa dicocokkan.
        $kode = (int) $request->input('month', 0);
        $bulan = $kode > 9999 ? $kode % 100 : $kode;

        if ($site === '' || $mitra === '' || $bulan < 1 || $bulan > 12) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Site, perusahaan, dan bulan wajib diisi untuk membuka rincian.',
            ]);
        }

        if (in_array($bulan, self::EXCLUDED_MONTHS, true)) {
            return response()->json([
                'ok' => false,
                'pesan' => self::monthLabel($bulan) . ' masih berjalan saat data ini diambil dan '
                    . 'dikecualikan dari seluruh halaman, jadi rinciannya tidak ditampilkan.',
            ]);
        }

        $months = $this->bulanTercakup();
        $riwayat = $this->riwayatPasangan($site, $mitra, $months, $bulan);
        $jumlah = $this->jumlahPelanggar($bulan, $site, $mitra);
        $siteBulan = $this->jumlahPelanggar($bulan, $site);
        $semuaBulan = $this->jumlahPelanggar($bulan);
        $total = array_sum(array_column($riwayat, 'jumlah'));
        $kena = count(array_filter($riwayat, static fn (array $r): bool => $r['jumlah'] > 0));
        $puncak = $riwayat === [] ? 0 : max(array_column($riwayat, 'jumlah'));
        $bulanPuncak = null;

        foreach ($riwayat as $r) {
            if ($puncak > 0 && $r['jumlah'] === $puncak && $bulanPuncak === null) {
                $bulanPuncak = $r['bulan'];
            }
        }

        return response()->json([
            'ok' => true,
            'judul' => [
                'site' => $site,
                'mitra' => $mitra,
                'bulan' => self::monthLabel($bulan),
            ],
            'sel' => [
                'jumlah' => $jumlah,
                'bersih' => $jumlah === 0,
                // Porsi, bukan capaian: parameter ini tidak punya penyebut
                // sehingga tidak ada band Nilai 1-4 yang bisa diturunkan.
                'porsi_site' => $siteBulan > 0 ? round($jumlah / $siteBulan * 100, 2) : null,
                'porsi_semua' => $semuaBulan > 0 ? round($jumlah / $semuaBulan * 100, 2) : null,
            ],
            'pasangan' => [
                'total' => $total,
                'bulan_count' => count($months),
                'bulan_kena' => $kena,
                'bulan_bersih' => count($months) - $kena,
                'puncak' => $puncak,
                'bulan_puncak' => $bulanPuncak,
            ],
            'site_bulan' => [
                'jumlah' => $siteBulan,
                'mitra_count' => $this->cacahDimensi($bulan, self::COL_PERUSAHAAN, $site),
            ],
            'semua_bulan' => [
                'jumlah' => $semuaBulan,
                'site_count' => $this->cacahDimensi($bulan, self::COL_SITE),
            ],
            'riwayat' => $riwayat,
            'sebulan' => $this->sebulanDiSite($site, $bulan, $mitra),
            'pic' => $this->picDiSite($site, $bulan, $mitra),
        ]);
    }

    /** Tabel dengan bulan yang dikecualikan sudah dibuang, tanpa filter halaman. */
    private function tabelTercakup(): Builder
    {
        $query = DB::table(self::TABLE);

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn(self::COL_BULAN, $this->namaBulan($nomor));
        }

        return $query;
    }

    /**
     * Bulan yang dipakai matriks, dihitung dengan cara yang sama persis dengan
     * overview() tetapi tanpa filter halaman: rincian memang sengaja
     * memperlihatkan seluruh riwayat, bukan potongan yang sedang disaring.
     *
     * @return array<int, int>
     */
    private function bulanTercakup(): array
    {
        $seen = [];

        foreach ($this->tabelTercakup()->distinct()->pluck(self::COL_BULAN) as $nilai) {
            $nomor = $this->nomorBulan((string) $nilai);

            if ($nomor !== 0) {
                $seen[$nomor] = true;
            }
        }

        return $this->rentangBulan(array_keys($seen));
    }

    /** Cacah pelanggar satu bulan, dipersempit ke site dan/atau perusahaan. */
    private function jumlahPelanggar(int $bulan, ?string $site = null, ?string $mitra = null): int
    {
        $query = DB::table(self::TABLE)->whereIn(self::COL_BULAN, $this->namaBulan($bulan));

        if ($site !== null) {
            $query->where(self::COL_SITE, $site);
        }

        if ($mitra !== null) {
            $query->where(self::COL_PERUSAHAAN, $mitra);
        }

        return (int) $query->sum(self::COL_JUMLAH);
    }

    /** Banyaknya nilai berbeda satu kolom pada bulan itu, opsional dalam satu site. */
    private function cacahDimensi(int $bulan, string $kolom, ?string $site = null): int
    {
        $query = DB::table(self::TABLE)->whereIn(self::COL_BULAN, $this->namaBulan($bulan));

        if ($site !== null) {
            $query->where(self::COL_SITE, $site);
        }

        return $query->distinct()->count($kolom);
    }

    /**
     * Pasangan site x perusahaan yang sama sepanjang bulan yang tercakup:
     * kronis atau sesaat?
     *
     * Bulan tanpa baris diterbitkan sebagai 0, bukan dilewati — sama dengan
     * buildMatrix(), karena bulan bersih justru kabar baiknya.
     *
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    private function riwayatPasangan(string $site, string $mitra, array $months, int $bulanIni): array
    {
        $perBulan = [];

        $rows = $this->tabelTercakup()
            ->where(self::COL_SITE, $site)
            ->where(self::COL_PERUSAHAAN, $mitra)
            ->selectRaw(self::COL_BULAN . ' AS bulan, SUM(' . self::COL_JUMLAH . ') AS jumlah')
            ->groupBy('bulan')
            ->get();

        foreach ($rows as $r) {
            $nomor = $this->nomorBulan((string) $r->bulan);

            if ($nomor !== 0) {
                $perBulan[$nomor] = ($perBulan[$nomor] ?? 0) + (int) $r->jumlah;
            }
        }

        $out = [];

        foreach ($months as $nomor) {
            $out[] = [
                'nomor' => $nomor,
                'bulan' => self::monthLabel($nomor),
                'jumlah' => $perBulan[$nomor] ?? 0,
                'ini' => $nomor === $bulanIni,
            ];
        }

        return $out;
    }

    /**
     * Site x bulan yang sama di seluruh perusahaan: pelanggarnya milik satu
     * perusahaan saja atau memang merata se-site?
     *
     * Perusahaan sel yang sedang dibuka selalu disertakan, walau nol, supaya
     * sel bersih tetap punya barisnya sendiri untuk dibandingkan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sebulanDiSite(string $site, int $bulan, string $mitraTerpilih): array
    {
        $rows = DB::table(self::TABLE)
            ->where(self::COL_SITE, $site)
            ->whereIn(self::COL_BULAN, $this->namaBulan($bulan))
            ->selectRaw(self::COL_PERUSAHAAN . ' AS mitra, SUM(' . self::COL_JUMLAH . ') AS jumlah')
            ->groupBy('mitra')
            ->get();

        $out = [];
        $adaTerpilih = false;

        foreach ($rows as $r) {
            $mitra = trim((string) $r->mitra);
            $adaTerpilih = $adaTerpilih || $mitra === $mitraTerpilih;

            $out[] = [
                'mitra' => $mitra,
                'jumlah' => (int) $r->jumlah,
                'ini' => $mitra === $mitraTerpilih,
            ];
        }

        if (! $adaTerpilih) {
            $out[] = ['mitra' => $mitraTerpilih, 'jumlah' => 0, 'ini' => true];
        }

        usort($out, static fn (array $a, array $b): int => ($b['jumlah'] <=> $a['jumlah'])
            ?: strcmp($a['mitra'], $b['mitra']));

        return $out;
    }

    /**
     * PIC approval di site ini — dimensi yang membedakan parameter ini dari
     * halaman cacah lain, dan satu-satunya sumbu ketiga yang dipunyainya.
     *
     * Daftar PIC-nya diambil dari SELURUH tabel untuk site itu, bukan hanya
     * bulan yang dibuka. Kalau hanya bulan itu yang dikueri, sel bersih akan
     * tampil tanpa satu nama pun, padahal justru itu yang ingin dibaca: siapa
     * PIC di site ini, dan di bulan ini tidak ada satu pun pelanggar di bawahnya.
     *
     * @return array<int, array<string, mixed>>
     */
    private function picDiSite(string $site, int $bulan, string $mitra): array
    {
        $nilaiBulan = $this->namaBulan($bulan);

        $total = $this->tabelTercakup()
            ->where(self::COL_SITE, $site)
            ->selectRaw(
                self::COL_PIC . ' AS pic, '
                . 'SUM(' . self::COL_JUMLAH . ') AS jumlah, '
                . 'COUNT(DISTINCT ' . self::COL_PERUSAHAAN . ') AS mitra_count'
            )
            ->groupBy('pic')
            ->get();

        $perBulan = $this->ringkasPicBulan($site, $nilaiBulan);
        $perSel = $this->ringkasPicBulan($site, $nilaiBulan, $mitra);

        $out = [];

        foreach ($total as $r) {
            $pic = trim((string) $r->pic);
            $sel = $perSel[$pic] ?? 0;

            $out[] = [
                'pic' => $pic,
                'sel' => $sel,
                'bulan_ini' => $perBulan[$pic] ?? 0,
                'total' => (int) $r->jumlah,
                'mitra_count' => (int) $r->mitra_count,
                'ini' => $sel > 0,
            ];
        }

        usort($out, static fn (array $a, array $b): int => ($b['sel'] <=> $a['sel'])
            ?: (($b['bulan_ini'] <=> $a['bulan_ini'])
            ?: ($b['total'] <=> $a['total'])));

        return $out;
    }

    /**
     * Cacah pelanggar per PIC pada satu bulan di satu site, opsional dipersempit
     * ke satu perusahaan.
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<string, int>
     */
    private function ringkasPicBulan(string $site, array $nilaiBulan, ?string $mitra = null): array
    {
        $query = DB::table(self::TABLE)
            ->where(self::COL_SITE, $site)
            ->whereIn(self::COL_BULAN, $nilaiBulan);

        if ($mitra !== null) {
            $query->where(self::COL_PERUSAHAAN, $mitra);
        }

        $rows = $query
            ->selectRaw(self::COL_PIC . ' AS pic, SUM(' . self::COL_JUMLAH . ') AS jumlah')
            ->groupBy('pic')
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $out[trim((string) $r->pic)] = (int) $r->jumlah;
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
            ['Site', 'PIC Approval', 'Perusahaan', 'Bulan', 'Jumlah Pelanggar'],
            function (object $row): array {
                $p = $this->present($row);

                return [$p['site'], $p['pic'], $p['mitra'], $p['bulan'], $p['jumlah']];
            },
            'pelanggaran-overspeed'
        );
    }

    /** @return array<int, string> */
    private function columns(): array
    {
        return [
            'id',
            self::COL_SITE . ' AS site',
            self::COL_PIC . ' AS pic',
            self::COL_PERUSAHAAN . ' AS mitra',
            self::COL_BULAN . ' AS bulan_sumber',
            self::COL_JUMLAH . ' AS jumlah',
        ];
    }

    private function dataQuery(Request $request): Builder
    {
        $query = $this->baseQuery($request);

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            self::SEARCHABLE
        );

        return $query;
    }

    /** Tabel dengan filter dimensi & bulan terpasang. */
    private function baseQuery(Request $request): Builder
    {
        $query = DB::table(self::TABLE);

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
     * Satu baris dalam bentuk siap tampil.
     *
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        $monthNo = $this->nomorBulan((string) $row->bulan_sumber);

        return [
            'site' => trim((string) $row->site),
            'pic' => trim((string) $row->pic),
            'mitra' => trim((string) $row->mitra),
            'bulan' => $monthNo === 0 ? trim((string) $row->bulan_sumber) : self::monthLabel($monthNo),
            'jumlah' => (int) $row->jumlah,
        ];
    }

    // ======================================================================
    // Utilitas
    // ======================================================================

    /**
     * Nomor bulan dari dua bentuk penulisan: "M01".."M12" maupun nama bulan
     * Inggris. 0 bila tidak dikenali.
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
