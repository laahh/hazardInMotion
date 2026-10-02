<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Concerns\ServesDataTable;
use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tab "Pelaksanaan Peer Pressure" — satu baris per kejadian edukasi
 * (peer_pressure_kejadian_edukasi), dilengkapi jumlah peserta dari
 * peer_pressure_peserta_edukasi.
 */
final class PelaksanaanEdukasiController extends Controller
{
    use ServesDataTable;

    private const TABLE = 'peer_pressure_kejadian_edukasi';
    private const PESERTA_TABLE = 'peer_pressure_peserta_edukasi';

    /**
     * Jumlah peserta diambil lewat subquery SELECT, BUKAN JOIN + GROUP BY.
     *
     * Alasannya: dengan GROUP BY, count() Laravel mengembalikan jumlah per
     * grup (bukan satu angka), sehingga recordsFiltered dan paging jadi salah.
     * Subquery membuat query tetap satu baris per kejadian, jadi count() dan
     * LIMIT/OFFSET berperilaku normal. Subquery-nya pun hanya dihitung untuk
     * baris yang benar-benar dikembalikan (25 per halaman).
     */
    private const COUNT_PESERTA_SQL = '(SELECT COUNT(*) FROM peer_pressure_peserta_edukasi p WHERE p.kejadian_edukasi_id = peer_pressure_kejadian_edukasi.id)';
    private const COUNT_PELANGGAR_SQL = "(SELECT COUNT(*) FROM peer_pressure_peserta_edukasi p WHERE p.kejadian_edukasi_id = peer_pressure_kejadian_edukasi.id AND p.peran = 'pelanggar')";
    private const COUNT_PEER_SQL = "(SELECT COUNT(*) FROM peer_pressure_peserta_edukasi p WHERE p.kejadian_edukasi_id = peer_pressure_kejadian_edukasi.id AND p.peran = 'peer')";

    /** Index kolom DataTable -> kolom SQL (whitelist). */
    private const ORDERABLE = [
        0 => 'tanggal_edukasi',
        1 => 'site',
        2 => 'perusahaan',
        3 => 'kategori_deviasi',
        4 => 'lokasi_edukasi',
        5 => 'pemimpin_edukasi',
        6 => 'durasi_edukasi_menit',
        7 => 'status_pelaksanaan_edukasi',
        // 8-10 = jumlah peserta (hasil subquery), tidak orderable
    ];

    private const SEARCHABLE = [
        'site', 'perusahaan', 'kategori_deviasi', 'lokasi_edukasi',
        'lokasi_temuan', 'pemimpin_edukasi', 'departemen',
        'tasklist_temuan', 'kronologi_temuan', 'id_berecord',
    ];

    private const FILTERABLE = [
        'site' => 'site',
        'perusahaan' => 'perusahaan',
        'kategori_deviasi' => 'kategori_deviasi',
        'status_pelaksanaan_edukasi' => 'status_pelaksanaan_edukasi',
        'departemen' => 'departemen',
    ];

    public function data(Request $request): JsonResponse
    {
        $query = $this->filtered($request);

        $rows = (clone $query)
            ->select([
                'id', 'tanggal_temuan', 'tanggal_edukasi', 'site', 'perusahaan',
                'kategori_deviasi', 'lokasi_temuan', 'lokasi_edukasi',
                'pemimpin_edukasi', 'departemen', 'durasi_edukasi_menit',
                'status_pelaksanaan_edukasi', 'id_berecord',
            ])
            ->selectRaw(self::COUNT_PESERTA_SQL . ' AS jumlah_peserta')
            ->selectRaw(self::COUNT_PELANGGAR_SQL . ' AS jumlah_pelanggar')
            ->selectRaw(self::COUNT_PEER_SQL . ' AS jumlah_peer')
            ->orderBy(
                $this->dtOrderColumn($request, self::ORDERABLE, 'tanggal_edukasi'),
                $this->dtDirection($request, 'desc')
            )
            ->orderBy('id')
            ->forPage($this->dtPage($request), $this->dtPageLength($request))
            ->get();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $this->base()->count(),
            'recordsFiltered' => (clone $query)->count(),
            'data' => $rows,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)
            ->select([
                'tanggal_temuan', 'tanggal_edukasi', 'site', 'perusahaan',
                'kategori_deviasi', 'lokasi_temuan', 'lokasi_edukasi',
                'pemimpin_edukasi', 'departemen', 'durasi_edukasi_menit',
                'status_pelaksanaan_edukasi', 'id_berecord',
            ])
            ->selectRaw(self::COUNT_PESERTA_SQL . ' AS jumlah_peserta')
            ->selectRaw(self::COUNT_PELANGGAR_SQL . ' AS jumlah_pelanggar')
            ->selectRaw(self::COUNT_PEER_SQL . ' AS jumlah_peer')
            ->orderBy('tanggal_edukasi', 'desc')
            ->orderBy('id');

        return $this->dtExport(
            $request,
            $query,
            [
                'Tanggal Temuan', 'Tanggal Edukasi', 'Site', 'Perusahaan',
                'Kategori Deviasi', 'Lokasi Temuan', 'Lokasi Edukasi',
                'Pemimpin Edukasi', 'Departemen', 'Durasi (menit)',
                'Status', 'ID beRecord', 'Jumlah Peserta', 'Pelanggar', 'Peer',
            ],
            static fn (object $row): array => [
                (string) $row->tanggal_temuan,
                (string) $row->tanggal_edukasi,
                (string) $row->site,
                (string) $row->perusahaan,
                (string) $row->kategori_deviasi,
                (string) $row->lokasi_temuan,
                (string) $row->lokasi_edukasi,
                (string) $row->pemimpin_edukasi,
                (string) $row->departemen,
                (int) $row->durasi_edukasi_menit,
                (string) $row->status_pelaksanaan_edukasi,
                (string) $row->id_berecord,
                (int) $row->jumlah_peserta,
                (int) $row->jumlah_pelanggar,
                (int) $row->jumlah_peer,
            ],
            'pelaksanaan-peer-pressure'
        );
    }

    /** @return array<string, array<int, string>> */
    public function filterOptions(): array
    {
        $options = [];

        foreach (array_keys(self::FILTERABLE) as $column) {
            $options[$column] = $this->dtDistinctValues($this->base(), $column);
        }

        return $options;
    }

    private function base(): Builder
    {
        return DB::table(self::TABLE);
    }

    private function filtered(Request $request): Builder
    {
        $query = $this->base();

        $this->dtApplyEqualsFilters($query, $request, self::FILTERABLE);
        $this->dtApplyDateRange($query, $request, 'tanggal_edukasi');
        $this->dtApplySearch($query, (string) $request->input('search.value', $request->input('search', '')), self::SEARCHABLE);

        return $query;
    }
}
