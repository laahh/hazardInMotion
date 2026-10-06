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
 * Dasar bersama untuk tiga parameter bulanan berbasis persentase:
 *
 *   lead_sobriety_test                  -> Pelaksanaan Sobriety Test
 *   lead_replikasi_rekayasa_engineering -> Penuntasan pengendalian rekayasa
 *   lead_utilisasi_besigma              -> Utilisasi BeSigma
 *
 * Ketiganya berbentuk sama: satu baris per site, perusahaan, dan bulan.
 * Yang berbeda hanya nama kolom, cara persentasenya dihitung, dan band-nya --
 * ketiganya disediakan lewat metode abstrak di bawah.
 *
 * NILAI DISIMPAN SEBAGAI RASIO 0-1 di dua tabel pertama (0,966244726 berarti
 * 96,62%), jadi ekspresi persentasenya mengalikan 100. Jangan diperlakukan
 * sebagai persen mentah.
 *
 * BULAN TERSIMPAN SEBAGAI NAMA INGGRIS ("October"), bukan angka, jadi
 * mengurutkannya di SQL hanya menghasilkan urutan abjad yang menyesatkan.
 * Pemetaannya dilakukan di PHP lewat MONTH_MAP.
 */
abstract class AbstractLeadBulananController extends Controller
{
    use ServesDataTable;

    protected const MONTH_MAP = [
        'January' => [1, 'Januari'], 'February' => [2, 'Februari'], 'March' => [3, 'Maret'],
        'April' => [4, 'April'], 'May' => [5, 'Mei'], 'June' => [6, 'Juni'],
        'July' => [7, 'Juli'], 'August' => [8, 'Agustus'], 'September' => [9, 'September'],
        'October' => [10, 'Oktober'], 'November' => [11, 'November'], 'December' => [12, 'Desember'],
    ];

    /** Batas baris yang dikirim ke modal rincian sel. */
    protected const DETAIL_LIMIT = 200;

    abstract protected function tabel(): string;

    /** Judul halaman, mis. "Utilisasi BeSigma". */
    abstract protected function judul(): string;

    /** Awalan rute dan nama berkas ekspor. */
    abstract protected function slug(): string;

    /** Satu kalimat tentang apa yang diukur, tampil di bawah judul. */
    abstract protected function penjelasan(): string;

    /**
     * Nama kolom sumber.
     *
     * @return array{site: string, mitra: string, bulan: string, nilai: string}
     */
    abstract protected function kolom(): array;

    /**
     * Ekspresi SQL yang menghasilkan persentase untuk satu kelompok, beserta
     * pembilang dan penyebutnya untuk ditampilkan di modal.
     *
     * Harus menghasilkan kolom: persen, pembilang, penyebut.
     */
    abstract protected function ekspresiPersen(): string;

    /** Label pembilang dan penyebut untuk layar, mis. ['SID aktif', 'terdaftar']. */
    abstract protected function labelPecahan(): array;

    /**
     * Nilai berkoma untuk satu capaian, atau [null, null] kalau parameter itu
     * memang belum punya band resmi yang bisa dihitung.
     *
     * @return array{0: float|null, 1: string|null}
     */
    abstract protected function nilaiUntuk(float $persen): array;

    /** @return array<int, array{nilai: int, label: string}> */
    abstract protected function legendaBand(): array;

    /** Band tertinggi tercapai mulai persentase berapa. */
    abstract protected function target(): float;

    /**
     * Penyebut yang datangnya dari sumber LAIN, bukan dari query tabel lead.
     *
     * Dipakai ketika penyebutnya ada di database berbeda sehingga tidak bisa
     * di-JOIN -- misalnya Utilisasi BeSigma, yang pembilangnya di MySQL dan
     * penyebutnya di Postgres. Mengembalikan:
     *
     *   null  -> tidak ada penyebut luar; pakai apa adanya dari SQL
     *   0.0   -> penyebutnya memang tidak ketemu; sel itu tidak dinilai
     *   >0    -> penyebut yang dipakai membagi
     */
    protected function penyebutLuar(string $site, string $mitra): ?float
    {
        return null;
    }

    /**
     * Menerapkan penyebut luar ke satu baris hasil query.
     *
     * Site dan perusahaan boleh diberikan terpisah karena sebagian query --
     * misalnya riwayat di modal -- sudah menyaringnya dan tidak ikut
     * memilihnya sebagai kolom.
     */
    protected function terapkanPenyebut(object $row, ?string $site = null, ?string $mitra = null): object
    {
        $penyebut = $this->penyebutLuar(
            $site ?? trim((string) ($row->site ?? '')),
            $mitra ?? trim((string) ($row->mitra ?? ''))
        );

        if ($penyebut === null) {
            return $row;
        }

        $row->penyebut = $penyebut;
        $row->persen = $penyebut > 0.0
            ? round((float) $row->pembilang / $penyebut * 100, 2)
            : null;

        return $row;
    }

