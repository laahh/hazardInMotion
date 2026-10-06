<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Concerns\ServesDataTable;
use App\Http\Controllers\Controller;
use App\Services\OhsScoreCard\LaporanPerizinanUsahaJasa;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Parameter "Laporan Perizinan Usaha Jasa".
 *
 * Sumbernya scr_business_license_performance: 16 baris, satu per main_cont x
 * site_dedicated, BERFORMAT LEBAR dengan bulan sebagai kolom.
 *
 * ANGKANYA PROPORSI DEVIASI, BUKAN KEPATUHAN. Nol berarti tidak ada
 * subkontraktor yang menyimpang -- hasil TERBAIK. Seluruh pengurutan,
 * peringkat, dan pewarnaan di halaman ini karena itu berarah TURUN: yang
 * terbesar justru yang paling perlu dilihat. Lihat LaporanPerizinanUsahaJasa
 * untuk pembuktian rumusnya.
 *
 * BAND RESMINYA BELUM ADA. Ambang di AMBANG_SEMENTARA bukan dari tabel band
 * resmi; dipakai hanya supaya selnya terbaca. Karena itu halaman ini TIDAK
 * menampilkan angka Nilai 1-4 sama sekali -- menampilkannya berarti mengarang
 * skor resmi.
 */
final class LaporanPerizinanUsahaJasaController extends Controller
{
    use ServesDataTable;

    private const TABEL = LaporanPerizinanUsahaJasa::TABEL;

    /**
     * Ambang warna SEMENTARA, bukan band resmi, dan BERARAH TURUN: nol persen
     * deviasi adalah hasil terbaik.
     *
     * Bentuknya mengikuti band "% Blindspot temuan Real Time" yang memang
     * berarah sama (0% terbaik, >5% terburuk), tetapi itu band parameter LAIN.
     * Begitu band resmi parameter ini ada, ganti di sini saja.
     */
    private const AMBANG_SEMENTARA = [0.0, 3.0, 5.0];

    /** Batas baris yang dikirim ke modal rincian sel. */
    private const BATAS_DETAIL = 200;

    // ======================================================================
    // Halaman
    // ======================================================================

