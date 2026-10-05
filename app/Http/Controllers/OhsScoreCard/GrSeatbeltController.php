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
 * Parameter SOD "GR Seatbelt".
 *
 * Sumbernya lead_gr_seatbelt: satu baris per site x perusahaan x bulan yang
 * KEDAPATAN pelanggaran seatbelt, berisi cacah task-nya. Tidak ada tabel
 * rinciannya.
 *
 * BENTUKNYA SAMA PERSIS DENGAN "Tidak ada temuan penggunaan HP", dan dibaca
 * dengan cara yang sama:
 *
 *   - Yang diukur CACAH PELANGGARAN, bukan persentase. Tidak ada penyebut,
 *     jadi tidak ada band Nilai 1-4 seperti di halaman Ratio atau Blindspot.
 *   - TARGETNYA NOL. Yang baik adalah tidak muncul sama sekali di tabel ini.
 *   - SEL KOSONG BERARTI BAIK. Di halaman capaian sel kosong berarti "datanya
 *     belum ada"; di sini berarti "tidak ada pelanggaran". Karena itu sel yang
 *     tidak punya baris dikirim sebagai 0, bukan null, dan diwarnai hijau.
 *     Lihat buildMatrix().
 *
 * BARIS MATRIKS HANYA PASANGAN YANG PERNAH KEDAPATAN, karena memuat seluruh
 * 19 perusahaan x 7 site akan menghasilkan baris yang hampir semuanya nol.
 */
