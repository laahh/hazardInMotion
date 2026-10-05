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
 * Parameter SOD "Leadtime Alert DMS masuk ke Server".
 *
 * Ukurannya: dari sekian alert DMS, berapa persen yang evidence-nya sudah
 * masuk ke server BeDMS dalam 5 menit. Makin tinggi makin baik.
 *
 * SUMBERNYA TABEL REKAP, BUKAN TABEL MENTAH. Halaman ini membaca
 * lead_leadtime_alert_entry_to_bedms_month (satu baris per site x perusahaan
 * x bulan). Angkanya sendiri dihitung dari bcsid.dms_alert dan
 * bcsid.dms_alert_evidence di Postgres, tetapi lewat perintah terjadwal
 * `ohs:isi-leadtime-alert`, bukan saat halaman dibuka: memindai tabel alert
 * mentah setahun penuh terlalu berat untuk dijalankan tiap kali filter
 * diganti. Pola ini sama dengan parameter OHS Score Card lainnya.
 *
 * NAMA KOLOM DIBACA DARI SKEMA, bukan ditulis mati. Tabel ini sudah dua kali
 * berganti bentuk: mula-mula hasil scrape Tableau (Month_of_event_time,
 * Perusahaan, Leadtime_Alert_masuk_ke_Server_Evidence_BeDMS_under_5_min), kini
 * snake_case dengan nama persentase yang terpotong di 60 huruf
 * (pct_leadtime_alert_masuk_ke_server_evidence_bedms_under_5_mi). Bulannya pun
 * ikut berubah dari nama Inggris menjadi M01-M12. kolom() dan nomorBulan()
 * menangani keduanya sekaligus.
 *
 * SKALA ANGKA. Kolom sumbernya bertipe double tanpa satuan yang pasti:
 * perintah pengisi menulis persen (0-100), sedangkan hasil scrape Tableau
 * untuk parameter sejenis menulis pecahan (0-1). Karena itu skalanya
 * dideteksi sekali per permintaan lewat nilai tertingginya, lihat
 * skalaPersen(). Kalau seluruh isinya <= 1, angkanya dikalikan 100.
 */
final class LeadtimeAlertBedmsController extends Controller
{
    use ServesDataTable;

    private const TABLE = 'lead_leadtime_alert_entry_to_bedms_month';

    /**
     * Calon nama kolom untuk tiap peran, diurutkan dari bentuk yang dipakai
     * sekarang ke bentuk lama. Lihat catatan NAMA KOLOM di docblock kelas.
     *
     * @var array<string, array<int, string>>
     */
    private const KOLOM_CALON = [
        'site' => ['site'],
        'mitra' => ['perusahaan', 'Perusahaan', 'perusahaan_pic'],
        'bulan' => ['month_of_event_time', 'Month_of_event_time', 'Month_of_Event_Time'],
        'persen' => ['Leadtime_Alert_masuk_ke_Server_Evidence_BeDMS_under_5_min'],
    ];

    /**
     * Cadangan untuk kolom persentase: namanya terpotong saat dirapikan
     * ("..._under_5_min" menjadi "..._under_5_mi"), jadi mencocokkan nama
     * lengkap saja rapuh. Kolom mana pun yang namanya diawali ini dipakai.
     */
    private const PREFIKS_PERSEN = 'pct_leadtime';

    /** Ambang evidence dianggap tepat waktu, ikut definisi parameternya. */
    private const AMBANG_MENIT = 5;

    private const TARGET_PERCENT = 90.0;

    private const SCORE_BANDS = [
        [98.0, 4, '98% - 100%'],
        [90.0, 3, '90% - <98%'],
        [80.0, 2, '80% - <90%'],
        [0.0,  1, '<80%'],
    ];

    /** Bulan bisa tertulis M01-M12 maupun nama Inggris; lihat nomorBulan(). */
    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /**
     * Bulan yang tidak ikut dihitung, sejalan dengan halaman parameter
     * lainnya: Oktober masih berjalan saat data ini diambil.
     */
    private const EXCLUDED_MONTHS = [10];

    /**
     * Dimensi yang bisa difilter. Berupa metode, bukan konstanta, karena nama
     * kolomnya baru diketahui setelah skema tabel dibaca.
     *
     * @return array<string, string>
     */
    private function filterable(): array
    {
        $k = $this->kolom();

        return ['site' => $k['site'], 'mitra' => $k['mitra']];
    }

