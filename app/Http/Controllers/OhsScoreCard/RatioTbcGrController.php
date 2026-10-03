<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Concerns\ServesDataTable;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Parameter SOD "Ratio TBC & GR".
 *
 * ===========================================================================
 * STATUS: RANGKA TAMPILAN. Tabel sumbernya BELUM ditentukan.
 * ===========================================================================
 *
 * Seluruh angka di halaman ini masih contoh, dibangkitkan di placeholderRows()
 * dan diberi label "Data contoh" di layar supaya tidak ada yang mengira ini
 * capaian sebenarnya.
 *
 * Bentuk payload sengaja dibuat identik dengan RoadSummaryController::overview()
 * agar tampilannya bisa memakai kerangka yang sama persis. Saat tabel sumber
 * sudah dipilih, yang perlu diganti hanya placeholderRows(): kembalikan baris
 * dengan kolom site, mitra, year, week, total, memenuhi, lalu seluruh rekap,
 * grafik, dan tabel ikut benar dengan sendirinya.
 */
final class RatioTbcGrController extends Controller
{
    use ServesDataTable;

    /** Penanda agar tidak ada angka contoh yang lolos tanpa keterangan. */
    private const IS_PLACEHOLDER = true;

    private const TARGET_PERCENT = 90.0;

    private const MONTH_LABELS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Konversi persentase pemenuhan menjadi Nilai 1-4, memakai ambang yang
     * sama dengan parameter lain di modul ini.
     */
    private const SCORE_BANDS = [
        [98.0, 4, '98% - 100%'],
        [90.0, 3, '90% - <98%'],
        [80.0, 2, '80% - <90%'],
        [0.0,  1, '<80%'],
    ];

    /** Dimensi contoh; ikut daftar site & mitra yang dipakai parameter lain. */
    private const SITES = ['BMO 1', 'BMO 2', 'BMO 3', 'GMO', 'LMO', 'SMO'];
    private const MITRA = ['BUMA', 'FAD', 'KDC', 'MTL', 'MTN', 'PAMA'];
    private const AREAS = ['Hauling', 'Pit Utara', 'Pit Selatan', 'Disposal', 'Workshop', 'Jetty'];

    public function index(): View
    {
        return view('ohs-score-card.ratio-tbc-gr.index', [
            'isPlaceholder' => self::IS_PLACEHOLDER,
            // Opsi diambil dari baris yang benar-benar ada, bukan dari
            // konstanta: dropdown tidak boleh menawarkan nilai yang hasilnya
            // nol baris.
            'filterOptions' => [
                'site' => $this->distinctValues('site'),
                'mitra' => $this->distinctValues('mitra'),
                'area' => $this->distinctValues('area'),
            ],
            'monthOptions' => $this->monthOptions(),
        ]);
    }

    /** Payload tab Ringkasan, bentuknya sama dengan parameter Jalan Sesuai Standar. */
    public function overview(Request $request): JsonResponse
    {
        $rows = $this->filteredPlaceholderRows($request);

        $matrix = [];
        $weekBuckets = [];
        $monthSeen = [];
        $weekSeen = [];
        $perusahaan = [];

        foreach ($rows as $row) {
            $month = $this->monthOfIsoWeek($row['year'], $row['week']);
            $key = $row['site'] . '|' . $row['mitra'];

            if ($month > 0) {
                $monthSeen[$month] = true;
                $matrix[$key]['site'] = $row['site'];
                $matrix[$key]['mitra'] = $row['mitra'];
                $matrix[$key]['bulan'][$month]['total'] = ($matrix[$key]['bulan'][$month]['total'] ?? 0) + $row['total'];
                $matrix[$key]['bulan'][$month]['standar'] = ($matrix[$key]['bulan'][$month]['standar'] ?? 0) + $row['memenuhi'];
            }

            $weekKey = sprintf('%04d-%02d', $row['year'], $row['week']);
            $weekSeen[$weekKey] = true;
            $weekBuckets[$row['mitra']][$weekKey]['total'] = ($weekBuckets[$row['mitra']][$weekKey]['total'] ?? 0) + $row['total'];
            $weekBuckets[$row['mitra']][$weekKey]['standar'] = ($weekBuckets[$row['mitra']][$weekKey]['standar'] ?? 0) + $row['memenuhi'];

            $perusahaan[$row['mitra']]['total'] = ($perusahaan[$row['mitra']]['total'] ?? 0) + $row['total'];
            $perusahaan[$row['mitra']]['standar'] = ($perusahaan[$row['mitra']]['standar'] ?? 0) + $row['memenuhi'];
        }

        ksort($monthSeen);
        ksort($weekSeen);
        ksort($matrix);
        $months = array_keys($monthSeen);
        $weeks = array_keys($weekSeen);

        $matrixRows = $this->buildMatrix($matrix, $months);

        return response()->json([
            'placeholder' => self::IS_PLACEHOLDER,
            'months' => array_map(fn (int $m): array => [
                'number' => $m,
                'label' => mb_strtoupper(mb_substr(self::MONTH_LABELS[$m] ?? '-', 0, 3)),
            ], $months),
            'kpi' => $this->buildKpi($matrixRows, count($matrix)),
            'matrix' => $matrixRows,
            'perusahaan' => $this->buildPerusahaan($perusahaan),
            'site_vs_target' => $this->buildSiteVsTarget($matrixRows),
            'top_terendah' => $this->buildTopTerendah($matrixRows),
            'pareto' => $this->buildPareto($rows),
            'per_area' => $this->buildPerArea($rows),
            'monthly' => $this->buildMonthlySeries($matrix, $months),
            'weekly' => $this->buildWeeklySeries($weekBuckets, $weeks),
        ]);
    }