    public function index(): View
    {
        return view('ohs-score-card.laporan-perizinan-usaha-jasa.index', [
            'judul' => 'Laporan Perizinan Usaha Jasa',
            'penjelasan' => 'Subkontraktor yang menyimpang dibagi seluruh subkontraktor '
                . 'tiap main contractor di tiap site',
            'tabel' => self::TABEL,
            'legenda' => $this->legendaSementara(),
            'filterOptions' => [
                'site' => $this->nilaiBerbeda('site_dedicated'),
                'mitra' => $this->nilaiBerbeda('main_cont'),
            ],
            'monthOptions' => LaporanPerizinanUsahaJasa::LABEL_BULAN,
        ]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request): JsonResponse
    {
        $bulanDipilih = $this->bulanDipilih($request);
        $baris = [];

        foreach ($this->queryDasar($request)->orderBy('id')->get() as $r) {
            $site = trim((string) $r->site_dedicated);
            $mitra = trim((string) $r->main_cont);

            if ($site === '' || $mitra === '') {
                continue;
            }

            $sel = [];
            $deviasi = 0;
            $dasar = 0;

            foreach ($bulanDipilih as $akhiran => $nomor) {
                $angka = LaporanPerizinanUsahaJasa::selBulan($r, $akhiran);

                if ($angka === null) {
                    // Bulan itu belum terdata -- bukan nol deviasi.
                    $sel[] = ['ada' => false, 'pct' => null, 'band' => null];
                    continue;
                }

                $persen = round($angka['deviasi'] / $angka['total'] * 100, 2);

                $sel[] = [
                    'ada' => true,
                    'pct' => $persen,
                    'band' => $this->bandSementara($persen),
                    'deviasi' => $angka['deviasi'],
                    'total' => $angka['total'],
                    // Kolom pct bawaan disimpan apa adanya supaya selisih
                    // dengan hitungan sendiri ketahuan kalau sumbernya berubah.
                    'pct_sumber' => $r->{LaporanPerizinanUsahaJasa::kolomPersen($akhiran)} === null
                        ? null
                        : round((float) $r->{LaporanPerizinanUsahaJasa::kolomPersen($akhiran)} * 100, 2),
                ];

                $deviasi += $angka['deviasi'];
                $dasar += $angka['total'];
            }

            // RATA-RATANYA TERTIMBANG: dijumlahkan dulu deviasi dan
            // penyebutnya, baru dibagi. Merata-ratakan persentase bulanan
            // memberi bobot sama kepada main contractor berisi 1 subkontraktor
            // dan yang berisi 38.
            $rata = $dasar > 0 ? round($deviasi / $dasar * 100, 2) : null;

            $baris[] = [
                'site' => $site,
                'mitra' => $mitra,
                'cells' => $sel,
                'average' => $rata,
                'band' => $rata === null ? null : $this->bandSementara($rata),
                'deviasi' => $deviasi,
                'subcont' => (int) $r->total_perusahaan_subcontractor,
                'bulan_terisi' => count(array_filter($sel, static fn (array $s): bool => $s['ada'])),
                'puncak' => $this->bulanPuncak($sel, $bulanDipilih),
            ];
        }

        // ARAH TURUN: deviasi TERBESAR lebih dulu, karena itu yang perlu
        // ditindak. Kebalikan dari parameter kepatuhan.
        usort($baris, static function (array $a, array $b): int {
            return ($b['average'] ?? -1.0) <=> ($a['average'] ?? -1.0);
        });

        return response()->json([
            'months' => array_values(array_map(
                static fn (int $n): array => [
                    'number' => $n,
                    'label' => LaporanPerizinanUsahaJasa::LABEL_BULAN[$n],
                    'short' => mb_substr(LaporanPerizinanUsahaJasa::LABEL_BULAN[$n], 0, 3),
                ],
                $bulanDipilih
            )),
            'matrix' => $baris,
            'kpi' => $this->bangunKpi($baris),
            'per_site' => $this->ringkasPer($baris, 'site'),
            'per_mitra' => $this->ringkasPer($baris, 'mitra'),
            'per_bulan' => $this->ringkasBulan($baris, $bulanDipilih),
            'catatan' => $this->catatan($request),
        ]);
    }

    /**
     * Bulan dengan deviasi tertinggi di satu baris, untuk kolom penanda.
     *
     * @param  array<int, array<string, mixed>>  $sel
     * @param  array<string, int>  $bulan
     */
    private function bulanPuncak(array $sel, array $bulan): ?string
    {
        $nomor = array_values($bulan);
        $terbaik = null;
        $indeks = null;

        foreach ($sel as $i => $s) {
            if (!$s['ada'] || $s['pct'] <= 0.0) {
                continue;
            }

            if ($terbaik === null || $s['pct'] > $terbaik) {
                $terbaik = $s['pct'];
                $indeks = $i;
            }
        }

        return $indeks === null ? null : LaporanPerizinanUsahaJasa::LABEL_BULAN[$nomor[$indeks]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     * @return array<string, mixed>
     */
    private function bangunKpi(array $baris): array
    {
        $deviasi = 0;
        $dasar = 0;
        $subcont = 0;
        $tertinggi = null;
        $bersih = 0;

        foreach ($baris as $b) {
            $subcont += $b['subcont'];

            foreach ($b['cells'] as $s) {
                if (!$s['ada']) {
                    continue;
                }

                $deviasi += $s['deviasi'];
                $dasar += $s['total'];
            }

            if ($b['average'] === null) {
                continue;
            }

            $tertinggi = $tertinggi === null ? $b['average'] : max($tertinggi, $b['average']);

            if ($b['average'] <= 0.0) {
                $bersih++;
            }
        }

        $rata = $dasar > 0 ? round($deviasi / $dasar * 100, 2) : null;

        return [
            'rata' => $rata,
            'band' => $rata === null ? null : $this->bandSementara($rata),
            'deviasi' => $deviasi,
            'subcont' => $subcont,
            'kombinasi' => count($baris),
            'kombinasi_bersih' => $bersih,
            'tertinggi' => $tertinggi,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     * @return array<int, array<string, mixed>>
     */
    private function ringkasPer(array $baris, string $kunci): array
    {
        $kelompok = [];

        foreach ($baris as $b) {
            $label = (string) $b[$kunci];

            foreach ($b['cells'] as $s) {
                if (!$s['ada']) {
                    continue;
                }

                $kelompok[$label]['deviasi'] = ($kelompok[$label]['deviasi'] ?? 0) + $s['deviasi'];
                $kelompok[$label]['total'] = ($kelompok[$label]['total'] ?? 0) + $s['total'];
            }

            $kelompok[$label]['baris'] = ($kelompok[$label]['baris'] ?? 0) + 1;
        }

        $out = [];

        foreach ($kelompok as $label => $a) {
            if (($a['total'] ?? 0) <= 0) {
                continue;
            }

            $persen = round($a['deviasi'] / $a['total'] * 100, 2);

            $out[] = [
                'label' => (string) $label,
                'percent' => $persen,
                'band' => $this->bandSementara($persen),
                'deviasi' => $a['deviasi'],
                'total' => $a['total'],
                'sel' => $a['baris'],
            ];
        }

        // Deviasi terbesar lebih dulu.
        usort($out, static fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     * @param  array<string, int>  $bulan
     * @return array<string, mixed>
     */
    private function ringkasBulan(array $baris, array $bulan): array
    {
        $label = [];
        $data = [];
        $i = 0;

        foreach ($bulan as $nomor) {
            $deviasi = 0;
            $total = 0;

            foreach ($baris as $b) {
                $s = $b['cells'][$i] ?? null;

                if ($s === null || !$s['ada']) {
                    continue;
                }

                $deviasi += $s['deviasi'];
                $total += $s['total'];
            }

            $label[] = LaporanPerizinanUsahaJasa::LABEL_BULAN[$nomor];
            $data[] = $total > 0 ? round($deviasi / $total * 100, 2) : null;
            $i++;
        }

        return ['labels' => $label, 'data' => $data];
    }

    private function catatan(Request $request): ?string
    {
        $bagian = [];

        // Bulan yang seluruh barisnya nol deviasi. Perlu disebut karena nol di
        // sini berarti "sudah diperiksa dan bersih", dan itu mudah tertukar
        // dengan "belum diisi".
        // SATU QUERY UNTUK SEMBILAN BULAN, bukan sembilan COUNT terpisah.
        // Databasenya jauh, dan sembilan perjalanan pulang-pergi hanya untuk
        // satu kalimat catatan membuat halaman ini menunggu beberapa detik.
        $pilih = [];

        foreach (array_keys(LaporanPerizinanUsahaJasa::BULAN) as $akhiran) {
            $kolom = LaporanPerizinanUsahaJasa::kolomDeviasi($akhiran);
            $pilih[] = 'SUM(`' . $kolom . '` <> 0) AS `' . $akhiran . '`';
        }

        $cacah = DB::table(self::TABEL)->selectRaw(implode(', ', $pilih))->first();
        $nol = [];

        foreach (LaporanPerizinanUsahaJasa::BULAN as $akhiran => $nomor) {
            if ((int) ($cacah->{$akhiran} ?? 0) === 0) {
                $nol[] = LaporanPerizinanUsahaJasa::LABEL_BULAN[$nomor];
            }
        }

        if ($nol !== []) {
            $bagian[] = sprintf(
                'Pada %s seluruh baris mencatat 0 deviasi. Kolom deviasi memang terisi '
                . '(bukan kosong), jadi itu dibaca sebagai tidak ada penyimpangan, bukan '
                . 'sebagai data yang belum masuk.',
                implode(' dan ', $nol)
            );
        }

        $bagian[] = 'Angka di halaman ini adalah PROPORSI DEVIASI, bukan tingkat kepatuhan: '
            . '0% berarti tidak ada subkontraktor yang menyimpang dan itu hasil terbaik. '
            . 'Urutannya karena itu dari yang terbesar.';

        return implode(' ', $bagian);
    }

    // ======================================================================
    // Modal rincian sel
    // ======================================================================

    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));
        $nomor = (int) $request->input('bulan', 0);
        $akhiran = array_search($nomor, LaporanPerizinanUsahaJasa::BULAN, true);

        if ($site === '' || $mitra === '' || $akhiran === false) {
            return response()->json(['message' => 'Site, perusahaan, dan bulan wajib diisi.'], 422);
        }

        $row = DB::table(self::TABEL)
            ->whereRaw('TRIM(site_dedicated) = ?', [$site])
            ->whereRaw('TRIM(main_cont) = ?', [$mitra])
            ->first();

        if ($row === null) {
            return response()->json(['message' => 'Tidak ada data untuk kombinasi itu.'], 404);
        }

        $angka = LaporanPerizinanUsahaJasa::selBulan($row, $akhiran);

        if ($angka === null) {
            return response()->json(['message' => 'Bulan itu belum terdata untuk kombinasi ini.'], 404);
        }

        $persen = round($angka['deviasi'] / $angka['total'] * 100, 2);

        // Riwayat kombinasi yang sama sepanjang bulan yang ada.
        $riwayat = [];

        foreach (LaporanPerizinanUsahaJasa::BULAN as $a => $n) {
            $x = LaporanPerizinanUsahaJasa::selBulan($row, $a);

            if ($x === null) {
                continue;
            }

            $p = round($x['deviasi'] / $x['total'] * 100, 2);

            $riwayat[] = [
                'bulan' => $n,
                'label' => LaporanPerizinanUsahaJasa::LABEL_BULAN[$n],
                'persen' => $p,
                'band' => $this->bandSementara($p),
                'deviasi' => $x['deviasi'],
                'total' => $x['total'],
                'ini' => $n === $nomor,
            ];
        }

        // Peringkat sel ini di antara kombinasi lain pada bulan yang sama.
        // ARAH TURUN: deviasi terbesar menempati peringkat 1.
        $sebulan = [];

        foreach (DB::table(self::TABEL)->get() as $r) {
            $x = LaporanPerizinanUsahaJasa::selBulan($r, $akhiran);

            if ($x === null) {
                continue;
            }

            $sebulan[] = [
                'site' => trim((string) $r->site_dedicated),
                'mitra' => trim((string) $r->main_cont),
                'persen' => round($x['deviasi'] / $x['total'] * 100, 2),
                'deviasi' => $x['deviasi'],
                'total' => $x['total'],
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
            'bulan' => LaporanPerizinanUsahaJasa::LABEL_BULAN[$nomor],
            'persen' => $persen,
            'band' => $this->bandSementara($persen),
            'deviasi' => $angka['deviasi'],
            'total' => $angka['total'],
            'peringkat' => $peringkat,
            'dari' => count($sebulan),
            'riwayat' => $riwayat,
            'sebulan' => array_slice($sebulan, 0, self::BATAS_DETAIL),
        ]);
    }

    // ======================================================================
    // Tab Data
    // ======================================================================

    public function data(Request $request): JsonResponse
    {
        $query = $this->queryDasar($request);

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            ['site_dedicated', 'main_cont']
        );

        $rows = (clone $query)
            ->orderBy(
                $this->dtOrderColumn($request, [
                    0 => 'site_dedicated', 1 => 'main_cont',
                    2 => 'total_perusahaan_subcontractor',
                ], 'site_dedicated'),
                $this->dtDirection($request, 'asc')
            )
            ->orderBy('main_cont')
            ->forPage($this->dtPage($request), $this->dtPageLength($request))
            ->get()
            ->map(fn (object $row): array => $this->sajikan($row))
            ->all();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => (int) DB::table(self::TABEL)->count(),
            'recordsFiltered' => (clone $query)->count(),
            'data' => $rows,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->queryDasar($request);

        $this->dtApplySearch(
            $query,
            (string) $request->input('search', ''),
            ['site_dedicated', 'main_cont']
        );

        $rows = $query
            ->orderBy('site_dedicated')
            ->orderBy('main_cont')
            ->limit($this->dtExportRowLimit())
            ->get()
            ->map(fn (object $row): array => $this->sajikan($row));

        $kepala = ['Site', 'Main Contractor', 'Total Subcontractor'];

        foreach (LaporanPerizinanUsahaJasa::BULAN as $n) {
            $kepala[] = LaporanPerizinanUsahaJasa::LABEL_BULAN[$n] . ' (deviasi)';
            $kepala[] = LaporanPerizinanUsahaJasa::LABEL_BULAN[$n] . ' (%)';
        }

        $kepala[] = 'Rata-rata (%)';

        return $this->dtExport(
            'laporan-perizinan-usaha-jasa.csv',
            $kepala,
            $rows->map(static function (array $r): array {
                $baris = [$r['site'], $r['mitra'], $r['subcont']];

                foreach ($r['bulan'] as $b) {
                    $baris[] = $b['ada'] ? $b['deviasi'] : '-';
                    $baris[] = $b['ada'] ? number_format($b['persen'], 2, ',', '.') : '-';
                }

                $baris[] = $r['rata'] === null
                    ? '-'
                    : number_format((float) $r['rata'], 2, ',', '.');

                return $baris;
            })->all()
        );
    }

    /** @return array<string, mixed> */
    private function sajikan(object $row): array
    {
        $bulan = [];
        $deviasi = 0;
        $dasar = 0;

        foreach (LaporanPerizinanUsahaJasa::BULAN as $akhiran => $nomor) {
            $angka = LaporanPerizinanUsahaJasa::selBulan($row, $akhiran);

            if ($angka === null) {
                $bulan[] = ['ada' => false, 'label' => LaporanPerizinanUsahaJasa::LABEL_BULAN[$nomor]];
                continue;
            }

            $persen = round($angka['deviasi'] / $angka['total'] * 100, 2);

            $bulan[] = [
                'ada' => true,
                'label' => LaporanPerizinanUsahaJasa::LABEL_BULAN[$nomor],
                'deviasi' => $angka['deviasi'],
                'persen' => $persen,
                'band' => $this->bandSementara($persen),
            ];

            $deviasi += $angka['deviasi'];
            $dasar += $angka['total'];
        }

        $rata = $dasar > 0 ? round($deviasi / $dasar * 100, 2) : null;

        return [
            'site' => trim((string) $row->site_dedicated),
            'mitra' => trim((string) $row->main_cont),
            'subcont' => (int) $row->total_perusahaan_subcontractor,
            'deviasi' => $deviasi,
            'bulan' => $bulan,
            'rata' => $rata,
            'band' => $rata === null ? null : $this->bandSementara($rata),
        ];
    }

    // ======================================================================
    // Pembantu
    // ======================================================================

    private function queryDasar(Request $request): Builder
    {
        $query = DB::table(self::TABEL);

        foreach (['site' => 'site_dedicated', 'mitra' => 'main_cont'] as $param => $kolom) {
            $nilai = trim((string) $request->input($param, ''));

            if ($nilai !== '') {
                $query->whereRaw('TRIM(' . $kolom . ') = ?', [$nilai]);
            }
        }

        return $query;
    }

    /** @return array<string, int> */
    private function bulanDipilih(Request $request): array
    {
        $nomor = (int) $request->input('bulan_filter', 0);

        if ($nomor >= 1 && $nomor <= 12) {
            foreach (LaporanPerizinanUsahaJasa::BULAN as $akhiran => $n) {
                if ($n === $nomor) {
                    return [$akhiran => $n];
                }
            }
        }

        return LaporanPerizinanUsahaJasa::BULAN;
    }

    /**
     * Nomor band 1-4 dari ambang SEMENTARA, BERARAH TURUN.
     *
     * Nol persen deviasi adalah hasil terbaik, jadi band 4 ada di bawah dan
     * band 1 di atas -- kebalikan dari parameter kepatuhan. Ini bukan Nilai
     * resmi dan tidak pernah ditampilkan sebagai angka Nilai; nomornya hanya
     * menentukan warna sel.
     */
    private function bandSementara(float $persen): int
    {
        [$b4, $b3, $b2] = self::AMBANG_SEMENTARA;

        if ($persen <= $b4) {
            return 4;
        }

        if ($persen <= $b3) {
            return 3;
        }

        if ($persen <= $b2) {
            return 2;
        }

        return 1;
    }

    /** @return array<int, array{band: int, label: string}> */
    private function legendaSementara(): array
    {
        [$b4, $b3, $b2] = self::AMBANG_SEMENTARA;

        return [
            ['band' => 1, 'label' => '>' . $this->pct($b2)],
            ['band' => 2, 'label' => '>' . $this->pct($b3) . ' - ' . $this->pct($b2)],
            ['band' => 3, 'label' => '>' . $this->pct($b4) . ' - ' . $this->pct($b3)],
            ['band' => 4, 'label' => $this->pct($b4)],
        ];
    }

    private function pct(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',') . '%';
    }

    /** @return array<int, string> */
    private function nilaiBerbeda(string $kolom): array
    {
        return DB::table(self::TABEL)
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
}