final class GrSeatbeltController extends Controller
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
        [0.0, 4, 'tidak ada pelanggaran'],
        [3.0, 3, '1 - 3 pelanggaran'],
        [5.0, 2, '4 - 5 pelanggaran'],
    ];

    private const TABLE = 'lead_gr_seatbelt';

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
        return view('ohs-score-card.gr-seatbelt.index', [
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
        // baiknya, dan menghilangkannya membuat bulan bersih tak terlihat.
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

        return [1, 'lebih dari 5 pelanggaran'];
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
                // pelanggaran, dan itu kabar baik yang harus kelihatan.
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
     * rowspan di tabel, site dengan pelanggaran terbanyak di atas.
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

        // Naik berarti memburuk: pelanggaran bertambah.
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
     * Cacah pelanggaran per site atau per perusahaan.
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
     * Satu garis total pelanggaran per bulan, ditambah garis per site teratas.
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

        return 'Tabel ' . self::TABLE . ' hanya memuat site, perusahaan, dan bulan yang kedapatan '
            . 'pelanggaran. Sel bernilai 0 berarti tidak ada pelanggaran pada bulan itu, bukan '
            . 'datanya belum masuk, dan perusahaan yang tidak pernah muncul memang bersih.';
    }

    // ======================================================================
    // Rincian satu sel matriks
    // ======================================================================

    /**
     * Konteks di sekeliling satu sel matriks (site x perusahaan x bulan).
     *
     * Tidak ada tabel rincian per pelanggaran — sumbernya sudah berupa cacah —
     * jadi yang disajikan konteksnya: riwayat pasangan ini sepanjang bulan,
     * seluruh perusahaan di site itu pada bulan yang sama, dan perusahaan itu
     * di site lain pada bulan yang sama.
     *
     * KARTUNYA BUKAN KARTU HALAMAN PERSENTASE. Tidak ada penyebut di parameter
     * ini, jadi tidak ada capaian, tidak ada Nilai 1-4, dan tidak ada selisih
     * ke target persen. Targetnya nol pelanggaran.
     *
     * SEL BERNILAI 0 ADALAH JAWABAN YANG SAH, bukan "data belum ada". Tabelnya
     * hanya memuat baris yang kedapatan, jadi tidak adanya baris berarti tidak
     * ada pelanggaran — dan itu justru hasil yang diinginkan. Modal karena itu
     * tetap menjawab penuh untuk sel nol, lengkap dengan berapa bulan beruntun
     * pasangan ini bersih.
     */
    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));

        // Kolom bulan di sumber ini tidak bertahun ("M07"), jadi matriksnya
        // memakai nomor 1-12. Kode tahun*100+bulan tetap diterima supaya
        // tautan dari halaman lain tidak patah; tahunnya sendiri tidak bisa
        // dipakai menyaring apa pun di sini.
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
                'pesan' => self::monthLabel($bulan) . ' tidak ikut dihitung di parameter ini '
                    . 'karena bulannya masih berjalan saat data diambil.',
            ]);
        }

        $nilaiBulan = $this->namaBulan($bulan);
        $riwayat = $this->riwayatPasangan($site, $mitra, $bulan);
        $sebulan = $this->sebulanDiSite($site, $nilaiBulan, $mitra);
        $jumlah = $this->cacah(
            DB::table(self::TABLE)
                ->where(self::COL_SITE, $site)
                ->where(self::COL_PERUSAHAAN, $mitra)
                ->whereIn(self::COL_BULAN, $nilaiBulan)
        );

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
            ],
            'site_bulan' => $this->ringkasSebulan($sebulan),
            'peringkat' => $this->peringkatDiSite($sebulan, $jumlah),
            'rekam' => $this->rekamJejak($riwayat, $bulan),
            'riwayat' => $riwayat,
            'sebulan' => $sebulan,
            'lintas_site' => $this->lintasSite($mitra, $nilaiBulan, $site),
        ]);
    }

    /**
     * Cacah pelanggaran sekumpulan baris, dijumlahkan persis seperti
     * overview() supaya sel dan modal tidak mungkin berselisih.
     */
    private function cacah(Builder $query): int
    {
        $row = $query
            ->selectRaw('COALESCE(SUM(' . self::COL_JUMLAH . '), 0) AS jumlah')
            ->first();

        return (int) ($row->jumlah ?? 0);
    }

    /**
     * Bulan yang tercakup matriks tanpa filter: dari bulan paling awal sampai
     * paling akhir ada pelanggaran, termasuk bulan bersih di antaranya. Sama
     * persis dengan rentangBulan() yang dipakai overview().
     *
     * @return array<int, int>
     */
    private function bulanTercakup(): array
    {
        $ada = [];

        foreach ($this->distinctValues(self::COL_BULAN) as $nilai) {
            $nomor = $this->nomorBulan($nilai);

            if ($nomor !== 0) {
                $ada[$nomor] = true;
            }
        }

        return $this->rentangBulan(array_keys($ada));
    }

    /**
     * Pasangan site x perusahaan yang sama sepanjang bulan: pelanggarannya
     * menetap atau sekali saja?
     *
     * Bulan tanpa baris dikirim sebagai 0, bukan dilewati — di parameter ini
     * bulan bersih adalah kabar baik yang justru harus kelihatan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function riwayatPasangan(string $site, string $mitra, int $bulanIni): array
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

            if ($nomor !== 0) {
                $perBulan[$nomor] = ($perBulan[$nomor] ?? 0) + (int) $row->jumlah;
            }
        }

        $out = [];

        foreach ($this->bulanTercakup() as $nomor) {
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
     * Seluruh perusahaan yang pernah kedapatan di site ini, dengan cacahnya
     * pada bulan yang dibuka. Yang tidak punya baris bulan itu ikut tampil
     * dengan 0, supaya terlihat siapa yang bersih dan siapa yang tidak.
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<int, array<string, mixed>>
     */
    private function sebulanDiSite(string $site, array $nilaiBulan, string $mitraTerpilih): array
    {
        return $this->cacahPerKolom(
            $site,
            self::COL_SITE,
            self::COL_PERUSAHAAN,
            'mitra',
            $nilaiBulan,
            $mitraTerpilih
        );
    }

    /**
     * Perusahaan yang sama di site lain pada bulan yang sama: masalahnya
     * melekat pada perusahaannya atau pada site ini saja?
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<int, array<string, mixed>>
     */
    private function lintasSite(string $mitra, array $nilaiBulan, string $siteTerpilih): array
    {
        return $this->cacahPerKolom(
            $mitra,
            self::COL_PERUSAHAAN,
            self::COL_SITE,
            'site',
            $nilaiBulan,
            $siteTerpilih
        );
    }

    /**
     * Daftar nilai $kolomPecah yang pernah muncul berpasangan dengan
     * $nilaiTetap, beserta cacah pelanggarannya pada bulan yang dibuka.
     *
     * Daftarnya diambil dari seluruh tabel, bukan dari bulan itu saja: itulah
     * yang membuat baris bernilai 0 ikut muncul, dan baris nol itulah yang
     * membedakan "bersih" dari "tidak terdaftar".
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<int, array<string, mixed>>
     */
    private function cacahPerKolom(
        string $nilaiTetap,
        string $kolomTetap,
        string $kolomPecah,
        string $kunci,
        array $nilaiBulan,
        string $terpilih
    ): array {
        $pernah = DB::table(self::TABLE)
            ->where($kolomTetap, $nilaiTetap)
            ->distinct()
            ->pluck($kolomPecah)
            ->map(static fn ($v): string => trim((string) $v))
            ->filter(static fn (string $v): bool => $v !== '')
            ->unique()
            ->all();

        $bulanIni = [];

        $rows = DB::table(self::TABLE)
            ->where($kolomTetap, $nilaiTetap)
            ->whereIn(self::COL_BULAN, $nilaiBulan)
            ->selectRaw($kolomPecah . ' AS label, SUM(' . self::COL_JUMLAH . ') AS jumlah')
            ->groupBy('label')
            ->get();

        foreach ($rows as $row) {
            $label = trim((string) $row->label);
            $bulanIni[$label] = ($bulanIni[$label] ?? 0) + (int) $row->jumlah;
        }

        // Yang sedang dibuka selalu ikut, walau filter halaman membuatnya tidak
        // pernah muncul di daftar di atas.
        if ($terpilih !== '' && ! in_array($terpilih, $pernah, true)) {
            $pernah[] = $terpilih;
        }

        $out = [];

        foreach ($pernah as $label) {
            $out[] = [
                $kunci => $label,
                'jumlah' => $bulanIni[$label] ?? 0,
                'ini' => $label === $terpilih,
            ];
        }

        // Terbanyak di atas; kalau sama, yang namanya lebih awal dulu supaya
        // urutannya tidak berubah-ubah antar pemuatan.
        usort($out, static fn (array $a, array $b): int => $b['jumlah'] <=> $a['jumlah']
            ?: strcmp((string) $a[$kunci], (string) $b[$kunci]));

        return $out;
    }

    /**
     * Keadaan site itu pada bulan yang dibuka secara keseluruhan.
     *
     * @param  array<int, array<string, mixed>>  $sebulan
     * @return array<string, int>
     */
    private function ringkasSebulan(array $sebulan): array
    {
        return [
            'jumlah' => array_sum(array_column($sebulan, 'jumlah')),
            'kedapatan' => count(array_filter($sebulan, static fn (array $r): bool => $r['jumlah'] > 0)),
            'pernah' => count($sebulan),
        ];
    }

    /**
     * Posisi perusahaan ini di antara yang kedapatan di site itu pada bulan
     * yang dibuka, terburuk lebih dulu.
     *
     * Yang bersih sengaja tidak diberi peringkat: memberi nomor urut pada nol
     * akan menyiratkan ada yang lebih baik dan lebih buruk di antara sesama
     * nol, padahal nol adalah targetnya.
     *
     * @param  array<int, array<string, mixed>>  $sebulan
     * @return array<string, int|null>
     */
    private function peringkatDiSite(array $sebulan, int $jumlah): array
    {
        $kena = array_filter($sebulan, static fn (array $r): bool => $r['jumlah'] > 0);

        if ($jumlah === 0) {
            return ['posisi' => null, 'dari' => count($kena)];
        }

        $lebihBuruk = count(array_filter(
            $kena,
            static fn (array $r): bool => $r['jumlah'] > $jumlah
        ));

        return ['posisi' => $lebihBuruk + 1, 'dari' => count($kena)];
    }

    /**
     * Rekam jejak pasangan ini sepanjang bulan yang tercakup.
     *
     * "Bersih beruntun" dihitung mundur dari bulan yang dibuka, termasuk bulan
     * itu sendiri: untuk parameter bertarget nol, lamanya bersih adalah
     * prestasinya, dan itu tidak terbaca dari angka sel.
     *
     * @param  array<int, array<string, mixed>>  $riwayat
     * @return array<string, mixed>
     */
    private function rekamJejak(array $riwayat, int $bulanIni): array
    {
        $total = 0;
        $bersih = 0;
        $terakhirKena = null;
        $beruntun = 0;
        $menghitungBeruntun = false;

        foreach (array_reverse($riwayat) as $baris) {
            $total += $baris['jumlah'];

            // Riwayat dibalik jadi menurun, sehingga bulan kedapatan pertama
            // yang ditemui adalah yang terakhir secara kalender.
            if ($baris['jumlah'] === 0) {
                $bersih++;
            } elseif ($terakhirKena === null) {
                $terakhirKena = $baris['bulan'];
            }

            if ($baris['nomor'] === $bulanIni) {
                $menghitungBeruntun = true;
            }

            if ($menghitungBeruntun) {
                if ($baris['jumlah'] > 0) {
                    $menghitungBeruntun = false;
                } else {
                    $beruntun++;
                }
            }
        }

        return [
            'total' => $total,
            'bulan_count' => count($riwayat),
            'bulan_bersih' => $bersih,
            'bulan_kena' => count($riwayat) - $bersih,
            'terakhir_kena' => $terakhirKena,
            'bersih_beruntun' => $beruntun,
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
            ['Site', 'Perusahaan PIC', 'Bulan', 'Jumlah Pelanggaran'],
            function (object $row): array {
                $p = $this->present($row);

                return [$p['site'], $p['mitra'], $p['bulan'], $p['jumlah']];
            },
            'gr-seatbelt'
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
