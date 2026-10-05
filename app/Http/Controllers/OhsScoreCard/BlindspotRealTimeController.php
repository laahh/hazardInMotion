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
 * Parameter SOD "% Blindspot temuan Real Time".
 *
 *   lead_blindspot_laporan_real_time        -> persentase per site x perusahaan x bulan
 *   detail_lead_blindspot_laporan_real_time -> rincian tiap temuan
 *
 * Blindspot temuan real time = pelanggaran yang tertangkap alat pengawasan
 * (DMS, drone, CCTV, dan seterusnya) di area sebuah perusahaan, bukan oleh
 * pengawas perusahaan itu sendiri. Makin tinggi angkanya, makin banyak yang
 * luput dari pengawasan si PIC.
 *
 * BULAN DITULIS DUA CARA YANG BERBEDA ANTAR TABEL: tabel bulanan memakai
 * M01-M10, tabel detail memakai nama bulan Inggris. nomorBulan() mengenali
 * keduanya, dan namaBulan() menghasilkan semua ejaan satu bulan untuk dipakai
 * di whereIn, sehingga filter bulan bekerja di kedua tabel sekaligus.
 *
 * ANGKANYA PERSEN, BUKAN PECAHAN, meski nilainya kecil: rentangnya 0,00-16,67
 * dengan 126 dari 170 baris tepat 0. Deteksi skala tetap dipasang dan di data
 * sekarang menghasilkan pengali 1.
 *
 * TIDAK ADA KOLOM is_blindspot di sini, berbeda dengan Blindspot GR, jadi
 * tidak ada baris yang perlu disaring; seluruh 129 baris detail ber-pct 100,00.
 *
 * ADA DIMENSI TAMBAHAN tools_pengawasan (Post Event - DMS, Drone, CCTV
 * Support, Mining Eyes, CCTV Portable, BeGesit). Itu menjawab "ketahuan lewat
 * alat apa", dan mendapat panelnya sendiri.
 */
final class BlindspotRealTimeController extends Controller
{
    use ServesDataTable;

    private const TABEL_BULANAN = 'lead_blindspot_laporan_real_time';
    private const TABEL_DETAIL = 'detail_lead_blindspot_laporan_real_time';

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
    private const COL_TOOLS = 'tools_pengawasan';

    /** Kolom tabel bulanan; calon pertama yang cocok dengan skema dipakai. */
    private const KOLOM_BULANAN = [
        'site' => ['site'],
        'mitra' => ['perusahaan_pic'],
        'bulan' => ['month_of_date_for_join', 'Month_of_Date_for_Join'],
        'persen' => ['pct_blindspot_temuan_real_time', 'Blindspot_temuan_Real_Time'],
    ];

    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /** Oktober masih berjalan saat data ini diambil, sejalan halaman lain. */
    private const EXCLUDED_MONTHS = [10];

    /**
     * Berapa persen blindspot sudah pantas disebut tinggi.
     *
     * TEBAKAN. Sebarannya jauh lebih rendah daripada Blindspot TBC/GR -- 126
     * dari 170 baris tepat 0 dan tertingginya 16,67 -- jadi ambang 5% di sana
     * terlalu longgar di sini. Dipasang 1% supaya yang menonjol tetap
     * kelihatan; ubah begitu ambang resminya diketahui.
     */
    private const AMBANG_PERSEN = 1.0;

    /**
     * Batas baris yang dikirim ke modal rincian. Sel terpadat berisi 20
     * temuan, jadi batas ini jauh dari terpakai; dipasang supaya sumber
     * yang membengkak tidak diam-diam mengirim ribuan baris ke browser.
     */
    private const BATAS_BARIS_MODAL = 500;

    private const DETAIL_FILTERABLE = [
        'site' => self::COL_SITE,
        'mitra' => self::COL_PIC_PERUSAHAAN,
        'pelapor' => self::COL_PELAPOR_PERUSAHAAN,
    ];

