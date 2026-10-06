<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Concerns\ServesDataTable;
use App\Http\Controllers\Controller;
use App\Services\OhsScoreCard\PemenuhanRegulasi;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Parameter "Pemenuhan Regulasi".
 *
 * DUA TABEL YANG TIDAK BISA DIGABUNG, dan itu hal terpenting di berkas ini:
 *
 *   regulatory_compliance_summary -> 19 baris, satu per department x
 *                                    user_group, dengan tiga sektor sebagai
 *                                    KOLOM. Ini yang jadi matriks.
 *   regulatory_compliance_detail  -> 363 baris, daftar regulasinya sendiri.
 *                                    TIDAK punya kolom department maupun
 *                                    user_group sama sekali.
 *
 * JANGAN MENJUMLAHKAN KEDUANYA. Detail mencatat 5.020 kewajiban sedangkan
 * summary 3.115; keduanya memang cakupan yang berbeda, bukan salah satu
 * kurang lengkap. Sektornya pun beda kosakata: summary memakai "Environment"
 * sementara detail menulis "Lingkungan Hidup", detail punya "Technical" yang
 * tidak ada di summary, dan summary punya "Additional Compliance" yang tidak
 * ada di detail. Memasangkannya lewat nama sektor akan menghasilkan angka
 * yang kelihatan masuk akal tetapi salah. Karena itu halaman ini menaruhnya
 * di dua tab terpisah dan tidak pernah menjumlahkan silang.
 *
 * ANGKANYA DISIMPAN SEBAGAI VARCHAR, BUKAN ANGKA, dan sel kosong ditulis "-"
 * (tanda hubung), bukan NULL. Membacanya dengan (float) langsung membuat "-"
 * menjadi 0 dan menyeret rata-rata turun tanpa dasar: 4 dari 19 baris memakai
 * "-" di Additional Compliance dan Environment. Semua pembacaan angka lewat
 * angka().
 *
 * RUMUS KEPATUHAN SUDAH DIBUKTIKAN dari datanya sendiri:
 * complied / (complied + in_progress), cocok di 49 dari 49 sel yang berangka,
 * tanpa satu pun selisih. Jadi persentase dihitung ulang dari cacahnya --
 * bukan dibaca dari kolom rate -- supaya rata-ratanya bisa TERTIMBANG.
 *
 * TIDAK ADA KOLOM BULAN DI MANA PUN. Kedua tabel ini potret satu waktu, bukan
 * deret bulanan, jadi halaman ini sengaja tidak punya penyaring bulan dan
 * tidak menggambar tren.
 *
 * BAND RESMINYA BELUM ADA. Ambang di AMBANG_SEMENTARA bukan dari tabel band
 * resmi, melainkan dipakai supaya selnya terbaca. Karena itu halaman ini
 * TIDAK menampilkan angka Nilai 1-4 sama sekali -- menampilkannya berarti
 * mengarang skor resmi.
 */
final class PemenuhanRegulasiController extends Controller
{
    use ServesDataTable;

    // Nama tabel, daftar sektor, dan cara membaca angka bertanda "-" tinggal
    // di PemenuhanRegulasi supaya halaman ini dan baris Score Card tidak bisa
    // berangsur berbeda aturan.
    private const TABEL_RINGKASAN = PemenuhanRegulasi::TABEL_RINGKASAN;

    private const TABEL_DETAIL = PemenuhanRegulasi::TABEL_DETAIL;

    private const SEKTOR = PemenuhanRegulasi::SEKTOR;

    /**
     * Ambang warna SEMENTARA, bukan band resmi.
     *
     * Dipakai hanya untuk mewarnai sel supaya yang rendah langsung kelihatan.
     * Begitu band resminya ada, ganti di sini saja -- tidak ada ambang lain
     * yang tersebar di view, karena nomor bandnya dikirim dari controller.
     */
    private const AMBANG_SEMENTARA = [95.0, 98.0, 100.0];

    /** Batas baris yang dikirim ke modal rincian sel. */
    private const BATAS_DETAIL = 200;

    // ======================================================================
    // Halaman
    // ======================================================================

