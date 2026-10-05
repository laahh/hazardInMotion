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
 * Dasar bersama untuk dua parameter sertifikasi kompetensi:
 *
 *   competency_pengawas_teknis -> Pemenuhan Sertifikasi Pengawas Teknis
 *   competency_tenaga_teknis   -> Pemenuhan Sertifikasi Tenaga Teknis
 *
 * Keduanya berbagi bentuk tabel, rumus, band, dan seluruh tampilan; yang
 * berbeda hanya nama tabel dan sebutan perannya. Karena itu logikanya tinggal
 * di sini dan tiap halaman cukup jadi subclass tipis.
 *
 * YANG DIHITUNG ORANG, BUKAN BARIS. Satu karyawan punya banyak baris -- satu
 * untuk tiap pasangan dokumen x izin kerja -- sehingga 12.051 baris pengawas
 * teknis hanya mewakili 314 orang. Menghitung per baris akan melipatgandakan
 * orang yang izin kerjanya banyak. Rumusnya:
 *
 *     persen = COUNT(DISTINCT orang bersertifikat) / COUNT(DISTINCT orang) x 100
 *
 * PENANDA BERSERTIFIKAT ADALAH KOLOM sertifikasi YANG TERISI, dan itu bukan
 * tebakan melainkan hasil pengukuran:
 *
 *   - Pengawas teknis: dokumen_karyawan terisi untuk 314 dari 314 orang
 *     (100%), jadi kolom itu tidak membedakan apa pun. sertifikasi terisi
 *     untuk 71 orang (22,6%).
 *   - Tenaga teknis: sertifikasi_sesuai_dokumen bernilai 1 di SELURUH 6.043
 *     baris, jadi kolom itu pun tidak membedakan apa pun. sertifikasi terisi
 *     untuk 1.042 dari 3.069 orang (34,0%), persis sama dengan
 *     dokumen_karyawan.
 *
 * TIDAK ADA KOLOM BULAN di kedua tabel; yang ada hanya created_at, yaitu
 * waktu impor dan seluruhnya jatuh pada satu hari. Karena itu halaman ini
 * TIDAK punya matriks "Capaian per Bulan" seperti parameter lain. Sumbu
 * keduanya diisi perusahaan, sehingga matriksnya site x perusahaan.
 *
 * SATU ORANG BISA TERCATAT DI LEBIH DARI SATU SITE/PERUSAHAAN: ada 329
 * kombinasi nama+site+perusahaan untuk 314 nama pengawas. Di dalam satu sel
 * matriks hal itu tidak jadi soal, tetapi angka keseluruhan TIDAK BOLEH
 * dijumlahkan dari sel-selnya -- orang yang sama akan terhitung dua kali.
 * Karena itu KPI dihitung ulang dengan DISTINCT atas seluruh data.
 */
abstract class AbstractSertifikasiKompetensiController extends Controller
{
    use ServesDataTable;

    protected const COL_NAMA = 'nama_karyawan';
    protected const COL_SITE = 'nama_site';
    protected const COL_PERUSAHAAN = 'perusahaan';
    protected const COL_KATEGORI = 'kategori_work_permit';
    protected const COL_DOKUMEN = 'dokumen_karyawan';
    protected const COL_IZIN = 'work_permit';
    protected const COL_SERTIFIKASI = 'sertifikasi';

    /**
     * Band resmi parameter ini: [batas bawah, batas atas, nilai dasar, label].
     *
     * Urutannya terbaik dulu, dan nilainya berkoma: di dalam satu band nilai
     * melandai mengikuti posisi capaian di antara kedua batasnya, sehingga
     * 70% bernilai 3,50 dan bukan 3.
     */
    protected const SCORE_BANDS = [
        [80.0, 100.0, 4, '80% - 100%'],
        [60.0, 80.0, 3, '60% - <80%'],
        [50.0, 60.0, 2, '50% - <60%'],
        [0.0, 50.0, 1, '<50%'],
    ];

    /** Pintu masuk band tertinggi. */
    protected const TARGET_PERCENT = 80.0;

    /** Batas baris yang dikirim ke modal rincian sel. */
    protected const DETAIL_LIMIT = 300;

    /**
     * Kolom yang bisa diurutkan di tab Data, memakai ALIAS hasil subquery di
     * dataQuery() -- bukan nama kolom tabel sumber. Mengurutkan dengan
     * nama_karyawan di sini akan gagal, karena di luar subquery kolom itu
     * sudah bernama "nama".
     */
    protected const ORDERABLE = [
        0 => 'nama',
        1 => 'site',
        2 => 'mitra',
        3 => 'izin',
        5 => 'bersertifikat',
    ];

