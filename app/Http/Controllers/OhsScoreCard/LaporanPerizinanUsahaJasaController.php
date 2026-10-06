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
 * PERSENTASENYA DARI KOLOM performance_<bulan>_26_pct (rasio 0-1, dikali 100)
 * dan merupakan TINGKAT PEMENUHAN: 100% berarti tidak ada subkontraktor yang
 * menyimpang, dan itu hasil TERBAIK. Arahnya NAIK -- pengurutan, peringkat,
 * dan pewarnaan semuanya menempatkan yang TERENDAH sebagai yang paling perlu
 * ditindak.
 *
 * Cacah deviasi tetap ditampilkan sebagai pendamping dan dipakai memeriksa
 * silang kolom pct; lihat LaporanPerizinanUsahaJasa, termasuk catatan bahwa
 * arti kolom itu pernah berubah.
 *
 * RATA-RATANYA TERTIMBANG terhadap cacah subkontraktor. Untuk SATU baris itu
 * tidak mengubah apa pun -- penyebutnya sama di seluruh bulan, sehingga
 * rata-rata tertimbang dan rata-rata biasa menghasilkan angka yang sama. Yang
 * membedakan ada di tingkat site dan keseluruhan, tempat main contractor
 * dengan satu subkontraktor tidak boleh berbobot sama dengan yang punya tiga
 * puluh delapan.
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
     * Ambang warna SEMENTARA, bukan band resmi, BERARAH NAIK: seratus persen
     * pemenuhan adalah hasil terbaik.
     *
     * Bentuknya mengikuti parameter kepatuhan lain di modul ini, dan rentang
     * datanya sekarang memang 88,89%-100%. Begitu band resmi parameter ini
     * ada, ganti di sini saja.
     */
    private const AMBANG_SEMENTARA = [95.0, 98.0, 100.0];

    /** Batas baris yang dikirim ke modal rincian sel. */
    private const BATAS_DETAIL = 200;

    // ======================================================================
    // Halaman
    // ======================================================================

    public function index(): View
    {
        return view('ohs-score-card.laporan-perizinan-usaha-jasa.index', [
            'judul' => 'Laporan Perizinan Usaha Jasa',
            'penjelasan' => 'Tingkat pemenuhan perizinan subkontraktor tiap main contractor '
                . 'di tiap site',
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
            $penuh = 0.0;
            $dasar = 0;
            $deviasi = 0;

            foreach ($bulanDipilih as $akhiran => $nomor) {
                $angka = LaporanPerizinanUsahaJasa::selBulan($r, $akhiran);

                if ($angka === null) {
                    // Bulan itu belum terdata -- bukan nol pemenuhan.
                    $sel[] = ['ada' => false, 'pct' => null, 'band' => null];
                    continue;
                }

                $persen = $angka['persen'];

                $sel[] = [
                    'ada' => true,
                    'pct' => $persen,
                    'band' => $this->bandSementara($persen),
                    'deviasi' => $angka['deviasi'],
                    'total' => $angka['total'],
                    'sepakat' => $angka['sepakat'],
                ];

                // Pembilang diturunkan DARI PERSENNYA, bukan dari kolom
                // deviasi, supaya kolom pct benar-benar yang menentukan setiap
                // angka di halaman ini -- termasuk rata-rata tertimbangnya.
                $penuh += $persen / 100 * $angka['total'];
                $dasar += $angka['total'];
                $deviasi += (int) ($angka['deviasi'] ?? 0);
            }

            $rata = $dasar > 0 ? round($penuh / $dasar * 100, 2) : null;

            $baris[] = [
                'site' => $site,
                'mitra' => $mitra,
                'cells' => $sel,
                'average' => $rata,
                'band' => $rata === null ? null : $this->bandSementara($rata),
                'deviasi' => $deviasi,
                'subcont' => (int) $r->total_perusahaan_subcontractor,
                'bulan_terisi' => count(array_filter($sel, static fn (array $s): bool => $s['ada'])),
                'terendah' => $this->bulanTerendah($sel, $bulanDipilih),
            ];
        }

        // ARAH NAIK: pemenuhan TERENDAH lebih dulu, karena itu yang perlu
        // ditindak. Baris tanpa angka ditaruh di belakang, bukan di depan.
        usort($baris, static function (array $a, array $b): int {
            return ($a['average'] ?? 101.0) <=> ($b['average'] ?? 101.0);
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
     * Bulan dengan pemenuhan terendah di satu baris, untuk kolom penanda.
     *
     * @param  array<int, array<string, mixed>>  $sel
     * @param  array<string, int>  $bulan
     */
    private function bulanTerendah(array $sel, array $bulan): ?string
    {
        $nomor = array_values($bulan);
        $terendah = null;
        $indeks = null;

        foreach ($sel as $i => $s) {
            // Bulan yang sudah sempurna bukan temuan, jadi tidak dianggap
            // sebagai titik terendah yang perlu ditunjuk.
            if (!$s['ada'] || $s['pct'] >= 100.0) {
                continue;
            }

            if ($terendah === null || $s['pct'] < $terendah) {
                $terendah = $s['pct'];
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
        $penuh = 0.0;
        $dasar = 0;
        $subcont = 0;
        $deviasi = 0;
        $terendah = null;
        $sempurna = 0;

        foreach ($baris as $b) {
            $subcont += $b['subcont'];
            $deviasi += $b['deviasi'];

            foreach ($b['cells'] as $s) {
                if (!$s['ada']) {
                    continue;
                }

                $penuh += $s['pct'] / 100 * $s['total'];
                $dasar += $s['total'];
            }

            if ($b['average'] === null) {
                continue;
            }

            $terendah = $terendah === null ? $b['average'] : min($terendah, $b['average']);

            if ($b['average'] >= 100.0) {
                $sempurna++;
            }
        }

        $rata = $dasar > 0 ? round($penuh / $dasar * 100, 2) : null;

        return [
            'rata' => $rata,
            'band' => $rata === null ? null : $this->bandSementara($rata),
            'deviasi' => $deviasi,
            'subcont' => $subcont,
            'kombinasi' => count($baris),
            'kombinasi_sempurna' => $sempurna,
            'terendah' => $terendah,
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

                $kelompok[$label]['penuh'] = ($kelompok[$label]['penuh'] ?? 0.0)
                    + $s['pct'] / 100 * $s['total'];
                $kelompok[$label]['total'] = ($kelompok[$label]['total'] ?? 0) + $s['total'];
            }

            $kelompok[$label]['deviasi'] = ($kelompok[$label]['deviasi'] ?? 0) + $b['deviasi'];
            $kelompok[$label]['baris'] = ($kelompok[$label]['baris'] ?? 0) + 1;
        }

        $out = [];

        foreach ($kelompok as $label => $a) {
            if (($a['total'] ?? 0) <= 0) {
                continue;
            }

            $persen = round($a['penuh'] / $a['total'] * 100, 2);

            $out[] = [
                'label' => (string) $label,
                'percent' => $persen,
                'band' => $this->bandSementara($persen),
                'deviasi' => $a['deviasi'],
                'total' => $a['total'],
                'sel' => $a['baris'],
            ];
        }

        // Pemenuhan terendah lebih dulu.
        usort($out, static fn (array $a, array $b): int => $a['percent'] <=> $b['percent']);

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
            $penuh = 0.0;
            $total = 0;

            foreach ($baris as $b) {
                $s = $b['cells'][$i] ?? null;

                if ($s === null || !$s['ada']) {
                    continue;
                }

                $penuh += $s['pct'] / 100 * $s['total'];
                $total += $s['total'];
            }

            $label[] = LaporanPerizinanUsahaJasa::LABEL_BULAN[$nomor];
            $data[] = $total > 0 ? round($penuh / $total * 100, 2) : null;
            $i++;
        }

        return ['labels' => $label, 'data' => $data];
    }

    private function catatan(Request $request): ?string
    {
        $bagian = [];

        // Bulan yang seluruh barisnya sudah 100%. Perlu disebut karena sempurna
        // di sini berarti "sudah diperiksa dan bersih", dan itu mudah tertukar
        // dengan "belum diisi".
        //
        // SATU QUERY UNTUK SEMBILAN BULAN, bukan sembilan COUNT terpisah:
        // databasenya jauh, dan sembilan perjalanan pulang-pergi hanya untuk
        // satu kalimat catatan membuat halaman ini menunggu beberapa detik.
        $pilih = [];

        foreach (array_keys(LaporanPerizinanUsahaJasa::BULAN) as $akhiran) {
            $kolom = LaporanPerizinanUsahaJasa::kolomPersen($akhiran);
            $pilih[] = 'SUM(`' . $kolom . '` < 1) AS `' . $akhiran . '`';
        }

        $cacah = DB::table(self::TABEL)->selectRaw(implode(', ', $pilih))->first();
        $sempurna = [];

        foreach (LaporanPerizinanUsahaJasa::BULAN as $akhiran => $nomor) {
            if ((int) ($cacah->{$akhiran} ?? 0) === 0) {
                $sempurna[] = LaporanPerizinanUsahaJasa::LABEL_BULAN[$nomor];
            }
        }

        if ($sempurna !== []) {
            $bagian[] = sprintf(
                'Pada %s seluruh baris mencatat 100%%. Kolomnya memang terisi (bukan kosong), '
                . 'jadi itu dibaca sebagai tidak ada penyimpangan, bukan sebagai data yang '
                . 'belum masuk.',
                implode(' dan ', $sempurna)
            );
        }

        // PEMERIKSAAN SILANG, bukan sekadar catatan. Arti kolom performance_*_pct
        // pernah berubah dari proporsi deviasi menjadi komplemennya, dan
        // perubahan seperti itu tidak mengubah bentuk datanya sama sekali --
        // satu-satunya cara menangkapnya adalah membandingkannya dengan cacah
        // deviasi. Yang ditampilkan tetap kolom persen; selisihnya disebutkan
        // supaya tidak tersembunyi.
        $tidakSepakat = 0;

        foreach ($this->queryDasar($request)->get() as $r) {
            foreach (array_keys(LaporanPerizinanUsahaJasa::BULAN) as $akhiran) {
                $x = LaporanPerizinanUsahaJasa::selBulan($r, $akhiran);

                if ($x !== null && !$x['sepakat']) {
                    $tidakSepakat++;
                }
            }
        }

        if ($tidakSepakat > 0) {
            $bagian[] = sprintf(
                'PERHATIAN: %d sel memiliki kolom performance_*_pct yang tidak sejalan dengan '
                . '1 dikurangi deviasi dibagi total subcontractor. Yang ditampilkan adalah '
                . 'kolom performance_*_pct.',
                $tidakSepakat
            );
        }

        $bagian[] = 'Persentase di halaman ini dibaca dari kolom performance_<bulan>_26_pct '
            . '(rasio 0-1, dikali 100) dan merupakan TINGKAT PEMENUHAN: 100% berarti tidak ada '
            . 'subkontraktor yang menyimpang dan itu hasil terbaik. Urutannya karena itu dari '
            . 'yang terendah.';

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

        $persen = $angka['persen'];

        // Riwayat kombinasi yang sama sepanjang bulan yang ada.
        $riwayat = [];

        foreach (LaporanPerizinanUsahaJasa::BULAN as $a => $n) {
            $x = LaporanPerizinanUsahaJasa::selBulan($row, $a);

            if ($x === null) {
                continue;
            }

            $riwayat[] = [
                'bulan' => $n,
                'label' => LaporanPerizinanUsahaJasa::LABEL_BULAN[$n],
                'persen' => $x['persen'],
                'band' => $this->bandSementara($x['persen']),
                'deviasi' => $x['deviasi'],
                'total' => $x['total'],
                'ini' => $n === $nomor,
            ];
        }

        // Peringkat sel ini di antara kombinasi lain pada bulan yang sama.
        // ARAH NAIK: pemenuhan terendah menempati peringkat 1, karena itu yang
        // paling perlu ditindak.
        $sebulan = [];

        foreach (DB::table(self::TABEL)->get() as $r) {
            $x = LaporanPerizinanUsahaJasa::selBulan($r, $akhiran);

            if ($x === null) {
                continue;
            }

            $sebulan[] = [
                'site' => trim((string) $r->site_dedicated),
                'mitra' => trim((string) $r->main_cont),
                'persen' => $x['persen'],
                'deviasi' => $x['deviasi'],
                'total' => $x['total'],
            ];
        }

        usort($sebulan, static fn (array $a, array $b): int => $a['persen'] <=> $b['persen']);

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
            $kepala[] = LaporanPerizinanUsahaJasa::LABEL_BULAN[$n] . ' (%)';
            $kepala[] = LaporanPerizinanUsahaJasa::LABEL_BULAN[$n] . ' (deviasi)';
        }

        $kepala[] = 'Rata-rata (%)';

        return $this->dtExport(
            'laporan-perizinan-usaha-jasa.csv',
            $kepala,
            $rows->map(static function (array $r): array {
                $baris = [$r['site'], $r['mitra'], $r['subcont']];

                foreach ($r['bulan'] as $b) {
                    $baris[] = $b['ada'] ? number_format($b['persen'], 2, ',', '.') : '-';
                    $baris[] = $b['ada'] && $b['deviasi'] !== null ? $b['deviasi'] : '-';
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
        $penuh = 0.0;
        $dasar = 0;
        $deviasi = 0;

        foreach (LaporanPerizinanUsahaJasa::BULAN as $akhiran => $nomor) {
            $angka = LaporanPerizinanUsahaJasa::selBulan($row, $akhiran);

            if ($angka === null) {
                $bulan[] = ['ada' => false, 'label' => LaporanPerizinanUsahaJasa::LABEL_BULAN[$nomor]];
                continue;
            }

            $persen = $angka['persen'];

            $bulan[] = [
                'ada' => true,
                'label' => LaporanPerizinanUsahaJasa::LABEL_BULAN[$nomor],
                'deviasi' => $angka['deviasi'],
                'persen' => $persen,
                'band' => $this->bandSementara($persen),
            ];

            $penuh += $persen / 100 * $angka['total'];
            $dasar += $angka['total'];
            $deviasi += (int) ($angka['deviasi'] ?? 0);
        }

        $rata = $dasar > 0 ? round($penuh / $dasar * 100, 2) : null;

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
     * Nomor band 1-4 dari ambang SEMENTARA, BERARAH NAIK.
     *
     * Seratus persen pemenuhan adalah hasil terbaik, jadi band 4 ada di atas.
     * Ini bukan Nilai resmi dan tidak pernah ditampilkan sebagai angka Nilai;
     * nomornya hanya menentukan warna sel.
     */
    private function bandSementara(float $persen): int
    {
        [$b2, $b3, $b4] = self::AMBANG_SEMENTARA;

        if ($persen >= $b4) {
            return 4;
        }

        if ($persen >= $b3) {
            return 3;
        }

        if ($persen >= $b2) {
            return 2;
        }

        return 1;
    }

    /** @return array<int, array{band: int, label: string}> */
    private function legendaSementara(): array
    {
        [$b2, $b3, $b4] = self::AMBANG_SEMENTARA;

        return [
            ['band' => 1, 'label' => '<' . $this->pct($b2)],
            ['band' => 2, 'label' => $this->pct($b2) . ' - <' . $this->pct($b3)],
            ['band' => 3, 'label' => $this->pct($b3) . ' - <' . $this->pct($b4)],
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