    public function index(): View
    {
        return view('ohs-score-card.pemenuhan-regulasi.index', [
            'judul' => 'Pemenuhan Regulasi',
            'penjelasan' => 'Kewajiban regulasi yang sudah dipenuhi dibagi seluruh '
                . 'kewajiban yang ditugaskan, per site dan perusahaan',
            'tabelRingkasan' => self::TABEL_RINGKASAN,
            'tabelDetail' => self::TABEL_DETAIL,
            'sektor' => self::SEKTOR,
            'legenda' => $this->legendaSementara(),
            'filterOptions' => [
                'site' => $this->nilaiBerbeda(self::TABEL_RINGKASAN, 'department'),
                'mitra' => $this->nilaiBerbeda(self::TABEL_RINGKASAN, 'user_group'),
                'sektor_detail' => $this->nilaiBerbeda(self::TABEL_DETAIL, 'regulation_sector'),
                'kategori_detail' => $this->nilaiBerbeda(self::TABEL_DETAIL, 'regulation_category'),
            ],
        ]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request): JsonResponse
    {
        $sektorDipilih = $this->sektorDipilih($request);
        $baris = [];

        foreach ($this->queryRingkasan($request)->orderBy('no')->get() as $r) {
            $site = trim((string) $r->department);
            $mitra = trim((string) $r->user_group);

            if ($site === '' || $mitra === '') {
                continue;
            }

            $sel = [];
            $patuh = 0.0;
            $dasar = 0.0;

            foreach ($sektorDipilih as $kunci => $label) {
                $c = $this->angka($r->{'complied_' . $kunci} ?? null);
                $p = $this->angka($r->{'in_progress_' . $kunci} ?? null);

                // "-" berarti sektor itu memang tidak ditugaskan ke kombinasi
                // ini, bukan nol persen.
                if ($c === null && $p === null) {
                    $sel[] = ['ada' => false, 'pct' => null, 'band' => null];
                    continue;
                }

                $c ??= 0.0;
                $p ??= 0.0;
                $total = $c + $p;

                if ($total <= 0.0) {
                    $sel[] = ['ada' => false, 'pct' => null, 'band' => null];
                    continue;
                }

                $persen = round($c / $total * 100, 2);

                $sel[] = [
                    'ada' => true,
                    'pct' => $persen,
                    'band' => $this->bandSementara($persen),
                    'patuh' => $c,
                    'proses' => $p,
                    'total' => $total,
                    // Kolom rate bawaan disimpan apa adanya supaya selisih
                    // dengan hitungan sendiri bisa ketahuan kalau sumbernya
                    // berubah aturan.
                    'rate_sumber' => $this->angka($r->{'compliance_rate_' . $kunci} ?? null),
                ];

                $patuh += $c;
                $dasar += $total;
            }

            // RATA-RATANYA TERTIMBANG: dijumlahkan dulu kewajibannya, baru
            // dibagi. Merata-ratakan persentase antar sektor memberi bobot
            // sama kepada sektor berisi 3 kewajiban dan sektor berisi 300.
            $rata = $dasar > 0.0 ? round($patuh / $dasar * 100, 2) : null;

            $baris[] = [
                'site' => $site,
                'mitra' => $mitra,
                'cells' => $sel,
                'average' => $rata,
                'band' => $rata === null ? null : $this->bandSementara($rata),
                'patuh' => $patuh,
                'proses' => $dasar - $patuh,
                'total' => $dasar,
                'sektor_terisi' => count(array_filter($sel, static fn (array $s): bool => $s['ada'])),
            ];
        }

        usort($baris, static function (array $a, array $b): int {
            return ($a['average'] ?? 101.0) <=> ($b['average'] ?? 101.0);
        });

        return response()->json([
            'sektor' => array_values(array_map(
                static fn (string $l): array => ['label' => $l, 'short' => $l],
                $sektorDipilih
            )),
            'sektor_kunci' => array_keys($sektorDipilih),
            'matrix' => $baris,
            'kpi' => $this->bangunKpi($baris),
            'per_site' => $this->ringkasPer($baris, 'site'),
            'per_mitra' => $this->ringkasPer($baris, 'mitra'),
            'per_sektor' => $this->ringkasSektor($baris, $sektorDipilih),
            'catatan' => $this->catatan($request),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     * @return array<string, mixed>
     */
    private function bangunKpi(array $baris): array
    {
        $patuh = 0.0;
        $total = 0.0;
        $terendah = null;
        $penuh = 0;

        foreach ($baris as $b) {
            $patuh += $b['patuh'];
            $total += $b['total'];

            if ($b['average'] === null) {
                continue;
            }

            $terendah = $terendah === null ? $b['average'] : min($terendah, $b['average']);

            if ($b['average'] >= 100.0) {
                $penuh++;
            }
        }

        $rata = $total > 0.0 ? round($patuh / $total * 100, 2) : null;

        return [
            'rata' => $rata,
            'band' => $rata === null ? null : $this->bandSementara($rata),
            'patuh' => $patuh,
            'proses' => $total - $patuh,
            'total' => $total,
            'kombinasi' => count($baris),
            'kombinasi_penuh' => $penuh,
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
            $kelompok[$label]['patuh'] = ($kelompok[$label]['patuh'] ?? 0.0) + $b['patuh'];
            $kelompok[$label]['total'] = ($kelompok[$label]['total'] ?? 0.0) + $b['total'];
            $kelompok[$label]['baris'] = ($kelompok[$label]['baris'] ?? 0) + 1;
        }

        $out = [];

        foreach ($kelompok as $label => $a) {
            if ($a['total'] <= 0.0) {
                continue;
            }

            $persen = round($a['patuh'] / $a['total'] * 100, 2);

            $out[] = [
                'label' => (string) $label,
                'percent' => $persen,
                'band' => $this->bandSementara($persen),
                'patuh' => $a['patuh'],
                'total' => $a['total'],
                'sel' => $a['baris'],
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['percent'] <=> $b['percent']);

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     * @param  array<string, string>  $sektor
     * @return array<int, array<string, mixed>>
     */
    private function ringkasSektor(array $baris, array $sektor): array
    {
        $out = [];
        $i = 0;

        foreach ($sektor as $label) {
            $patuh = 0.0;
            $total = 0.0;
            $terisi = 0;

            foreach ($baris as $b) {
                $sel = $b['cells'][$i] ?? null;

                if ($sel === null || !$sel['ada']) {
                    continue;
                }

                $patuh += $sel['patuh'];
                $total += $sel['total'];
                $terisi++;
            }

            if ($total > 0.0) {
                $persen = round($patuh / $total * 100, 2);

                $out[] = [
                    'label' => $label,
                    'percent' => $persen,
                    'band' => $this->bandSementara($persen),
                    'patuh' => $patuh,
                    'total' => $total,
                    'sel' => $terisi,
                ];
            }

            $i++;
        }

        usort($out, static fn (array $a, array $b): int => $a['percent'] <=> $b['percent']);

        return $out;
    }

    private function catatan(Request $request): ?string
    {
        $kosong = 0;

        foreach ($this->queryRingkasan($request)->get() as $r) {
            foreach (array_keys(self::SEKTOR) as $k) {
                if ($this->angka($r->{'complied_' . $k} ?? null) === null
                    && $this->angka($r->{'in_progress_' . $k} ?? null) === null) {
                    $kosong++;
                }
            }
        }

        $bagian = [];

        if ($kosong > 0) {
            $bagian[] = sprintf(
                '%d sel di %s ditulis "-" alih-alih angka. Itu berarti sektor tersebut '
                . 'memang tidak ditugaskan ke kombinasi itu, jadi tidak ikut dihitung '
                . 'dan BUKAN dianggap 0%%.',
                $kosong,
                self::TABEL_RINGKASAN
            );
        }

        $bagian[] = sprintf(
            'Tab Data memuat %s, yaitu daftar regulasinya sendiri. Tabel itu tidak punya '
            . 'kolom site maupun perusahaan, cakupannya berbeda dari ringkasan di tab ini, '
            . 'dan kosakata sektornya pun berbeda — jadi angka di kedua tab tidak bisa '
            . 'dijumlahkan atau dipasangkan satu sama lain.',
            self::TABEL_DETAIL
        );

        return implode(' ', $bagian);
    }

    // ======================================================================
    // Modal rincian sel
    // ======================================================================

    public function detailSel(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));
        $kunci = trim((string) $request->input('sektor', ''));

        if ($site === '' || $mitra === '' || !isset(self::SEKTOR[$kunci])) {
            return response()->json(['message' => 'Site, perusahaan, dan sektor wajib diisi.'], 422);
        }

        $row = DB::table(self::TABEL_RINGKASAN)
            ->whereRaw('TRIM(department) = ?', [$site])
            ->whereRaw('TRIM(user_group) = ?', [$mitra])
            ->first();

        if ($row === null) {
            return response()->json(['message' => 'Tidak ada data untuk kombinasi itu.'], 404);
        }

        $c = $this->angka($row->{'complied_' . $kunci} ?? null);
        $p = $this->angka($row->{'in_progress_' . $kunci} ?? null);

        if ($c === null && $p === null) {
            return response()->json(['message' => 'Sektor itu tidak ditugaskan ke kombinasi ini.'], 404);
        }

        $c ??= 0.0;
        $p ??= 0.0;
        $total = $c + $p;
        $persen = $total > 0.0 ? round($c / $total * 100, 2) : null;

        // Sektor lain milik kombinasi yang sama, supaya sel ini terbaca dalam
        // konteks dan bukan sebagai angka tunggal.
        $sektorLain = [];

        foreach (self::SEKTOR as $k => $label) {
            $cc = $this->angka($row->{'complied_' . $k} ?? null);
            $pp = $this->angka($row->{'in_progress_' . $k} ?? null);

            if ($cc === null && $pp === null) {
                $sektorLain[] = [
                    'label' => $label, 'persen' => null, 'patuh' => null,
                    'total' => null, 'ini' => $k === $kunci, 'ditugaskan' => false,
                ];
                continue;
            }

            $cc ??= 0.0;
            $pp ??= 0.0;
            $t = $cc + $pp;

            $sektorLain[] = [
                'label' => $label,
                'persen' => $t > 0.0 ? round($cc / $t * 100, 2) : null,
                'patuh' => $cc,
                'total' => $t,
                'ini' => $k === $kunci,
                'ditugaskan' => true,
            ];
        }

        // Peringkat sel ini di antara kombinasi lain pada sektor yang sama.
        $sesektor = [];

        foreach (DB::table(self::TABEL_RINGKASAN)->get() as $r) {
            $cc = $this->angka($r->{'complied_' . $kunci} ?? null);
            $pp = $this->angka($r->{'in_progress_' . $kunci} ?? null);

            if ($cc === null && $pp === null) {
                continue;
            }

            $cc ??= 0.0;
            $pp ??= 0.0;
            $t = $cc + $pp;

            if ($t <= 0.0) {
                continue;
            }

            $sesektor[] = [
                'site' => trim((string) $r->department),
                'mitra' => trim((string) $r->user_group),
                'persen' => round($cc / $t * 100, 2),
                'patuh' => $cc,
                'total' => $t,
            ];
        }

        usort($sesektor, static fn (array $a, array $b): int => $b['persen'] <=> $a['persen']);

        $peringkat = 0;

        foreach ($sesektor as $i => $s) {
            if ($s['site'] === $site && $s['mitra'] === $mitra) {
                $peringkat = $i + 1;
                break;
            }
        }

        return response()->json([
            'judul' => $site . ' · ' . $mitra,
            'sektor' => self::SEKTOR[$kunci],
            'persen' => $persen,
            'band' => $persen === null ? null : $this->bandSementara($persen),
            'patuh' => $c,
            'proses' => $p,
            'total' => $total,
            'rate_sumber' => $this->angka($row->{'compliance_rate_' . $kunci} ?? null),
            'peringkat' => $peringkat,
            'dari' => count($sesektor),
            'sektor_lain' => $sektorLain,
            'sesektor' => array_slice($sesektor, 0, self::BATAS_DETAIL),
        ]);
    }

    // ======================================================================
    // Tab Data — daftar regulasi (regulatory_compliance_detail)
    // ======================================================================

    public function data(Request $request): JsonResponse
    {
        $query = $this->queryDetail($request);

        $rows = (clone $query)
            ->orderBy(
                $this->dtOrderColumn($request, [
                    0 => 'regulation_name', 1 => 'regulations', 2 => 'regulation_sector',
                    3 => 'regulation_category', 4 => 'total_obligations',
                    5 => 'complied', 6 => 'compliance_rate_pct',
                ], 'no'),
                $this->dtDirection($request, 'asc')
            )
            ->orderBy('no')
            ->forPage($this->dtPage($request), $this->dtPageLength($request))
            ->get()
            ->map(fn (object $row): array => $this->sajikan($row))
            ->all();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => (int) DB::table(self::TABEL_DETAIL)->count(),
            'recordsFiltered' => (clone $query)->count(),
            'data' => $rows,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->queryDetail($request)
            ->orderBy('no')
            ->limit($this->dtExportRowLimit())
            ->get()
            ->map(fn (object $row): array => $this->sajikan($row));

        return $this->dtExport(
            'pemenuhan-regulasi.csv',
            [
                'No', 'Regulasi', 'Nama Regulasi', 'Sektor', 'Kategori', 'Acuan',
                'Total Kewajiban', 'Patuh', 'Dalam Proses', 'Belum Ditindak',
                'Kepatuhan (%)', 'Tautan',
            ],
            $rows->map(static fn (array $r): array => [
                $r['no'], $r['regulasi'], $r['nama'], $r['sektor'], $r['kategori'],
                $r['acuan'], $r['total'], $r['patuh'], $r['proses'], $r['sisa'],
                $r['persen'] === null ? '-' : number_format((float) $r['persen'], 2, ',', '.'),
                $r['tautan'],
            ])->all()
        );
    }

    private function queryDetail(Request $request): Builder
    {
        $query = DB::table(self::TABEL_DETAIL);

        $this->dtApplyEqualsFilters($query, $request, [
            'sektor_detail' => 'regulation_sector',
            'kategori_detail' => 'regulation_category',
        ]);

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            ['regulations', 'regulation_name', 'regulation_sector', 'regulation_category']
        );

        return $query;
    }

    /** @return array<string, mixed> */
    private function sajikan(object $row): array
    {
        $total = (int) ($row->total_obligations ?? 0);
        $patuh = (int) ($row->complied ?? 0);
        $proses = (int) ($row->in_progress ?? 0);

        return [
            'no' => (int) ($row->no ?? 0),
            'regulasi' => (string) ($row->regulations ?? ''),
            'nama' => (string) ($row->regulation_name ?? ''),
            'sektor' => (string) ($row->regulation_sector ?? ''),
            'kategori' => (string) ($row->regulation_category ?? ''),
            'acuan' => (string) ($row->regulatory_reference ?? ''),
            'total' => $total,
            'patuh' => $patuh,
            'proses' => $proses,
            // DI 40 DARI 363 BARIS, total_obligations LEBIH BESAR daripada
            // patuh + proses. Selisihnya kewajiban yang belum berstatus apa
            // pun, dan ditampilkan apa adanya alih-alih dibulatkan hilang.
            'sisa' => max(0, $total - $patuh - $proses),
            'persen' => $row->compliance_rate_pct === null
                ? null
                : round((float) $row->compliance_rate_pct, 2),
            'band' => $row->compliance_rate_pct === null
                ? null
                : $this->bandSementara((float) $row->compliance_rate_pct),
            'tautan' => (string) ($row->regulation_link ?? ''),
        ];
    }

    // ======================================================================
    // Pembantu
    // ======================================================================

    private function queryRingkasan(Request $request): Builder
    {
        $query = DB::table(self::TABEL_RINGKASAN);

        foreach (['site' => 'department', 'mitra' => 'user_group'] as $param => $kolom) {
            $nilai = trim((string) $request->input($param, ''));

            if ($nilai !== '') {
                $query->whereRaw('TRIM(' . $kolom . ') = ?', [$nilai]);
            }
        }

        return $query;
    }

    /** @return array<string, string> */
    private function sektorDipilih(Request $request): array
    {
        $pilih = trim((string) $request->input('sektor', ''));

        return isset(self::SEKTOR[$pilih])
            ? [$pilih => self::SEKTOR[$pilih]]
            : self::SEKTOR;
    }

    /** Lihat PemenuhanRegulasi::angka() -- "-" berarti tidak ditugaskan. */
    private function angka(mixed $mentah): ?float
    {
        return PemenuhanRegulasi::angka($mentah);
    }

    /**
     * Nomor band 1-4 dari ambang SEMENTARA.
     *
     * Ini bukan Nilai resmi dan tidak pernah ditampilkan sebagai angka Nilai;
     * nomornya hanya menentukan warna sel.
     */
    private function bandSementara(float $persen): int
    {
        [$b1, $b2, $b3] = self::AMBANG_SEMENTARA;

        if ($persen >= $b3) {
            return 4;
        }

        if ($persen >= $b2) {
            return 3;
        }

        if ($persen >= $b1) {
            return 2;
        }

        return 1;
    }

    /** @return array<int, array{band: int, label: string}> */
    private function legendaSementara(): array
    {
        [$b1, $b2, $b3] = self::AMBANG_SEMENTARA;

        return [
            ['band' => 1, 'label' => '<' . $this->pct($b1)],
            ['band' => 2, 'label' => $this->pct($b1) . ' - <' . $this->pct($b2)],
            ['band' => 3, 'label' => $this->pct($b2) . ' - <' . $this->pct($b3)],
            ['band' => 4, 'label' => $this->pct($b3)],
        ];
    }

    private function pct(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',') . '%';
    }

    /** @return array<int, string> */
    private function nilaiBerbeda(string $tabel, string $kolom): array
    {
        return DB::table($tabel)
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
