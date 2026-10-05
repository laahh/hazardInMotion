<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Concerns\MembacaObds;
use App\Http\Controllers\Concerns\ServesDataTable;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Master Data Incident Management: baris mentah bcbeats.mv_investigasi.
 *
 * Berbeda dari tab Ringkasan dan Deep Dive yang menyajikan angka olahan,
 * halaman ini memperlihatkan barisnya apa adanya supaya bisa ditelusuri,
 * dicari, dan diunduh. Paging, pengurutan, pencarian dan filter dikerjakan di
 * SQL, bukan dikirim semua ke browser.
 *
 * TIDAK ADA BARIS YANG DISARING DIAM-DIAM, dan itu disengaja. Tab Ringkasan
 * membuang status DELETED, TIDAK INVESTIGASI, dan data uji; di sini semuanya
 * ikut tampil karena justru itu gunanya halaman master data. Penyaringannya
 * diserahkan ke filter status yang kelihatan, sehingga selisih angka dengan tab
 * lain selalu bisa dijelaskan, bukan jadi misteri.
 *
 * VIEW INI TIDAK PUNYA KUNCI UNIK. id_investigasi kosong di 30 baris (insiden
 * yang dilaporkan ke CCR tapi belum dibuka jadi investigasi), dan id_ccr ada di
 * semua baris tetapi tidak unik. Pasangan keduanya pun menyisakan 24 kunci
 * kembar yang mencakup 56 baris -- dan baris-baris kembar itu isinya identik
 * (status, site, dan kronologinya sama persis), jadi memang duplikat di
 * sumbernya. Untuk paging, pengurutan ditutup dengan id_investigasi lalu
 * id_ccr: satu-satunya kemenduaan yang tersisa hanyalah urutan antar baris yang
 * isinya sama, sehingga tidak terlihat di tampilan.
 *
 * PENCARIANNYA ILIKE, bukan LIKE: Postgres membedakan huruf besar-kecil pada
 * LIKE, dan mencari "pama" tidak boleh gagal hanya karena sumbernya menulis
 * "PAMA".
 */
final class IncidentMasterDataController extends Controller
{
    use MembacaObds;
    use ServesDataTable;

    private const TABLE = 'bcbeats.mv_investigasi';

    /** Pilihan dropdown jarang berubah dan dipakai di tiap pemuatan halaman. */
    private const CACHE_TTL_FILTER = 1800;

    private const CACHE_KEY_FILTER = 'ohs-score-card.incident-master.filter.v1';

    /** Kronologi dipotong supaya satu baris tabel tidak jadi satu paragraf. */
    private const PANJANG_KRONOLOGI = 400;

    private const SQL_TANGGAL = 'COALESCE(m.tanggal_kejadian, m.ccr_tanggal_insiden)';

    private const SQL_SITE = "COALESCE(NULLIF(TRIM(m.site), ''), NULLIF(TRIM(m.ccr_site), ''))";

    private const SQL_LOKASI = "COALESCE(NULLIF(TRIM(m.lokasi), ''), NULLIF(TRIM(m.ccr_lokasi), ''))";

    private const SQL_DETIL = "COALESCE(NULLIF(TRIM(m.detil_lokasi), ''), NULLIF(TRIM(m.ccr_detil_lokasi), ''))";

    /** Nama parameter kueri => ekspresi SQL yang dibandingkan persis. */
    private const FILTERABLE = [
        'site' => self::SQL_SITE,
        'jenis' => 'm.ccr_jenis_insiden',
        'kategori' => 'm.kategori_kecelakaan',
        'status' => 'm.status_investigasi',
    ];

    private const SEARCHABLE = [
        'm.ccr_jenis_insiden', 'm.kategori_kecelakaan', 'm.status_investigasi',
        'm.perusahaan', 'm.pja_bc', 'm.pja_mitra_kerja',
        'm.kronologi_kecelakaan', 'm.ccr_kronologi',
    ];

    /** Index kolom DataTable => ekspresi untuk ORDER BY. */
    private const ORDERABLE = [
        0 => 'm.id_investigasi',
        1 => self::SQL_TANGGAL,
        2 => self::SQL_SITE,
        3 => self::SQL_LOKASI,
        4 => 'm.perusahaan',
        5 => 'm.ccr_jenis_insiden',
        6 => 'm.kategori_kecelakaan',
        7 => 'm.status_investigasi',
        8 => "jsonb_array_length(COALESCE(m.rootcause, '[]'))",
        9 => "jsonb_array_length(COALESCE(m.tindakan_perbaikan, '[]'))",
    ];

