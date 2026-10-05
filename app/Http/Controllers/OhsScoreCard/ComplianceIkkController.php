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
 * Parameter SOD "Kesesuaian Implementasi IKK".
 *
 * Sumbernya lead_coverage_daily_area_kritis_pengawas_safety: 58 baris, satu
 * baris per site x perusahaan pemilik izin x bulan. Yang diukur berapa persen
 * izin kerja khusus yang sudah dilengkapi OKK, dari
 * yang terdaftar. Makin tinggi makin baik. Tidak ada tabel rinciannya.
 *
 * KEMBARAN DEKAT "Coverage Area Kritis Pengawas Suptend up", DAN PERBEDAANNYA
 * BUKAN SEKADAR NAMA. Halaman ini diturunkan dari halaman itu karena bentuk
 * tabelnya sama persis, tetapi dua hal berbeda dan keduanya mengubah arti
 * angkanya:
 *
 *   - PENYEBUTNYA PER HARI, bukan per minggu. Kolomnya
 *     distinct_count_of_helper_detail_lokasi_teregister_date, sementara
 *     halaman Suptend up memakai ..._teregister_week. Satuan di seluruh
 *     halaman ini karena itu "IPK": satu lokasi yang terdaftar empat
 *     minggu dihitung dua puluh delapan, bukan empat. Angkanya pun jauh lebih
 *     besar -- 6.662 IPK dibanding 1.203 lokasi-minggu di halaman
 *     Suptend up -- jadi kedua halaman TIDAK bisa dibandingkan langsung.
 *   - YANG DIUKUR PENGAWAS SAFETY, bukan superintendent ke atas. Populasi
 *     pengawasnya berbeda, jadi capaian rendah di sini tidak berarti hal yang
 *     sama dengan capaian rendah di halaman itu.
 *
 * PERSENNYA BISA DITURUNKAN. pctcoverage_safety sama persis dengan
 * coverage_safety dibagi penyebutnya; sudah diperiksa, cocok di SELURUH 58
 * baris tanpa satu pun selisih. Karena itu rata-rata keseluruhan dihitung
 * BERBOBOT, bukan dengan merata-ratakan persentase.
 *
 * ANGKA BERBOBOTNYA LEBIH RENDAH, dan itu kebalikan dari dugaan biasa.
 * Capaian berbobotnya 45,83% (3.053 dari 6.662 IPK), sementara
 * merata-ratakan persentase memberi angka lebih tinggi. Sebabnya site dengan
 * lokasi terdaftar paling banyak justru capaiannya paling rendah -- BMO 2
 * memikul 2.014 IPK sementara BMO 1 hanya 416 -- sehingga rata-rata
 * polos menyembunyikan beban yang sebenarnya.
 *
 * BESAR SELISIHNYA TERGANTUNG BASIS, jadi JANGAN ditulis mati di sini.
 * Dihitung atas 11 baris matriks (site x PIC) selisihnya sekitar 6 poin;
 * dihitung atas 58 baris sumber sekitar 8 poin. Yang ditampilkan ke pembaca
 * adalah basis matriks, dan catatan() menurunkannya sendiri dari $matrix
 * supaya angkanya ikut berubah saat datanya bertambah. Kartu ringkasan memakai
 * angka berbobot, matriks menampilkan persen per sel apa adanya.
 *
 * TIDAK ADA OKTOBER DI TABEL INI. Isinya April sampai September 2026 saja,
 * jadi EXCLUDED_MONTHS tidak pernah benar-benar menyaring apa pun di sini.
 * Konstanta itu tetap dipasang supaya halaman ini berperilaku sama dengan
 * halaman OHS Score Card lain begitu Oktober masuk.
 *
 * NAMA KOLOM PERSENNYA TANPA GARIS BAWAH: "pctcoverage_safety", bukan
 * "pct_coverage_safety" seperti pola tabel lain. Mudah salah ketik.
 *
 * SATU NILAI PIC BERISI DUA PERUSAHAAN, "BAR,ACI". Dibiarkan apa adanya karena
 * memang begitu tersimpan di sumber; memecahnya akan menggandakan cacah
 * IPK yang penyebutnya tidak ikut terpecah.
 *
 * AMBANG & BAND mengikuti sistem penilaian OHS Score Card yang sama dengan
 * halaman lain (target 90%, band 98/90/80). Belum ada konfirmasi bahwa
 * parameter ini memakai band yang sama; kalau berbeda, ubah SCORE_BANDS dan
 * TARGET_PERCENT di bawah.
 */
final class ComplianceIkkController extends Controller
{
    use ServesDataTable;

    /**
     * TABLE di halaman ini BUKAN tabel yang dibaca langsung: seluruh kueri
     * lewat sumberIzin(), yang mendedupe per kode izin. Nama ini tetap ada
     * karena dipakai di pesan catatan() dan keterangan di layar.
     */
    private const TABLE = 'detail_lead_compliance_ikk';

