<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Concerns\ServesDataTable;
use App\Http\Controllers\Controller;
use App\Services\OhsScoreCard\KesiapanAlatEmergency;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Parameter "Kesiapan alat Emergency".
 *
 * DUA TABEL, DUA PERAN YANG BERBEDA:
 *
 *   emergency_equipment_inventory        -> BASELINE. 4.373 alat, satu baris
 *                                           per no_registrasi (sudah dicek
 *                                           unik). Ini PENYEBUT-nya.
 *   emergency_equipment_daily_inspection -> lembar periksa bulanan. Satu baris
 *                                           per alat per bulan, dengan 31
 *                                           kolom day_NN_condition. Ini yang
 *                                           menentukan PEMBILANG-nya.
 *
 * PENYEBUTNYA SELURUH INVENTARIS, BUKAN YANG DIPERIKSA SAJA. Alat yang bulan
 * itu tidak muncul di lembar periksa dihitung sebagai tidak siap, karena yang
 * ditanyakan memang "di setiap bulannya diperiksa atau engga".
 *
 * SEBUAH ALAT DISEBUT SIAP bila bulan itu ada hari yang terisi DAN tidak satu
 * pun harinya berstatus Not Good, Breakdown, atau Kembali ke CCR. Pilihan ini
 * aman: dari 22.170 baris, hanya 262 (1,2%) yang kondisinya campur dalam satu
 * bulan, jadi aturan "semua harinya Good" dan "ada Good-nya" hampir tidak
 * berbeda hasilnya -- tetapi yang pertama tidak pernah menyebut alat rusak
 * sebagai siap.
 *
 * PERBANDINGANNYA WAJIB NULL-SAFE (`<=>`, bukan `=`). Kolom hari yang kosong
 * membuat `kolom = 'Good'` bernilai NULL, dan satu NULL menular ke seluruh
 * penjumlahan sehingga hasilnya ikut NULL. Dengan `=` biasa, setiap bulan
 * 30 hari dan bulan berjalan yang belum penuh terbuang diam-diam: terukur
 * 0% untuk Juni, September, dan Oktober padahal sebenarnya 95-96%.
 *
 * SITE DIAMBIL DARI INVENTARIS, bukan dari lembar periksa: inventaris adalah
 * baseline-nya, dan ada 137 pasangan yang site-nya berbeda di antara kedua
 * tabel. Memakai site lembar periksa akan membuat sebuah alat berpindah site
 * dari bulan ke bulan sehingga penyebutnya tidak pernah cocok.
 *
 * Band resmi: <80% -> 1, 80-<90% -> 2, 90-<98% -> 3, 98-100% -> 4.
 */
final class KesiapanAlatEmergencyController extends Controller
{
    use ServesDataTable;

    // Nama tabel, aturan "siap", dan cara membaca perusahaan pemilik tinggal
    // di KesiapanAlatEmergency supaya halaman ini dan sel Score Card tidak
    // bisa berangsur berbeda aturan.
    private const TABEL_INVENTARIS = KesiapanAlatEmergency::TABEL_INVENTARIS;

    private const TABEL_INSPEKSI = KesiapanAlatEmergency::TABEL_INSPEKSI;

    /**
     * Bulan tersimpan sebagai nama Indonesia tanpa tahun ("September"), jadi
     * mengurutkannya di SQL hanya menghasilkan urutan abjad yang menyesatkan.
     */
    private const PETA_BULAN = [
        'Januari' => 1, 'Februari' => 2, 'Maret' => 3, 'April' => 4,
        'Mei' => 5, 'Juni' => 6, 'Juli' => 7, 'Agustus' => 8,
        'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12,
    ];

    /** Label untuk alat yang pemiliknya memang tidak bisa ditentukan. */
    private const PEMILIK_TAK_DIKENAL = KesiapanAlatEmergency::PEMILIK_TAK_DIKENAL;

    /** Batas baris yang dikirim ke modal rincian sel. */
    private const BATAS_DETAIL = 200;

    // ======================================================================
    // Halaman
    // ======================================================================

