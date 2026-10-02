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
 * Tab "Speak Up" pada halaman Peer Pressure — tabel speak_up_fatigue
 * (koneksi mysql default / app_mixer).
 */
final class SpeakUpFatigueController extends Controller
{
    use ServesDataTable;

    private const TABLE = 'speak_up_fatigue';

    /** Index kolom DataTable -> kolom SQL (whitelist). */
    private const ORDERABLE = [
        0 => 'tanggal',
        1 => 'waktu',
        2 => 'site',
        3 => 'perusahaan',
        4 => 'sid',
        5 => 'nama',
    ];

    private const SEARCHABLE = ['site', 'perusahaan', 'sid', 'nama'];

    private const FILTERABLE = [
        'site' => 'site',
        'perusahaan' => 'perusahaan',
    ];

    public function data(Request $request): JsonResponse
    {
        $query = $this->filtered($request);

        $rows = (clone $query)
            ->select(['id', 'site', 'perusahaan', 'sid', 'nama', 'tanggal', 'waktu'])
            ->orderBy(
                $this->dtOrderColumn($request, self::ORDERABLE, 'tanggal'),
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
            ->select(['site', 'perusahaan', 'sid', 'nama', 'tanggal', 'waktu'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('id');

        return $this->dtExport(
            $request,
            $query,
            ['Site', 'Perusahaan', 'SID', 'Nama', 'Tanggal', 'Waktu'],
            static fn (object $row): array => [
                (string) $row->site,
                (string) $row->perusahaan,
                (string) $row->sid,
                (string) $row->nama,
                (string) $row->tanggal,
                (string) $row->waktu,
            ],
            'speak-up-fatigue'
        );
    }

    /** @return array<string, array<int, string>> */
    public function filterOptions(): array
    {
        return [
            'site' => $this->dtDistinctValues($this->base(), 'site'),
            'perusahaan' => $this->dtDistinctValues($this->base(), 'perusahaan'),
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
        $this->dtApplyDateRange($query, $request, 'tanggal');
        $this->dtApplySearch($query, (string) $request->input('search.value', $request->input('search', '')), self::SEARCHABLE);

        return $query;
    }
}