    /** Tabel mentahnya; hanya sumberIzin() yang menyentuhnya. */
    private const TABEL_SUMBER = 'detail_lead_compliance_ikk';

    /** Tabel bulanan resmi, dipakai sebagai pembanding di catatan(). */
    private const TABEL_BULANAN = 'lead_compliance_ikk';

    /**
     * Tabel rincian menyimpan tanggal sebagai TEKS berformat AS, mis.
     * "6/30/2026 2:30:00 PM", dan tidak punya kolom bulan sama sekali.
     * Seluruh 2.725 baris terurai dengan ekspresi ini, nol gagal.
     */
    private const EKSPR_TANGGAL = "STR_TO_DATE(second_of_start_date_convert, '%m/%d/%Y %r')";

    private const COL_SITE = 'site_ikk';
    private const COL_PIC = 'mitra_ikk';
    private const COL_BULAN = 'bulan_ikk';
    private const COL_PERSEN = 'persen_ikk';
    private const COL_TERCOVER = 'ada_okk';
    private const COL_TERDAFTAR = 'satu';

    private const TARGET_PERCENT = 90.0;

    private const SCORE_BANDS = [
        [98.0, 4, '98% - 100%'],
        [90.0, 3, '90% - <98%'],
        [80.0, 2, '80% - <90%'],
        [0.0,  1, '<80%'],
    ];

    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /**
     * Sejalan halaman OHS Score Card lain. Di tabel ini Oktober memang belum
     * ada sama sekali -- isinya April sampai September 2026 -- jadi saringan
     * ini sekarang tidak membuang apa pun. Tetap dipasang supaya begitu
     * Oktober masuk, bulan berjalan tidak langsung ikut menarik tren ke bawah
     * seperti yang sudah terjadi di halaman kembarannya.
     */
    private const EXCLUDED_MONTHS = [10];

    private const FILTERABLE = [
        'site' => self::COL_SITE,
        'pic' => self::COL_PIC,
    ];

    private const SEARCHABLE = [self::COL_SITE, self::COL_PIC, self::COL_BULAN];

    private const ORDERABLE = [
        0 => self::COL_SITE,
        1 => self::COL_PIC,
        3 => self::COL_TERCOVER,
        4 => self::COL_TERDAFTAR,
        5 => self::COL_PERSEN,
    ];

    public function index(): View
    {
        return view('ohs-score-card.compliance-ikk.index', [
            'filterOptions' => [
                'site' => $this->distinctValues(self::COL_SITE),
                'pic' => $this->distinctValues(self::COL_PIC),
            ],
            'monthOptions' => $this->monthOptions(),
            'target' => self::TARGET_PERCENT,
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
                . self::COL_PIC . ' AS pic, '
                . self::COL_BULAN . ' AS bulan, '
                . 'SUM(' . self::COL_TERCOVER . ') AS tercover, '
                . 'SUM(' . self::COL_TERDAFTAR . ') AS terdaftar'
            )
            ->groupBy('site', 'pic', 'bulan')
            ->get();

        $grid = [];
        $bulanAda = [];

        foreach ($rows as $row) {
            $kunciBulan = $this->uraikanBulan((string) $row->bulan);

            if ($kunciBulan === null || in_array($kunciBulan[1], self::EXCLUDED_MONTHS, true)) {
                continue;
            }

            $kode = $kunciBulan[0] * 100 + $kunciBulan[1];
            $bulanAda[$kode] = true;

            $site = trim((string) $row->site);
            $pic = trim((string) $row->pic);
            $kunci = $site . '|' . $pic;

            $grid[$kunci]['site'] = $site;
            $grid[$kunci]['pic'] = $pic;
            $grid[$kunci]['bulan'][$kode] = [
                'tercover' => (int) $row->tercover,
                'terdaftar' => (int) $row->terdaftar,
            ];
        }

        ksort($bulanAda);
        $months = array_keys($bulanAda);
        $matrix = $this->buildMatrix($grid, $months);

        return response()->json([
            'months' => $this->monthHeadings($months),
            'kpi' => $this->buildKpi($matrix, $months),
            'matrix' => $matrix,
            'per_site' => $this->ringkasPer($matrix, 'site'),
            'per_pic' => $this->ringkasPer($matrix, 'pic'),
            'terendah' => $this->buildTerendah($matrix),
            'monthly' => $this->buildMonthlySeries($matrix, $months),
            'catatan' => $this->catatan($request, $matrix),
        ]);
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
            $terisi = [];
            $tercover = 0;
            $terdaftar = 0;

            foreach ($months as $bulan) {
                $isi = $entry['bulan'][$bulan] ?? null;

                // NULL dibiarkan NULL: "bulan itu tidak ada areanya" bukan
                // "nol persen". Nol persen sungguhan tetap tampil sebagai 0.
                if ($isi === null || $isi['terdaftar'] <= 0) {
                    $cells[] = null;
                    continue;
                }

                $persen = round($isi['tercover'] / $isi['terdaftar'] * 100, 2);
                [, $nilai, $band] = $this->scoreBandFor($persen);

                $cells[] = [
                    'pct' => $persen,
                    'nilai' => $nilai,
                    'nilai_band' => $band,
                    'tercover' => $isi['tercover'],
                    'terdaftar' => $isi['terdaftar'],
                ];

                $terisi[] = $persen;
                $tercover += $isi['tercover'];
                $terdaftar += $isi['terdaftar'];
            }

            // Rata-rata baris pun berbobot, sejalan dengan kartu ringkasan.
            $average = $terdaftar > 0 ? round($tercover / $terdaftar * 100, 2) : null;
            [, $nilai, $band] = $this->scoreBandFor($average ?? 0.0);

            $out[] = [
                'site' => $entry['site'],
                'pic' => $entry['pic'],
                'cells' => $cells,
                'average' => $average,
                'tercover' => $tercover,
                'terdaftar' => $terdaftar,
                'terendah' => $terisi !== [] ? min($terisi) : null,
                'bulan_terisi' => count($terisi),
                'nilai' => $average === null ? null : $nilai,
                'nilai_band' => $average === null ? null : $band,
                'memenuhi_target' => $average !== null && $average >= self::TARGET_PERCENT,
                'trend' => $this->trendOf($terisi),
            ];
        }

        return $this->kelompokkanPerSite($out);
    }