    public function index(): View
    {
        return view('ohs-score-card.kesiapan-alat-emergency.index', [
            'judul' => 'Kesiapan Alat Emergency',
            'penjelasan' => 'Alat emergency yang terperiksa dan seluruh harinya Good, '
                . 'dibagi seluruh alat di inventaris',
            'tabelInventaris' => self::TABEL_INVENTARIS,
            'tabelInspeksi' => self::TABEL_INSPEKSI,
            'legenda' => $this->legendaBand(),
            'filterOptions' => [
                'site' => $this->nilaiBerbeda('site'),
                'pemilik' => $this->daftarPemilik(),
                'kategori' => $this->nilaiBerbeda('kategori_peralatan'),
                'klasifikasi' => $this->nilaiBerbeda('klasifikasi_alat'),
            ],
            'monthOptions' => $this->pilihanBulan(),
        ]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request): JsonResponse
    {
        $baseline = $this->baseline($request);
        $siap = $this->siapPerBulan($request);

        $grid = [];
        $bulanAda = [];

        foreach ($baseline as $kunci => $dasar) {
            $grid[$kunci] = [
                'site' => $dasar['site'],
                'pemilik' => $dasar['pemilik'],
                'total' => $dasar['total'],
                'bulan' => [],
            ];
        }

        foreach ($siap as $kunci => $perBulan) {
            // Alat yang diperiksa tetapi tidak ada di inventaris tidak punya
            // penyebut, jadi tidak bisa dijadikan persentase apa pun.
            if (!isset($grid[$kunci])) {
                continue;
            }

            foreach ($perBulan as $nomor => $angka) {
                $bulanAda[$nomor] = true;
                $grid[$kunci]['bulan'][$nomor] = $angka;
            }
        }

        ksort($bulanAda);
        $months = array_keys($bulanAda);
        $matrix = $this->bangunMatriks($grid, $months);

        return response()->json([
            'months' => $this->judulBulan($months),
            'matrix' => $matrix,
            'kpi' => $this->bangunKpi($matrix, $months),
            'per_site' => $this->ringkasPer($matrix, 'site'),
            'per_pemilik' => $this->ringkasPer($matrix, 'pemilik'),
            'monthly' => $this->deretBulanan($matrix, $months),
            'catatan' => $this->catatan($request),
        ]);
    }

    /**
     * Penyebut: cacah alat di inventaris per site dan perusahaan pemilik.
     *
     * @return array<string, array{site: string, pemilik: string, total: int}>
     */
    private function baseline(Request $request): array
    {
        $rows = $this->queryInventaris($request)
            ->selectRaw(
                'TRIM(site) AS site, '
                . $this->ekspresiPemilik() . ' AS pemilik, '
                . 'COUNT(*) AS total'
            )
            ->groupBy('site', 'pemilik')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $site = (string) $row->site;
            $pemilik = (string) $row->pemilik;

            if ($site === '' || $pemilik === '') {
                continue;
            }

            $out[$site . '|' . $pemilik] = [
                'site' => $site,
                'pemilik' => $pemilik,
                'total' => (int) $row->total,
            ];
        }

        return $out;
    }