    public function index(): View
    {
        $pilihan = ['site' => [], 'jenis' => [], 'kategori' => [], 'status' => [], 'tahun' => []];
        $tersambung = true;

        try {
            $pilihan = Cache::remember(
                self::CACHE_KEY_FILTER,
                self::CACHE_TTL_FILTER,
                fn (): array => $this->pilihanFilter()
            );
        } catch (Throwable $e) {
            // RDS tidak selalu terjangkau dari jaringan lokal; halaman tetap
            // tampil dengan peringatan, bukan 500.
            report($e);
            $tersambung = false;
        }

        return view('ohs-score-card.incident-management.master-data', [
            'pilihan' => $pilihan,
            'tersambung' => $tersambung,
            'tabel' => self::TABLE,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);

        try {
            $query = $this->dataQuery($request);

            $rows = (clone $query)
                ->selectRaw($this->ekspresiKolom())
                ->orderByRaw($this->ekspresiUrutan($request))
                // Penutup urutan; lihat catatan "tidak punya kunci unik".
                ->orderByRaw('m.id_investigasi DESC NULLS LAST')
                ->orderByRaw('m.id_ccr DESC')
                ->forPage($this->dtPage($request), $this->dtPageLength($request))
                ->get()
                ->map(fn (object $r): array => $this->present($r))
                ->all();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $this->totalBaris(),
                'recordsFiltered' => (clone $query)->count(),
                'data' => $rows,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Tidak bisa menghubungi OBDS (' . $this->koneksiObds() . '). '
                    . 'Dari jaringan lokal database OLAP memang tidak terjangkau.',
            ], 503);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->dataQuery($request)
            ->selectRaw($this->ekspresiKolom())
            ->orderByRaw(self::SQL_TANGGAL . ' DESC NULLS LAST')
            ->orderByRaw('m.id_investigasi DESC NULLS LAST')
            ->orderByRaw('m.id_ccr DESC');

        return $this->dtExport(
            $request,
            $query,
            [
                'ID Investigasi', 'ID CCR', 'Tanggal', 'Site', 'Lokasi', 'Detil Lokasi',
                'Perusahaan', 'PJA BC', 'PJA Mitra', 'Jenis Insiden', 'Kategori Kecelakaan',
                'Status Investigasi', 'Status LPI', 'Temuan IPLS', 'Tindakan Perbaikan',
                'Pekerja Terlibat', 'Kronologi',
            ],
            function (object $row): array {
                $p = $this->present($row);

                return [
                    $p['id'] ?? '', $p['id_ccr'], $p['tanggal'], $p['site'], $p['lokasi'],
                    $p['detil_lokasi'], $p['perusahaan'], $p['pja_bc'], $p['pja_mitra'],
                    $p['jenis'], $p['kategori'], $p['status'], $p['status_lpi'],
                    $p['n_temuan'], $p['n_car'], $p['n_pekerja'], $p['kronologi'],
                ];
            },
            'incident-master-data'
        );
    }

    // ======================================================================
    // Kueri
    // ======================================================================

    private function dasar(): Builder
    {
        return DB::connection($this->koneksiObds())->table(self::TABLE . ' AS m');
    }

    private function dataQuery(Request $request): Builder
    {
        $query = $this->dasar();

        foreach (self::FILTERABLE as $parameter => $kolom) {
            $nilai = trim((string) $request->input($parameter, ''));

            if ($nilai !== '') {
                $query->whereRaw($kolom . ' = ?', [$nilai]);
            }
        }

        $tahun = (int) $request->input('tahun', 0);

        if ($tahun >= 2000 && $tahun <= 2100) {
            $query->whereRaw('EXTRACT(YEAR FROM ' . self::SQL_TANGGAL . ') = ?', [$tahun]);
        }

        // ILIKE, bukan LIKE; lihat catatan di docblock kelas.
        $this->dtApplySearch(
            $query,
            (string) $request->input('search.value', $request->input('search', '')),
            self::SEARCHABLE,
            'ilike'
        );

        return $query;
    }

    private function ekspresiKolom(): string
    {
        $panjang = self::PANJANG_KRONOLOGI;

        return implode(', ', [
            'm.id_investigasi',
            'm.id_ccr',
            self::SQL_TANGGAL . ' AS tanggal',
            self::SQL_SITE . ' AS site',
            self::SQL_LOKASI . ' AS lokasi',
            self::SQL_DETIL . ' AS detil_lokasi',
            'm.perusahaan',
            'm.pja_bc',
            'm.pja_mitra_kerja',
            'm.ccr_jenis_insiden AS jenis',
            'm.kategori_kecelakaan AS kategori',
            'm.status_investigasi AS status',
            'm.status_lpi',
            "jsonb_array_length(COALESCE(m.rootcause, '[]')) AS n_temuan",
            "jsonb_array_length(COALESCE(m.tindakan_perbaikan, '[]')) AS n_car",
            "jsonb_array_length(COALESCE(m.pekerja_terlibat, '[]')) AS n_pekerja",
            "left(COALESCE(m.kronologi_kecelakaan, m.ccr_kronologi), {$panjang}) AS kronologi",
        ]);
    }