    /** Nama tabel sumber. */
    abstract protected function tabel(): string;

    /** Sebutan peran, mis. "Pengawas Teknis". */
    abstract protected function peran(): string;

    /** Awalan rute, mis. "sertifikasi-pengawas-teknis". */
    abstract protected function slug(): string;

    // ======================================================================
    // Halaman
    // ======================================================================

    public function index(): View
    {
        return view('ohs-score-card.sertifikasi-kompetensi.index', [
            'peran' => $this->peran(),
            'slug' => $this->slug(),
            'tabel' => $this->tabel(),
            'target' => self::TARGET_PERCENT,
            'bands' => $this->bandUntukView(),
            'filterOptions' => [
                'site' => $this->distinctValues(self::COL_SITE),
                'perusahaan' => $this->distinctValues(self::COL_PERUSAHAAN),
                'izin' => $this->distinctValues(self::COL_IZIN),
            ],
        ]);
    }

    // ======================================================================
    // Tab Ringkasan
    // ======================================================================

    public function overview(Request $request): JsonResponse
    {
        $perSel = $this->agregatPer($request, [self::COL_SITE, self::COL_PERUSAHAAN]);

        $sites = $this->urutDariAgregat($perSel, 'site');
        $perusahaan = $this->urutDariAgregat($perSel, 'mitra');

        return response()->json([
            'sites' => $sites,
            'perusahaan' => $perusahaan,
            'matrix' => $this->buildMatrix($perSel, $sites, $perusahaan),
            'kpi' => $this->buildKpi($request),
            'per_site' => $this->ringkasPer($request, self::COL_SITE),
            'per_mitra' => $this->ringkasPer($request, self::COL_PERUSAHAAN),
            'per_izin' => $this->ringkasPer($request, self::COL_IZIN, 12),
            'catatan' => $this->catatan($request),
        ]);
    }

    /**
     * Agregat orang unik per kombinasi kolom.
     *
     * @param  array<int, string>  $kolom
     * @return array<string, array<string, mixed>>
     */
    protected function agregatPer(Request $request, array $kolom, ?int $limit = null): array
    {
        $pilih = [];

        foreach ($kolom as $i => $k) {
            $pilih[] = $this->kutip($k) . ' AS k' . $i;
        }

        $query = $this->baseQuery($request)
            ->selectRaw(implode(', ', $pilih) . ', ' . $this->ekspresiAgregat())
            ->groupBy(array_map(static fn (int $i): string => 'k' . $i, array_keys($kolom)))
            ->orderByDesc('total');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $out = [];

        foreach ($query->get() as $row) {
            $kunci = [];

            foreach (array_keys($kolom) as $i) {
                $kunci[] = trim((string) $row->{'k' . $i});
            }

            $out[implode('|', $kunci)] = $this->bentukNilai(
                $kunci,
                (int) $row->total,
                (int) $row->bersertifikat
            );
        }

        return $out;
    }

    /** COUNT(DISTINCT orang) dan COUNT(DISTINCT orang bersertifikat). */
    protected function ekspresiAgregat(): string
    {
        $nama = $this->kutip(self::COL_NAMA);
        $sertifikasi = $this->kutip(self::COL_SERTIFIKASI);

        return 'COUNT(DISTINCT ' . $nama . ') AS total, '
            . 'COUNT(DISTINCT CASE WHEN ' . $sertifikasi . ' IS NOT NULL'
            . " AND TRIM(" . $sertifikasi . ") <> '' THEN " . $nama . ' END) AS bersertifikat';
    }

    /**
     * @param  array<int, string>  $kunci
     * @return array<string, mixed>
     */
    protected function bentukNilai(array $kunci, int $total, int $bersertifikat): array
    {
        $persen = $total > 0 ? round($bersertifikat / $total * 100, 2) : null;
        [, $nilai, $band] = $this->scoreBandFor($persen ?? 0.0);

        return [
            'site' => $kunci[0] ?? '',
            'mitra' => $kunci[1] ?? ($kunci[0] ?? ''),
            'label' => implode(' · ', $kunci),
            'total' => $total,
            'bersertifikat' => $bersertifikat,
            'belum' => max(0, $total - $bersertifikat),
            'persen' => $persen,
            'nilai' => $persen === null ? null : $nilai,
            'nilai_band' => $persen === null ? null : $band,
            'memenuhi_target' => $persen !== null && $persen >= self::TARGET_PERCENT,
        ];
    }

