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
 * Parameter wellbeing "Speak up fatigue".
 *
 * Sumbernya satu tabel, lead_speak_up_sebelum_alert: 111 baris, satu baris per
 * site x perusahaan x bulan. Yang diukur berapa persen true alert fatigue yang
 * SUDAH didahului speak up sebelum alertnya berbunyi. Makin tinggi makin baik:
 * artinya pengawas atau rekan kerja menangkap gejala lelah lebih dulu, bukan
 * menunggu alat yang memberi tahu. Tidak ada tabel rinciannya.
 *
 * 26 DARI 111 BARIS TIDAK PUNYA SITE, dan itu bukan kerusakan yang boleh
 * dibuang diam-diam. site_dedicated-nya NULL sementara perusahaan dan bulannya
 * terisi penuh, jadi barisnya tetap data yang sah. Kalau dibiarkan apa adanya,
 * baris itu muncul di matriks sebagai sel site yang kosong melompong dan
 * terbaca seperti cacat tampilan. Karena itu labelnya ditegaskan menjadi
 * SITE_KOSONG ("(Tanpa site)") di satu tempat -- lihat labelSite() -- sehingga
 * pembaca tahu baris itu memang tidak bersite, dan angkanya tetap ikut
 * terhitung. Jangan diganti menjadi penyaringan WHERE site IS NOT NULL: itu
 * akan membuang hampir seperempat datanya.
 *
 * SUMBERNYA HANYA MENYIMPAN PERSENTASE. Tidak ada pembilang maupun penyebut,
 * jadi sel TIDAK bisa diurai menjadi "sekian dari sekian alert". Ini penting
 * karena nilai 100,00% di sini sering berasal dari penyebut yang sangat kecil
 * -- satu alert yang didahului satu speak up sudah menghasilkan 100% -- dan
 * tanpa penyebutnya pembaca tidak bisa membedakannya dari capaian yang benar
 * -- benar baik. Modal menyebutkan keterbatasan itu alih-alih mengarang angka.
 *
 * SEBARANNYA TIMPANG DAN ITU KETERANGAN PENTING. 68 dari 111 baris tepat 0,00%
 * sementara nilai tertingginya 100,00%. Per perusahaan, PT Pamapersada
 * Nusantara rata-rata 0,90% dari 25 baris sedangkan PT Madhani Talatah
 * Nusantara 25,22% dari 27 baris. Jadi rata-rata keseluruhan akan terlihat
 * rendah bukan karena satu bulan buruk, melainkan karena sebagian besar
 * pasangan memang tidak pernah mencatat speak up sama sekali.
 *
 * BULAN DITULIS NAMA INGGRIS TANPA TAHUN ("August", bukan "August 2026"), jadi
 * monthHeadings() mengirim 1-12, bukan kode tahun*100+bulan. nomorBulan()
 * mengenali bentuk M01-M12 juga, supaya halaman tidak rusak kalau sumbernya
 * berganti gaya penulisan.
 *
 * TIDAK ADA OKTOBER DI TABEL INI. Isinya Januari sampai September, jadi
 * EXCLUDED_MONTHS tidak pernah benar-benar menyaring apa pun. Konstanta itu
 * tetap dipasang supaya halaman ini berperilaku sama dengan halaman OHS Score
 * Card lain begitu Oktober masuk.
 *
 * AMBANG & BAND mengikuti sistem penilaian OHS Score Card yang sama dengan
 * halaman lain (target 90%, band 98/90/80). Belum ada konfirmasi bahwa
 * parameter ini memakai band yang sama -- dan dengan sebaran di atas, hampir
 * semua sel akan jatuh di Nilai 1 -- jadi kalau ambangnya ternyata berbeda,
 * ubah SCORE_BANDS dan TARGET_PERCENT di bawah.
 */
final class SpeakUpFatigueController extends Controller
{
    use ServesDataTable;

    private const TABLE = 'lead_speak_up_sebelum_alert';

