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
 * Parameter SOD "Blindspot GR".
 *
 *   lead_blindspot_gr_month  -> persentase resmi per site x perusahaan x bulan
 *   detail_lead_blindspot_gr -> rincian tiap temuan
 *
 * Blindspot = pelanggaran Golden Rules di area sebuah perusahaan yang justru
 * dilaporkan pihak lain, bukan oleh pengawas perusahaan itu sendiri. Makin
 * tinggi angkanya, makin banyak yang luput dari pengawasan si PIC.
 *
 * DUA UKURAN YANG BERBEDA, DAN SENGAJA TIDAK DICAMPUR, sama seperti halaman
 * Blindspot TBC: tabel bulanan memberi persentase, tabel detail memberi cacah
 * temuan beserta PIC, pelapor, dan deskripsinya.
 *
 * KOLOM is_blindspot MENENTUKAN. Tabel detail memuat 28 baris ber-is_blindspot
 * "True" dan 5 baris yang kolomnya kosong; yang kosong itu temuan yang ternyata
 * bukan blindspot (pct-nya pun 0,00 sementara yang True 100,00). Tanpa
 * disaring, cacah temuan melambung 18% dari yang sebenarnya. Penyaringannya
 * memakai daftar nilai yang dianggap "ya" (lihat NILAI_BLINDSPOT) karena
 * kolomnya bertipe teks di tabel minecon tetapi bigint di tabel subcon, jadi
 * isinya bisa "True" maupun 1.
 *
 * SKALA ANGKA. blindspot_gr di tabel bulanan berupa pecahan 0-1, bukan persen,
 * dan hanya 23 dari 168 barisnya terisi. Skalanya dideteksi dari nilai
 * tertinggi seluruh tabel, lihat skalaPersen().
 *
 * SUBCON BELUM IKUT. lead_subcont_blindspot_gr ada (61 baris, tanpa dimensi
 * perusahaan) dan detail_lead_subcont_blindspot_gr masih kosong. Begitu
 * keduanya siap, halaman ini bisa diperluas seperti Blindspot TBC.
 */
final class BlindspotGrController extends Controller
{
    use ServesDataTable;

    private const TABEL_BULANAN = 'lead_blindspot_gr_month';
    private const TABEL_DETAIL = 'detail_lead_blindspot_gr';

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
    private const COL_IS_BLINDSPOT = 'is_blindspot';

    /** Kolom tabel bulanan; calon pertama yang cocok dengan skema dipakai. */
    private const KOLOM_BULANAN = [
        'site' => ['site'],
        'mitra' => ['perusahaan_pic'],
        'bulan' => ['month_of_date_for_join', 'Month_of_Date_for_Join'],
        'persen' => ['blindspot_gr', 'Blindspot_GR', 'pct_blindspot_gr'],
    ];

    /** Nilai is_blindspot yang berarti "ya". Lihat catatan di docblock kelas. */
    private const NILAI_BLINDSPOT = ['True', 'true', 'TRUE', '1', 'Y', 'Ya'];

    private const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /** Oktober masih berjalan saat data ini diambil, sejalan halaman lain. */
    private const EXCLUDED_MONTHS = ['October'];

    /**
     * Berapa persen blindspot sudah pantas disebut tinggi.
     *
     * TEBAKAN, sama seperti di halaman Blindspot TBC; ubah begitu ambang yang
     * sebenarnya diketahui.
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
        $mon = $this->kolomBulanan();

        return view('ohs-score-card.blindspot-gr.index', [
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
            ->get();

        $grid = [];
        $monthSeen = [];

        foreach ($rows as $row) {
            $monthNo = self::MONTH_MAP[$row->bulan][0] ?? 0;

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
            'bukan_blindspot' => $this->cacahBukanBlindspot($request),
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

        $bukan = $this->cacahBukanBlindspot($request);

        if ($bukan > 0) {
            $bagian[] = $bukan . ' baris di ' . self::TABEL_DETAIL . ' tidak bertanda '
                . self::COL_IS_BLINDSPOT . ' dan dikeluarkan dari semua hitungan temuan';
        }

        if (! DB::table(self::TABEL_DETAIL)->exists()) {
            $bagian[] = 'Tabel ' . self::TABEL_DETAIL . ' masih kosong';
        }

        return $bagian === [] ? null : implode('. ', $bagian) . '.';
    }

    private function cacahBukanBlindspot(Request $request): int
    {
        if (! Schema::hasColumn(self::TABEL_DETAIL, self::COL_IS_BLINDSPOT)) {
            return 0;
        }

        return $this->detailFilterQuery($request, null, false)
            ->where(function (Builder $q): void {
                $q->whereNull(self::COL_IS_BLINDSPOT)
                    ->orWhereNotIn(self::COL_IS_BLINDSPOT, self::NILAI_BLINDSPOT);
            })
            ->count();
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
            'blindspot-gr'
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
     * @param  bool  $hanyaBlindspot  false dipakai saat justru ingin menghitung
     *                                baris yang disaring
     */
    private function detailFilterQuery(Request $request, ?array $map = null, bool $hanyaBlindspot = true): Builder
    {
        $query = DB::table(self::TABEL_DETAIL);

        if ($hanyaBlindspot && Schema::hasColumn(self::TABEL_DETAIL, self::COL_IS_BLINDSPOT)) {
            $query->whereIn(self::COL_IS_BLINDSPOT, self::NILAI_BLINDSPOT);
        }

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
     * blindspot_gr tersimpan sebagai pecahan 0-1. Diperiksa dari nilai
     * tertinggi seluruh tabel, bukan per baris, supaya satu baris bernilai 1%
     * tidak salah dikira pecahan.
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