    /** @return array<int, string> */
    private function searchable(): array
    {
        $k = $this->kolom();

        return [$k['site'], $k['mitra'], $k['bulan']];
    }

    /** @return array<int, string> */
    private function orderable(): array
    {
        $k = $this->kolom();

        return [0 => $k['site'], 1 => $k['mitra'], 3 => $k['persen']];
    }

    public function index(): View
    {
        return view('ohs-score-card.leadtime-alert-bedms.index', [
            'filterOptions' => [
                'site' => $this->distinctValues($this->kolom()['site']),
                'mitra' => $this->distinctValues($this->kolom()['mitra']),
            ],
            'monthOptions' => $this->monthOptions(),
            'target' => self::TARGET_PERCENT,
            'ambang' => self::AMBANG_MENIT,
            'tabel' => self::TABLE,
        ]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request): JsonResponse
    {
        $skala = $this->skalaPersen();

        $rows = $this->baseQuery($request)
            ->selectRaw(
                $this->kolom()['site'] . ' AS site, '
                . '`' . $this->kolom()['mitra'] . '` AS mitra, '
                . '`' . $this->kolom()['bulan'] . '` AS bulan, '
                . 'AVG(`' . $this->kolom()['persen'] . '`) AS persen'
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
            // NULL dibiarkan NULL: "belum ada alert" bukan "tidak ada yang tepat waktu".
            $grid[$site . '|' . $mitra]['bulan'][$monthNo] = $row->persen === null
                ? null
                : round((float) $row->persen * $skala, 2);
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
     * Site diurutkan dari yang paling rendah capaiannya, dan di dalam tiap
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
            'ambang_menit' => self::AMBANG_MENIT,
            'memenuhi_target' => $rata !== null && $rata >= self::TARGET_PERCENT,
            'tertinggi' => $nilai !== [] ? max($nilai) : null,
            'terendah' => $nilai !== [] ? min($nilai) : null,
            // Penyebutnya hanya pasangan yang punya angka; yang seluruh
            // bulannya kosong tidak bisa dibilang gagal memenuhi target.
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
     * Keterangan ketika sumbernya belum terisi atau masih bolong, supaya
     * matriks berlubang tidak dikira capaiannya nol.
     */
    private function catatan(Request $request): ?string
    {
        if (! DB::table(self::TABLE)->exists()) {
            return 'Tabel ' . self::TABLE . ' masih kosong, jadi belum ada yang bisa ditampilkan. '
                . 'Jalankan `php artisan ohs:isi-leadtime-alert` untuk menghitungnya dari '
                . 'bcsid.dms_alert di Postgres, atau tunggu scraper Tableau mengisinya.';
        }

        $kosong = (clone $this->baseQuery($request))->whereNull($this->kolom()['persen'])->count();

        if ($kosong === 0) {
            return null;
        }

        $total = (clone $this->baseQuery($request))->count();

        return $kosong . ' dari ' . $total . ' baris di ' . self::TABLE . ' belum berisi persentase. '
            . 'Sel yang kosong ditandai strip, bukan nol, dan tidak ikut dihitung dalam rata-rata.';
    }

    // ======================================================================
    // Rincian satu sel matriks
    // ======================================================================

    /**
     * Rincian satu sel "Capaian per Bulan": satu site x perusahaan x bulan.
     *
     * SUMBERNYA HANYA MENYIMPAN PERSENTASE. Tabel rekapnya cuma berisi site,
     * perusahaan, bulan, dan satu kolom persen. Pembilang dan penyebutnya --
     * jumlah alert dan berapa di antaranya yang evidence-nya masuk di bawah
     * AMBANG_MENIT -- memang dihitung oleh `ohs:isi-leadtime-alert`, tetapi
     * tidak ikut ditulis ke tabel rekap. Jadi modal ini TIDAK bisa menguraikan
     * sel menjadi "sekian dari sekian", dan tidak ada tabel rincian lain yang
     * bisa menggantikannya tanpa memindai bcsid.dms_alert di Postgres --
     * persis beban yang dihindari dengan adanya tabel rekap ini. Yang
     * disajikan konteks di sekeliling sel, seluruhnya dari tabel yang sama
     * dengan matriksnya, sehingga angkanya tidak mungkin bertentangan.
     *
     * TIGA SUMBU, karena satu perusahaan di sini bekerja di 2-5 site sekaligus
     * (PT PAMA di lima di antaranya):
     *
     *   riwayat      site x perusahaan sepanjang bulan -> kronis atau sesaat?
     *   sebulan      site x bulan di seluruh perusahaan -> satu mitra atau se-site?
     *   lintas_site  perusahaan x bulan di seluruh site -> mitranya atau sitenya?
     *
     * Tanpa sumbu ketiga, pembaca tidak bisa memisahkan "perusahaan ini memang
     * lambat" dari "server di site ini yang bermasalah".
     *
     * NAMA KOLOM TETAP LEWAT kolom(), tidak ditulis mati -- lihat catatan NAMA
     * KOLOM di docblock kelas. Begitu pula skalaPersen(), supaya sel dan modal
     * memakai satuan yang sama.
     *
     * PERINGKAT dihitung di antara seluruh sel bulan itu, tertinggi di urutan
     * pertama karena di parameter ini makin tinggi makin baik.
     */
    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));

        // monthHeadings() halaman ini mengirim nomor 1-12, tetapi kode
        // tahun*100+bulan tetap diterima supaya penanda sel yang lebih
        // lengkap tidak langsung ditolak. Tahunnya sendiri dibuang: tabel
        // rekap ini tidak menyimpan tahun sama sekali, jadi menyaringnya akan
        // menjanjikan ketelitian yang tidak dimiliki sumbernya.
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
                'pesan' => self::monthLabel($bulan) . ' tidak ikut dihitung di parameter ini, '
                    . 'jadi tidak ada sel yang bisa dirinci.',
            ]);
        }

        $nilaiBulan = $this->namaBulan($bulan);
        $skala = $this->skalaPersen();

        // AVG dipakai persis seperti di overview(), bukan nilai baris tunggal,
        // supaya sel dan modal tidak bisa berbeda kalau suatu saat sumbernya
        // memuat lebih dari satu baris per kunci.
        $persen = DB::table(self::TABLE)
            ->where($this->kolom()['site'], $site)
            ->where($this->kolom()['mitra'], $mitra)
            ->whereIn($this->kolom()['bulan'], $nilaiBulan)
            ->avg($this->kolom()['persen']);

        $persen = $persen === null ? null : round((float) $persen * $skala, 2);

        return response()->json([
            'ok' => true,
            'judul' => [
                'site' => $site,
                'mitra' => $mitra,
                'bulan' => self::monthLabel($bulan),
            ],
            'target' => self::TARGET_PERCENT,
            'ambang_menit' => self::AMBANG_MENIT,
            'sel' => $this->bentukNilai($persen),
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
     * lebih dulu. Sel tanpa persentase tidak ikut diurutkan maupun dihitung.
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<string, mixed>
     */
    private function peringkatBulan(array $nilaiBulan, string $site, string $mitra): array
    {
        $skala = $this->skalaPersen();

        $rows = DB::table(self::TABLE)
            ->whereIn($this->kolom()['bulan'], $nilaiBulan)
            ->whereNotNull($this->kolom()['persen'])
            ->selectRaw(
                '`' . $this->kolom()['site'] . '` AS site, '
                . '`' . $this->kolom()['mitra'] . '` AS mitra, '
                . 'AVG(`' . $this->kolom()['persen'] . '`) AS persen'
            )
            ->groupBy('site', 'mitra')
            ->get()
            ->map(static fn (object $r): array => [
                'site' => trim((string) $r->site),
                'mitra' => trim((string) $r->mitra),
                'persen' => round((float) $r->persen * $skala, 2),
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
        $skala = $this->skalaPersen();

        $rows = DB::table(self::TABLE)
            ->where($this->kolom()['site'], $site)
            ->where($this->kolom()['mitra'], $mitra)
            ->selectRaw(
                '`' . $this->kolom()['bulan'] . '` AS bulan, '
                . 'AVG(`' . $this->kolom()['persen'] . '`) AS persen'
            )
            ->groupBy('bulan')
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $nomor = $this->nomorBulan((string) $r->bulan);

            if ($nomor === 0 || in_array($nomor, self::EXCLUDED_MONTHS, true)) {
                continue; // sejalan dengan overview(): bulan tak dikenal & Oktober tidak ikut
            }

            $out[] = [
                'nomor' => $nomor,
                'bulan' => self::monthLabel($nomor),
                'persen' => $r->persen === null ? null : round((float) $r->persen * $skala, 2),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['nomor'] <=> $b['nomor']);

        return $out;
    }

    /**
     * Seluruh perusahaan di site ini pada bulan yang sama: lambatnya milik
     * satu mitra atau seluruh site?
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<int, array<string, mixed>>
     */
    private function sebulanDiSite(string $site, array $nilaiBulan, string $mitraTerpilih): array
    {
        return $this->ringkasKolom(
            DB::table(self::TABLE)
                ->where($this->kolom()['site'], $site)
                ->whereIn($this->kolom()['bulan'], $nilaiBulan),
            $this->kolom()['mitra'],
            'mitra',
            $mitraTerpilih
        );
    }

    /**
     * Perusahaan yang sama di seluruh site pada bulan yang sama.
     *
     * Panel ini masuk akal di sini karena tiap perusahaan di tabel ini bekerja
     * di beberapa site sekaligus; kalau evidence-nya telat di semua site,
     * yang bermasalah perangkat atau jaringan mitranya, bukan sitenya.
     *
     * @param  array<int, string>  $nilaiBulan
     * @return array<int, array<string, mixed>>
     */
    private function lintasSite(string $mitra, array $nilaiBulan, string $siteTerpilih): array
    {
        return $this->ringkasKolom(
            DB::table(self::TABLE)
                ->where($this->kolom()['mitra'], $mitra)
                ->whereIn($this->kolom()['bulan'], $nilaiBulan),
            $this->kolom()['site'],
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
        $skala = $this->skalaPersen();

        $out = $query
            ->selectRaw(
                '`' . $kolom . '` AS label, '
                . 'AVG(`' . $this->kolom()['persen'] . '`) AS persen'
            )
            ->groupBy('label')
            ->get()
            ->map(static fn (object $r): array => [
                'label' => trim((string) $r->label),
                'persen' => $r->persen === null ? null : round((float) $r->persen * $skala, 2),
            ])
            ->all();

        usort($out, static fn (array $a, array $b): int => ($b['persen'] ?? -1.0) <=> ($a['persen'] ?? -1.0));

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
        $skala = $this->skalaPersen();

        $rows = (clone $query)
            ->select($this->columns())
            ->orderBy(
                $this->dtOrderColumn($request, $this->orderable(), $this->kolom()['site']),
                $this->dtDirection($request, 'asc')
            )
            ->orderBy('id')
            ->forPage($this->dtPage($request), $this->dtPageLength($request))
            ->get()
            ->map(fn (object $row): array => $this->present($row, $skala))
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
        $skala = $this->skalaPersen();

        $query = $this->dataQuery($request)
            ->select($this->columns())
            ->orderBy($this->kolom()['site'])
            ->orderBy($this->kolom()['mitra'])
            ->orderBy('id');

        return $this->dtExport(
            $request,
            $query,
            ['Site', 'Perusahaan', 'Bulan', 'Leadtime <' . self::AMBANG_MENIT . ' Menit (%)', 'Nilai', 'Keterangan'],
            function (object $row) use ($skala): array {
                $p = $this->present($row, $skala);

                return [
                    $p['site'], $p['mitra'], $p['bulan'],
                    $p['persen'] ?? '', $p['nilai'] ?? '', $p['keterangan'],
                ];
            },
            'leadtime-alert-bedms'
        );
    }

    /** @return array<int, string> */
    private function columns(): array
    {
        // Tanpa backtick: select() sudah mengutip sendiri, menambahkannya di
        // sini menghasilkan kutipan ganda yang ditolak MySQL. (selectRaw di
        // overview() lain soal -- di sana tidak ada pengutipan otomatis.)
        return [
            $this->kolom()['site'] . ' AS site',
            $this->kolom()['mitra'] . ' AS mitra',
            $this->kolom()['bulan'] . ' AS bulan_sumber',
            $this->kolom()['persen'] . ' AS persen',
        ];
    }

    private function dataQuery(Request $request): Builder
    {
        $query = $this->baseQuery($request);
        $skala = $this->skalaPersen();

        $nilai = (int) $request->input('nilai', 0);

        if ($nilai >= 1 && $nilai <= 4) {
            [$batas] = self::SCORE_BANDS[4 - $nilai];
            $query->whereNotNull($this->kolom()['persen'])
                ->where($this->kolom()['persen'], '>=', $batas / $skala);

            // Band teratas sengaja tanpa batas atas. Sebelumnya dibatasi
            // < 101 dan angka di atas itu -- entah salah hitung di sumber atau
            // satuan yang berbeda -- lenyap dari semua filter Nilai sekaligus,
            // sehingga jumlah keempat band tidak lagi sama dengan jumlah baris.
            if ($nilai < 4) {
                $atas = self::SCORE_BANDS[3 - $nilai][0];
                $query->where($this->kolom()['persen'], '<', $atas / $skala);
            }
        } elseif (trim((string) $request->input('nilai', '')) === 'kosong') {
            $query->whereNull($this->kolom()['persen']);
        }

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            $this->searchable()
        );

        return $query;
    }

    /** Tabel dengan filter dimensi & bulan terpasang. */
    private function baseQuery(Request $request): Builder
    {
        $query = DB::table(self::TABLE);

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn($this->kolom()['bulan'], $this->namaBulan($nomor));
        }

        foreach ($this->filterable() as $parameter => $column) {
            $value = trim((string) $request->input($parameter, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $month = (int) $request->input('month', 0);

        if ($month >= 1 && $month <= 12) {
            $query->whereIn($this->kolom()['bulan'], $this->namaBulan($month));
        }

        return $query;
    }

    /**
     * Satu baris dalam bentuk siap tampil.
     *
     * @return array<string, mixed>
     */
    private function present(object $row, float $skala): array
    {
        $persen = $row->persen === null ? null : round((float) $row->persen * $skala, 2);
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
     * Pengali agar nilainya menjadi persen.
     *
     * Kolom sumbernya bertipe double tanpa satuan yang pasti. Perintah
     * ohs:isi-leadtime-alert menulis persen (0-100), tetapi hasil scrape
     * Tableau untuk parameter sejenis menulis pecahan (0-1). Diperiksa dari
     * nilai tertinggi seluruh tabel, bukan per baris, supaya satu baris
     * bernilai 1% tidak salah dikira pecahan.
     */
    private function skalaPersen(): float
    {
        static $skala = null;

        if ($skala !== null) {
            return $skala;
        }

        $max = DB::table(self::TABLE)->max($this->kolom()['persen']);

        return $skala = ($max !== null && (float) $max <= 1.0) ? 100.0 : 1.0;
    }

    /**
     * Nama kolom menurut skema tabelnya sendiri.
     *
     * Tabel ini sudah dua kali diganti bentuknya, jadi namanya tidak ditulis
     * mati. Hasilnya di-cache per permintaan karena dipakai berkali-kali.
     *
     * @return array<string, string>
     */
    private function kolom(): array
    {
        static $peta = null;

        if ($peta !== null) {
            return $peta;
        }

        $ada = [];

        foreach (Schema::getColumnListing(self::TABLE) as $column) {
            $ada[mb_strtolower($column)] = $column;
        }

        $out = [];

        foreach (self::KOLOM_CALON as $peran => $calon) {
            $out[$peran] = $calon[0];

            foreach ($calon as $nama) {
                if (isset($ada[mb_strtolower($nama)])) {
                    $out[$peran] = $ada[mb_strtolower($nama)];
                    continue 2;
                }
            }

            // Hanya kolom persentase yang punya cadangan berbasis awalan;
            // peran lain memang harus cocok persis.
            if ($peran === 'persen') {
                foreach ($ada as $kecil => $asli) {
                    if (str_starts_with($kecil, self::PREFIKS_PERSEN)) {
                        $out[$peran] = $asli;
                        break;
                    }
                }
            }
        }

        return $peta = $out;
    }

    /**
     * Nomor bulan dari dua bentuk penulisan yang pernah dipakai tabel ini:
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
            $query->whereNotIn($this->kolom()['bulan'], $this->namaBulan($nomor));
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

        foreach ($this->distinctValues($this->kolom()['bulan']) as $nilai) {
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
        foreach (self::SCORE_BANDS as $band) {
            if ($percent >= $band[0]) {
                return $band;
            }
        }

        return [0.0, 1, '<80%'];
    }
}
