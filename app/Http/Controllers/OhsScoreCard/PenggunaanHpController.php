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
 * Parameter SOD "Tidak ada temuan penggunaan HP".
 *
 * Sumbernya lead_gr_penggunaan_hp: satu baris per site x perusahaan x bulan
 * yang KEDAPATAN temuan, berisi cacah task-nya. Tidak ada tabel rinciannya.
 *
 * BENTUKNYA BERBEDA DARI PARAMETER LAIN DI MODUL INI, dan itu mengubah cara
 * membacanya:
 *
 *   - Yang diukur CACAH TEMUAN, bukan persentase. Tidak ada penyebut, jadi
 *     tidak ada band Nilai 1-4 seperti di halaman Ratio atau Blindspot;
 *     memaksakannya hanya akan mengarang angka.
 *   - TARGETNYA NOL. Nama parameternya "tidak ada temuan", jadi yang baik
 *     adalah tidak muncul sama sekali di tabel ini.
 *   - SEL KOSONG BERARTI BAIK. Di halaman lain sel kosong berarti "datanya
 *     belum ada"; di sini berarti "tidak ada temuan". Karena itu sel yang
 *     tidak punya baris dikirim sebagai 0, bukan null, dan diwarnai hijau.
 *     Lihat buildMatrix().
 *
 * BARIS MATRIKS HANYA PASANGAN YANG PERNAH KEDAPATAN. Memuat seluruh 21
 * perusahaan x 6 site akan menghasilkan 126 baris yang hampir semuanya nol dan
 * tidak terbaca; yang berguna justru siapa yang pernah muncul.
 */
final class PenggunaanHpController extends Controller
{
    use ServesDataTable;

    private const TABLE = 'lead_gr_penggunaan_hp';

    private const COL_SITE = 'site';
    private const COL_PERUSAHAAN = 'perusahaan_pic';
    private const COL_BULAN = 'month_of_date_for_join';
    private const COL_JUMLAH = 'distinct_count_of_task_number';

    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /** Oktober masih berjalan saat data ini diambil, sejalan halaman lain. */
    private const EXCLUDED_MONTHS = [10];

    /**
     * Targetnya nol, bukan sebuah persentase: nama parameternya "tidak ada
     * temuan penggunaan HP", jadi yang baik adalah tidak muncul sama sekali.
     */
    private const TARGET_TEMUAN = 0;

    private const FILTERABLE = [
        'site' => self::COL_SITE,
        'mitra' => self::COL_PERUSAHAAN,
    ];

    private const SEARCHABLE = [self::COL_SITE, self::COL_PERUSAHAAN, self::COL_BULAN];

    private const ORDERABLE = [
        0 => self::COL_SITE,
        1 => self::COL_PERUSAHAAN,
        3 => self::COL_JUMLAH,
    ];