    /**
     * Daftar site atau perusahaan yang benar-benar punya data, terbanyak dulu.
     *
     * @param  array<string, array<string, mixed>>  $agregat
     * @return array<int, string>
     */
    protected function urutDariAgregat(array $agregat, string $peran): array
    {
        $jumlah = [];

        foreach ($agregat as $sel) {
            $k = (string) $sel[$peran];
            $jumlah[$k] = ($jumlah[$k] ?? 0) + (int) $sel['total'];
        }

        arsort($jumlah);

        return array_keys($jumlah);
    }

    /**
     * Matriks site (baris) x perusahaan (kolom).
     *
     * Sumbunya BUKAN bulan seperti parameter lain, karena tabel sumber tidak
     * punya kolom bulan sama sekali.
     *
     * @param  array<string, array<string, mixed>>  $perSel
     * @param  array<int, string>  $sites
     * @param  array<int, string>  $perusahaan
     * @return array<int, array<string, mixed>>
     */
    protected function buildMatrix(array $perSel, array $sites, array $perusahaan): array
    {
        $out = [];

        foreach ($sites as $site) {
            $cells = [];
            $total = 0;
            $bersertifikat = 0;

            foreach ($perusahaan as $mitra) {
                $sel = $perSel[$site . '|' . $mitra] ?? null;

                if ($sel === null) {
                    // Kosong berarti perusahaan itu memang tidak punya orang
                    // di site ini -- bukan nol persen.
                    $cells[] = ['ada' => false, 'persen' => null, 'nilai' => null];
                    continue;
                }

                $cells[] = [
                    'ada' => true,
                    'persen' => $sel['persen'],
                    'nilai' => $sel['nilai'],
                    'nilai_band' => $sel['nilai_band'],
                    'total' => $sel['total'],
                    'bersertifikat' => $sel['bersertifikat'],
                ];

                $total += (int) $sel['total'];
                $bersertifikat += (int) $sel['bersertifikat'];
            }

            // Baris ini menjumlahkan sel-selnya, dan itu sah: seorang karyawan
            // hanya bisa menempati satu kolom perusahaan dalam satu site.
            $persen = $total > 0 ? round($bersertifikat / $total * 100, 2) : null;
            [, $nilai, $band] = $this->scoreBandFor($persen ?? 0.0);

            $out[] = [
                'site' => $site,
                'cells' => $cells,
                'total' => $total,
                'bersertifikat' => $bersertifikat,
                'persen' => $persen,
                'nilai' => $persen === null ? null : $nilai,
                'nilai_band' => $persen === null ? null : $band,
            ];
        }

        return $out;
    }

    /**
     * Angka keseluruhan.
     *
     * DIHITUNG ULANG DENGAN DISTINCT, bukan dijumlahkan dari sel matriks:
     * sebagian karyawan tercatat di lebih dari satu site/perusahaan dan akan
     * terhitung dua kali kalau selnya dijumlahkan.
     *
     * @return array<string, mixed>
     */
    protected function buildKpi(Request $request): array
    {
        $row = $this->baseQuery($request)->selectRaw($this->ekspresiAgregat())->first();

        $total = (int) ($row->total ?? 0);
        $bersertifikat = (int) ($row->bersertifikat ?? 0);
        $persen = $total > 0 ? round($bersertifikat / $total * 100, 2) : null;
        [, $nilai, $band] = $this->scoreBandFor($persen ?? 0.0);

        // Berapa banyak pasangan nama+site+perusahaan. Angkanya lebih besar
        // daripada jumlah orang kalau ada yang tercatat di lebih dari satu
        // site atau perusahaan, dan selisih itu memang perlu kelihatan.
        $kombinasi = (int) DB::query()
            ->fromSub(
                $this->baseQuery($request)
                    ->selectRaw('1 AS satu')
                    ->groupBy(self::COL_NAMA, self::COL_SITE, self::COL_PERUSAHAAN),
                'k'
            )
            ->count();

        return [
            'total' => $total,
            'bersertifikat' => $bersertifikat,
            'belum' => max(0, $total - $bersertifikat),
            'persen' => $persen,
            'nilai' => $persen === null ? null : $nilai,
            'nilai_band' => $persen === null ? null : $band,
            'target' => self::TARGET_PERCENT,
            'memenuhi_target' => $persen !== null && $persen >= self::TARGET_PERCENT,
            'baris' => (int) $this->baseQuery($request)->count(),
            'kombinasi' => $kombinasi,
        ];
    }