    /**
     * Pembilang: cacah alat siap per site, pemilik, dan bulan.
     *
     * @return array<string, array<int, array{siap: int, diperiksa: int}>>
     */
    private function siapPerBulan(Request $request): array
    {
        $rows = $this->queryKesiapan($request)
            ->selectRaw(
                'site, pemilik, bulan, '
                . 'COUNT(*) AS diperiksa, '
                . 'SUM(siap) AS siap'
            )
            ->groupBy('site', 'pemilik', 'bulan')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $nomor = $this->nomorBulan((string) $row->bulan);

            if ($nomor === 0) {
                continue; // nama bulan tak dikenal: jangan diam-diam dianggap bulan lain
            }

            $kunci = trim((string) $row->site) . '|' . trim((string) $row->pemilik);

            $out[$kunci][$nomor] = [
                'siap' => (int) $row->siap,
                'diperiksa' => (int) $row->diperiksa,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $grid
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    private function bangunMatriks(array $grid, array $months): array
    {
        $out = [];

        foreach ($grid as $entry) {
            $total = (int) $entry['total'];

            if ($total <= 0) {
                continue;
            }

            $cells = [];
            $terisi = [];
            $jumlahSiap = 0;
            $jumlahDasar = 0;

            foreach ($months as $month) {
                $angka = $entry['bulan'][$month] ?? null;

                if ($angka === null) {
                    // Bulan tanpa satu pun lembar periksa untuk kelompok ini
                    // berarti belum ada datanya, bukan 0%.
                    $cells[] = ['ada' => false, 'pct' => null, 'nilai' => null];
                    continue;
                }

                $persen = round($angka['siap'] / $total * 100, 2);
                [$nilai, $band] = $this->nilaiUntuk($persen);

                $cells[] = [
                    'ada' => true,
                    'pct' => $persen,
                    'nilai' => $nilai,
                    'nilai_band' => $band,
                    'siap' => $angka['siap'],
                    'diperiksa' => $angka['diperiksa'],
                    'total' => $total,
                ];

                $terisi[] = $persen;
                $jumlahSiap += $angka['siap'];
                $jumlahDasar += $total;
            }

            // RATA-RATANYA TERTIMBANG: dijumlahkan dulu pembilang dan
            // penyebutnya, baru dibagi. Merata-ratakan persentase bulanan
            // memberi bobot sama kepada bulan yang belum lengkap.
            $rata = $jumlahDasar > 0 ? round($jumlahSiap / $jumlahDasar * 100, 2) : null;
            [$nilaiRata, $bandRata] = $this->nilaiUntuk($rata ?? 0.0);

            $out[] = [
                'site' => $entry['site'],
                'pemilik' => $entry['pemilik'],
                'cells' => $cells,
                'average' => $rata,
                'nilai' => $rata === null ? null : $nilaiRata,
                'nilai_band' => $rata === null ? null : $bandRata,
                'total' => $total,
                'bulan_terisi' => count($terisi),
                'terendah' => $terisi !== [] ? min($terisi) : null,
                'trend' => $this->trenDari($terisi),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return ($a['average'] ?? 101.0) <=> ($b['average'] ?? 101.0);
        });

        return $out;
    }

    /** @param array<int, float> $terisi */
    private function trenDari(array $terisi): ?string
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
    private function bangunKpi(array $matrix, array $months): array
    {
        $siap = 0;
        $dasar = 0;
        $semua = [];

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $sel) {
                if (!$sel['ada']) {
                    continue;
                }

                $semua[] = $sel['pct'];
                $siap += $sel['siap'];
                $dasar += $sel['total'];
            }
        }

        $rata = $dasar > 0 ? round($siap / $dasar * 100, 2) : null;
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
            'alat' => $this->cacahAlat($matrix),
        ];
    }

    /** @param array<int, array<string, mixed>> $matrix */
    private function cacahAlat(array $matrix): int
    {
        $total = 0;

        foreach ($matrix as $row) {
            $total += (int) $row['total'];
        }

        return $total;
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function ringkasPer(array $matrix, string $kunci): array
    {
        $kelompok = [];

        foreach ($matrix as $row) {
            foreach ($row['cells'] as $sel) {
                if (!$sel['ada']) {
                    continue;
                }

                $label = (string) $row[$kunci];
                $kelompok[$label]['siap'] = ($kelompok[$label]['siap'] ?? 0) + $sel['siap'];
                $kelompok[$label]['dasar'] = ($kelompok[$label]['dasar'] ?? 0) + $sel['total'];
                $kelompok[$label]['sel'] = ($kelompok[$label]['sel'] ?? 0) + 1;
            }
        }

        $out = [];

        foreach ($kelompok as $label => $angka) {
            if ($angka['dasar'] <= 0) {
                continue;
            }

            $rata = round($angka['siap'] / $angka['dasar'] * 100, 2);
            [$nilai, $band] = $this->nilaiUntuk($rata);

            $out[] = [
                'label' => (string) $label,
                'percent' => $rata,
                'nilai' => $nilai,
                'nilai_band' => $band,
                'sel' => $angka['sel'],
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
    private function deretBulanan(array $matrix, array $months): array
    {
        $data = [];

        foreach (array_keys($months) as $i) {
            $siap = 0;
            $dasar = 0;

            foreach ($matrix as $row) {
                $sel = $row['cells'][$i] ?? null;

                if ($sel === null || !$sel['ada']) {
                    continue;
                }

                $siap += $sel['siap'];
                $dasar += $sel['total'];
            }

            $data[] = $dasar > 0 ? round($siap / $dasar * 100, 2) : null;
        }

        return [
            'labels' => array_map(fn (int $m): string => $this->namaBulan($m), $months),
            'data' => $data,
        ];
    }

    private function catatan(Request $request): ?string
    {
        $inventaris = (int) $this->queryInventaris($request)->count();

        if ($inventaris === 0) {
            return null;
        }

        $catatan = [];

        // Alat yang sepanjang seluruh bulan tidak pernah muncul di lembar
        // periksa sama sekali. Ini bukan "belum ada datanya" yang netral --
        // alat itu memang tidak pernah diperiksa, dan ikut menekan kesiapan.
        $tanpaPeriksa = (int) $this->queryInventaris($request)
            ->whereNotExists(function (Builder $q): void {
                $q->select(DB::raw(1))
                    ->from(self::TABEL_INSPEKSI . ' as d')
                    ->whereColumn('d.no_registrasi', self::TABEL_INVENTARIS . '.no_registrasi');
            })
            ->count();

        if ($tanpaPeriksa > 0) {
            $catatan[] = sprintf(
                '%s dari %s alat di %s tidak pernah muncul di %s. Alat itu tetap '
                . 'ikut menjadi penyebut dan dihitung belum siap, karena yang diukur '
                . 'adalah "di setiap bulannya diperiksa atau tidak".',
                number_format($tanpaPeriksa, 0, ',', '.'),
                number_format($inventaris, 0, ',', '.'),
                self::TABEL_INVENTARIS,
                self::TABEL_INSPEKSI
            );
        }

        $takDikenal = (int) $this->queryInventaris($request)
            ->whereRaw($this->ekspresiPemilik() . ' = ?', [self::PEMILIK_TAK_DIKENAL])
            ->count();

        if ($takDikenal > 0) {
            $catatan[] = sprintf(
                '%s alat tidak bisa dipetakan ke perusahaan pemilik: perusahaan_pemilik '
                . 'kosong dan kepemilikan_peralatan bukan BC, sehingga tidak ada yang bisa '
                . 'dijadikan namanya. Alat itu dikumpulkan di baris "%s", bukan dibuang, '
                . 'supaya penyebutnya tetap utuh.',
                number_format($takDikenal, 0, ',', '.'),
                self::PEMILIK_TAK_DIKENAL
            );
        }

        return $catatan === [] ? null : implode(' ', $catatan);
    }

    // ======================================================================
    // Modal rincian sel
    // ======================================================================

    public function detailBulan(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $pemilik = trim((string) $request->input('pemilik', ''));
        $bulan = (int) $request->input('bulan', 0);

        if ($site === '' || $pemilik === '' || $bulan < 1 || $bulan > 12) {
            return response()->json(['message' => 'Site, perusahaan, dan bulan wajib diisi.'], 422);
        }

        $total = (int) $this->queryInventaris($request)
            ->whereRaw('TRIM(site) = ?', [$site])
            ->whereRaw($this->ekspresiPemilik() . ' = ?', [$pemilik])
            ->count();

        if ($total === 0) {
            return response()->json(['message' => 'Tidak ada alat untuk kombinasi itu.'], 404);
        }

        $namaBulan = $this->namaBulan($bulan);

        $sel = $this->queryKesiapan($request)
            ->where('site', $site)
            ->where('pemilik', $pemilik)
            ->where('bulan', $namaBulan)
            ->selectRaw('COUNT(*) AS diperiksa, SUM(siap) AS siap')
            ->first();

        $siap = (int) ($sel->siap ?? 0);
        $diperiksa = (int) ($sel->diperiksa ?? 0);
        $persen = round($siap / $total * 100, 2);
        [$nilai, $band] = $this->nilaiUntuk($persen);

        // Riwayat kombinasi yang sama sepanjang bulan yang ada, supaya sel ini
        // terbaca dalam konteks, bukan sebagai angka tunggal.
        $riwayat = [];

        foreach ($this->queryKesiapan($request)
            ->where('site', $site)
            ->where('pemilik', $pemilik)
            ->selectRaw('bulan, COUNT(*) AS diperiksa, SUM(siap) AS siap')
            ->groupBy('bulan')
            ->get() as $r) {
            $n = $this->nomorBulan((string) $r->bulan);

            if ($n === 0) {
                continue;
            }

            $p = round((int) $r->siap / $total * 100, 2);
            [$angka, ] = $this->nilaiUntuk($p);

            $riwayat[$n] = [
                'bulan' => $n,
                'label' => $this->namaBulan($n),
                'persen' => $p,
                'nilai' => $angka,
                'ini' => $n === $bulan,
            ];
        }

        ksort($riwayat);

        // Rincian per kategori alat di dalam sel ini: satu perusahaan di satu
        // site bisa memegang ratusan alat dari berbagai kategori, dan yang
        // menarik justru kategori mana yang menyeret angkanya turun.
        $perKategori = $this->queryKesiapan($request)
            ->where('site', $site)
            ->where('pemilik', $pemilik)
            ->where('bulan', $namaBulan)
            ->selectRaw('kategori, COUNT(*) AS diperiksa, SUM(siap) AS siap')
            ->groupBy('kategori')
            ->orderBy('kategori')
            ->get()
            ->map(static fn (object $r): array => [
                'kategori' => (string) $r->kategori,
                'siap' => (int) $r->siap,
                'diperiksa' => (int) $r->diperiksa,
                'persen' => (int) $r->diperiksa > 0
                    ? round((int) $r->siap / (int) $r->diperiksa * 100, 2)
                    : null,
            ])
            ->all();

        // Alat yang bulan itu TIDAK siap, beserta alasannya. Ini yang membuat
        // angka di sel bisa ditelusuri sampai ke nomor registrasinya.
        $belumSiap = $this->queryKesiapan($request)
            ->where('site', $site)
            ->where('pemilik', $pemilik)
            ->where('bulan', $namaBulan)
            ->where('siap', 0)
            ->selectRaw(
                'no_registrasi, nama_peralatan, kategori, '
                . 'hari_isi, hari_good, hari_ng, hari_bd, hari_ccr'
            )
            ->orderBy('no_registrasi')
            ->limit(self::BATAS_DETAIL)
            ->get()
            ->map(fn (object $r): array => [
                'no_registrasi' => (string) $r->no_registrasi,
                'nama' => (string) $r->nama_peralatan,
                'kategori' => (string) $r->kategori,
                'hari_isi' => (int) $r->hari_isi,
                'hari_good' => (int) $r->hari_good,
                'alasan' => $this->alasanBelumSiap($r),
            ])
            ->all();

        return response()->json([
            'judul' => $site . ' · ' . $pemilik,
            'bulan' => $namaBulan,
            'persen' => $persen,
            'nilai' => $nilai,
            'nilai_band' => $band,
            'siap' => $siap,
            'diperiksa' => $diperiksa,
            'total' => $total,
            // Alat yang bulan itu tidak punya lembar periksa sama sekali.
            'tanpa_lembar' => max(0, $total - $diperiksa),
            'target' => $this->target(),
            'memenuhi_target' => $persen >= $this->target(),
            'riwayat' => array_values($riwayat),
            'per_kategori' => $perKategori,
            'belum_siap' => $belumSiap,
            'belum_siap_dipotong' => count($belumSiap) >= self::BATAS_DETAIL,
        ]);
    }

    private function alasanBelumSiap(object $row): string
    {
        if ((int) $row->hari_isi === 0) {
            return 'Lembar periksa ada tetapi tidak satu hari pun diisi';
        }

        $bagian = [];

        foreach ([
            'hari_ng' => 'Not Good',
            'hari_bd' => 'Breakdown',
            'hari_ccr' => 'Kembali ke CCR',
        ] as $kolom => $label) {
            $n = (int) ($row->{$kolom} ?? 0);

            if ($n > 0) {
                $bagian[] = $n . ' hari ' . $label;
            }
        }

        return $bagian === [] ? 'Tidak ada temuan' : implode(' · ', $bagian);
    }

    // ======================================================================
    // Tab Data
    // ======================================================================

    public function data(Request $request): JsonResponse
    {
        $query = $this->queryData($request);

        $rows = (clone $query)
            ->orderBy(
                $this->dtOrderColumn($request, [
                    0 => 'no_registrasi', 1 => 'nama_peralatan', 2 => 'site',
                    3 => 'pemilik', 4 => 'kategori', 6 => 'hari_good',
                ], 'no_registrasi'),
                $this->dtDirection($request, 'asc')
            )
            ->orderBy('no_registrasi')
            ->forPage($this->dtPage($request), $this->dtPageLength($request))
            ->get()
            ->map(fn (object $row): array => $this->sajikan($row))
            ->all();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $this->cacahDasar(),
            'recordsFiltered' => (clone $query)->count(),
            'data' => $rows,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->queryData($request)
            ->orderBy('site')
            ->orderBy('pemilik')
            ->orderBy('no_registrasi')
            ->limit($this->dtExportRowLimit())
            ->get()
            ->map(fn (object $row): array => $this->sajikan($row));

        return $this->dtExport(
            'kesiapan-alat-emergency.csv',
            [
                'No Registrasi', 'Nama Peralatan', 'Site', 'Perusahaan Pemilik',
                'Kategori', 'Bulan', 'Hari Terisi', 'Hari Good', 'Hari Tidak Good',
                'Siap', 'Keterangan',
            ],
            $rows->map(static fn (array $r): array => [
                $r['no_registrasi'], $r['nama'], $r['site'], $r['pemilik'],
                $r['kategori'], $r['bulan'],
                $r['hari_isi'], $r['hari_good'], $r['hari_tidak_good'],
                $r['siap'] ? 'Ya' : 'Tidak',
                $r['alasan'],
            ])->all()
        );
    }

    private function queryData(Request $request): Builder
    {
        $query = $this->queryKesiapan($request);

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            ['no_registrasi', 'nama_peralatan', 'site', 'pemilik', 'kategori']
        );

        return $query;
    }

    /** @return array<string, mixed> */
    private function sajikan(object $row): array
    {
        $nomor = $this->nomorBulan((string) $row->bulan);
        $tidakGood = (int) $row->hari_ng + (int) $row->hari_bd + (int) $row->hari_ccr;

        return [
            'no_registrasi' => (string) $row->no_registrasi,
            'nama' => (string) $row->nama_peralatan,
            'site' => (string) $row->site,
            'pemilik' => (string) $row->pemilik,
            'kategori' => (string) $row->kategori,
            'bulan' => $nomor === 0 ? trim((string) $row->bulan) : $this->namaBulan($nomor),
            'bulan_nomor' => $nomor,
            'hari_isi' => (int) $row->hari_isi,
            'hari_good' => (int) $row->hari_good,
            'hari_tidak_good' => $tidakGood,
            'siap' => (int) $row->siap === 1,
            'alasan' => (int) $row->siap === 1
                ? 'Seluruh hari terisi berstatus Good'
                : $this->alasanBelumSiap($row),
        ];
    }

    private function cacahDasar(): int
    {
        return (int) DB::table(self::TABEL_INSPEKSI)->count();
    }

    // ======================================================================
    // Query inti
    // ======================================================================

    /** Lihat KesiapanAlatEmergency::ekspresiPemilik() untuk alasannya. */
    private function ekspresiPemilik(string $awalan = ''): string
    {
        return KesiapanAlatEmergency::ekspresiPemilik($awalan);
    }

    /**
     * Inventaris sebagai baseline, dengan filter layar diterapkan.
     */
    private function queryInventaris(Request $request): Builder
    {
        $query = DB::table(self::TABEL_INVENTARIS);

        foreach ([
            'site' => 'site',
            'kategori' => 'kategori_peralatan',
            'klasifikasi' => 'klasifikasi_alat',
        ] as $param => $kolom) {
            $nilai = trim((string) $request->input($param, ''));

            if ($nilai !== '') {
                $query->whereRaw('TRIM(' . $kolom . ') = ?', [$nilai]);
            }
        }

        $pemilik = trim((string) $request->input('pemilik', ''));

        if ($pemilik !== '') {
            $query->whereRaw($this->ekspresiPemilik() . ' = ?', [$pemilik]);
        }

        return $query;
    }

    /**
     * Satu baris per alat per bulan, sudah lengkap dengan cacah hari dan
     * penanda siap. Site, pemilik, dan kategorinya diambil dari INVENTARIS
     * lewat JOIN, bukan dari lembar periksa.
     *
     * JOIN-nya INNER dan itu disengaja: alat yang diperiksa tetapi tidak ada
     * di inventaris tidak punya penyebut, jadi tidak bisa dijadikan persentase.
     */
    private function queryKesiapan(Request $request): Builder
    {
        $query = DB::query()
            ->fromSub(KesiapanAlatEmergency::perAlat(), 'p')
            ->join(self::TABEL_INVENTARIS . ' as i', 'i.no_registrasi', '=', 'p.no_registrasi')
            ->selectRaw(
                'p.no_registrasi, i.nama_peralatan, '
                . 'TRIM(i.site) AS site, '
                . $this->ekspresiPemilik('i') . ' AS pemilik, '
                . 'TRIM(i.kategori_peralatan) AS kategori, '
                . 'p.bulan, p.hari_isi, p.hari_good, p.hari_ng, p.hari_bd, p.hari_ccr, '
                . KesiapanAlatEmergency::ekspresiSiap('p') . ' AS siap'
            );

        foreach ([
            'site' => 'i.site',
            'kategori' => 'i.kategori_peralatan',
            'klasifikasi' => 'i.klasifikasi_alat',
        ] as $param => $kolom) {
            $nilai = trim((string) $request->input($param, ''));

            if ($nilai !== '') {
                $query->whereRaw('TRIM(' . $kolom . ') = ?', [$nilai]);
            }
        }

        $pemilik = trim((string) $request->input('pemilik', ''));

        if ($pemilik !== '') {
            $query->whereRaw($this->ekspresiPemilik('i') . ' = ?', [$pemilik]);
        }

        $bulan = (int) $request->input('bulan_filter', 0);

        if ($bulan >= 1 && $bulan <= 12) {
            $query->where('p.bulan', $this->namaBulan($bulan));
        }

        // Dibungkus sekali lagi supaya alias site/pemilik/kategori/siap bisa
        // dipakai di WHERE dan ORDER BY oleh pemanggilnya.
        return DB::query()->fromSub($query, 'k');
    }

    // ======================================================================
    // Band
    // ======================================================================

    /**
     * Nilai berkoma untuk satu capaian.
     *
     * Band teratas rata di 4,00; band di bawahnya melandai di dalam rentangnya
     * sendiri dan dijepit di x,99 supaya tidak pernah menyentuh band atasnya.
     *
     * @return array{0: float, 1: string}
     */
    private function nilaiUntuk(float $persen): array
    {
        if ($persen >= 98.0) {
            return [4.0, '98% - 100%'];
        }

        if ($persen >= 90.0) {
            return [round(min(3.0 + ($persen - 90.0) / 8.0, 3.99), 2), '90% - <98%'];
        }

        if ($persen >= 80.0) {
            return [round(min(2.0 + ($persen - 80.0) / 10.0, 2.99), 2), '80% - <90%'];
        }

        return [round(min(1.0 + $persen / 80.0, 1.99), 2), '<80%'];
    }

    /** @return array<int, array{nilai: int, label: string}> */
    private function legendaBand(): array
    {
        return [
            ['nilai' => 1, 'label' => '<80%'],
            ['nilai' => 2, 'label' => '80-<90%'],
            ['nilai' => 3, 'label' => '90-<98%'],
            ['nilai' => 4, 'label' => '98-100%'],
        ];
    }

    private function target(): float
    {
        return 98.0;
    }

    // ======================================================================
    // Pembantu
    // ======================================================================

    /** @return array<int, string> */
    private function nilaiBerbeda(string $kolom): array
    {
        return DB::table(self::TABEL_INVENTARIS)
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
    private function daftarPemilik(): array
    {
        return DB::table(self::TABEL_INVENTARIS)
            ->selectRaw($this->ekspresiPemilik() . ' AS pemilik')
            ->distinct()
            ->orderBy('pemilik')
            ->pluck('pemilik')
            ->map(static fn ($v): string => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function pilihanBulan(): array
    {
        $ada = DB::table(self::TABEL_INSPEKSI)
            ->distinct()
            ->pluck('bulan')
            ->map(fn ($v): int => $this->nomorBulan((string) $v))
            ->filter(static fn (int $n): bool => $n > 0)
            ->unique()
            ->sort()
            ->values();

        $out = [];

        foreach ($ada as $n) {
            $out[$n] = $this->namaBulan($n);
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $months
     * @return array<int, array<string, mixed>>
     */
    private function judulBulan(array $months): array
    {
        $out = [];

        foreach ($months as $n) {
            $label = $this->namaBulan($n);

            $out[] = ['number' => $n, 'label' => $label, 'short' => mb_substr($label, 0, 3)];
        }

        return $out;
    }

    private function nomorBulan(string $nama): int
    {
        $bersih = trim($nama);

        foreach (self::PETA_BULAN as $label => $nomor) {
            if (strcasecmp($bersih, $label) === 0) {
                return $nomor;
            }
        }

        return 0;
    }

    private function namaBulan(int $nomor): string
    {
        foreach (self::PETA_BULAN as $label => $n) {
            if ($n === $nomor) {
                return $label;
            }
        }

        return '-';
    }
}