    public function index(): View
    {
        return view('ohs-score-card.penggunaan-hp.index', [
            'filterOptions' => [
                'site' => $this->distinctValues(self::COL_SITE),
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
        // baiknya, dan menghilangkannya membuat bulan tanpa temuan tak terlihat.
        $months = $this->rentangBulan(array_keys($monthSeen));
        $matrix = $this->buildMatrix($grid, $months);

        return response()->json([
            'months' => $this->monthHeadings($months),
            'kpi' => $this->buildKpi($matrix, $months),
            'matrix' => $matrix,
            'per_site' => $this->ringkasPer($matrix, 'site'),
            'per_mitra' => $this->ringkasPer($matrix, 'mitra'),
            'monthly' => $this->buildMonthlySeries($matrix, $months),
            'catatan' => $this->catatan(),
        ]);
    }

    /**
     * Bulan yang ditampilkan: dari yang paling awal sampai paling akhir ada
     * temuan, termasuk bulan di antaranya yang bersih.
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
    private function buildMatrix(array $grid, array $months): array
    {
        $out = [];

        foreach ($grid as $entry) {
            $cells = [];
            $total = 0;
            $bersih = 0;

            foreach ($months as $month) {
                // 0, bukan null: tidak adanya baris di sini berarti tidak ada
                // temuan, dan itu kabar baik yang harus kelihatan.
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
    private function buildKpi(array $matrix, array $months): array
    {
        $total = array_sum(array_column($matrix, 'total'));

        // Bulan yang sama sekali tidak ada temuan di seluruh site & perusahaan.
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
            'site_count' => count(array_unique(array_column($matrix, 'site'))),
            'mitra_count' => count(array_unique(array_column($matrix, 'mitra'))),
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
     * Cacah temuan per site atau per perusahaan.
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
            . 'temuan. Sel bernilai 0 berarti tidak ada temuan pada bulan itu, bukan datanya belum '
            . 'masuk, dan perusahaan yang tidak pernah muncul sama sekali memang tidak punya temuan.';
    }

    // ======================================================================
    // Rincian satu sel matriks
    // ======================================================================

    /**
     * Rincian satu sel "Capaian per Bulan": site x perusahaan x bulan.
     *
     * TIDAK ADA KARTU CAPAIAN MAUPUN NILAI 1-4 DI SINI, dan itu disengaja.
     * Yang diukur cacah temuan tanpa penyebut, jadi persentase dan band Nilai
     * tidak bisa diturunkan dari sumber; memaksakannya sama saja mengarang
     * angka. Penggantinya cacah itu sendiri terhadap target nol, ditambah
     * konteks riwayat dan sebulan.
     *
     * SEL BERNILAI 0 ADALAH JAWABAN YANG SAH, BUKAN DATA YANG HILANG.
     * buildMatrix() mengirim bulan tanpa baris sebagai 0 dan mewarnainya hijau,
     * jadi rincian untuk sel itu pun harus berbunyi "tidak ada temuan di bulan
     * ini" — hasil yang justru bagus — bukan panel kosong.
     *
     * Kedua panel sengaja dipadankan dengan sumbu matriksnya, supaya modal
     * membaca persis seperti baris dan kolom yang diklik:
     *   - riwayat memuat seluruh bulan yang tercakup data, bulan bersih ikut
     *     dengan nilai 0;
     *   - sebulan memuat semua perusahaan yang pernah kedapatan di site itu,
     *     yang bersih pada bulan ini ikut dengan nilai 0.
     */
    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));

        // monthHeadings() halaman ini mengirim nomor 1-12 polos karena bulan di
        // sumber tidak bertahun ("M01"/"January"). Kode tahun*100+bulan tetap
        // diterima supaya tautan dari halaman bertahun tidak patah.
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
                'pesan' => self::monthLabel($bulan) . ' tidak ikut dihitung di parameter ini karena '
                    . 'bulannya masih berjalan saat data diambil.',
            ]);
        }

        $nilaiBulan = $this->namaBulan($bulan);
        $sebulan = $this->sebulanDiSite($site, $nilaiBulan, $mitra);
        $riwayat = $this->riwayatPasangan($site, $mitra, $this->sumbuBulan($bulan), $bulan);

        // Angka sel diambil dari riwayat, bukan dari query terpisah: keduanya
        // jadi satu agregasi yang sama persis dengan overview(), sehingga sel
        // matriks dan isi modal tidak mungkin berbeda.
        $jumlah = 0;

        foreach ($riwayat as $baris) {
            if ($baris['ini']) {
                $jumlah = $baris['jumlah'];
            }
        }

        return response()->json([
            'ok' => true,
            'judul' => [
                'site' => $site,
                'mitra' => $mitra,
                'bulan' => self::monthLabel($bulan),
            ],
            'target' => self::TARGET_TEMUAN,
            'sel' => [
                'jumlah' => $jumlah,
                'bersih' => $jumlah === self::TARGET_TEMUAN,
            ],
            'ringkas' => $this->ringkasRiwayat($riwayat, $bulan),
            'riwayat' => $riwayat,
            'sebulan' => $sebulan,
            'site' => [
                'jumlah' => array_sum(array_column($sebulan, 'jumlah')),
                'mitra_kena' => count(array_filter($sebulan, static fn (array $r): bool => $r['jumlah'] > 0)),
                'mitra_count' => count($sebulan),
            ],
            'semua' => $this->semuaSiteBulan($nilaiBulan),
        ]);
    }

    /**
     * Sumbu bulan panel riwayat: rentang bulan yang tercakup data, ditambah
     * bulan yang dibuka kalau kebetulan di luar rentang itu — selnya tetap
     * harus bisa ditunjuk di panelnya.
     *
     * @return array<int, int>
     */
    private function sumbuBulan(int $bulanIni): array
    {
        $ada = [];

        foreach ($this->distinctValues(self::COL_BULAN) as $nilai) {
            $nomor = $this->nomorBulan($nilai);

            if ($nomor !== 0) {
                $ada[] = $nomor;
            }
        }

        $months = $this->rentangBulan($ada);

        if (! in_array($bulanIni, $months, true)) {
            $months[] = $bulanIni;
            sort($months);
        }

        return $months;
    }

    /**
     * Baris matriksnya: pasangan site x perusahaan ini sepanjang bulan yang
     * tercakup. Bulan tanpa baris ikut bernilai 0 — itu bulan bersihnya, dan
     * menyembunyikannya akan membuat yang baik tampak seperti data hilang.
     *
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    private function riwayatPasangan(string $site, string $mitra, array $months, int $bulanIni): array
    {
        $rows = DB::table(self::TABLE)
            ->where(self::COL_SITE, $site)
            ->where(self::COL_PERUSAHAAN, $mitra)
            ->selectRaw(self::COL_BULAN . ' AS bulan, SUM(' . self::COL_JUMLAH . ') AS jumlah')
            ->groupBy('bulan')
            ->get();

        $perBulan = [];

        foreach ($rows as $row) {
            $nomor = $this->nomorBulan((string) $row->bulan);

            if ($nomor === 0) {
                continue; // nama bulan tak dikenal: jangan diam-diam dianggap bulan lain
            }

            $perBulan[$nomor] = ($perBulan[$nomor] ?? 0) + (int) $row->jumlah;
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
     * Kolom matriksnya: seluruh perusahaan yang pernah kedapatan di site ini,
     * dengan cacah temuannya pada bulan yang dibuka. Yang bersih bulan ini
     * tetap ditampilkan bernilai 0, sama seperti selnya di matriks.
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<int, array<string, mixed>>
     */
    private function sebulanDiSite(string $site, array $nilaiBulan, string $mitraTerpilih): array
    {
        $daftarQuery = DB::table(self::TABLE)->where(self::COL_SITE, $site);

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $daftarQuery->whereNotIn(self::COL_BULAN, $this->namaBulan($nomor));
        }

        $daftar = $daftarQuery
            ->distinct()
            ->pluck(self::COL_PERUSAHAAN)
            ->map(static fn ($v): string => trim((string) $v))
            ->filter(static fn (string $v): bool => $v !== '')
            ->unique()
            ->values()
            ->all();

        // Perusahaan yang dibuka selalu ikut, walau belum pernah kedapatan di
        // site ini sama sekali — modalnya tidak boleh menghilangkan selnya.
        if (! in_array($mitraTerpilih, $daftar, true)) {
            $daftar[] = $mitraTerpilih;
        }

        $bulanIni = DB::table(self::TABLE)
            ->where(self::COL_SITE, $site)
            ->whereIn(self::COL_BULAN, $nilaiBulan)
            ->selectRaw(self::COL_PERUSAHAAN . ' AS mitra, SUM(' . self::COL_JUMLAH . ') AS jumlah')
            ->groupBy('mitra')
            ->pluck('jumlah', 'mitra');

        $out = [];

        foreach ($daftar as $mitra) {
            $out[] = [
                'mitra' => $mitra,
                'jumlah' => (int) ($bulanIni[$mitra] ?? 0),
                'ini' => $mitra === $mitraTerpilih,
            ];
        }

        usort(
            $out,
            static fn (array $a, array $b): int => $b['jumlah'] <=> $a['jumlah']
                ?: strcmp($a['mitra'], $b['mitra'])
        );

        return $out;
    }

    /**
     * Bentuk temuan parameter ini pada bulan yang sama di seluruh site, untuk
     * menjawab "bulannya memang ramai atau cuma site ini".
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<string, mixed>
     */
    private function semuaSiteBulan(array $nilaiBulan): array
    {
        $row = DB::table(self::TABLE)
            ->whereIn(self::COL_BULAN, $nilaiBulan)
            ->selectRaw(
                'COALESCE(SUM(' . self::COL_JUMLAH . '), 0) AS jumlah, '
                . 'COUNT(DISTINCT ' . self::COL_SITE . ') AS site_kena'
            )
            ->first();

        return [
            'jumlah' => (int) ($row->jumlah ?? 0),
            'site_kena' => (int) ($row->site_kena ?? 0),
        ];
    }

    /**
     * Ringkasan baris riwayat: berapa bulan bersih, kapan terakhir kedapatan,
     * dan sudah berapa bulan beruntun bersih sampai bulan yang dibuka.
     *
     * @param  array<int, array<string, mixed>>  $riwayat
     * @return array<string, mixed>
     */
    private function ringkasRiwayat(array $riwayat, int $bulanIni): array
    {
        $total = 0;
        $bersih = 0;
        $puncak = 0;
        $puncakBulan = null;
        $terakhirKena = null;
        $beruntun = 0;

        foreach ($riwayat as $baris) {
            $total += $baris['jumlah'];

            if ($baris['jumlah'] === 0) {
                $bersih++;
                continue;
            }

            $terakhirKena = $baris['bulan'];

            if ($baris['jumlah'] > $puncak) {
                $puncak = $baris['jumlah'];
                $puncakBulan = $baris['bulan'];
            }
        }

        // Beruntun dihitung mundur dari bulan yang dibuka, bukan dari bulan
        // terakhir di sumbu: yang ditanya "sampai bulan ini sudah berapa lama
        // bersih", bukan keadaan sesudahnya.
        $sampaiSini = [];

        foreach ($riwayat as $baris) {
            $sampaiSini[] = $baris['jumlah'];

            if ($baris['nomor'] === $bulanIni) {
                break;
            }
        }

        for ($i = count($sampaiSini) - 1; $i >= 0 && $sampaiSini[$i] === 0; $i--) {
            $beruntun++;
        }

        return [
            'bulan_count' => count($riwayat),
            'bulan_bersih' => $bersih,
            'bulan_kena' => count($riwayat) - $bersih,
            'total' => $total,
            'puncak' => $puncak,
            'puncak_bulan' => $puncakBulan,
            'terakhir_kena' => $terakhirKena,
            'beruntun_bersih' => $beruntun,
        ];
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
            ['Site', 'Perusahaan PIC', 'Bulan', 'Jumlah Temuan'],
            function (object $row): array {
                $p = $this->present($row);

                return [$p['site'], $p['mitra'], $p['bulan'], $p['jumlah']];
            },
            'penggunaan-hp'
        );
    }

    /** @return array<int, string> */
    private function columns(): array
    {
        return [
            self::COL_SITE . ' AS site',
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