    /** Tabel rinci pada tab Data. */
    public function data(Request $request): JsonResponse
    {
        $rows = $this->filteredPlaceholderRows($request);
        $search = trim((string) $request->input('search.value', $request->input('search', '')));

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, static function (array $r) use ($needle): bool {
                return str_contains(mb_strtolower($r['site'] . ' ' . $r['mitra'] . ' ' . $r['area']), $needle);
            }));
        }

        $total = count($rows);
        $length = $this->dtPageLength($request);
        $page = $this->dtPage($request);

        $paged = array_slice($rows, ($page - 1) * $length, $length);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => count($this->placeholderRows()),
            'recordsFiltered' => $total,
            'data' => array_map(fn (array $r): array => $this->presentRow($r), $paged),
            'placeholder' => self::IS_PLACEHOLDER,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filteredPlaceholderRows($request);

        // dtExport() bekerja atas query builder; di sini sumbernya masih array,
        // jadi ekspornya ditulis langsung dengan format yang sama (CSV ber-BOM).
        $filename = 'ratio-tbc-gr-CONTOH-' . now()->format('Ymd-His') . '.csv';

        return response()->stream(
            function () use ($rows): void {
                $out = fopen('php://output', 'wb');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['CATATAN', 'Berkas ini berisi DATA CONTOH, bukan capaian sebenarnya.'], ';');
                fputcsv($out, $this->exportHeaders(), ';');

                foreach ($rows as $row) {
                    $p = $this->presentRow($row);
                    fputcsv($out, [
                        $p['site'], $p['mitra'], $p['area'], $p['year'], $p['week'], $p['bulan'],
                        $p['total'], $p['tbc'], $p['gr'], $p['memenuhi'], $p['persen'], $p['nilai'],
                    ], ';');
                }

                fclose($out);
            },
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store, no-cache',
            ]
        );
    }

    /** @return array<int, string> */
    private function exportHeaders(): array
    {
        return [
            'Site', 'Perusahaan', 'Area', 'Tahun', 'Minggu', 'Bulan',
            'Total Wajib Lapor', 'TBC', 'GR', 'Terpenuhi', 'Persentase', 'Nilai',
        ];
    }

    /**
     * Satu baris mentah menjadi satu baris tampilan.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function presentRow(array $row): array
    {
        $pct = $row['total'] > 0 ? round($row['memenuhi'] / $row['total'] * 100, 2) : 0.0;
        [, $nilai] = $this->scoreBandFor($pct);
        $month = $this->monthOfIsoWeek($row['year'], $row['week']);

        return [
            'site' => $row['site'],
            'mitra' => $row['mitra'],
            'area' => $row['area'],
            'year' => $row['year'],
            'week' => $row['week'],
            'bulan' => self::MONTH_LABELS[$month] ?? '-',
            'total' => $row['total'],
            'tbc' => $row['tbc'],
            'gr' => $row['gr'],
            'memenuhi' => $row['memenuhi'],
            'persen' => $pct,
            'nilai' => $nilai,
        ];
    }

    // ======================================================================
    // Sumber data contoh. GANTI SELURUH BAGIAN INI saat tabel asli dipilih.
    // ======================================================================

    /**
     * Baris contoh yang deterministik: dibangkitkan dari crc32 nama dimensi,
     * jadi nilainya tetap sama tiap kali halaman dibuka (bukan acak).
     *
     * @return array<int, array<string, mixed>>
     */
    private function placeholderRows(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $rows = [];
        $year = (int) now()->format('Y');
        $lastWeek = min(39, (int) now()->format('W'));

        // Pasangan site-mitra yang dipakai ditentukan lebih dulu, baru area
        // dibagikan bergilir ke pasangan yang lolos.
        //
        // Sebelumnya area dipilih dengan rumus yang sama-sama kelipatan 3
        // dengan aturan pelewatan, sehingga satu area tidak pernah kebagian
        // pasangan sama sekali: dropdown menawarkannya, tapi hasilnya nol.
        $pairs = [];

        foreach (self::SITES as $siteIndex => $site) {
            foreach (self::MITRA as $mitraIndex => $mitra) {
                // Tidak semua mitra bekerja di semua site; sebagian pasangan
                // sengaja dikosongkan agar tampilannya realistis.
                if ((($siteIndex * 7) + $mitraIndex) % 3 === 0) {
                    continue;
                }

                $pairs[] = [$site, $mitra];
            }
        }

        foreach ($pairs as $pairIndex => [$site, $mitra]) {
            $area = self::AREAS[$pairIndex % count(self::AREAS)];

            for ($week = 1; $week <= $lastWeek; $week++) {
                $hash = crc32($site . '|' . $mitra . '|' . $week);
                $total = 40 + ($hash % 60);
                $share = match (true) {
                    $hash % 100 < 45 => 0.92 + ($hash >> 5) % 8 / 100,
                    $hash % 100 < 75 => 0.82 + ($hash >> 5) % 10 / 100,
                    $hash % 100 < 92 => 0.70 + ($hash >> 5) % 12 / 100,
                    default          => 0.45 + ($hash >> 5) % 25 / 100,
                };

                $memenuhi = (int) round($total * min($share, 1.0));
                $tbc = (int) round($memenuhi * (0.55 + ($hash >> 9) % 20 / 100));

                $rows[] = [
                    'site' => $site,
                    'mitra' => $mitra,
                    'area' => $area,
                    'year' => $year,
                    'week' => $week,
                    'total' => $total,
                    'memenuhi' => $memenuhi,
                    'tbc' => $tbc,
                    'gr' => $memenuhi - $tbc,
                ];
            }
        }

        return $cache = $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function filteredPlaceholderRows(Request $request): array
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));
        $area = trim((string) $request->input('area', ''));
        $month = (int) $request->input('month', 0);

        return array_values(array_filter(
            $this->placeholderRows(),
            function (array $row) use ($site, $mitra, $area, $month): bool {
                if ($site !== '' && $row['site'] !== $site) {
                    return false;
                }
                if ($mitra !== '' && $row['mitra'] !== $mitra) {
                    return false;
                }
                if ($area !== '' && $row['area'] !== $area) {
                    return false;
                }
                if ($month >= 1 && $month <= 12
                    && $this->monthOfIsoWeek($row['year'], $row['week']) !== $month) {
                    return false;
                }

                return true;
            }
        ));
    }

    // ======================================================================
    // Rekap. Bagian ini tetap dipakai apa adanya setelah data asli masuk.
    // ======================================================================

    /**
     * @param  array<string, mixed>  $matrix
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    private function buildMatrix(array $matrix, array $months): array
    {
        $out = [];

        foreach ($matrix as $entry) {
            $cells = [];
            $grandTotal = 0;
            $grandStandar = 0;
            $filled = [];

            foreach ($months as $month) {
                $total = $entry['bulan'][$month]['total'] ?? 0;

                if ($total === 0) {
                    $cells[] = null;
                    continue;
                }

                $standar = $entry['bulan'][$month]['standar'] ?? 0;
                $pct = round($standar / $total * 100, 2);
                [, $nilai, $band] = $this->scoreBandFor($pct);

                $cells[] = [
                    'pct' => $pct, 'total' => $total, 'standar' => $standar,
                    'nilai' => $nilai, 'nilai_band' => $band,
                ];
                $filled[] = $pct;
                $grandTotal += $total;
                $grandStandar += $standar;
            }

            $avg = $grandTotal > 0 ? round($grandStandar / $grandTotal * 100, 2) : 0.0;
            [, $nilai, $band] = $this->scoreBandFor($avg);

            $trend = null;
            if (count($filled) >= 2) {
                $trend = $filled[count($filled) - 1] >= $filled[count($filled) - 2] ? 'up' : 'down';
            }

            $out[] = [
                'site' => $entry['site'],
                'mitra' => $entry['mitra'],
                'cells' => $cells,
                'average' => $avg,
                'total' => $grandTotal,
                'tidak_sesuai' => $grandTotal - $grandStandar,
                'nilai' => $nilai,
                'nilai_band' => $band,
                'trend' => $trend,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @return array<string, mixed>
     */
    private function buildKpi(array $matrixRows, int $kombinasi): array
    {
        $total = array_sum(array_column($matrixRows, 'total'));
        $belum = array_sum(array_column($matrixRows, 'tidak_sesuai'));
        $terpenuhi = $total - $belum;
        $pct = $total > 0 ? round($terpenuhi / $total * 100, 2) : 0.0;
        [, $nilai, $band] = $this->scoreBandFor($pct);

        return [
            'total' => $total,
            'standar' => $terpenuhi,
            'tidak_sesuai' => $belum,
            'standar_pct' => $pct,
            'tidak_sesuai_pct' => $total > 0 ? round($belum / $total * 100, 2) : 0.0,
            'nilai' => $nilai,
            'nilai_band' => $band,
            'target' => self::TARGET_PERCENT,
            'memenuhi_target' => $pct >= self::TARGET_PERCENT,
            'site_count' => count(array_unique(array_column($matrixRows, 'site'))),
            'mitra_count' => count(array_unique(array_column($matrixRows, 'mitra'))),
            'kombinasi' => $kombinasi,
            'bulan_terakhir' => null,
        ];
    }

    /**
     * @param  array<string, array{total: int, standar: int}>  $perusahaan
     * @return array<int, array<string, mixed>>
     */
    private function buildPerusahaan(array $perusahaan): array
    {
        $out = [];

        foreach ($perusahaan as $mitra => $agg) {
            $pct = $agg['total'] > 0 ? round($agg['standar'] / $agg['total'] * 100, 2) : 0.0;
            [, $nilai, $band] = $this->scoreBandFor($pct);

            $out[] = [
                'mitra' => (string) $mitra,
                'total' => $agg['total'],
                'standar' => $agg['standar'],
                'percent' => $pct,
                'nilai' => $nilai,
                'nilai_band' => $band,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @return array<int, array<string, mixed>>
     */
    private function buildSiteVsTarget(array $matrixRows): array
    {
        $perSite = [];

        foreach ($matrixRows as $row) {
            $perSite[$row['site']]['total'] = ($perSite[$row['site']]['total'] ?? 0) + $row['total'];
            $perSite[$row['site']]['belum'] = ($perSite[$row['site']]['belum'] ?? 0) + $row['tidak_sesuai'];
        }

        ksort($perSite);
        $out = [];

        foreach ($perSite as $site => $agg) {
            $pct = $agg['total'] > 0 ? round(($agg['total'] - $agg['belum']) / $agg['total'] * 100, 2) : 0.0;
            [, $nilai] = $this->scoreBandFor($pct);

            $out[] = [
                'site' => (string) $site,
                'percent' => $pct,
                'target' => self::TARGET_PERCENT,
                'nilai' => $nilai,
                'total' => $agg['total'],
                'tidak_sesuai' => $agg['belum'],
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrixRows
     * @return array<int, array<string, mixed>>
     */
    private function buildTopTerendah(array $matrixRows): array
    {
        $rows = $matrixRows;
        usort($rows, static fn (array $a, array $b): int => $a['average'] <=> $b['average']);

        return array_map(static fn (array $r): array => [
            'site' => $r['site'], 'mitra' => $r['mitra'],
            'percent' => $r['average'], 'nilai' => $r['nilai'],
            'total' => $r['total'], 'tidak_sesuai' => $r['tidak_sesuai'],
        ], array_slice($rows, 0, 5));
    }

    /**
     * Komposisi laporan: TBC, GR, dan yang belum dilaporkan.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildPareto(array $rows): array
    {
        $tbc = array_sum(array_column($rows, 'tbc'));
        $gr = array_sum(array_column($rows, 'gr'));
        $belum = array_sum(array_column($rows, 'total')) - $tbc - $gr;

        $buckets = ['TBC' => $tbc, 'GR' => $gr, 'Belum Dilaporkan' => max(0, $belum)];
        arsort($buckets);

        $grand = array_sum($buckets);
        $kumulatif = 0;
        $out = [];

        foreach ($buckets as $label => $jumlah) {
            $kumulatif += $jumlah;
            $out[] = [
                'label' => $label,
                'jumlah' => $jumlah,
                'percent' => $grand > 0 ? round($jumlah / $grand * 100, 1) : 0.0,
                'kumulatif' => $grand > 0 ? round($kumulatif / $grand * 100, 1) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildPerArea(array $rows): array
    {
        $perArea = [];

        foreach ($rows as $row) {
            $perArea[$row['area']]['total'] = ($perArea[$row['area']]['total'] ?? 0) + $row['total'];
            $perArea[$row['area']]['belum'] = ($perArea[$row['area']]['belum'] ?? 0)
                + ($row['total'] - $row['memenuhi']);
        }

        $out = [];

        foreach ($perArea as $area => $agg) {
            $out[] = [
                'area' => (string) $area,
                'total' => $agg['total'],
                'tidak_sesuai' => $agg['belum'],
                'percent' => $agg['total'] > 0 ? round($agg['belum'] / $agg['total'] * 100, 2) : 0.0,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['tidak_sesuai'] <=> $a['tidak_sesuai']);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    private function buildMonthlySeries(array $matrix, array $months): array
    {
        $byMitra = [];

        foreach ($matrix as $entry) {
            foreach ($months as $month) {
                $byMitra[$entry['mitra']][$month]['total'] = ($byMitra[$entry['mitra']][$month]['total'] ?? 0)
                    + ($entry['bulan'][$month]['total'] ?? 0);
                $byMitra[$entry['mitra']][$month]['standar'] = ($byMitra[$entry['mitra']][$month]['standar'] ?? 0)
                    + ($entry['bulan'][$month]['standar'] ?? 0);
            }
        }

        ksort($byMitra);
        $series = [];

        foreach ($byMitra as $mitra => $perMonth) {
            $data = [];

            foreach ($months as $month) {
                $total = $perMonth[$month]['total'] ?? 0;
                $data[] = $total > 0 ? round($perMonth[$month]['standar'] / $total * 100, 2) : null;
            }

            $series[] = ['name' => (string) $mitra, 'data' => $data];
        }

        return [
            'labels' => array_map(fn (int $m): string => self::MONTH_LABELS[$m] ?? '-', $months),
            'series' => $series,
        ];
    }

    /**
     * @param  array<string, mixed>  $weekBuckets
     * @param  array<int, string>  $weeks
     * @return array<string, mixed>
     */
    private function buildWeeklySeries(array $weekBuckets, array $weeks): array
    {
        ksort($weekBuckets);
        $series = [];

        foreach ($weekBuckets as $mitra => $perWeek) {
            $data = [];

            foreach ($weeks as $week) {
                $total = $perWeek[$week]['total'] ?? 0;
                $data[] = $total > 0 ? round($perWeek[$week]['standar'] / $total * 100, 2) : null;
            }

            $series[] = ['name' => (string) $mitra, 'data' => $data];
        }

        return [
            'labels' => array_map(static fn (string $w): string => 'W' . (int) substr($w, 5), $weeks),
            'series' => $series,
        ];
    }

    // ======================================================================
    // Utilitas
    // ======================================================================

    /**
     * Nilai unik sebuah kolom pada baris yang ada, untuk mengisi dropdown.
     *
     * @return array<int, string>
     */
    private function distinctValues(string $column): array
    {
        $values = array_unique(array_column($this->placeholderRows(), $column));
        sort($values);

        return array_values($values);
    }

    /** @return array<int, string> */
    private function monthOptions(): array
    {
        $months = [];

        foreach ($this->placeholderRows() as $row) {
            $month = $this->monthOfIsoWeek($row['year'], $row['week']);

            if ($month > 0) {
                $months[$month] = self::MONTH_LABELS[$month];
            }
        }

        ksort($months);

        return $months;
    }

    /** Bulan pemilik minggu ISO: bulan tempat hari Kamis-nya jatuh. */
    private function monthOfIsoWeek(int $year, int $week): int
    {
        if ($week < 1 || $week > 53 || $year < 1970) {
            return 0;
        }

        return (int) (new \DateTimeImmutable())->setISODate($year, $week, 4)->format('n');
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
