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
 * Parameter SOD "% SPIP yang dilakukan Commissioning".
 *
 * Sumbernya satu tabel, lead_scr_spip_commisioning_new: 691 baris, satu baris
 * per site x perusahaan pemilik x bulan, isinya persentase SPIP yang sudah
 * dilakukan commissioning. Makin tinggi makin baik. Tidak ada tabel
 * rinciannya, jadi halaman ini hanya punya dua tab: Ringkasan dan Data.
 *
 * NAMA TABELNYA SALAH EJA DI SUMBER: "commisioning" dengan satu s, bukan
 * "commissioning". Dibiarkan apa adanya karena itu nama tabel yang sebenarnya;
 * jangan "diperbaiki" tanpa mengganti tabelnya juga.
 *
 * BULAN DITULIS SINGKATAN TIGA HURUF BESERTA TAHUN -- "Oct 2026" -- dan itu
 * bentuk yang tidak dipakai parameter OHS Score Card lain. MONTH_MAP karena
 * itu memuat singkatan di samping nama panjang, dan nomorBulan() melepas
 * tahunnya sebelum mencocokkan. Lihat tahunTersedia() untuk apa yang terjadi
 * kalau tahun kedua masuk.
 *
 * ADA BARIS UNTUK BULAN YANG BELUM BERJALAN, dan itu bukan data yang hilang.
 * November memuat 77 baris dan Desember 130 baris yang seluruhnya ber-pct
 * NULL: itu rencana commissioning yang belum dikerjakan. Oktober pun baru 25
 * dari 80 baris yang terisi karena bulannya masih berjalan. overview() karena
 * itu membuang bulan yang TIDAK PUNYA SATU PUN nilai, dengan aturan umum
 * bukan nama bulan yang ditulis mati, sehingga kolomnya muncul sendiri begitu
 * datanya masuk. Oktober tetap dibuang lewat EXCLUDED_MONTHS seperti halaman
 * lain. Yang tersisa 9 bulan, 404 baris, 398 di antaranya terisi.
 *
 * MATRIKSNYA JAUH LEBIH LEBAR DARIPADA HALAMAN LAIN. Ada 103 perusahaan
 * pemilik dan 9 site -- termasuk HO, MARINE, dan EKSPLORASI yang tidak muncul
 * di parameter operasional -- sehingga totalnya 207 pasangan site x
 * perusahaan. Hanya 106 di antaranya pernah punya nilai di bulan yang
 * ditampilkan, jadi pasangan yang sama sekali kosong dibuang dari matriks;
 * sisanya hanya akan menambah baris kosong di tabel yang sudah panjang.
 *
 * SUMBERNYA HANYA MENYIMPAN PERSENTASE. Tidak ada pembilang maupun penyebut,
 * jadi rata-rata keseluruhan TIDAK BISA dibobot seperti di halaman Coverage,
 * dan sel tidak bisa diurai menjadi "sekian dari sekian SPIP". Rata-rata polos
 * di luar Oktober/November/Desember adalah 43,96%.
 *
 * SEBARANNYA MENUMPUK DI DUA UJUNG. Dari 423 nilai yang terisi, 158 tepat
 * 0,00% dan 101 tepat 100,00% -- jadi lebih dari separuh pasangan bulanan
 * berada di salah satu ujung, bukan tersebar di tengah. Rata-rata karena itu
 * kurang mewakili daripada di parameter lain, dan matriks per sel lebih
 * berguna untuk membaca halaman ini.
 *
 * NILAI KOSONG DIBIARKAN KOSONG, bukan diubah jadi nol: nol berarti tidak ada
 * SPIP yang di-commissioning, sementara kosong berarti belum ada datanya, dan
 * itu dua hal yang berbeda.
 *
 * AMBANG & BAND mengikuti sistem penilaian OHS Score Card yang sama dengan
 * halaman lain (target 90%, band 98/90/80). Belum ada konfirmasi bahwa
 * parameter ini memakai band yang sama; kalau berbeda, ubah SCORE_BANDS
 * dan TARGET_PERCENT di bawah.
 */
final class SpipCommissioningController extends Controller
{
    use ServesDataTable;

    private const TABLE = 'lead_scr_spip_commisioning_new';

    private const COL_SITE = 'site_existing';
    private const COL_PERUSAHAAN = 'perusahaan_pemilik_existing';
    private const COL_BULAN = 'performance_month';
    private const COL_PERSEN = 'performance_pct';

    private const TARGET_PERCENT = 90.0;