    /**
     * Label untuk 26 baris yang site_dedicated-nya NULL. Lihat docblock kelas:
     * baris itu sah dan tidak boleh dibuang, tetapi tanpa label ia tampil
     * sebagai sel kosong yang terbaca seperti cacat tampilan.
     */
    private const SITE_KOSONG = '(Tanpa site)';

    private const COL_SITE = 'site_dedicated';
    private const COL_PERUSAHAAN = 'nama_perusahaan';
    private const COL_BULAN = 'month_of_event_time';
    private const COL_PERSEN = 'pct_true_alert_fatigue_speak_up_sebelum';

    private const TARGET_PERCENT = 100.0;

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
        [96.0, 98.0, 2, '96% - <98%'],
        [0.0, 96.0, 1, '<96%'],
    ];

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
        3 => self::COL_PERSEN,
    ];

    public function index(): View
    {
        return view('ohs-score-card.speak-up-fatigue.index', [
            'filterOptions' => [
                'site' => $this->nilaiSite(),
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

            if ($monthNo === 0 || in_array($monthNo, self::EXCLUDED_MONTHS, true)) {
                continue;
            }

            $monthSeen[$monthNo] = true;
            $site = $this->labelSite($row->site);
            $mitra = trim((string) $row->mitra);

            $grid[$site . '|' . $mitra]['site'] = $site;
            $grid[$site . '|' . $mitra]['mitra'] = $mitra;
            // NULL dibiarkan NULL: "belum ada pengisian tercatat" bukan "nol persen".
            $grid[$site . '|' . $mitra]['bulan'][$monthNo] = $row->persen === null
                ? null
                : round((float) $row->persen, 2);
        }

        ksort($monthSeen);
        $months = array_keys($monthSeen);
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
     * Site diurutkan dari yang capaiannya paling rendah, dan di dalam tiap site
     * barisnya juga dari yang paling rendah, sehingga yang perlu ditangani
     * lebih dulu tetap berada di atas meski sudah dikelompokkan.
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

    /** Keterangan tentang sel kosong, supaya matriks berlubang tidak disalahbaca. */
    private function catatan(Request $request): ?string
    {
        if (! DB::table(self::TABLE)->exists()) {
            return 'Tabel ' . self::TABLE . ' masih kosong, jadi belum ada yang bisa ditampilkan.';
        }

        $kosong = (clone $this->baseQuery($request))->whereNull(self::COL_PERSEN)->count();

        if ($kosong === 0) {
            return null;
        }

        $total = (clone $this->baseQuery($request))->count();

        return $kosong . ' dari ' . $total . ' baris di ' . self::TABLE . ' belum berisi persentase. '
            . 'Sel yang kosong ditandai strip, bukan nol, dan tidak ikut dihitung dalam rata-rata.';
    }

    // ======================================================================
    // Modal rincian satu sel
    // ======================================================================

    /**
     * Isi satu sel matriks Capaian per Bulan, untuk modal rincian.
     *
     * SUMBERNYA HANYA PERSENTASE. lead_speak_up_sebelum_alert cuma punya empat
     * kolom berisi -- site_dedicated, nama_perusahaan, month_of_tanggal_date,
     * dan pct_pengisian_aggregator -- sehingga tidak ada pembilang (pekerja
     * yang mengisi aggregator) maupun penyebut (pekerja yang seharusnya
     * mengisi) untuk mengurai sel jadi "sekian dari sekian", dan tidak ada
     * tabel rincian mana pun untuk parameter ini. Karena itu modal ini
     * menyajikan konteks di sekeliling sel, seluruhnya dari tabel yang sama
     * dengan matriksnya, supaya angka modal tidak mungkin bertentangan dengan
     * sel yang diklik.
     *
     * PERUSAHAAN DI SINI BEKERJA LINTAS SITE: lima dari tujuh perusahaan di
     * sumber ini muncul di 2-3 site (PT Madhani Talatah Nusantara di BMO 1,
     * LMO, dan SMO sekaligus). Jadi modal ini punya panel ketiga, capaian
     * perusahaan yang sama di site lain pada bulan yang sama. Tanpa panel itu
     * pembaca tidak bisa memisahkan "perusahaannya memang jarang mengisi" dari
     * "site ini yang bermasalah".
     *
     *   riwayat      site x perusahaan sepanjang bulan -> kronis atau sesaat?
     *   sebulan      site x bulan di seluruh perusahaan -> satu mitra atau se-site?
     *   lintas_site  perusahaan x bulan di seluruh site -> mitranya atau sitenya?
     *
     * PERINGKAT dihitung di antara seluruh sel pada bulan yang sama (13 sel
     * per bulan di sumber saat ini), tertinggi di urutan pertama karena di
     * parameter ini makin tinggi makin baik.
     */
    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));

        // monthHeadings() halaman ini mengirim nomor polos 1-12, tetapi kode
        // tahun*100+bulan tetap diterima supaya tautan dari halaman yang
        // bulannya bertahun tidak patah kalau suatu saat diarahkan ke sini.
        $kode = (int) $request->input('month', 0);
        $bulan = $kode > 9999 ? $kode % 100 : $kode;

        if ($site === '' || $mitra === '' || $bulan < 1 || $bulan > 12) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Site, perusahaan, dan bulan wajib diisi untuk membuka rincian.',
            ]);
        }

        // overview() membuang bulan ini dari matriks, jadi menampilkan
        // rinciannya akan memunculkan angka yang tidak punya sel asal.
        if (in_array($bulan, self::EXCLUDED_MONTHS, true)) {
            return response()->json([
                'ok' => false,
                'pesan' => self::monthLabel($bulan) . ' masih berjalan dan tidak ikut dinilai, '
                    . 'jadi tidak ada sel yang bisa dirinci.',
            ]);
        }

        $nilaiBulan = $this->namaBulan($bulan);

        // AVG dipakai persis seperti di overview(), bukan nilai baris tunggal,
        // supaya sel dan modal tetap sama kalau sumbernya suatu saat memuat
        // lebih dari satu baris per site x perusahaan x bulan.
        $persen = $this->saringSite(DB::table(self::TABLE), $site)
            ->where(self::COL_PERUSAHAAN, $mitra)
            ->whereIn(self::COL_BULAN, $nilaiBulan)
            ->avg(self::COL_PERSEN);

        return response()->json([
            'ok' => true,
            'judul' => [
                'site' => $site,
                'mitra' => $mitra,
                'bulan' => self::monthLabel($bulan),
            ],
            'target' => self::TARGET_PERCENT,
            'sel' => $this->bentukNilai($persen === null ? null : round((float) $persen, 2)),
            'peringkat' => $this->peringkatBulan($nilaiBulan, $site, $mitra),
            'riwayat' => $this->riwayatSelama($site, $mitra),
            'sebulan' => $this->sebulanDiSite($site, $nilaiBulan, $mitra),
            'lintas_site' => $this->lintasSite($mitra, $nilaiBulan, $site),
        ]);
    }

    /**
     * Satu persentase lengkap dengan nilai, band, dan selisihnya ke target.
     *
     * @return array<string, mixed>
     */
    private function bentukNilai(?float $persen): array
    {
        if ($persen === null) {
            return [
                'persen' => null, 'nilai' => null, 'nilai_band' => null,
                'memenuhi_target' => false, 'selisih' => null,
            ];
        }

        [, $nilai, $band] = $this->scoreBandFor($persen);

        return [
            'persen' => $persen,
            'nilai' => $nilai,
            'nilai_band' => $band,
            'memenuhi_target' => $persen >= self::TARGET_PERCENT,
            'selisih' => round($persen - self::TARGET_PERCENT, 2),
        ];
    }

    /**
     * Urutan sel ini di antara seluruh sel pada bulan yang sama, tertinggi
     * lebih dulu. Sel tanpa persentase tidak ikut diurutkan maupun dihitung,
     * sejalan dengan matriks yang menandainya strip.
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<string, mixed>
     */
    private function peringkatBulan(array $nilaiBulan, string $site, string $mitra): array
    {
        $rows = DB::table(self::TABLE)
            ->whereIn(self::COL_BULAN, $nilaiBulan)
            ->whereNotNull(self::COL_PERSEN)
            ->selectRaw(
                self::COL_SITE . ' AS site, '
                . self::COL_PERUSAHAAN . ' AS mitra, '
                . 'AVG(' . self::COL_PERSEN . ') AS persen'
            )
            ->groupBy('site', 'mitra')
            ->get()
            ->map(fn (object $r): array => [
                'site' => $this->labelSite($r->site),
                'mitra' => trim((string) $r->mitra),
                'persen' => round((float) $r->persen, 2),
            ])
            ->all();

        usort($rows, static fn (array $a, array $b): int => $b['persen'] <=> $a['persen']);

        $posisi = null;

        foreach ($rows as $i => $r) {
            if ($r['site'] === $site && $r['mitra'] === $mitra) {
                $posisi = $i + 1;
                break;
            }
        }

        $semua = array_column($rows, 'persen');

        return [
            'posisi' => $posisi,
            'dari' => count($rows),
            'rata' => $semua === [] ? null : round(array_sum($semua) / count($semua), 2),
        ];
    }

    /**
     * Site x perusahaan yang sama sepanjang bulan: kronis atau sesaat?
     *
     * @return array<int, array<string, mixed>>
     */
    private function riwayatSelama(string $site, string $mitra): array
    {
        $rows = $this->saringSite(DB::table(self::TABLE), $site)
            ->where(self::COL_PERUSAHAAN, $mitra)
            ->selectRaw(
                self::COL_BULAN . ' AS bulan, AVG(' . self::COL_PERSEN . ') AS persen'
            )
            ->groupBy('bulan')
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $nomor = $this->nomorBulan((string) $r->bulan);

            if ($nomor === 0 || in_array($nomor, self::EXCLUDED_MONTHS, true)) {
                continue;
            }

            $out[] = [
                'nomor' => $nomor,
                'bulan' => self::monthLabel($nomor),
                'persen' => $r->persen === null ? null : round((float) $r->persen, 2),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['nomor'] <=> $b['nomor']);

        return $out;
    }

    /**
     * Seluruh perusahaan di site ini pada bulan yang sama: pengisian yang
     * tertinggal milik satu mitra saja atau memang se-site?
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<int, array<string, mixed>>
     */
    private function sebulanDiSite(string $site, array $nilaiBulan, string $mitraTerpilih): array
    {
        return $this->ringkasKolom(
            $this->saringSite(DB::table(self::TABLE), $site)
                ->whereIn(self::COL_BULAN, $nilaiBulan),
            self::COL_PERUSAHAAN,
            'mitra',
            $mitraTerpilih
        );
    }

    /**
     * Perusahaan yang sama di seluruh site pada bulan yang sama: mitranya yang
     * jarang mengisi atau sitenya? Panel ini masuk akal di sini karena lima
     * dari tujuh perusahaan di sumber bekerja di lebih dari satu site.
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
            $siteTerpilih
        );
    }

    /**
     * Rata-rata persentase per nilai satu kolom, tertinggi di atas, dengan
     * penanda pada baris yang sedang dibuka.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ringkasKolom(Builder $query, string $kolom, string $kunci, string $terpilih): array
    {
        // Label "(Tanpa site)" hanya sah untuk sumbu site. Kolom perusahaan
        // tidak pernah kosong di tabel ini, dan memberinya label site akan
        // menyesatkan kalau suatu saat ada yang kosong.
        $sumbuSite = $kolom === self::COL_SITE;

        $out = $query
            ->selectRaw($kolom . ' AS label, AVG(' . self::COL_PERSEN . ') AS persen')
            ->groupBy('label')
            ->get()
            ->map(fn (object $r): array => [
                'label' => $sumbuSite ? $this->labelSite($r->label) : trim((string) $r->label),
                'persen' => $r->persen === null ? null : round((float) $r->persen, 2),
            ])
            ->all();

        usort($out, static fn (array $a, array $b): int => ($b['persen'] ?? -1) <=> ($a['persen'] ?? -1));

        return array_map(static fn (array $r): array => [
            $kunci => $r['label'],
            'persen' => $r['persen'],
            'ini' => $r['label'] === $terpilih,
        ], $out);
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
            ['Site', 'Perusahaan', 'Bulan', 'Pengisian Aggregator (%)', 'Nilai', 'Keterangan'],
            function (object $row): array {
                $p = $this->present($row);

                return [
                    $p['site'], $p['mitra'], $p['bulan'],
                    $p['persen'] ?? '', $p['nilai'] ?? '', $p['keterangan'],
                ];
            },
            'speak-up-fatigue'
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

            // Band teratas sengaja tanpa batas atas, supaya angka di atas 100
            // tidak lenyap dari semua filter sekaligus.
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
    /**
     * Pilihan filter site, termasuk label untuk baris tanpa site kalau memang
     * ada. Tanpa ini, 26 baris itu tidak bisa disaring sama sekali dari tab
     * Data meski tampil di matriks.
     *
     * @return array<int, string>
     */
    private function nilaiSite(): array
    {
        $nilai = $this->distinctValues(self::COL_SITE);

        $adaKosong = DB::table(self::TABLE)
            ->where(function (Builder $q): void {
                $q->whereNull(self::COL_SITE)->orWhere(self::COL_SITE, '');
            })
            ->exists();

        if ($adaKosong) {
            $nilai[] = self::SITE_KOSONG;
        }

        return $nilai;
    }

    /** Nilai site apa adanya, dengan yang kosong diberi label tegas. */
    private function labelSite(mixed $nilai): string
    {
        $teks = trim((string) $nilai);

        return $teks === '' ? self::SITE_KOSONG : $teks;
    }

    /**
     * Kebalikan labelSite(): menyaring kueri ke site yang diminta, termasuk
     * saat yang diminta adalah label untuk site kosong. Dipakai di SETIAP
     * tempat yang menyaring per site, supaya sel "(Tanpa site)" yang diklik
     * menjaring baris yang sama dengan yang membentuk sel itu.
     */
    private function saringSite(Builder $query, string $site): Builder
    {
        if ($site === self::SITE_KOSONG) {
            return $query->where(function (Builder $q): void {
                $q->whereNull(self::COL_SITE)->orWhere(self::COL_SITE, '');
            });
        }

        return $query->where(self::COL_SITE, $site);
    }

    private function baseQuery(Request $request): Builder
    {
        $query = DB::table(self::TABLE);

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn(self::COL_BULAN, $this->namaBulan($nomor));
        }

        foreach (self::FILTERABLE as $parameter => $column) {
            $value = trim((string) $request->input($parameter, ''));

            if ($value === '') {
                continue;
            }

            // Site punya label khusus untuk nilai kosong, jadi penyaringannya
            // lewat saringSite(); kolom lain cukup pencocokan biasa.
            if ($column === self::COL_SITE) {
                $this->saringSite($query, $value);
            } else {
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
        $monthNo = $this->nomorBulan((string) $row->bulan_sumber);

        return [
            'site' => $this->labelSite($row->site),
            'mitra' => trim((string) $row->mitra),
            'bulan' => $monthNo === 0 ? trim((string) $row->bulan_sumber) : self::monthLabel($monthNo),
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
     * Nomor bulan dari dua bentuk penulisan yang dipakai sumber ini:
     * "M01".."M12" maupun nama bulan Inggris. 0 bila tidak dikenali.
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
     * Semua cara penulisan satu nomor bulan, untuk dipakai di whereIn.
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

    /** @return array{0: float, 1: int, 2: string} */
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

        return [0.0, 1.0, '<96%'];
    }
}