    /**
     * ORDER BY untuk kolom yang diklik.
     *
     * NULLS LAST dipasang eksplisit: bawaan Postgres menaruh NULL di akhir saat
     * ASC tapi di awal saat DESC, jadi tanpa ini mengurutkan menurun membuat
     * 30 baris tanpa id_investigasi menumpuk di halaman pertama.
     */
    private function ekspresiUrutan(Request $request): string
    {
        $kolom = $this->dtOrderColumn($request, self::ORDERABLE, self::SQL_TANGGAL, 1);
        $arah = $this->dtDirection($request, 'desc') === 'asc' ? 'ASC' : 'DESC';

        return $kolom . ' ' . $arah . ' NULLS LAST';
    }

    private function totalBaris(): int
    {
        return Cache::remember(
            'ohs-score-card.incident-master.total.v1',
            self::CACHE_TTL_FILTER,
            fn (): int => $this->dasar()->count()
        );
    }

    /**
     * Satu baris siap tampil.
     *
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        return [
            'id' => $row->id_investigasi === null ? null : (int) $row->id_investigasi,
            'id_ccr' => $row->id_ccr === null ? '-' : (int) $row->id_ccr,
            'tanggal' => $row->tanggal === null
                ? '-'
                : date('d/m/Y H:i', strtotime((string) $row->tanggal)),
            'site' => trim((string) ($row->site ?? '')) ?: '-',
            'lokasi' => trim((string) ($row->lokasi ?? '')) ?: '-',
            'detil_lokasi' => trim((string) ($row->detil_lokasi ?? '')) ?: '-',
            'perusahaan' => trim((string) ($row->perusahaan ?? '')) ?: '-',
            'pja_bc' => trim((string) ($row->pja_bc ?? '')) ?: '-',
            'pja_mitra' => trim((string) ($row->pja_mitra_kerja ?? '')) ?: '-',
            'jenis' => trim((string) ($row->jenis ?? '')) ?: '-',
            'kategori' => trim((string) ($row->kategori ?? '')) ?: 'Belum dikategorikan',
            'status' => trim((string) ($row->status ?? '')) ?: '-',
            'status_lpi' => trim((string) ($row->status_lpi ?? '')) ?: '-',
            'n_temuan' => (int) $row->n_temuan,
            'n_car' => (int) $row->n_car,
            'n_pekerja' => (int) $row->n_pekerja,
            'kronologi' => trim((string) ($row->kronologi ?? '')),
        ];
    }

    /**
     * Isi dropdown, diambil dari nilai yang benar-benar ada di sumber.
     *
     * @return array<string, array<int, mixed>>
     */
    private function pilihanFilter(): array
    {
        $row = $this->bacaSatuObds(
            'SELECT ' . implode(', ', [
                "(SELECT json_agg(DISTINCT s ORDER BY s) FROM (SELECT " . self::SQL_SITE
                    . ' AS s FROM ' . self::TABLE . ' m) x WHERE s IS NOT NULL) AS site',
                "(SELECT json_agg(DISTINCT ccr_jenis_insiden ORDER BY ccr_jenis_insiden) FROM "
                    . self::TABLE . ' WHERE ccr_jenis_insiden IS NOT NULL) AS jenis',
                "(SELECT json_agg(DISTINCT kategori_kecelakaan ORDER BY kategori_kecelakaan) FROM "
                    . self::TABLE . ' WHERE kategori_kecelakaan IS NOT NULL) AS kategori',
                "(SELECT json_agg(DISTINCT status_investigasi ORDER BY status_investigasi) FROM "
                    . self::TABLE . ' WHERE status_investigasi IS NOT NULL) AS status',
                '(SELECT json_agg(DISTINCT t ORDER BY t DESC) FROM (SELECT EXTRACT(YEAR FROM '
                    . self::SQL_TANGGAL . ')::int AS t FROM ' . self::TABLE . ' m) y WHERE t IS NOT NULL) AS tahun',
            ])
        );

        if ($row === null) {
            return ['site' => [], 'jenis' => [], 'kategori' => [], 'status' => [], 'tahun' => []];
        }

        return [
            'site' => $this->uraikanJson($row->site),
            'jenis' => $this->uraikanJson($row->jenis),
            'kategori' => $this->uraikanJson($row->kategori),
            'status' => $this->uraikanJson($row->status),
            'tahun' => $this->uraikanJson($row->tahun),
        ];
    }
}