    /**
     * Band penilaian: [batas bawah, batas atas, nilai dasar, label].
     *
     * NILAINYA BERKOMA, BUKAN BULAT. Di dalam satu band, nilai melandai
     * mengikuti posisi capaian di antara kedua batasnya -- 97,0% bernilai
     * 3,50, bukan 3 -- sehingga dua baris yang sama-sama "Nilai 3" tetap bisa
     * dibedakan. Lihat scoreBandFor().
     *
     * BAND TERATAS DATAR DI 4,00: begitu capaian menyentuh 98%, nilainya sudah
     * penuh dan tidak melandai lagi sampai 100%.
     *
     * BAND TERBAWAH melandai dari 1,00 di 0% sampai 2,00 di 94%, jadi capaian
     * buruk pun tetap terbedakan satu sama lain.
     */
    private const SCORE_BANDS = [
        [98.0, 100.0, 4, '98% - 100%'],
        [96.0,  98.0, 3, '96% - <98%'],
        [94.0,  96.0, 2, '94% - <96%'],
        [0.0,   94.0, 1, '<94%'],
    ];

    /**
     * Bulan di tabel ini tertulis SINGKATAN TIGA HURUF BESERTA TAHUN, mis.
     * "Oct 2026" -- bentuk yang tidak dipakai parameter OHS Score Card mana
     * pun. Singkatannya didaftarkan di sini supaya nomorBulan() mengenalinya,
     * dan nama panjang serta M01-M12 tetap dikenali agar halaman tidak rusak
     * kalau sumbernya berganti gaya penulisan. monthLabel() memakai padanan
     * pertama yang cocok, jadi urutannya nama panjang dulu.
     */
    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
        'Jan' => [1, 'Januari'], 'Feb' => [2, 'Februari'], 'Mar' => [3, 'Maret'],
        'Apr' => [4, 'April'], 'Jun' => [6, 'Juni'], 'Jul' => [7, 'Juli'],
        'Aug' => [8, 'Agustus'], 'Sep' => [9, 'September'], 'Oct' => [10, 'Oktober'],
        'Nov' => [11, 'November'], 'Dec' => [12, 'Desember'],
    ];

    /**
     * Bulan yang tidak ikut dihitung, sejalan dengan halaman Ratio TBC & GR
     * dan Blindspot TBC: Oktober masih berjalan saat data ini diambil.
     */
    private const EXCLUDED_MONTHS = [10];

    private const FILTERABLE = [
        'site' => self::COL_SITE,
        'mitra' => self::COL_PERUSAHAAN,
    ];

    private const SEARCHABLE = [self::COL_SITE, self::COL_PERUSAHAAN, self::COL_BULAN];

    private const ORDERABLE = [
        0 => self::COL_SITE,
        1 => self::COL_PERUSAHAAN,
        3 => self::COL_PERSEN,
    ];

    public function index(): View
    {
        return view('ohs-score-card.spip-commissioning.index', [
            'filterOptions' => [
                'site' => $this->distinctValues(self::COL_SITE),
                'mitra' => $this->distinctValues(self::COL_PERUSAHAAN),
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
                . self::COL_PERUSAHAAN . ' AS mitra, '
                . self::COL_BULAN . ' AS bulan, '
                . 'AVG(' . self::COL_PERSEN . ') AS persen'
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
            // NULL dibiarkan NULL: "belum ada datanya" bukan "kinerjanya nol".
            $grid[$site . '|' . $mitra]['bulan'][$monthNo] = $row->persen === null
                ? null
                : round((float) $row->persen, 2);
        }

        // BULAN YANG SAMA SEKALI TIDAK PUNYA NILAI DIBUANG. Sumber ini
        // memuat baris rencana untuk bulan yang belum berjalan -- November 77
        // baris dan Desember 130 baris, keduanya nol terisi -- dan menampilkan
        // dua kolom kosong melompong hanya membuat matriks sulit dibaca.
        // Aturannya umum, bukan nama bulan yang ditulis mati, jadi kolomnya
        // muncul sendiri begitu nilainya masuk.
        foreach (array_keys($monthSeen) as $nomor) {
            $adaNilai = false;

            foreach ($grid as $baris) {
                if (($baris['bulan'][$nomor] ?? null) !== null) {
                    $adaNilai = true;
                    break;
                }
            }

            if (! $adaNilai) {
                unset($monthSeen[$nomor]);
            }
        }

        ksort($monthSeen);
        $months = array_keys($monthSeen);
        // Begitu pula pasangannya: dari 207 pasangan site x perusahaan,
        // hanya sekitar separuh yang pernah punya nilai di bulan yang
        // ditampilkan. Sisanya hanya menambah baris kosong di matriks yang
        // barisnya sudah banyak.
        $grid = array_filter($grid, static function (array $baris) use ($months): bool {
            foreach ($months as $nomor) {
                if (($baris['bulan'][$nomor] ?? null) !== null) {
                    return true;
                }
            }

            return false;
        });

        $matrix = $this->buildMatrix($grid, $months);

        return response()->json([
            'months' => $this->monthHeadings($months),
            'kpi' => $this->buildKpi($matrix, $months),
            'matrix' => $matrix,
            'per_site' => $this->ringkasPer($matrix, 'site'),
            'per_mitra' => $this->ringkasPer($matrix, 'mitra'),
            'terendah' => $this->buildTerendah($matrix),
            'monthly' => $this->buildMonthlySeries($matrix, $months),
            'catatan' => $this->catatan($request),
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

            foreach ($months as $month) {
                $value = $entry['bulan'][$month] ?? null;

                if ($value === null) {
                    $cells[] = null;
                    continue;
                }

                [, $nilai, $band] = $this->scoreBandFor($value);
                $cells[] = ['pct' => $value, 'nilai' => $nilai, 'nilai_band' => $band];
                $terisi[] = $value;
            }

            $average = $terisi !== [] ? round(array_sum($terisi) / count($terisi), 2) : null;
            [, $nilai, $band] = $this->scoreBandFor($average ?? 0.0);

            $out[] = [
                'site' => $entry['site'],
                'mitra' => $entry['mitra'],
                'cells' => $cells,
                'average' => $average,
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
     * Site diurutkan dari yang paling rendah kinerjanya, dan di dalam tiap
     * site barisnya juga dari yang paling rendah, sehingga yang perlu
     * ditangani lebih dulu tetap berada di atas meski sudah dikelompokkan.
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

        // Bobot sebuah site = capaian terendahnya; satu perusahaan yang jeblok
        // tidak boleh tersamarkan oleh perusahaan lain yang bagus di site sama.
        $bobot = [];

        foreach ($perSite as $site => $baris) {
            $nilai = array_filter(
                array_column($baris, 'average'),
                static fn (?float $v): bool => $v !== null
            );
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
        $nilai = array_values(array_filter(
            array_column($matrix, 'average'),
            static fn (?float $v): bool => $v !== null
        ));

        $rata = $nilai !== [] ? round(array_sum($nilai) / count($nilai), 2) : null;
        [, $band, $bandLabel] = $this->scoreBandFor($rata ?? 0.0);

        $selKosong = 0;
        $selTerisi = 0;

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $cell) {
                $cell === null ? $selKosong++ : $selTerisi++;
            }
        }

        return [
            'rata' => $rata,
            'nilai' => $rata === null ? null : $band,
            'nilai_band' => $rata === null ? null : $bandLabel,
            'target' => self::TARGET_PERCENT,
            'memenuhi_target' => $rata !== null && $rata >= self::TARGET_PERCENT,
            'tertinggi' => $nilai !== [] ? max($nilai) : null,
            'terendah' => $nilai !== [] ? min($nilai) : null,
            // Penyebutnya hanya pasangan yang punya angka. Pasangan yang
            // seluruh bulannya kosong tidak bisa dibilang gagal memenuhi
            // target, jadi dilaporkan terpisah.
            'kombinasi' => count($nilai),
            'kombinasi_kosong' => count($matrix) - count($nilai),
            'memenuhi' => count(array_filter(
                $matrix,
                static fn (array $r): bool => $r['memenuhi_target']
            )),
            'site_count' => count(array_unique(array_column($matrix, 'site'))),
            'mitra_count' => count(array_unique(array_column($matrix, 'mitra'))),
            'bulan_count' => count($months),
            'sel_terisi' => $selTerisi,
            'sel_kosong' => $selKosong,
        ];
    }

    /**
     * Rata-rata per site atau per perusahaan.
     *
     * Rata-rata antar baris, bukan antar bulan, supaya pasangan yang datanya
     * lengkap tidak berbobot lebih besar daripada yang datanya bolong.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function ringkasPer(array $matrix, string $key): array
    {
        $kelompok = [];

        foreach ($matrix as $row) {
            if ($row['average'] === null) {
                continue;
            }

            $kelompok[$row[$key]][] = $row['average'];
        }

        $out = [];

        foreach ($kelompok as $label => $nilai) {
            $rata = round(array_sum($nilai) / count($nilai), 2);
            [, $band] = $this->scoreBandFor($rata);

            $out[] = [
                $key => (string) $label,
                'percent' => $rata,
                'nilai' => $band,
                'jumlah' => count($nilai),
                'terendah' => min($nilai),
                'target' => self::TARGET_PERCENT,
                'memenuhi_target' => $rata >= self::TARGET_PERCENT,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $out;
    }

    /**
     * Lima pasangan dengan capaian terendah: itu yang perlu dibaca lebih dulu.
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
            'mitra' => $r['mitra'],
            'percent' => $r['average'],
            'nilai' => $r['nilai'],
            'terendah' => $r['terendah'],
        ], array_slice($rows, 0, 5));
    }

    /**
     * Satu garis per perusahaan.
     *
     * @param  array<int, array<string, mixed>>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $matrix, array $months): array
    {
        $perMitra = [];

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $i => $cell) {
                if ($cell === null) {
                    continue;
                }

                $perMitra[$row['mitra']][$i][] = $cell['pct'];
            }
        }

        ksort($perMitra);
        $series = [];

        foreach ($perMitra as $mitra => $perBulan) {
            $data = [];

            foreach (array_keys($months) as $i) {
                $nilai = $perBulan[$i] ?? null;
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
     * Keterangan tentang sel yang kosong, supaya matriks berlubang tidak
     * dikira kinerjanya nol.
     */
    private function catatan(Request $request): ?string
    {
        $pesan = [];

        // Kunci bulan di halaman ini tidak memuat tahun, jadi tahun kedua akan
        // menggabungkan dua kolom tanpa terlihat. Lebih baik diumumkan di layar
        // daripada tersimpan sebagai catatan di kode yang tidak dibaca siapa pun.
        $tahun = $this->tahunTersedia();

        if (count($tahun) > 1) {
            $pesan[] = 'Sumber memuat lebih dari satu tahun (' . implode(', ', $tahun) . ') '
                . 'sementara matriks halaman ini mengelompokkan per nomor bulan saja, sehingga '
                . 'bulan yang sama dari tahun berbeda tergabung menjadi satu kolom. Angka per sel '
                . 'karena itu belum bisa dipercaya sampai halaman ini dipindahkan ke kunci '
                . 'tahun+bulan.';
        }

        $kosong = (clone $this->baseQuery($request))->whereNull(self::COL_PERSEN)->count();

        if ($kosong > 0) {
            $total = (clone $this->baseQuery($request))->count();

            $pesan[] = $kosong . ' dari ' . $total . ' baris di ' . self::TABLE . ' belum berisi '
                . 'persentase; sebagian di antaranya rencana commissioning untuk bulan yang belum '
                . 'berjalan. Bulan yang sama sekali tidak punya nilai tidak ditampilkan sebagai '
                . 'kolom, dan sel kosong ditandai strip -- bukan nol -- serta tidak ikut dihitung '
                . 'dalam rata-rata.';
        }

        return $pesan === [] ? null : implode(' ', $pesan);
    }

    // ======================================================================
    // Modal rincian satu sel
    // ======================================================================

    /**
     * Isi satu sel matriks Capaian per Bulan, untuk modal rincian.
     *
     * SUMBERNYA HANYA PERSENTASE. lead_scr_spip_commisioning_new cuma punya
     * performance_pct -- tidak ada pembilang maupun penyebut
     * seperti halaman Coverage yang menyimpan tercover dan terdaftar, dan tidak
     * ada tabel rincian yang bisa menggantikannya (lead_..._month masih nol
     * baris). Jadi sel ini TIDAK bisa diurai jadi "sekian dari sekian"; yang
     * disajikan konteks di sekelilingnya, seluruhnya dari tabel yang sama
     * dengan matriksnya, sehingga angkanya tidak mungkin bertentangan.
     *
     * KOSONG BUKAN NOL, DAN ADA DUA MACAM KOSONG. 40 dari 155 baris di luar
     * Oktober ber-pct NULL, dan di samping itu 43 sel matriks memang tidak
     * punya barisnya sama sekali. Keduanya tampil sebagai strip di matriks,
     * padahal artinya berbeda: yang pertama "barisnya ada, angkanya belum
     * diisi", yang kedua "pasangan ini memang tidak beroperasi di bulan itu".
     * Karena itu tiap baris panel di modal membawa ada_baris, dan panel-panel
     * di sini sengaja menampilkan bulan/perusahaan/site yang TIDAK punya angka
     * sekalipun -- kalau yang kosong disembunyikan, pembaca akan mengira
     * pembandingnya memang cuma segitu.
     *
     *   riwayat      site x perusahaan sepanjang bulan -> kronis atau sesaat?
     *   sebulan      site x bulan di seluruh perusahaan -> satu mitra atau se-site?
     *   lintas_site  perusahaan x bulan di seluruh site -> mitranya atau sitenya?
     *
     * PANEL KETIGA dipakai karena perusahaan di sini bekerja lintas site --
     * tiap perusahaan muncul di 2 sampai 5 site, PT PAMA di lima. Tanpa itu
     * pembaca tidak bisa memisahkan "perusahaan ini memang lemah" dari "site
     * ini yang bermasalah".
     *
     * PERINGKAT dihitung hanya di antara pasangan yang punya angka pada bulan
     * itu, tertinggi lebih dulu karena di parameter ini makin tinggi makin
     * baik. Pasangan tanpa angka tidak ikut diperingkat -- dan jumlahnya
     * dilaporkan lewat kelengkapan supaya penyebutnya tidak terbaca sebagai
     * seluruh pasangan.
     */
    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));

        // monthHeadings() halaman ini mengirim number 1-12, tetapi kode
        // tahun*100+bulan ikut diterima supaya modal tetap bekerja kalau suatu
        // saat sumbernya menyimpan tahun juga.
        $kode = (int) $request->input('month', 0);
        $bulan = $kode > 9999 ? $kode % 100 : $kode;

        if ($site === '' || $mitra === '' || $bulan < 1 || $bulan > 12) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Site, perusahaan, dan bulan wajib diisi untuk membuka rincian.',
            ]);
        }

        // Oktober dikecualikan di overview(), jadi tidak boleh punya rincian --
        // kalau dibiarkan, modal akan memunculkan angka yang tidak ada selnya.
        if (in_array($bulan, self::EXCLUDED_MONTHS, true)) {
            return response()->json([
                'ok' => false,
                'pesan' => self::monthLabel($bulan) . ' tidak ikut dihitung di parameter ini, '
                    . 'jadi tidak ada rinciannya.',
            ]);
        }

        $nilaiBulan = $this->namaBulan($bulan);

        // Diagregasi persis seperti overview(), bukan diambil nilai baris
        // tunggal, supaya sel dan modal tidak bisa berbeda kalau suatu saat
        // sumbernya memuat lebih dari satu baris per kunci.
        $sel = $this->agregat(
            DB::table(self::TABLE)
                ->where(self::COL_SITE, $site)
                ->where(self::COL_PERUSAHAAN, $mitra)
                ->whereIn(self::COL_BULAN, $nilaiBulan)
        );

        $konteks = $this->konteksBulan($nilaiBulan, $site, $mitra);

        return response()->json([
            'ok' => true,
            'judul' => [
                'site' => $site,
                'mitra' => $mitra,
                'bulan' => self::monthLabel($bulan),
            ],
            'target' => self::TARGET_PERCENT,
            'sel' => $this->bentukNilai($sel['persen'], $sel['baris'] > 0),
            'peringkat' => $konteks['peringkat'],
            'kelengkapan' => $konteks['kelengkapan'],
            'riwayat' => $this->riwayatSelama($site, $mitra),
            'sebulan' => $this->sebulanDiSite($site, $nilaiBulan, $mitra),
            'lintas_site' => $this->lintasSite($mitra, $nilaiBulan, $site),
        ]);
    }

    /**
     * Rata-rata persentase sebuah himpunan baris, beserta cacah barisnya.
     *
     * SUM/COUNT dipakai, bukan AVG, karena hasilnya sama persis tetapi bisa
     * dijumlahkan antar kelompok -- kolom bulan pernah ditulis dua gaya
     * ("M01" dan "January"), dan kalau keduanya muncul bersamaan, AVG per
     * kelompok tidak bisa digabung tanpa membobot ulang.
     *
     * @return array{baris: int, terisi: int, persen: float|null}
     */
    private function agregat(Builder $query): array
    {
        $row = $query->selectRaw(
            'COUNT(*) AS baris, '
            . 'COUNT(' . self::COL_PERSEN . ') AS terisi, '
            . 'SUM(' . self::COL_PERSEN . ') AS jumlah'
        )->first();

        $terisi = $row === null ? 0 : (int) $row->terisi;

        return [
            'baris' => $row === null ? 0 : (int) $row->baris,
            'terisi' => $terisi,
            'persen' => $terisi === 0 ? null : round((float) $row->jumlah / $terisi, 2),
        ];
    }

    /**
     * Satu persentase lengkap dengan nilai, band, dan selisihnya ke target.
     *
     * ada_baris memisahkan dua sebab sel bisa kosong, dan itu yang membuat
     * modal tidak boleh menampilkan 0 untuk keduanya.
     *
     * @return array<string, mixed>
     */
    private function bentukNilai(?float $persen, bool $adaBaris): array
    {
        if ($persen === null) {
            return [
                'persen' => null, 'nilai' => null, 'nilai_band' => null,
                'memenuhi_target' => false, 'selisih' => null, 'ada_baris' => $adaBaris,
            ];
        }

        [, $nilai, $band] = $this->scoreBandFor($persen);

        return [
            'persen' => $persen,
            'nilai' => $nilai,
            'nilai_band' => $band,
            'memenuhi_target' => $persen >= self::TARGET_PERCENT,
            'selisih' => round($persen - self::TARGET_PERCENT, 2),
            'ada_baris' => $adaBaris,
        ];
    }

    /**
     * Peringkat sel ini di bulan yang sama, sekaligus kelengkapan data bulan
     * itu. Keduanya lahir dari satu query yang sama supaya penyebut peringkat
     * dan cacah "belum berdata" tidak mungkin saling bertentangan.
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array{peringkat: array<string, mixed>, kelengkapan: array<string, int>}
     */
    private function konteksBulan(array $nilaiBulan, string $site, string $mitra): array
    {
        $rows = DB::table(self::TABLE)
            ->whereIn(self::COL_BULAN, $nilaiBulan)
            ->selectRaw(
                self::COL_SITE . ' AS site, '
                . self::COL_PERUSAHAAN . ' AS mitra, '
                . 'COUNT(' . self::COL_PERSEN . ') AS terisi, '
                . 'SUM(' . self::COL_PERSEN . ') AS jumlah'
            )
            ->groupBy('site', 'mitra')
            ->get();

        $berangka = [];
        $tanpaPersen = 0;

        foreach ($rows as $r) {
            if ((int) $r->terisi === 0) {
                $tanpaPersen++; // barisnya ada, persentasenya belum diisi
                continue;
            }

            $berangka[] = [
                'site' => trim((string) $r->site),
                'mitra' => trim((string) $r->mitra),
                'persen' => round((float) $r->jumlah / (int) $r->terisi, 2),
            ];
        }

        usort($berangka, static fn (array $a, array $b): int => $b['persen'] <=> $a['persen']);

        $posisi = null;

        foreach ($berangka as $i => $r) {
            if ($r['site'] === $site && $r['mitra'] === $mitra) {
                $posisi = $i + 1;
                break;
            }
        }

        $semua = array_column($berangka, 'persen');
        $pasangan = $this->jumlahPasangan();

        return [
            'peringkat' => [
                'posisi' => $posisi,
                'dari' => count($berangka),
                'rata' => $semua === [] ? null : round(array_sum($semua) / count($semua), 2),
            ],
            'kelengkapan' => [
                'berangka' => count($berangka),
                'tanpa_persen' => $tanpaPersen,
                'tanpa_baris' => max(0, $pasangan - count($berangka) - $tanpaPersen),
                'pasangan' => $pasangan,
            ],
        ];
    }

    /** Banyaknya pasangan site x perusahaan yang muncul di matriks. */
    private function jumlahPasangan(): int
    {
        $query = DB::table(self::TABLE);

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn(self::COL_BULAN, $this->namaBulan($nomor));
        }

        $row = $query->selectRaw(
            'COUNT(DISTINCT ' . self::COL_SITE . ', ' . self::COL_PERUSAHAAN . ') AS n'
        )->first();

        return $row === null ? 0 : (int) $row->n;
    }

    /**
     * Site x perusahaan yang sama sepanjang bulan: kronis atau sesaat?
     *
     * Seluruh bulan sumbu matriks ditulis, termasuk yang tidak punya baris,
     * supaya bulan yang hilang tidak lenyap begitu saja dari pembanding.
     *
     * @return array<int, array<string, mixed>>
     */
    private function riwayatSelama(string $site, string $mitra): array
    {
        $rows = DB::table(self::TABLE)
            ->where(self::COL_SITE, $site)
            ->where(self::COL_PERUSAHAAN, $mitra)
            ->selectRaw(
                self::COL_BULAN . ' AS bulan, '
                . 'COUNT(*) AS baris, '
                . 'COUNT(' . self::COL_PERSEN . ') AS terisi, '
                . 'SUM(' . self::COL_PERSEN . ') AS jumlah'
            )
            ->groupBy('bulan')
            ->get();

        $perNomor = [];

        foreach ($rows as $r) {
            $nomor = $this->nomorBulan((string) $r->bulan);

            if ($nomor === 0) {
                continue; // nama bulan tak dikenal: jangan diam-diam digabung
            }

            $perNomor[$nomor]['baris'] = ($perNomor[$nomor]['baris'] ?? 0) + (int) $r->baris;
            $perNomor[$nomor]['terisi'] = ($perNomor[$nomor]['terisi'] ?? 0) + (int) $r->terisi;
            $perNomor[$nomor]['jumlah'] = ($perNomor[$nomor]['jumlah'] ?? 0.0) + (float) $r->jumlah;
        }

        $out = [];

        foreach ($this->monthOptions() as $nomor => $label) {
            $entri = $perNomor[$nomor] ?? null;

            $out[] = [
                'nomor' => $nomor,
                'bulan' => $label,
                'persen' => $entri === null || $entri['terisi'] === 0
                    ? null
                    : round($entri['jumlah'] / $entri['terisi'], 2),
                'ada_baris' => $entri !== null,
            ];
        }

        return $out;
    }

    /**
     * Seluruh perusahaan di site ini pada bulan yang sama: masalahnya milik
     * satu mitra atau menyeluruh?
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<int, array<string, mixed>>
     */
    private function sebulanDiSite(string $site, array $nilaiBulan, string $mitraTerpilih): array
    {
        return $this->ringkasKolom(
            DB::table(self::TABLE)
                ->where(self::COL_SITE, $site)
                ->whereIn(self::COL_BULAN, $nilaiBulan),
            self::COL_PERUSAHAAN,
            'mitra',
            $mitraTerpilih,
            $this->labelTersedia(self::COL_PERUSAHAAN, self::COL_SITE, $site)
        );
    }

    /**
     * Perusahaan yang sama di seluruh site pada bulan yang sama: mitranya yang
     * lemah atau sitenya?
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<int, array<string, mixed>>
     */
    private function lintasSite(string $mitra, array $nilaiBulan, string $siteTerpilih): array
    {
        return $this->ringkasKolom(
            DB::table(self::TABLE)
                ->where(self::COL_PERUSAHAAN, $mitra)
                ->whereIn(self::COL_BULAN, $nilaiBulan),
            self::COL_SITE,
            'site',
            $siteTerpilih,
            $this->labelTersedia(self::COL_SITE, self::COL_PERUSAHAAN, $mitra)
        );
    }

    /**
     * Nilai satu kolom yang pernah muncul bersama sebuah penyaring, di luar
     * bulan yang dikecualikan. Dipakai sebagai daftar lengkap pembanding,
     * supaya yang tidak punya baris di bulan terpilih tetap ikut tampil.
     *
     * @return array<int, string>
     */
    private function labelTersedia(string $kolom, string $filterKolom, string $filterNilai): array
    {
        $query = DB::table(self::TABLE)->where($filterKolom, $filterNilai);

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn(self::COL_BULAN, $this->namaBulan($nomor));
        }

        return $query->distinct()
            ->pluck($kolom)
            ->map(static fn ($v): string => trim((string) $v))
            ->filter(static fn (string $v): bool => $v !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Rata-rata persentase per nilai satu kolom, tertinggi di atas, dengan
     * penanda pada baris yang sedang dibuka.
     *
     * Yang tidak punya angka ditaruh di bawah dan dibedakan: "barisnya ada
     * tetapi persentasenya kosong" tidak sama dengan "tidak ada barisnya".
     *
     * @param  array<int, string>  $semuaLabel
     * @return array<int, array<string, mixed>>
     */
    private function ringkasKolom(
        Builder $query,
        string $kolom,
        string $kunci,
        string $terpilih,
        array $semuaLabel
    ): array {
        $rows = $query
            ->selectRaw(
                $kolom . ' AS label, '
                . 'COUNT(*) AS baris, '
                . 'COUNT(' . self::COL_PERSEN . ') AS terisi, '
                . 'SUM(' . self::COL_PERSEN . ') AS jumlah'
            )
            ->groupBy('label')
            ->get();

        $perLabel = [];

        foreach ($rows as $r) {
            $label = trim((string) $r->label);
            $perLabel[$label]['baris'] = ($perLabel[$label]['baris'] ?? 0) + (int) $r->baris;
            $perLabel[$label]['terisi'] = ($perLabel[$label]['terisi'] ?? 0) + (int) $r->terisi;
            $perLabel[$label]['jumlah'] = ($perLabel[$label]['jumlah'] ?? 0.0) + (float) $r->jumlah;
        }

        $out = [];

        foreach ($semuaLabel as $label) {
            $entri = $perLabel[$label] ?? null;

            $out[] = [
                $kunci => $label,
                'persen' => $entri === null || $entri['terisi'] === 0
                    ? null
                    : round($entri['jumlah'] / $entri['terisi'], 2),
                'ada_baris' => $entri !== null,
                'ini' => $label === $terpilih,
            ];
        }

        // Yang berangka lebih dulu dan dari yang tertinggi; sisanya diurutkan
        // abjad supaya posisinya tidak berubah-ubah antar bulan.
        usort($out, static function (array $a, array $b) use ($kunci): int {
            if (($a['persen'] === null) !== ($b['persen'] === null)) {
                return $a['persen'] === null ? 1 : -1;
            }

            return $a['persen'] === null
                ? strcmp((string) $a[$kunci], (string) $b[$kunci])
                : $b['persen'] <=> $a['persen'];
        });

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
            ['Site', 'Perusahaan', 'Bulan', 'Kinerja (%)', 'Nilai', 'Keterangan'],
            function (object $row): array {
                $p = $this->present($row);

                return [
                    $p['site'], $p['mitra'], $p['bulan'],
                    $p['persen'] ?? '', $p['nilai'] ?? '', $p['keterangan'],
                ];
            },
            'spip-commissioning'
        );
    }

    /** @return array<int, string> */
    private function columns(): array
    {
        return [
            self::COL_SITE . ' AS site',
            self::COL_PERUSAHAAN . ' AS mitra',
            self::COL_BULAN . ' AS bulan_sumber',
            self::COL_PERSEN . ' AS persen',
        ];
    }

    private function dataQuery(Request $request): Builder
    {
        $query = $this->baseQuery($request);

        $nilai = (int) $request->input('nilai', 0);

        if ($nilai >= 1 && $nilai <= 4) {
            [$batas] = self::SCORE_BANDS[4 - $nilai];
            $query->whereNotNull(self::COL_PERSEN)
                ->where(self::COL_PERSEN, '>=', $batas);

            // Band teratas sengaja tanpa batas atas. Sebelumnya dibatasi
            // < 101 dan angka di atas itu -- entah salah hitung di sumber atau
            // satuan yang berbeda -- lenyap dari semua filter Nilai sekaligus,
            // sehingga jumlah keempat band tidak lagi sama dengan jumlah baris.
            if ($nilai < 4) {
                $atas = self::SCORE_BANDS[3 - $nilai][0];
                $query->where(self::COL_PERSEN, '<', $atas);
            }
        } elseif (trim((string) $request->input('nilai', '')) === 'kosong') {
            $query->whereNull(self::COL_PERSEN);
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
        $persen = $row->persen === null ? null : round((float) $row->persen, 2);
        [, $nilai, $band] = $this->scoreBandFor($persen ?? 0.0);

        return [
            'site' => trim((string) $row->site),
            'mitra' => trim((string) $row->mitra),
            'bulan' => $this->nomorBulan((string) $row->bulan_sumber) === 0
                ? trim((string) $row->bulan_sumber)
                : self::monthLabel($this->nomorBulan((string) $row->bulan_sumber)),
            'persen' => $persen,
            'nilai' => $persen === null ? null : $nilai,
            'nilai_band' => $persen === null ? null : $band,
            'memenuhi_target' => $persen !== null && $persen >= self::TARGET_PERCENT,
            'keterangan' => $persen === null
                ? 'Belum ada data'
                : ($persen >= self::TARGET_PERCENT ? 'Memenuhi target' : 'Di bawah target'),
        ];
    }

    // ======================================================================
    // Utilitas
    // ======================================================================

    /**
     * Nomor bulan dari dua bentuk penulisan yang pernah dipakai tabel ini:
     * "M01".."M12" maupun nama bulan Inggris. 0 bila tidak dikenali.
     */
    /**
     * Tahun yang benar-benar ada di sumber.
     *
     * TAHUN TIDAK IKUT JADI KUNCI BULAN di halaman ini, dan itu keputusan
     * sadar: seluruh 691 baris bertahun 2026, jadi kunci 1-12 sudah cukup dan
     * jauh lebih sederhana daripada kode tahun*100+bulan. Tahunnya tetap
     * dikumpulkan di sini supaya namaBulan() bisa menghasilkan bentuk "Oct
     * 2026" untuk whereIn.
     *
     * KALAU TAHUN KEDUA MASUK, matriks akan menggabungkan "Jan 2026" dan "Jan
     * 2027" menjadi satu kolom tanpa memberi tahu siapa pun. Karena itu
     * catatan() memeriksa jumlah tahun dan memperingatkan di layar begitu
     * lebih dari satu ditemukan; kalau itu terjadi, halaman ini perlu pindah
     * ke kode tahun*100+bulan seperti Coverage Area Kritis.
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
            if (preg_match('/(\d{4})/', $nilai, $cocok) === 1) {
                $tahun[(int) $cocok[1]] = true;
            }
        }

        $cache = array_keys($tahun);
        sort($cache);

        return $cache;
    }

    private function nomorBulan(string $nilai): int
    {
        $nilai = trim($nilai);

        if (preg_match('/^M(\d{1,2})$/i', $nilai, $cocok) === 1) {
            $nomor = (int) $cocok[1];

            return $nomor >= 1 && $nomor <= 12 ? $nomor : 0;
        }

        // "Oct 2026" -> tahunnya dilepas dulu; lihat tahunTersedia() untuk
        // alasan mengapa tahun tidak ikut jadi bagian kunci bulan.
        if (preg_match('/^([A-Za-z]+)(?:\s+(\d{4}))?$/', $nilai, $cocok) !== 1) {
            return 0;
        }

        return self::MONTH_MAP[ucfirst(strtolower($cocok[1]))][0] ?? 0;
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
            if ($no !== $nomor) {
                continue;
            }

            $out[] = $inggris;

            // Sumber menulis bulan bersama tahunnya ("Oct 2026"), jadi bentuk
            // bertahun harus ikut dihasilkan atau whereIn tidak akan cocok.
            foreach ($this->tahunTersedia() as $tahun) {
                $out[] = $inggris . ' ' . $tahun;
            }
        }

        return array_values(array_unique($out));
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

    /** @return array{0: float, 1: int, 2: string} */
    /**
     * Nilai berkoma untuk satu capaian.
     *
     * Dikembalikan sebagai [batas bawah band, nilai, label band] supaya
     * pemanggil yang membongkar [, $nilai, $band] tetap bekerja.
     *
     * @return array{0: float, 1: float, 2: string}
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

            // Tidak boleh menyentuh angka band berikutnya: 93,90% menghasilkan
            // 1,99893 yang membulat jadi 2,00 dan akan tampil sebagai "Nilai 2"
            // di sel yang labelnya masih "<94%".
            $nilai = min($nilai, $dasar + 0.99);

            return [$bawah, round(max(1.0, min(4.0, $nilai)), 2), $label];
        }

        return [0.0, 1.0, '<94%'];
    }
}