    /**
     * Ringkasan per satu kolom, terendah lebih dulu supaya yang perlu
     * perhatian muncul di atas.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function ringkasPer(Request $request, string $kolom, ?int $limit = null): array
    {
        $agregat = $this->agregatPer($request, [$kolom], $limit);

        $out = array_values($agregat);

        usort($out, static function (array $a, array $b): int {
            return ($a['persen'] ?? 101.0) <=> ($b['persen'] ?? 101.0);
        });

        return $out;
    }

    protected function catatan(Request $request): ?string
    {
        $kpi = $this->buildKpi($request);

        if ($kpi['total'] === 0) {
            return null;
        }

        return sprintf(
            '%s orang %s terdata, %s di antaranya sudah bersertifikat (%s%%). '
            . 'Dihitung per orang, bukan per baris: satu orang punya satu baris '
            . 'untuk tiap pasangan dokumen dan izin kerja, sehingga %s baris di '
            . '%s hanya mewakili %s orang.',
            number_format($kpi['total'], 0, ',', '.'),
            mb_strtolower($this->peran()),
            number_format($kpi['bersertifikat'], 0, ',', '.'),
            number_format((float) $kpi['persen'], 2, ',', '.'),
            number_format($kpi['baris'], 0, ',', '.'),
            $this->tabel(),
            number_format($kpi['total'], 0, ',', '.')
        );
    }

    // ======================================================================
    // Modal rincian sel
    // ======================================================================

    public function detailSel(Request $request): JsonResponse
    {
        $site = trim((string) $request->input('site', ''));
        $mitra = trim((string) $request->input('mitra', ''));

        if ($site === '' || $mitra === '') {
            return response()->json(['message' => 'Site dan perusahaan wajib diisi.'], 422);
        }

        $query = $this->baseQuery($request)
            ->where(self::COL_SITE, $site)
            ->where(self::COL_PERUSAHAAN, $mitra);

        $row = (clone $query)->selectRaw($this->ekspresiAgregat())->first();
        $total = (int) ($row->total ?? 0);
        $bersertifikat = (int) ($row->bersertifikat ?? 0);

        if ($total === 0) {
            return response()->json(['message' => 'Tidak ada data untuk kombinasi itu.'], 404);
        }

        $orang = (clone $query)
            ->selectRaw(
                $this->kutip(self::COL_NAMA) . ' AS nama, '
                . 'COUNT(DISTINCT ' . $this->kutip(self::COL_IZIN) . ') AS izin, '
                . 'MAX(' . $this->kutip(self::COL_SERTIFIKASI) . ') AS sertifikasi'
            )
            ->groupBy(self::COL_NAMA)
            ->orderByRaw('MAX(' . $this->kutip(self::COL_SERTIFIKASI) . ') IS NULL DESC')
            ->orderBy(self::COL_NAMA)
            ->limit(self::DETAIL_LIMIT)
            ->get()
            ->map(static fn (object $o): array => [
                'nama' => trim((string) $o->nama),
                'izin' => (int) $o->izin,
                'sertifikasi' => trim((string) ($o->sertifikasi ?? '')) ?: null,
                'bersertifikat' => trim((string) ($o->sertifikasi ?? '')) !== '',
            ])
            ->all();

        $sel = $this->bentukNilai([$site, $mitra], $total, $bersertifikat);

        return response()->json([
            'judul' => $site . ' · ' . $mitra,
            'peran' => $this->peran(),
            'nilai' => $sel,
            'orang' => $orang,
            'dipotong' => $total > self::DETAIL_LIMIT,
            'batas' => self::DETAIL_LIMIT,
            'izin' => $this->rincianPer(clone $query, self::COL_IZIN, 10),
            'sertifikasi' => $this->rincianPer(clone $query, self::COL_SERTIFIKASI, 10),
        ]);
    }

    /**
     * Pecahan orang per nilai sebuah kolom, di dalam satu sel.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rincianPer(Builder $query, string $kolom, int $limit): array
    {
        return $query
            ->selectRaw(
                $this->kutip($kolom) . ' AS label, '
                . 'COUNT(DISTINCT ' . $this->kutip(self::COL_NAMA) . ') AS orang'
            )
            ->whereNotNull($kolom)
            ->where($kolom, '<>', '')
            ->groupBy($kolom)
            ->orderByDesc('orang')
            ->limit($limit)
            ->get()
            ->map(static fn (object $o): array => [
                'label' => trim((string) $o->label),
                'orang' => (int) $o->orang,
            ])
            ->all();
    }

    // ======================================================================
    // Tab Data
    // ======================================================================

    public function data(Request $request): JsonResponse
    {
        $query = $this->dataQuery($request);

        $rows = (clone $query)
            ->orderBy(
                $this->dtOrderColumn($request, self::ORDERABLE, 'nama'),
                $this->dtDirection($request, 'asc')
            )
            ->orderBy('nama')
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
            ->orderBy('nama')
            ->limit($this->dtExportRowLimit())
            ->get()
            ->map(fn (object $row): array => $this->present($row));

        return $this->dtExport(
            'sertifikasi-' . $this->slug() . '.csv',
            ['Nama', 'Site', 'Perusahaan', 'Jumlah izin kerja', 'Sertifikasi', 'Status'],
            $rows->map(static fn (array $r): array => [
                $r['nama'], $r['site'], $r['mitra'], $r['izin'],
                $r['sertifikasi'] ?? '-', $r['status'],
            ])->all()
        );
    }

    /**
     * Satu baris = satu ORANG di satu site/perusahaan, bukan satu baris tabel.
     * Inilah satuan yang dipakai rumusnya, jadi tab Data memakai satuan yang
     * sama supaya jumlah barisnya bisa dicocokkan dengan KPI.
     */
    protected function dataQuery(Request $request): Builder
    {
        $sub = $this->baseQuery($request)
            ->selectRaw(
                $this->kutip(self::COL_NAMA) . ' AS nama, '
                . $this->kutip(self::COL_SITE) . ' AS site, '
                . $this->kutip(self::COL_PERUSAHAAN) . ' AS mitra, '
                . 'COUNT(DISTINCT ' . $this->kutip(self::COL_IZIN) . ') AS izin, '
                . 'MAX(' . $this->kutip(self::COL_SERTIFIKASI) . ') AS sertifikasi, '
                . 'MAX(CASE WHEN ' . $this->kutip(self::COL_SERTIFIKASI) . ' IS NOT NULL'
                . " AND TRIM(" . $this->kutip(self::COL_SERTIFIKASI) . ") <> ''"
                . ' THEN 1 ELSE 0 END) AS bersertifikat'
            )
            ->groupBy(self::COL_NAMA, self::COL_SITE, self::COL_PERUSAHAAN);

        $query = DB::query()->fromSub($sub, 'o');

        $status = trim((string) $request->input('status', ''));

        if ($status === 'bersertifikat') {
            $query->where('bersertifikat', 1);
        } elseif ($status === 'belum') {
            $query->where('bersertifikat', 0);
        }

        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            ['nama', 'site', 'mitra', 'sertifikasi']
        );