    /**
     * Mengelompokkan baris per site supaya sel site-nya bisa digabung dengan
     * rowspan di tabel.
     *
     * Site diurutkan dari capaian paling rendah, dan di dalam tiap site
     * barisnya juga dari yang paling rendah, sehingga yang perlu ditangani
     * lebih dulu tetap di atas meski sudah dikelompokkan.
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
            $nilai = array_filter(
                array_column($baris, 'average'),
                static fn (?float $v): bool => $v !== null
            );
            // Baris tanpa angka didorong ke belakang lewat sentinel 101.
            $bobot[$site] = $nilai !== [] ? min($nilai) : 101.0;
        }

        asort($bobot);
        $out = [];

        foreach (array_keys($bobot) as $site) {
            $baris = $perSite[$site];
            usort(
                $baris,
                static fn (array $a, array $b): int => ($a['average'] ?? 101.0) <=> ($b['average'] ?? 101.0)
            );
            $out = array_merge($out, $baris);
        }

        return $out;
    }

    /** @param  array<int, float>  $terisi */
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
     * @param  array<int, array<string, mixed>>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildKpi(array $matrix, array $months): array
    {
        $tercover = array_sum(array_column($matrix, 'tercover'));
        $terdaftar = array_sum(array_column($matrix, 'terdaftar'));

        $berbobot = $terdaftar > 0 ? round($tercover / $terdaftar * 100, 2) : null;
        [, $band, $bandLabel] = $this->scoreBandFor($berbobot ?? 0.0);

        $nilai = array_values(array_filter(
            array_column($matrix, 'average'),
            static fn (?float $v): bool => $v !== null
        ));

        $polos = $nilai !== [] ? round(array_sum($nilai) / count($nilai), 2) : null;

        $selKosong = 0;
        $selTerisi = 0;

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $cell) {
                $cell === null ? $selKosong++ : $selTerisi++;
            }
        }

        return [
            'rata' => $berbobot,
            'rata_polos' => $polos,
            'nilai' => $berbobot === null ? null : $band,
            'nilai_band' => $berbobot === null ? null : $bandLabel,
            'target' => self::TARGET_PERCENT,
            'memenuhi_target' => $berbobot !== null && $berbobot >= self::TARGET_PERCENT,
            'tercover' => $tercover,
            'terdaftar' => $terdaftar,
            'belum_tercover' => $terdaftar - $tercover,
            'tertinggi' => $nilai !== [] ? max($nilai) : null,
            'terendah' => $nilai !== [] ? min($nilai) : null,
            'kombinasi' => count($nilai),
            'kombinasi_kosong' => count($matrix) - count($nilai),
            'memenuhi' => count(array_filter(
                $matrix,
                static fn (array $r): bool => $r['memenuhi_target']
            )),
            'site_count' => count(array_unique(array_column($matrix, 'site'))),
            'pic_count' => count(array_unique(array_column($matrix, 'pic'))),
            'bulan_count' => count($months),
            'sel_terisi' => $selTerisi,
            'sel_kosong' => $selKosong,
        ];
    }

    /**
     * Capaian per site atau per PIC, berbobot jumlah lokasi terdaftar.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function ringkasPer(array $matrix, string $key): array
    {
        $kelompok = [];

        foreach ($matrix as $row) {
            if ($row['terdaftar'] <= 0) {
                continue;
            }

            $label = $row[$key];
            $kelompok[$label]['tercover'] = ($kelompok[$label]['tercover'] ?? 0) + $row['tercover'];
            $kelompok[$label]['terdaftar'] = ($kelompok[$label]['terdaftar'] ?? 0) + $row['terdaftar'];
            $kelompok[$label]['baris'] = ($kelompok[$label]['baris'] ?? 0) + 1;
            $kelompok[$label]['terendah'] = min(
                $kelompok[$label]['terendah'] ?? 101.0,
                $row['average'] ?? 101.0
            );
        }

        $out = [];

        foreach ($kelompok as $label => $agg) {
            $rata = round($agg['tercover'] / $agg['terdaftar'] * 100, 2);
            [, $band] = $this->scoreBandFor($rata);

            $out[] = [
                $key => (string) $label,
                'percent' => $rata,
                'nilai' => $band,
                'jumlah' => $agg['baris'],
                'tercover' => $agg['tercover'],
                'terdaftar' => $agg['terdaftar'],
                'terendah' => $agg['terendah'] > 100.0 ? $rata : $agg['terendah'],
                'target' => self::TARGET_PERCENT,
                'memenuhi_target' => $rata >= self::TARGET_PERCENT,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $out;
    }

    /**
     * Lima pasangan dengan capaian terendah.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function buildTerendah(array $matrix): array
    {
        $rows = array_filter($matrix, static fn (array $r): bool => $r['average'] !== null);
        usort($rows, static fn (array $a, array $b): int => $a['average'] <=> $b['average']);

        return array_map(static fn (array $r): array => [
            'site' => $r['site'],
            'pic' => $r['pic'],
            'percent' => $r['average'],
            'nilai' => $r['nilai'],
            'tercover' => $r['tercover'],
            'terdaftar' => $r['terdaftar'],
        ], array_slice($rows, 0, 5));
    }

    /**
     * Satu garis per site, berbobot per bulan.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $matrix, array $months): array
    {
        $perSite = [];

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $i => $cell) {
                if ($cell === null) {
                    continue;
                }

                $perSite[$row['site']][$i]['tercover'] =
                    ($perSite[$row['site']][$i]['tercover'] ?? 0) + $cell['tercover'];
                $perSite[$row['site']][$i]['terdaftar'] =
                    ($perSite[$row['site']][$i]['terdaftar'] ?? 0) + $cell['terdaftar'];
            }
        }

        ksort($perSite);
        $series = [];

        foreach ($perSite as $site => $perBulan) {
            $data = [];

            foreach (array_keys($months) as $i) {
                $isi = $perBulan[$i] ?? null;
                // null, bukan 0: bulan tanpa area harus putus di grafik.
                $data[] = $isi === null || $isi['terdaftar'] <= 0
                    ? null
                    : round($isi['tercover'] / $isi['terdaftar'] * 100, 2);
            }

            $series[] = ['name' => (string) $site, 'data' => $data];
        }

        return [
            'labels' => array_map(fn (int $kode): string => $this->labelBulan($kode), $months),
            'series' => $series,
        ];
    }

    /**
     * Keterangan supaya selisih angka dan sel kosong tidak disalahbaca.
     *
     * Contoh ketimpangan areanya diambil dari matriks yang BENAR-BENAR
     * ditampilkan, bukan angka yang dipatok di kode: begitu filternya diubah
     * atau bulan yang dikecualikan bergeser, kalimat ini ikut bergeser. Versi
     * pertama memakai angka patokan dan langsung meleset, karena dihitung saat
     * Oktober masih ikut.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     */
    private function catatan(Request $request, array $matrix): ?string
    {
        if (! DB::table(self::TABEL_SUMBER)->exists()) {
            return 'Tabel ' . self::TABLE . ' masih kosong, jadi belum ada yang bisa ditampilkan.';
        }

        $pesan = [];
        $perSite = [];

        foreach ($matrix as $row) {
            $perSite[$row['site']] = ($perSite[$row['site']] ?? 0) + $row['terdaftar'];
        }

        $perSite = array_filter($perSite);
        arsort($perSite);
        $contoh = '';

        if (count($perSite) >= 2) {
            $besar = array_key_first($perSite);
            $kecil = array_key_last($perSite);
            $contoh = ' — ' . $besar . ' punya ' . number_format($perSite[$besar], 0, ',', '.')
                . ' IPK sementara ' . $kecil . ' hanya '
                . number_format($perSite[$kecil], 0, ',', '.')
                . ', dan tanpa pembobotan keduanya dihitung sama berat';
        }

        // Besar selisihnya ikut dihitung, tidak dibilang "belasan poin": di
        // halaman kembarannya selisihnya memang belasan, di sini sembilan, dan
        // frasa yang dipatok akan salah di salah satu dari keduanya.
        $tercover = array_sum(array_column($matrix, 'tercover'));
        $terdaftar = array_sum(array_column($matrix, 'terdaftar'));

        $polos = array_values(array_filter(
            array_column($matrix, 'average'),
            static fn (?float $v): bool => $v !== null
        ));

        $selisih = $terdaftar > 0 && $polos !== []
            ? $tercover / $terdaftar * 100 - array_sum($polos) / count($polos)
            : null;

        $pesan[] = 'Capaian di kartu ringkasan dihitung BERBOBOT: jumlah IPK tercakup '
            . 'dibagi jumlah yang terdaftar. Merata-ratakan persentase antar baris memberi angka '
            . ($selisih === null
                ? 'lebih rendah'
                : number_format(abs($selisih), 1, ',', '.') . ' poin lebih '
                    . ($selisih >= 0 ? 'rendah' : 'tinggi'))
            . ' karena luas area tidak merata' . $contoh
            . '. Kedua angka ditampilkan berdampingan.';

        $nol = (clone $this->baseQuery($request))
            ->where(self::COL_PERSEN, 0)
            ->where(self::COL_TERDAFTAR, '>', 0)
            ->count();

        if ($nol > 0) {
            $pesan[] = $nol . ' baris bercapaian nol persen padahal lokasinya terdaftar. Itu angka '
                . 'nol sungguhan, bukan data yang belum masuk, jadi tetap ditampilkan merah.';
        }

        return implode(' ', $pesan);
    }

    // ======================================================================
    // Tab Data
    // ======================================================================

    /**
     * Isi satu sel matriks Capaian per Bulan, untuk modal rincian.
     *
     * TIDAK ADA TABEL RINCIAN YANG BISA DIPAKAI, dan itu menentukan isi
     * modalnya. Kandidat terbaiknya, scr_hsecm_coverage_area_kritis_daily,
     * sekilas cocok sekali -- butirannya Detil_Lokasi x Site x minggu, satuan
     * IPK yang sama dengan parameter ini -- tetapi sudah diperiksa
     * dan ditolak karena tiga alasan yang berdiri sendiri-sendiri:
     *
     *   - isinya HANYA yang tidak tercover. Seluruh 4.944 barisnya berstatus
     *     "Tidak Tercover" dengan Tercover = 0; namanya pun "Trigger - Detail
     *     Lokasi Tidak Tercover". Itu daftar pemicu, bukan populasi, jadi
     *     penyebutnya tidak ada dan persentase mustahil diturunkan darinya;
     *   - tidak punya kolom PIC sama sekali, padahal baris matriks bergrain
     *     site x PIC (BAR, BAR,ACI, BUMA, FAD, KDC, MTN, PAMA), sehingga
     *     mustahil disaring ke sel yang diklik;
     *   - rentangnya minggu 30-41 tahun 2026 saja, sementara ringkasan
     *     berjalan April sampai Oktober. Empat dari enam bulan yang tampil
     *     tidak tercakup sama sekali.
     *
     * Karena itu modal ini TIDAK memecah sel jadi daftar lokasi. Yang
     * disajikan konteks di sekeliling sel, seluruhnya dari tabel ringkasan
     * yang sama dengan matriksnya, sehingga angkanya tidak mungkin
     * bertentangan dengan sel yang diklik:
     *
     *   riwayat  site x PIC yang sama sepanjang bulan -> kronis atau sesaat?
     *   sebulan  site x bulan yang sama di seluruh PIC -> satu PIC atau se-site?
     *
     * Mengarang daftar lokasi dari sumber yang tidak cocok akan memberi angka
     * yang terlihat meyakinkan tetapi salah, dan itu lebih buruk daripada
     * mengakui rinciannya belum ada.
     */
    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $pic = trim((string) $request->input('pic', ''));

        // Matriks halaman ini memakai KODE tahun*100 + bulan, bukan 1-12,
        // karena bulan di sumber bertahun ("April 2026"). Nomor bulan polos
        // tetap diterima supaya tautan lama tidak patah.
        $kode = (int) $request->input('month', 0);
        $bulan = $kode > 9999 ? $kode % 100 : $kode;
        $tahun = $kode > 9999 ? intdiv($kode, 100) : null;

        if ($site === '' || $pic === '' || $bulan < 1 || $bulan > 12) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Site, PIC, dan bulan wajib diisi untuk membuka rincian.',
            ]);
        }

        $nilaiBulan = $this->nilaiBulanTepat($bulan, $tahun);

        return response()->json([
            'ok' => true,
            'judul' => [
                'site' => $site,
                'pic' => $pic,
                'bulan' => $tahun === null
                    ? self::monthLabel($bulan)
                    : $this->labelBulan($tahun * 100 + $bulan),
            ],
            'target' => self::TARGET_PERCENT,
            'sel' => $this->agregat(
                $this->sumberIzin()
                    ->where(self::COL_SITE, $site)
                    ->where(self::COL_PIC, $pic)
                    ->whereIn(self::COL_BULAN, $nilaiBulan)
            ),
            'riwayat' => $this->riwayatSelama($site, $pic),
            'sebulan' => $this->sebulanDiSite($site, $nilaiBulan, $pic),
            'site' => $this->agregat(
                $this->sumberIzin()
                    ->where(self::COL_SITE, $site)
                    ->whereIn(self::COL_BULAN, $nilaiBulan)
            ),
        ]);
    }

    /**
     * Nilai month_of_date_hst yang berarti bulan ini, dipersempit ke tahunnya
     * kalau tahunnya diketahui. Tanpa penyempitan itu, "April 2026" dan
     * "April 2025" akan tercampur jadi satu sel.
     *
     * @return array<int, string>
     */
    private function nilaiBulanTepat(int $bulan, ?int $tahun): array
    {
        if ($tahun === null) {
            return $this->namaBulan($bulan);
        }

        $out = [];

        foreach (self::MONTH_MAP as $inggris => [$no]) {
            if ($no === $bulan) {
                $out[] = $inggris . ' ' . $tahun;
                $out[] = $inggris;
            }
        }

        return $out;
    }

    /**
     * Capaian berbobot sekumpulan baris: IPK tercover dibagi
     * IPK terdaftar. Bukan rata-rata persentase antar baris --
     * selisih keduanya 8 poin di parameter ini, lihat docblock kelas.
     *
     * @return array<string, mixed>
     */
    private function agregat(Builder $query): array
    {
        $row = $query
            ->selectRaw(
                'COALESCE(SUM(' . self::COL_TERCOVER . '), 0) AS tercover, '
                . 'COALESCE(SUM(' . self::COL_TERDAFTAR . '), 0) AS terdaftar'
            )
            ->first();

        $tercover = (int) ($row->tercover ?? 0);
        $terdaftar = (int) ($row->terdaftar ?? 0);
        $persen = $terdaftar > 0 ? round($tercover / $terdaftar * 100, 2) : null;
        [, $nilai, $band] = $this->scoreBandFor($persen ?? 0.0);

        return [
            'tercover' => $tercover,
            'terdaftar' => $terdaftar,
            'belum' => $terdaftar - $tercover,
            'persen' => $persen,
            'nilai' => $persen === null ? null : $nilai,
            'nilai_band' => $persen === null ? null : $band,
            'memenuhi_target' => $persen !== null && $persen >= self::TARGET_PERCENT,
        ];
    }

    /**
     * Site x PIC yang sama sepanjang bulan: capaian sel ini kronis atau sesaat?
     *
     * @return array<int, array<string, mixed>>
     */
    private function riwayatSelama(string $site, string $pic): array
    {
        $rows = $this->sumberIzin()
            ->where(self::COL_SITE, $site)
            ->where(self::COL_PIC, $pic)
            ->selectRaw(
                self::COL_BULAN . ' AS bulan, '
                . 'SUM(' . self::COL_TERCOVER . ') AS tercover, '
                . 'SUM(' . self::COL_TERDAFTAR . ') AS terdaftar'
            )
            ->groupBy('bulan')
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $kunci = $this->uraikanBulan((string) $r->bulan);

            if ($kunci === null || in_array($kunci[1], self::EXCLUDED_MONTHS, true)) {
                continue;
            }

            $terdaftar = (int) $r->terdaftar;

            $out[] = [
                'kode' => $kunci[0] * 100 + $kunci[1],
                'bulan' => $this->labelBulan($kunci[0] * 100 + $kunci[1]),
                'tercover' => (int) $r->tercover,
                'terdaftar' => $terdaftar,
                'persen' => $terdaftar > 0 ? round((int) $r->tercover / $terdaftar * 100, 2) : null,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['kode'] <=> $b['kode']);

        return $out;
    }

    /**
     * Site x bulan yang sama di seluruh PIC: yang tertinggal satu PIC saja
     * atau memang se-site?
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<int, array<string, mixed>>
     */
    private function sebulanDiSite(string $site, array $nilaiBulan, string $picTerpilih): array
    {
        $rows = $this->sumberIzin()
            ->where(self::COL_SITE, $site)
            ->whereIn(self::COL_BULAN, $nilaiBulan)
            ->selectRaw(
                self::COL_PIC . ' AS pic, '
                . 'SUM(' . self::COL_TERCOVER . ') AS tercover, '
                . 'SUM(' . self::COL_TERDAFTAR . ') AS terdaftar'
            )
            ->groupBy('pic')
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $terdaftar = (int) $r->terdaftar;
            $pic = trim((string) $r->pic);

            $out[] = [
                'pic' => $pic,
                'ini' => $pic === $picTerpilih,
                'tercover' => (int) $r->tercover,
                'terdaftar' => $terdaftar,
                'persen' => $terdaftar > 0 ? round((int) $r->tercover / $terdaftar * 100, 2) : null,
            ];
        }

        usort($out, static fn (array $a, array $b): int => ($b['persen'] ?? -1) <=> ($a['persen'] ?? -1));

        return $out;
    }

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
            ->orderBy(self::COL_PIC)
            ->orderBy('id');

        return $this->dtExport(
            $request,
            $query,
            ['Site', 'Perusahaan', 'Bulan', 'Dikunjungi', 'Terdaftar', 'Coverage (%)', 'Nilai', 'Keterangan'],
            function (object $row): array {
                $p = $this->present($row);

                return [
                    $p['site'], $p['pic'], $p['bulan'], $p['tercover'], $p['terdaftar'],
                    $p['persen'] ?? '', $p['nilai'] ?? '', $p['keterangan'],
                ];
            },
            'compliance-ikk'
        );
    }

    /** @return array<int, string> */
    private function columns(): array
    {
        return [
            'id',
            self::COL_SITE . ' AS site',
            self::COL_PIC . ' AS pic',
            self::COL_BULAN . ' AS bulan_sumber',
            self::COL_TERCOVER . ' AS tercover',
            self::COL_TERDAFTAR . ' AS terdaftar',
            self::COL_PERSEN . ' AS persen',
        ];
    }

    private function dataQuery(Request $request): Builder
    {
        $query = $this->baseQuery($request);

        $nilai = (int) $request->input('nilai', 0);

        if ($nilai >= 1 && $nilai <= 4) {
            [$batas] = self::SCORE_BANDS[4 - $nilai];
            $query->where(self::COL_PERSEN, '>=', $batas);

            // Band teratas sengaja tanpa batas atas, supaya angka di atas 100
            // tidak lenyap dari semua filter sekaligus.
            if ($nilai < 4) {
                $atas = self::SCORE_BANDS[3 - $nilai][0];
                $query->where(self::COL_PERSEN, '<', $atas);
            }
        }

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
        $query = $this->sumberIzin();

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn(self::COL_BULAN, $this->namaBulan($nomor));
        }

        foreach (self::FILTERABLE as $parameter => $column) {
            $value = trim((string) $request->input($parameter, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $bulan = (int) $request->input('month', 0);

        if ($bulan >= 1 && $bulan <= 12) {
            $query->whereIn(self::COL_BULAN, $this->namaBulan($bulan));
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
        $terdaftar = (int) $row->terdaftar;
        $tercover = (int) $row->tercover;
        $persen = $terdaftar > 0 ? round($tercover / $terdaftar * 100, 2) : null;
        [, $nilai, $band] = $this->scoreBandFor($persen ?? 0.0);
        $kunci = $this->uraikanBulan((string) $row->bulan_sumber);

        return [
            'site' => trim((string) $row->site),
            'pic' => trim((string) $row->pic),
            'bulan' => $kunci === null
                ? trim((string) $row->bulan_sumber)
                : $this->labelBulan($kunci[0] * 100 + $kunci[1]),
            'tercover' => $tercover,
            'terdaftar' => $terdaftar,
            'persen' => $persen,
            'nilai' => $persen === null ? null : $nilai,
            'nilai_band' => $persen === null ? null : $band,
            'memenuhi_target' => $persen !== null && $persen >= self::TARGET_PERCENT,
            'keterangan' => $persen === null
                ? 'Tidak ada lokasi terdaftar'
                : ($persen >= self::TARGET_PERCENT ? 'Memenuhi target' : 'Di bawah target'),
        ];
    }

    // ======================================================================
    // Utilitas
    // ======================================================================

    /**
     * Mengurai tulisan bulan jadi pasangan [tahun, bulan].
     *
     * Sumber ini memakai "April 2026"; dua bentuk lain yang dipakai tabel lain
     * di modul ini ("April" saja dan "M04") ikut dikenali supaya berpindah
     * sumber tidak langsung merusak halaman. Tanpa tahun, dipakai tahun
     * berjalan. null bila tidak terbaca sama sekali.
     *
     * @return array{0: int, 1: int}|null
     */
    private function uraikanBulan(string $nilai): ?array
    {
        $nilai = trim($nilai);

        if (preg_match('/^M(\d{1,2})$/i', $nilai, $cocok) === 1) {
            $bulan = (int) $cocok[1];

            return $bulan >= 1 && $bulan <= 12 ? [(int) date('Y'), $bulan] : null;
        }

        if (preg_match('/^([A-Za-z]+)(?:\s+(\d{4}))?$/', $nilai, $cocok) !== 1) {
            return null;
        }

        $bulan = self::MONTH_MAP[ucfirst(strtolower($cocok[1]))][0] ?? 0;

        if ($bulan === 0) {
            return null;
        }

        return [isset($cocok[2]) ? (int) $cocok[2] : (int) date('Y'), $bulan];
    }

    /**
     * Semua cara penulisan satu nomor bulan yang mungkin ada di sumber, untuk
     * dipakai di whereIn. Bentuk bertahun dirakit dari tahun yang benar-benar
     * ada di tabel, bukan ditebak.
     *
     * @return array<int, string>
     */
    private function namaBulan(int $nomor): array
    {
        $out = [sprintf('M%02d', $nomor), 'M' . $nomor];

        foreach (self::MONTH_MAP as $inggris => [$no]) {
            if ($no !== $nomor) {
                continue;
            }

            $out[] = $inggris;

            foreach ($this->tahunTersedia() as $tahun) {
                $out[] = $inggris . ' ' . $tahun;
            }
        }

        return $out;
    }

    /**
     * Tahun yang benar-benar muncul di kolom bulan.
     *
     * @return array<int, int>
     */
    private function tahunTersedia(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $tahun = [];

        foreach ($this->distinctValues(self::COL_BULAN) as $nilai) {
            $kunci = $this->uraikanBulan($nilai);

            if ($kunci !== null) {
                $tahun[$kunci[0]] = true;
            }
        }

        $cache = array_keys($tahun);
        sort($cache);

        return $cache;
    }

    private function baseCount(): int
    {
        $query = $this->sumberIzin();

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn(self::COL_BULAN, $this->namaBulan($nomor));
        }

        return $query->count();
    }

    /**
     * @param  array<int, int>  $months  kode tahun*100 + bulan
     * @return array<int, array<string, mixed>>
     */
    /**
     * Sumber seluruh halaman: SATU BARIS PER IZIN KERJA.
     *
     * Subquery ini sengaja memaparkan kolom dengan bentuk yang sama seperti
     * tabel ringkasan parameter Coverage, supaya seluruh mesin halaman --
     * matriks, KPI berbobot, tren, modal -- bekerja tanpa diubah:
     *
     *   ada_okk  0/1, berperan sebagai pembilang ("tercover")
     *   satu     selalu 1, berperan sebagai penyebut ("terdaftar")
     *   bulan_ikk  "September 2026", bentuk yang sudah dikenali uraikanBulan()
     *   persen_ikk 0/100 per izin, supaya kolom persen tetap ada untuk tab Data
     *
     * DEDUPE-NYA YANG MENENTUKAN. 60 kode muncul dua kali dengan status
     * berbeda; MAX() memenangkan salinan yang patuh, dan itulah yang membuat
     * seluruh 78 sel cocok dengan lead_compliance_ikk. Lihat docblock kelas.
     */
    private function sumberIzin(): Builder
    {
        return DB::query()->fromSub(
            DB::table(self::TABEL_SUMBER)->selectRaw(
                'ra_site_name AS site_ikk, '
                . 'company_name_ikk_work_permit AS mitra_ikk, '
                . "DATE_FORMAT(" . self::EKSPR_TANGGAL . ", '%M %Y') AS bulan_ikk, "
                . 'code AS kode_ikk, '
                . "MAX(status_okk = 'IPK ada OKK') AS ada_okk, "
                . '1 AS satu, '
                . "MAX(status_okk = 'IPK ada OKK') * 100 AS persen_ikk, "
                . 'MAX(name_ikk_work_permit) AS nama_ikk, '
                . 'MAX(department_name_ikk_work_permit) AS departemen_ikk, '
                . 'MAX(location_name) AS lokasi_ikk, '
                . 'MAX(location_detail_name) AS lokasi_detail_ikk, '
                . 'MIN(' . self::EKSPR_TANGGAL . ') AS tanggal_ikk'
            )->groupBy('site_ikk', 'mitra_ikk', 'bulan_ikk', 'kode_ikk'),
            'ikk'
        );
    }

    private function monthHeadings(array $months): array
    {
        // Tahun hanya dicantumkan kalau sumbernya memuat lebih dari satu tahun;
        // kalau cuma satu, "APR" lebih enak dibaca daripada "APR 26".
        $banyakTahun = count(array_unique(array_map(
            static fn (int $kode): int => intdiv($kode, 100),
            $months
        ))) > 1;

        return array_map(function (int $kode) use ($banyakTahun): array {
            $label = mb_strtoupper(mb_substr(self::monthLabel($kode % 100), 0, 3));

            return [
                'number' => $kode,
                'label' => $banyakTahun ? $label . ' ' . substr((string) intdiv($kode, 100), 2) : $label,
            ];
        }, $months);
    }

    /** Label panjang satu kode bulan, mis. "April 2026". */
    private function labelBulan(int $kode): string
    {
        return self::monthLabel($kode % 100) . ' ' . intdiv($kode, 100);
    }

    /** @return array<int, string> */
    private function distinctValues(string $column): array
    {
        return $this->sumberIzin()
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
            $kunci = $this->uraikanBulan($nilai);

            if ($kunci === null || in_array($kunci[1], self::EXCLUDED_MONTHS, true)) {
                continue;
            }

            $months[$kunci[1]] = self::monthLabel($kunci[1]);
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
