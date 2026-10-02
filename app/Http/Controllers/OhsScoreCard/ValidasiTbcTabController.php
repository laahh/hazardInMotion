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
 * Tab "Blindspot TBC" pada halaman Peer Pressure — tabel validasi_tbc
 * (koneksi mysql default / app_mixer).
 *
 * Dinamai *TabController* supaya tidak bentrok dengan
 * App\Http\Controllers\PeerPressureValidasiTbcController yang sudah ada
 * untuk modul Peer Pressure Edukasi.
 */
final class ValidasiTbcTabController extends Controller
{
    use ServesDataTable;

    private const TABLE = 'validasi_tbc';

    /** Index kolom DataTable -> kolom SQL (whitelist). */
    private const ORDERABLE = [
        0 => 'tasklist',
        1 => 'to_be_concerned_hazard',
        2 => 'gr',
        3 => 'kategori_gr',
        4 => 'no_item_pspp',
        5 => 'blindspot_terlapor_bc',
        6 => 'sid_pekerja_terlibat',
        7 => 'nama_pekerja_terlibat',
        // 8 = catatan, sengaja tidak orderable (teks panjang)
    ];

    private const SEARCHABLE = [
        'tasklist', 'to_be_concerned_hazard', 'gr', 'kategori_gr',
        'no_item_pspp', 'blindspot_terlapor_bc',
        'sid_pekerja_terlibat', 'nama_pekerja_terlibat', 'catatan',
    ];

    private const FILTERABLE = [
        'gr' => 'gr',
        'kategori_gr' => 'kategori_gr',
    ];

    public function data(Request $request): JsonResponse
    {
        $query = $this->filtered($request);

        $rows = (clone $query)
            ->select([
                'id', 'tasklist', 'to_be_concerned_hazard', 'gr', 'kategori_gr',
                'no_item_pspp', 'blindspot_terlapor_bc',
                'sid_pekerja_terlibat', 'nama_pekerja_terlibat', 'catatan',
            ])
            ->orderBy(
                $this->dtOrderColumn($request, self::ORDERABLE, 'id'),
                $this->dtDirection($request, 'asc')
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
                'tasklist', 'to_be_concerned_hazard', 'gr', 'kategori_gr',
                'no_item_pspp', 'blindspot_terlapor_bc',
                'sid_pekerja_terlibat', 'nama_pekerja_terlibat', 'catatan',
            ])
            ->orderBy('id');

        return $this->dtExport(
            $request,
            $query,
            [
                'Tasklist', 'To Be Concerned Hazard', 'GR', 'Kategori GR',
                'No Item PSPP', 'Blindspot Terlapor BC',
                'SID Pekerja Terlibat', 'Nama Pekerja Terlibat', 'Catatan',
            ],
            static fn (object $row): array => [
                (string) $row->tasklist,
                (string) $row->to_be_concerned_hazard,
                (string) $row->gr,
                (string) $row->kategori_gr,
                (string) $row->no_item_pspp,
                (string) $row->blindspot_terlapor_bc,
                (string) $row->sid_pekerja_terlibat,
                (string) $row->nama_pekerja_terlibat,
                (string) $row->catatan,
            ],
            'blindspot-tbc'
        );
    }

    /** @return array<string, array<int, string>> */
    public function filterOptions(): array
    {
        return [
            'gr' => $this->dtDistinctValues($this->base(), 'gr'),
            'kategori_gr' => $this->dtDistinctValues($this->base(), 'kategori_gr'),
        ];
    }

    private function base(): Builder
    {
        return DB::table(self::TABLE);
    }

    private function filtered(Request $request): Builder
    {
        $query = $this->base();

        $this->dtApplyEqualsFilters($query, $request, self::FILTERABLE);
        $this->dtApplySearch($query, (string) $request->input('search.value', $request->input('search', '')), self::SEARCHABLE);

        return $query;
    }
}