        return $query;
    }

    protected function baseQuery(Request $request): Builder
    {
        $query = DB::table($this->tabel());

        $this->dtApplyEqualsFilters($query, $request, [
            'site' => self::COL_SITE,
            'perusahaan' => self::COL_PERUSAHAAN,
            'izin' => self::COL_IZIN,
        ]);

        return $query;
    }

    /** @return array<string, mixed> */
    protected function present(object $row): array
    {
        $sertifikasi = trim((string) ($row->sertifikasi ?? ''));

        return [
            'nama' => trim((string) $row->nama),
            'site' => trim((string) $row->site),
            'mitra' => trim((string) $row->mitra),
            'izin' => (int) $row->izin,
            'sertifikasi' => $sertifikasi !== '' ? $sertifikasi : null,
            'bersertifikat' => (int) $row->bersertifikat === 1,
            'status' => (int) $row->bersertifikat === 1 ? 'Bersertifikat' : 'Belum bersertifikat',
        ];
    }

    protected function baseCount(): int
    {
        return (int) DB::query()
            ->fromSub(
                DB::table($this->tabel())
                    ->selectRaw('1 AS satu')
                    ->groupBy(self::COL_NAMA, self::COL_SITE, self::COL_PERUSAHAAN),
                'o'
            )
            ->count();
    }

    // ======================================================================
    // Band
    // ======================================================================

    /**
     * Nilai berkoma untuk satu capaian.
     *
     * @return array{0: float, 1: float, 2: string}
     */
    protected function scoreBandFor(float $percent): array
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

        return [0.0, 1.0, '<50%'];
    }

    /** @return array<int, array<string, mixed>> */
    protected function bandUntukView(): array
    {
        $out = [];

        foreach (self::SCORE_BANDS as [$bawah, $atas, $dasar, $label]) {
            $out[] = ['nilai' => $dasar, 'bawah' => $bawah, 'atas' => $atas, 'label' => $label];
        }

        return $out;
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

    protected function kutip(string $kolom): string
    {
        return '`' . str_replace('`', '', $kolom) . '`';
    }
}