    private const DETAIL_SEARCHABLE = [
        self::COL_SITE, self::COL_PIC_PERUSAHAAN, self::COL_PIC_SID, self::COL_PIC_NAMA,
        self::COL_PELAPOR_PERUSAHAAN, self::COL_PELAPOR_NAMA, self::COL_DESKRIPSI,
        self::COL_TOOLS,
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
        $mon = $this->kolomBulanan();

        return view('ohs-score-card.blindspot-real-time.index', [
            'filterOptions' => [
                'site' => $this->gabungNilai([
                    [self::TABEL_BULANAN, $mon['site']],
                    [self::TABEL_DETAIL, self::COL_SITE],
                ]),
                'mitra' => $this->gabungNilai([
                    [self::TABEL_BULANAN, $mon['mitra']],
                    [self::TABEL_DETAIL, self::COL_PIC_PERUSAHAAN],
                ]),
                'pelapor' => $this->distinctValues(self::TABEL_DETAIL, self::COL_PELAPOR_PERUSAHAAN),
            ],
            'monthOptions' => $this->monthOptions(),
            'ambang' => self::AMBANG_PERSEN,
            'tabel_bulanan' => self::TABEL_BULANAN,
            'tabel_detail' => self::TABEL_DETAIL,
        ]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request): JsonResponse
    {
        $persen = $this->buildPersen($request);
        $temuan = $this->buildTemuan($request);

        // Panel pendamping mengikuti sumber yang ada isinya, sama seperti di
        // halaman Blindspot TBC.
        $ukuran = $persen['tersedia'] ? 'persen' : 'temuan';
        $sumber = $ukuran === 'persen' ? $persen : $temuan;

        return response()->json([
            'ukuran' => $ukuran,
            'persen' => $persen,
            'temuan' => $temuan,
            'kpi' => $this->buildKpi($request, $persen, $temuan, $ukuran),
            'per_site' => $this->ringkasPer($sumber['rows'], 'site', $ukuran),
            'per_mitra' => array_slice($this->ringkasPer($sumber['rows'], 'mitra', $ukuran), 0, 12),
            'per_pic' => $this->buildPerPic($request),
            'per_pelapor' => $this->buildPerPelapor($request),
            'per_tools' => $this->buildPerTools($request),
            'monthly' => $this->buildMonthlySeries($sumber, $ukuran),
            'catatan' => $this->catatan($request),
        ]);
    }

    /**
     * Ukuran utama: persentase blindspot per site x perusahaan x bulan.
     *
     * @return array<string, mixed>
     */
    private function buildPersen(Request $request): array
    {
        $mon = $this->kolomBulanan();
        $skala = $this->skalaPersen();

        $query = DB::table(self::TABEL_BULANAN);

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn($mon['bulan'], $this->namaBulan($nomor));
        }

        foreach (['site' => $mon['site'], 'mitra' => $mon['mitra']] as $parameter => $column) {
            $value = trim((string) $request->input($parameter, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $month = (int) $request->input('month', 0);

        if ($month >= 1 && $month <= 12) {
            $query->whereIn($mon['bulan'], $this->namaBulan($month));
        }

        $rows = $query
            ->selectRaw(
                $mon['site'] . ' AS site, '
                . $mon['mitra'] . ' AS mitra, '
                . $mon['bulan'] . ' AS bulan, '
                . 'AVG(' . $mon['persen'] . ') AS persen'
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

            $site = trim((string) $row->site);
            $mitra = trim((string) $row->mitra);

            // Bulan yang seluruh nilainya NULL tidak dianggap tercakup: di
            // tabel ini 145 dari 168 baris memang masih kosong, dan menjadikan
            // semuanya kolom bulan hanya menghasilkan matriks penuh strip.
            if ($row->persen === null) {
                continue;
            }

            $monthSeen[$monthNo] = true;
            $grid[$site . '|' . $mitra]['site'] = $site;
            $grid[$site . '|' . $mitra]['mitra'] = $mitra;
            $grid[$site . '|' . $mitra]['bulan'][$monthNo] = round((float) $row->persen * $skala, 2);
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

        return [
            'tersedia' => $out !== [],
            'tabel' => self::TABEL_BULANAN,
            'months' => $this->monthHeadings($months),
            'rows' => $this->kelompokkanPerSite($out, static fn (array $r): float => $r['average'] ?? -1.0),
        ];
    }

    /**
     * Ukuran pendamping: cacah temuan dari tabel detail.
     *
     * @return array<string, mixed>
     */
    private function buildTemuan(Request $request): array
    {
        $rows = $this->detailFilterQuery($request)
            ->selectRaw(
                self::COL_SITE . ' AS site, '
                . self::COL_PIC_PERUSAHAAN . ' AS mitra, '
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

        return [
            'tersedia' => $out !== [],
            'tabel' => self::TABEL_DETAIL,
            'months' => $this->monthHeadings($months),
            'rows' => $this->kelompokkanPerSite($out, static fn (array $r): float => (float) $r['total']),
        ];
    }

    /**
     * Mengelompokkan baris per site supaya sel site-nya bisa digabung dengan
     * rowspan di tabel.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): float  $nilai
     * @return array<int, array<string, mixed>>
     */
    private function kelompokkanPerSite(array $rows, callable $nilai): array
    {
        $perSite = [];

        foreach ($rows as $row) {
            $perSite[$row['site']][] = $row;
        }

        // Bobot sebuah site = nilai tertingginya, bukan rata-ratanya: satu
        // pasangan yang parah tidak boleh tersamarkan oleh pasangan lain yang
        // bersih di site yang sama.
        $bobot = [];

        foreach ($perSite as $site => $baris) {
            $bobot[$site] = max(array_map($nilai, $baris));
        }

        arsort($bobot);
        $out = [];

        foreach (array_keys($bobot) as $site) {
            $baris = $perSite[$site];
            usort($baris, static fn (array $a, array $b): int => $nilai($b) <=> $nilai($a));
            $out = array_merge($out, $baris);
        }

        return $out;
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
    private function buildKpi(Request $request, array $persen, array $temuan, string $ukuran): array
    {
        $nilai = array_values(array_filter(
            array_column($persen['rows'], 'average'),
            static fn (?float $v): bool => $v !== null
        ));

        $detail = $this->detailFilterQuery($request)
            ->selectRaw(
                'COUNT(*) AS temuan, '
                . 'COUNT(DISTINCT ' . self::COL_PIC_SID . ') AS pic, '
                . 'COUNT(DISTINCT ' . self::COL_PELAPOR_PERUSAHAAN . ') AS pelapor'
            )
            ->first();

        $sumber = $ukuran === 'persen' ? $persen : $temuan;

        return [
            'ukuran' => $ukuran,
            'rata_persen' => $nilai !== [] ? round(array_sum($nilai) / count($nilai), 2) : null,
            'puncak_persen' => $nilai !== [] ? max($nilai) : null,
            'kombinasi' => count($persen['rows']),
            'di_atas_ambang' => count(array_filter(
                $persen['rows'],
                static fn (array $r): bool => $r['di_atas_ambang']
            )),
            'ambang' => self::AMBANG_PERSEN,
            'site_count' => count(array_unique(array_column($sumber['rows'], 'site'))),
            'mitra_count' => count(array_unique(array_column($sumber['rows'], 'mitra'))),
            'bulan_count' => count($sumber['months']),
            'temuan' => (int) ($detail->temuan ?? 0),
            'pic_count' => (int) ($detail->pic ?? 0),
            'pelapor_count' => (int) ($detail->pelapor ?? 0),
            'temuan_kombinasi' => count($temuan['rows']),
            'temuan_mitra' => count(array_unique(array_column($temuan['rows'], 'mitra'))),
            'tools_count' => count($this->buildPerTools($request)),
        ];
    }

    /**
     * Ringkasan per site atau per perusahaan.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function ringkasPer(array $rows, string $key, string $ukuran): array
    {
        $kelompok = [];

        foreach ($rows as $row) {
            $nilai = $ukuran === 'persen' ? $row['average'] : $row['total'];

            if ($nilai === null) {
                continue;
            }

            $kelompok[$row[$key]][] = (float) $nilai;
        }

        $out = [];

        foreach ($kelompok as $label => $nilai) {
            $angka = $ukuran === 'persen'
                ? round(array_sum($nilai) / count($nilai), 2)
                : array_sum($nilai);

            $out[] = [
                $key => (string) $label,
                'nilai' => $angka,
                'jumlah' => count($nilai),
                'puncak' => max($nilai),
                'di_atas_ambang' => $ukuran === 'persen' && $angka > self::AMBANG_PERSEN,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['nilai'] <=> $a['nilai']);

        return $out;
    }

    /**
     * PIC dengan temuan terbanyak.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPerPic(Request $request): array
    {
        $rows = $this->detailFilterQuery($request)
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
     * Dari mana temuannya datang.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPerPelapor(Request $request): array
    {
        $rows = $this->detailFilterQuery($request)
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(" . self::COL_PELAPOR_PERUSAHAAN . "), ''), '(Tanpa Nama)') AS label, "
                . 'COUNT(*) AS jumlah'
            )
            ->groupBy('label')
            ->orderByDesc('jumlah')
            ->get();

        return $rows->map(static fn (object $r): array => [
            'label' => (string) $r->label,
            'jumlah' => (int) $r->jumlah,
        ])->all();
    }

    /**
     * Lewat alat apa temuannya tertangkap.
     *
     * Dimensi ini tidak ada di parameter Blindspot TBC maupun GR; di sini ada
     * karena temuan real time memang datang dari alat pengawasan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPerTools(Request $request): array
    {
        $rows = $this->detailFilterQuery($request)
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(" . self::COL_TOOLS . "), ''), '(Tanpa Alat)') AS label, "
                . 'COUNT(*) AS jumlah'
            )
            ->groupBy('label')
            ->orderByDesc('jumlah')
            ->get();

        return $rows->map(static fn (object $r): array => [
            'label' => (string) $r->label,
            'jumlah' => (int) $r->jumlah,
        ])->all();
    }

    /**
     * Satu garis per perusahaan.
     *
     * @param  array<string, mixed>  $sumber
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $sumber, string $ukuran): array
    {
        $months = array_column($sumber['months'], 'number');
        $perMitra = [];

        foreach ($sumber['rows'] as $row) {
            foreach ($row['cells'] as $i => $value) {
                if ($value === null) {
                    continue;
                }

                $perMitra[$row['mitra']][$i][] = $value;
            }
        }

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
                if ($nilai === null) {
                    $data[] = null;
                    continue;
                }

                $data[] = $ukuran === 'persen'
                    ? round(array_sum($nilai) / count($nilai), 2)
                    : array_sum($nilai);
            }

            $series[] = ['name' => (string) $mitra, 'data' => $data];
        }

        return [
            'labels' => array_map(static fn (int $m): string => self::monthLabel($m), $months),
            'series' => $series,
        ];
    }

    /** Keterangan tentang sumber yang belum terisi atau baris yang disaring. */
    private function catatan(Request $request): ?string
    {
        $bagian = [];

        $terisi = DB::table(self::TABEL_BULANAN)
            ->whereNotNull($this->kolomBulanan()['persen'])
            ->count();
        $totalBulanan = DB::table(self::TABEL_BULANAN)->count();

        if ($terisi === 0) {
            $bagian[] = 'Tabel ' . self::TABEL_BULANAN . ' belum berisi persentase sama sekali, '
                . 'jadi semua angka di tab ini dihitung dari cacah temuan';
        } elseif ($terisi < $totalBulanan) {
            $bagian[] = 'Hanya ' . $terisi . ' dari ' . $totalBulanan . ' baris di '
                . self::TABEL_BULANAN . ' yang berisi persentase, sisanya belum terisi dan '
                . 'tidak ikut dihitung';
        }

        if (! DB::table(self::TABEL_DETAIL)->exists()) {
            $bagian[] = 'Tabel ' . self::TABEL_DETAIL . ' masih kosong';
        }

        return $bagian === [] ? null : implode('. ', $bagian) . '.';
    }

    // ======================================================================
    // Tab Data (tabel detail)
    // ======================================================================

    public function data(Request $request): JsonResponse
    {
        $query = $this->detailQuery($request);

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
            'recordsTotal' => $this->detailBaseCount(),
            'recordsFiltered' => (clone $query)->count(),
            'data' => $rows,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->detailQuery($request)
            ->select($this->detailColumns())
            ->orderBy(self::COL_SITE)
            ->orderBy(self::COL_PIC_PERUSAHAAN)
            ->orderBy('id');

        return $this->dtExport(
            $request,
            $query,
            [
                'Site', 'Perusahaan PIC', 'SID PIC', 'Nama PIC', 'Perusahaan Pelapor',
                'Nama Pelapor', 'Tahun', 'Bulan', 'Alat Pengawasan', 'Nomor Task',
                'Deskripsi Temuan',
            ],
            function (object $row): array {
                $p = $this->presentDetail($row);

                return [
                    $p['site'], $p['mitra'], $p['sid_pic'], $p['pic'],
                    $p['pelapor_perusahaan'], $p['pelapor'], $p['tahun'], $p['bulan'],
                    $p['tools'], $p['task'], $p['deskripsi'],
                ];
            },
            'blindspot-real-time'
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
            self::COL_TOOLS . ' AS tools',
            self::COL_TASK . ' AS task',
            self::COL_DESKRIPSI . ' AS deskripsi',
        ];
    }

    private function detailQuery(Request $request): Builder
    {
        $query = $this->detailFilterQuery($request, self::DETAIL_FILTERABLE);

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
    private function detailFilterQuery(Request $request, ?array $map = null): Builder
    {
        $query = DB::table(self::TABEL_DETAIL);

        foreach (self::EXCLUDED_MONTHS as $nomor) {
            $query->whereNotIn(self::COL_BULAN, $this->namaBulan($nomor));
        }

        foreach ($map ?? ['site' => self::COL_SITE, 'mitra' => self::COL_PIC_PERUSAHAAN] as $parameter => $column) {
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
            'bulan' => $this->nomorBulan((string) $row->bulan_sumber) === 0
                ? $teks($row->bulan_sumber)
                : self::monthLabel($this->nomorBulan((string) $row->bulan_sumber)),
            'tools' => $teks($row->tools),
            'task' => (string) $row->task,
            'deskripsi' => $teks($row->deskripsi),
        ];
    }

    /**
     * Isi satu sel matriks bulanan, untuk modal rincian.
     *
     * Dipakai kedua matriks di tab Ringkasan -- Persentase dan Jumlah Temuan --
     * karena koordinat selnya sama: site x perusahaan PIC x bulan.
     *
     * TIDAK PERLU DEDUPE. Berbeda dari detail_lead_subcont_blindspot_tbc yang
     * memuat tiap temuan dua kali, tabel ini sudah diperiksa dan tidak punya
     * satu pun kunci (site, perusahaan, bulan, task) berulang: 129 baris untuk
     * 129 temuan di 44 sel, terpadat 20 temuan.
     *
     * TIDAK ADA KOLOM is_blindspot yang perlu disaring, berbeda dari Blindspot
     * GR; seluruh baris di tabel ini memang temuan blindspot.
     *
     * PENYEBUT PERSENTASE TIDAK ADA DI KEDUA TABEL. Persentase di matriks
     * adalah temuan blindspot dibagi SELURUH temuan di sel itu, dan pembagi itu
     * tidak tersimpan di sini maupun di tabel bulanan. Penyebut tersiratnya
     * (temuan / persen) berkisar 6 sampai 1.194 dan tidak bisa dipulihkan
     * dengan tepat karena persennya dibulatkan dua desimal, jadi modal ini
     * sengaja TIDAK menampilkan angka itu. Yang bisa dijamin: ringkasan dan
     * rincian sepakat penuh tentang SEL MANA yang punya blindspot -- nol sel
     * berpersen 0 yang ternyata ada temuannya, nol sel berpersen di atas nol
     * yang rinciannya kosong, dan nol sel rincian yang hilang dari ringkasan.
     *
     * YANG DITONJOLKAN ALAT PENGAWASANNYA. Itu yang membedakan parameter ini
     * dari Blindspot GR maupun TBC: temuan di sini tertangkap alat (Post Event
     * - DMS 56, Drone 35, CCTV Support 23, Mining Eyes 10, CCTV Portable 4,
     * BeGesit 1), bukan oleh pengawas. Pertanyaan pertama pembaca adalah
     * "ketahuan lewat alat apa", jadi panel itu didahulukan.
     */
    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));
        $bulan = (int) $request->input('month', 0);

        if ($site === '' || $bulan < 1 || $bulan > 12) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Site dan bulan wajib diisi untuk membuka rincian.',
            ]);
        }

        $dasar = function () use ($site, $mitra, $bulan): Builder {
            $query = DB::table(self::TABEL_DETAIL)
                ->where(self::COL_SITE, $site)
                ->whereIn(self::COL_BULAN, $this->namaBulan($bulan));

            if ($mitra !== '') {
                $query->where(self::COL_PIC_PERUSAHAAN, $mitra);
            }

            return $query;
        };

        $baris = $dasar()
            ->select($this->detailColumns())
            ->orderBy(self::COL_TOOLS)
            ->orderBy(self::COL_TASK)
            ->limit(self::BATAS_BARIS_MODAL + 1)
            ->get()
            ->map(fn (object $row): array => $this->presentDetail($row))
            ->all();

        $terpotong = count($baris) > self::BATAS_BARIS_MODAL;

        if ($terpotong) {
            $baris = array_slice($baris, 0, self::BATAS_BARIS_MODAL);
        }

        $cacah = $dasar()
            ->selectRaw(
                'COUNT(*) AS temuan, '
                . 'COUNT(DISTINCT ' . self::COL_PIC_SID . ') AS pic, '
                . 'COUNT(DISTINCT ' . self::COL_TOOLS . ') AS alat, '
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
                'alat' => (int) ($cacah->alat ?? 0),
                'perusahaan_pelapor' => (int) ($cacah->perusahaan_pelapor ?? 0),
            ],
            'per_alat' => $this->alatTerbanyak($dasar()),
            'per_pic' => $this->picTerbanyak($dasar()),
            'terpotong' => $terpotong,
            'batas' => self::BATAS_BARIS_MODAL,
            'baris' => $baris,
        ]);
    }

    /**
     * Alat yang menangkap temuan di sel ini, terbanyak di atas. Panel ini khas
     * parameter real time: di sini yang menangkap adalah alat, bukan orang.
     *
     * @return array<int, array<string, mixed>>
     */
    private function alatTerbanyak(Builder $query): array
    {
        return $query
            ->selectRaw(self::COL_TOOLS . ' AS alat, COUNT(*) AS n')
            ->groupBy('alat')
            ->orderByDesc('n')
            ->get()
            ->map(static fn (object $r): array => [
                'alat' => trim((string) $r->alat) ?: '-',
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
                self::COL_PIC_NAMA . ' AS pic, '
                . self::COL_PIC_SID . ' AS sid, COUNT(*) AS n'
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
     * Nama kolom tabel bulanan menurut skemanya sendiri.
     *
     * @return array<string, string>
     */
    private function kolomBulanan(): array
    {
        static $peta = null;

        if ($peta !== null) {
            return $peta;
        }

        $ada = [];

        foreach (Schema::getColumnListing(self::TABEL_BULANAN) as $column) {
            $ada[mb_strtolower($column)] = $column;
        }

        $out = [];

        foreach (self::KOLOM_BULANAN as $peran => $calon) {
            $out[$peran] = $calon[0];

            foreach ($calon as $nama) {
                if (isset($ada[mb_strtolower($nama)])) {
                    $out[$peran] = $ada[mb_strtolower($nama)];
                    break;
                }
            }
        }

        return $peta = $out;
    }

    /**
     * Pengali agar nilainya menjadi persen.
     *
     * pct_blindspot_temuan_real_time tersimpan sebagai persen, tetapi nilainya
     * kecil-kecil (0,00-16,67). Diperiksa dari nilai tertinggi seluruh tabel,
     * bukan per baris, supaya satu baris bernilai 1% tidak salah dikira
     * pecahan; di data sekarang hasilnya pengali 1.
     */
    private function skalaPersen(): float
    {
        static $skala = null;

        if ($skala !== null) {
            return $skala;
        }

        $max = DB::table(self::TABEL_BULANAN)->max($this->kolomBulanan()['persen']);

        return $skala = ($max !== null && (float) $max <= 1.0) ? 100.0 : 1.0;
    }

    private function detailBaseCount(): int
    {
        return $this->detailFilterQuery(new Request())->count();
    }

    /**
     * Nomor bulan dari dua bentuk penulisan yang dipakai kedua tabel sumber:
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
     * Tabel bulanan dan tabel detail menulis bulan dengan cara berbeda, jadi
     * satu daftar yang memuat keduanya membuat filter bulan bekerja di
     * keduanya tanpa perlu tahu tabel mana yang sedang ditanya.
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
     * @return array<int, string>
     */
    private function monthOptions(): array
    {
        $months = [];

        $sumber = [
            [self::TABEL_DETAIL, self::COL_BULAN],
            [self::TABEL_BULANAN, $this->kolomBulanan()['bulan']],
        ];

        foreach ($sumber as [$table, $column]) {
            foreach ($this->distinctValues($table, $column) as $nilai) {
                $nomor = $this->nomorBulan($nilai);

                if ($nomor === 0 || in_array($nomor, self::EXCLUDED_MONTHS, true)) {
                    continue;
                }

                $months[$nomor] = self::monthLabel($nomor);
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