    /**
     * Satuan nilai yang ditampilkan: tanda persen untuk persentase, kosong
     * untuk cacah.
     *
     * Parameter yang satuannya bukan persen belum bisa dinilai dengan band
     * resmi -- band-nya berbasis persen -- jadi nilaiUntuk() mengembalikan
     * null dan selnya tampil tanpa angka Nilai, bukan diberi nilai karangan.
     */
    protected function satuan(): string
    {
        return '%';
    }

    // ======================================================================
    // Halaman
    // ======================================================================

    public function index(): View
    {
        $k = $this->kolom();

        return view('ohs-score-card.lead-bulanan.index', [
            'judul' => $this->judul(),
            'slug' => $this->slug(),
            'tabel' => $this->tabel(),
            'penjelasan' => $this->penjelasan(),
            'target' => $this->target(),
            'legenda' => $this->legendaBand(),
            'satuan' => $this->satuan(),
            'pecahan' => $this->labelPecahan(),
            'filterOptions' => [
                'site' => $this->distinctValues($k['site']),
                'mitra' => $this->distinctValues($k['mitra']),
            ],
            'monthOptions' => $this->monthOptions(),
        ]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request): JsonResponse
    {
        $k = $this->kolom();

        $rows = $this->baseQuery($request)
            ->selectRaw(
                $this->kutip($k['site']) . ' AS site, '
                . $this->kutip($k['mitra']) . ' AS mitra, '
                . $this->kutip($k['bulan']) . ' AS bulan, '
                . $this->ekspresiPersen()
            )
            ->groupBy('site', 'mitra', 'bulan')
            ->get();

        $grid = [];
        $bulanAda = [];

        foreach ($rows as $row) {
            $this->terapkanPenyebut($row);
            $nomor = $this->nomorBulan((string) $row->bulan);

            if ($nomor === 0) {
                continue; // nama bulan tak dikenal: jangan diam-diam dianggap bulan lain
            }

            // Penyebut nol berarti tidak ada yang bisa dibagi, bukan nol persen.
            if ((float) $row->penyebut <= 0.0 || $row->persen === null) {
                continue;
            }

            $bulanAda[$nomor] = true;
            $site = trim((string) $row->site);
            $mitra = trim((string) $row->mitra);
            $kunci = $site . '|' . $mitra;

            $grid[$kunci]['site'] = $site;
            $grid[$kunci]['mitra'] = $mitra;
            $grid[$kunci]['bulan'][$nomor] = [
                'persen' => round((float) $row->persen, 2),
                'pembilang' => (float) $row->pembilang,
                'penyebut' => (float) $row->penyebut,
            ];
        }

        ksort($bulanAda);
        $months = array_keys($bulanAda);
        $matrix = $this->buildMatrix($grid, $months);

        return response()->json([
            'months' => $this->monthHeadings($months),
            'matrix' => $matrix,
            'kpi' => $this->buildKpi($matrix, $months),
            'per_site' => $this->ringkasPer($matrix, 'site'),
            'per_mitra' => $this->ringkasPer($matrix, 'mitra'),
            'monthly' => $this->buildMonthlySeries($matrix, $months),
            'catatan' => $this->catatan($request),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $grid
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    protected function buildMatrix(array $grid, array $months): array
    {
        $out = [];

        foreach ($grid as $entry) {
            $cells = [];
            $terisi = [];

            foreach ($months as $month) {
                $nilai = $entry['bulan'][$month] ?? null;

                if ($nilai === null) {
                    // Bulan tanpa baris berarti belum ada datanya, bukan 0%.
                    $cells[] = ['ada' => false, 'pct' => null, 'nilai' => null];
                    continue;
                }

                [$angka, $band] = $this->nilaiUntuk($nilai['persen']);

                $cells[] = [
                    'ada' => true,
                    'pct' => $nilai['persen'],
                    'nilai' => $angka,
                    'nilai_band' => $band,
                    'pembilang' => $nilai['pembilang'],
                    'penyebut' => $nilai['penyebut'],
                ];

                $terisi[] = $nilai['persen'];
            }

            $rata = $terisi !== [] ? round(array_sum($terisi) / count($terisi), 2) : null;
            [$angkaRata, $bandRata] = $this->nilaiUntuk($rata ?? 0.0);

            $out[] = [
                'site' => $entry['site'],
                'mitra' => $entry['mitra'],
                'cells' => $cells,
                'average' => $rata,
                'nilai' => $rata === null ? null : $angkaRata,
                'nilai_band' => $rata === null ? null : $bandRata,
                'bulan_terisi' => count($terisi),
                'terendah' => $terisi !== [] ? min($terisi) : null,
                'trend' => $this->trendOf($terisi),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return ($a['average'] ?? 101.0) <=> ($b['average'] ?? 101.0);
        });

        return $out;
    }

    /** @param array<int, float> $terisi */
    protected function trendOf(array $terisi): ?string
    {
        if (count($terisi) < 2) {
            return null;
        }

        $akhir = $terisi[count($terisi) - 1];
        $sebelum = $terisi[count($terisi) - 2];

        if (abs($akhir - $sebelum) < 0.005) {
            return 'flat';
        }

        return $akhir > $sebelum ? 'up' : 'down';
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    protected function buildKpi(array $matrix, array $months): array
    {
        $semua = [];

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $sel) {
                if ($sel['ada']) {
                    $semua[] = $sel['pct'];
                }
            }
        }

        $rata = $semua !== [] ? round(array_sum($semua) / count($semua), 2) : null;
        [$nilai, $band] = $this->nilaiUntuk($rata ?? 0.0);

        $memenuhi = 0;

        foreach ($matrix as $row) {
            if ($row['average'] !== null && $row['average'] >= $this->target()) {
                $memenuhi++;
            }
        }

        return [
            'rata' => $rata,
            'nilai' => $rata === null ? null : $nilai,
            'nilai_band' => $rata === null ? null : $band,
            'target' => $this->target(),
            'memenuhi_target' => $rata !== null && $rata >= $this->target(),
            'kombinasi' => count($matrix),
            'kombinasi_memenuhi' => $memenuhi,
            'bulan_count' => count($months),
            'sel_terisi' => count($semua),
            'sel_total' => count($matrix) * max(count($months), 1),
            'terendah' => $semua !== [] ? min($semua) : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    protected function ringkasPer(array $matrix, string $key): array
    {
        $kelompok = [];

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $sel) {
                if ($sel['ada']) {
                    $kelompok[$row[$key]][] = $sel['pct'];
                }
            }
        }

        $out = [];

        foreach ($kelompok as $label => $nilai) {
            $rata = round(array_sum($nilai) / count($nilai), 2);
            [$angka, $band] = $this->nilaiUntuk($rata);

            $out[] = [
                'label' => (string) $label,
                'percent' => $rata,
                'nilai' => $angka,
                'nilai_band' => $band,
                'sel' => count($nilai),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['percent'] <=> $b['percent']);

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrix
     * @param  array<int, int>  $months
     * @return array<string, mixed>
     */
    protected function buildMonthlySeries(array $matrix, array $months): array
    {
        $data = [];

        foreach (array_keys($months) as $i) {
            $nilai = [];

            foreach ($matrix as $row) {
                if ($row['cells'][$i]['ada'] ?? false) {
                    $nilai[] = $row['cells'][$i]['pct'];
                }
            }

            $data[] = $nilai === [] ? null : round(array_sum($nilai) / count($nilai), 2);
        }

        return [
            'labels' => array_map(fn (int $m): string => self::MONTH_MAP[$this->namaInggris($m)][1] ?? '-', $months),
            'data' => $data,
        ];
    }

    protected function catatan(Request $request): ?string
    {
        $k = $this->kolom();

        $baris = (int) $this->baseQuery($request)->count();
        $kosong = (int) $this->baseQuery($request)->whereNull($k['nilai'])->count();

        if ($baris === 0) {
            return null;
        }

        if ($kosong === 0) {
            return null;
        }

        return sprintf(
            '%s dari %s baris di %s belum punya angka (kolom %s kosong), jadi '
            . 'kombinasi site, perusahaan, dan bulan itu tidak ikut dinilai. '
            . 'Sel kosong di matriks berarti belum ada datanya, bukan 0%%.',
            number_format($kosong, 0, ',', '.'),
            number_format($baris, 0, ',', '.'),
            $this->tabel(),
            $k['nilai']
        );
    }

    // ======================================================================
    // Modal rincian sel
    // ======================================================================

    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));
        $bulan = (int) $request->input('bulan', 0);

        if ($site === '' || $mitra === '' || $bulan < 1 || $bulan > 12) {
            return response()->json(['message' => 'Site, perusahaan, dan bulan wajib diisi.'], 422);
        }

        $k = $this->kolom();
        $namaBulan = $this->namaInggris($bulan);

        $row = $this->baseQuery($request)
            ->where($k['site'], $site)
            ->where($k['mitra'], $mitra)
            ->where($k['bulan'], $namaBulan)
            ->selectRaw($this->ekspresiPersen())
            ->first();

        if ($row !== null) {
            $row = $this->terapkanPenyebut($row, $site, $mitra);
        }

        if ($row === null || $row->persen === null || (float) $row->penyebut <= 0.0) {
            return response()->json(['message' => 'Tidak ada data untuk kombinasi itu.'], 404);
        }

        $persen = round((float) $row->persen, 2);
        [$nilai, $band] = $this->nilaiUntuk($persen);

        // Riwayat kombinasi yang sama sepanjang bulan yang ada, supaya sel ini
        // bisa dibaca dalam konteks, bukan sebagai angka tunggal.
        $riwayat = [];

        foreach ($this->baseQuery($request)
            ->where($k['site'], $site)
            ->where($k['mitra'], $mitra)
            ->selectRaw($this->kutip($k['bulan']) . ' AS bulan, ' . $this->ekspresiPersen())
            ->groupBy('bulan')
            ->get() as $r) {
            $this->terapkanPenyebut($r, $site, $mitra);
            $n = $this->nomorBulan((string) $r->bulan);

            if ($n === 0 || $r->persen === null || (float) $r->penyebut <= 0.0) {
                continue;
            }

            [$angka, ] = $this->nilaiUntuk(round((float) $r->persen, 2));

            $riwayat[$n] = [
                'bulan' => $n,
                'label' => self::MONTH_MAP[$this->namaInggris($n)][1] ?? '-',
                'persen' => round((float) $r->persen, 2),
                'nilai' => $angka,
                'ini' => $n === $bulan,
            ];
        }

        ksort($riwayat);

        // Peringkat sel ini di antara kombinasi lain pada bulan yang sama.
        $sebulan = [];

        foreach ($this->baseQuery($request)
            ->where($k['bulan'], $namaBulan)
            ->selectRaw(
                $this->kutip($k['site']) . ' AS site, '
                . $this->kutip($k['mitra']) . ' AS mitra, '
                . $this->ekspresiPersen()
            )
            ->groupBy('site', 'mitra')
            ->get() as $r) {
            $this->terapkanPenyebut($r);

            if ($r->persen === null || (float) $r->penyebut <= 0.0) {
                continue;
            }

            $sebulan[] = [
                'site' => trim((string) $r->site),
                'mitra' => trim((string) $r->mitra),
                'persen' => round((float) $r->persen, 2),
            ];
        }

        usort($sebulan, static fn (array $a, array $b): int => $b['persen'] <=> $a['persen']);

        $peringkat = 0;

        foreach ($sebulan as $i => $s) {
            if ($s['site'] === $site && $s['mitra'] === $mitra) {
                $peringkat = $i + 1;
                break;
            }
        }

        return response()->json([
            'judul' => $site . ' · ' . $mitra,
            'bulan' => self::MONTH_MAP[$namaBulan][1] ?? $namaBulan,
            'persen' => $persen,
            'nilai' => $nilai,
            'nilai_band' => $band,
            'pembilang' => (float) $row->pembilang,
            'penyebut' => (float) $row->penyebut,
            'label_pecahan' => $this->labelPecahan(),
            'target' => $this->target(),
            'memenuhi_target' => $persen >= $this->target(),
            'peringkat' => $peringkat,
            'dari' => count($sebulan),
            'riwayat' => array_values($riwayat),
            'sebulan' => array_slice($sebulan, 0, self::DETAIL_LIMIT),
        ]);
    }

    // ======================================================================
    // Tab Data
    // ======================================================================

    public function data(Request $request): JsonResponse
    {
        $query = $this->dataQuery($request);

        $rows = (clone $query)
            ->orderBy(
                $this->dtOrderColumn($request, [0 => 'site', 1 => 'mitra', 3 => 'persen'], 'site'),
                $this->dtDirection($request, 'asc')
            )
            ->orderBy('site')
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
        $rows = $this->dataQuery($request)
            ->orderBy('site')
            ->orderBy('mitra')
            ->limit($this->dtExportRowLimit())
            ->get()
            ->map(fn (object $row): array => $this->present($row));

        [$labelPembilang, $labelPenyebut] = $this->labelPecahan();

        return $this->dtExport(
            $this->slug() . '.csv',
            ['Site', 'Perusahaan', 'Bulan', $labelPembilang, $labelPenyebut, 'Persentase', 'Nilai'],
            $rows->map(static fn (array $r): array => [
                $r['site'], $r['mitra'], $r['bulan'],
                $r['pembilang'], $r['penyebut'],
                $r['persen'] === null ? '-' : number_format((float) $r['persen'], 2, ',', '.'),
                $r['nilai'] === null ? '-' : number_format((float) $r['nilai'], 2, ',', '.'),
            ])->all()
        );
    }

    protected function dataQuery(Request $request): Builder
    {
        $k = $this->kolom();

        $sub = $this->baseQuery($request)
            ->selectRaw(
                $this->kutip($k['site']) . ' AS site, '
                . $this->kutip($k['mitra']) . ' AS mitra, '
                . $this->kutip($k['bulan']) . ' AS bulan, '
                . $this->ekspresiPersen()
            )
            ->groupBy('site', 'mitra', 'bulan');

        $query = DB::query()->fromSub($sub, 'o');

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            ['site', 'mitra', 'bulan']
        );

        return $query;
    }

    protected function baseQuery(Request $request): Builder
    {
        $k = $this->kolom();
        $query = DB::table($this->tabel());

        $this->dtApplyEqualsFilters($query, $request, [
            'site' => $k['site'],
            'mitra' => $k['mitra'],
        ]);

        $bulan = (int) $request->input('bulan_filter', 0);

        if ($bulan >= 1 && $bulan <= 12) {
            $query->where($k['bulan'], $this->namaInggris($bulan));
        }

        return $query;
    }

    /** @return array<string, mixed> */
    protected function present(object $row): array
    {
        $this->terapkanPenyebut($row);
        $persen = $row->persen === null ? null : round((float) $row->persen, 2);
        $nomor = $this->nomorBulan((string) $row->bulan);
        [$nilai, $band] = $this->nilaiUntuk($persen ?? 0.0);

        return [
            'site' => trim((string) $row->site),
            'mitra' => trim((string) $row->mitra),
            'bulan' => $nomor === 0
                ? trim((string) $row->bulan)
                : (self::MONTH_MAP[$this->namaInggris($nomor)][1] ?? '-'),
            'bulan_nomor' => $nomor,
            'pembilang' => (float) $row->pembilang,
            'penyebut' => (float) $row->penyebut,
            'persen' => $persen,
            'nilai' => $persen === null ? null : $nilai,
            'nilai_band' => $persen === null ? null : $band,
            'memenuhi_target' => $persen !== null && $persen >= $this->target(),
        ];
    }

    protected function baseCount(): int
    {
        $k = $this->kolom();

        return (int) DB::query()
            ->fromSub(
                DB::table($this->tabel())
                    ->selectRaw('1 AS satu')
                    ->groupBy($k['site'], $k['mitra'], $k['bulan']),
                'o'
            )
            ->count();
    }

    // ======================================================================
    // Pembantu
    // ======================================================================

    /** @return array<int, string> */
    protected function distinctValues(string $kolom): array
    {
        return DB::table($this->tabel())
            ->whereNotNull($kolom)
            ->where($kolom, '<>', '')
            ->distinct()
            ->orderBy($kolom)
            ->pluck($kolom)
            ->map(static fn ($v): string => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    protected function monthOptions(): array
    {
        $ada = DB::table($this->tabel())
            ->distinct()
            ->pluck($this->kolom()['bulan'])
            ->map(fn ($v): int => $this->nomorBulan((string) $v))
            ->filter(static fn (int $n): bool => $n > 0)
            ->unique()
            ->sort()
            ->values();

        $out = [];

        foreach ($ada as $n) {
            $out[$n] = self::MONTH_MAP[$this->namaInggris($n)][1] ?? '-';
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    protected function monthHeadings(array $months): array
    {
        $out = [];

        foreach ($months as $n) {
            $label = self::MONTH_MAP[$this->namaInggris($n)][1] ?? '-';

            $out[] = ['number' => $n, 'label' => $label, 'short' => mb_substr($label, 0, 3)];
        }

        return $out;
    }

    protected function nomorBulan(string $nama): int
    {
        $bersih = trim($nama);

        foreach (self::MONTH_MAP as $inggris => [$nomor, ]) {
            if (strcasecmp($bersih, $inggris) === 0) {
                return $nomor;
            }
        }

        return 0;
    }

    protected function namaInggris(int $nomor): string
    {
        foreach (self::MONTH_MAP as $inggris => [$n, ]) {
            if ($n === $nomor) {
                return $inggris;
            }
        }

        return '';
    }

    protected function kutip(string $kolom): string
    {
        return '`' . str_replace('`', '', $kolom) . '`';
    }
}
