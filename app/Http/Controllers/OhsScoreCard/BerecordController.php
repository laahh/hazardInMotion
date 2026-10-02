<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Parameter HSECT — "Peer Pressure": data beRecord (sanksi & pelanggaran HSE)
 * dari hse_automation, materialized view bcsid.mv_berecord.
 *
 * DataTable-nya server-side: paging, sorting, search, dan filter dikerjakan
 * di SQL, bukan dikirim semua ke browser.
 */
final class BerecordController extends Controller
{
    /**
     * Koneksi langsung ke RDS, bukan lewat tunnel SSH (pgsql_ssh).
     * Mengikuti preseden SportEvaluationPvtRfidCheckinReader: tunnel di server
     * tidak selalu aktif, akses langsung lebih andal.
     */
    private const CONNECTION = 'pgsql_direct';

    private const TABLE = 'bcsid.mv_berecord';

    private const DEFAULT_PAGE_LENGTH = 25;
    private const MAX_PAGE_LENGTH = 200;

    /** Opsi dropdown jarang berubah; DISTINCT-nya tak perlu diulang tiap request. */
    private const FILTER_CACHE_TTL = 600;

    /** Kolom yang difilter persis dari query string. */
    private const FILTERABLE = [
        'perusahaan',
        'kategori_berecord',
        'tipe_berecord',
        'golden_rules',
        'status_berecord',
        'status_proses_berecord',
        'status_permit',
        'jabatan_fungsional',
    ];

    /** Index kolom DataTable -> kolom SQL. Whitelist, supaya order tak bisa diinjeksi. */
    private const ORDERABLE = [
        0 => 'kode_sid',
        1 => 'nama_karyawan',
        2 => 'perusahaan',
        3 => 'jabatan_fungsional',
        4 => 'kategori_berecord',
        5 => 'tipe_berecord',
        6 => 'golden_rules',
        7 => 'tanggal_mulai_berecord',
        8 => 'tanggal_selesai_berecord',
        9 => 'status_berecord',
        10 => 'status_proses_berecord',
        11 => 'status_permit',
    ];

    /** Kolom yang ikut kena kotak search bebas. */
    private const SEARCHABLE = [
        'kode_sid',
        'nama_karyawan',
        'perusahaan',
        'jabatan_fungsional',
        'jabatan_struktural',
        'kategori_berecord',
        'tipe_berecord',
        'golden_rules',
        'diskripsi',
    ];

    /**
     * "Banned" tidak punya kolom sendiri — hanya tersirat di teks tipe_berecord
     * ("SP3 - Banned", "L1 - Not Banned", ...). Penulisannya tidak konsisten
     * (ada spasi ganda, ada spasi di ujung), jadi dicocokkan dengan ILIKE.
     * Urutan penting: "Not Banned" harus dikecualikan lebih dulu, karena
     * teksnya juga mengandung kata "Banned".
     *
     * Sudah diperiksa ke data: 1.106 banned + 1.385 not banned + 240 tanpa
     * label = 2.731 total, jadi ekspresi ini membagi habis tanpa tumpang tindih.
     */
    private const BANNED_SQL = "(tipe_berecord ILIKE '%banned%' AND tipe_berecord NOT ILIKE '%not banned%')";

    public function index(): View
    {
        $connectionUp = true;
        $filterOptions = [];
        $total = 0;

        try {
            $filterOptions = $this->filterOptions();
            $total = $this->totalCount();
        } catch (Throwable $e) {
            // RDS tidak selalu terjangkau (mis. dari jaringan lokal tanpa tunnel).
            // Halaman tetap tampil dengan peringatan, bukan error 500.
            report($e);
            $connectionUp = false;
        }

        return view('ohs-score-card.peer-pressure.index', [
            'filterOptions' => $filterOptions,
            'totalRecords' => $total,
            'connectionUp' => $connectionUp,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);

        try {
            $filtered = $this->buildFilteredQuery($request);

            $recordsFiltered = (clone $filtered)->count();
            $summary = $this->summarise(clone $filtered);

            $rows = $filtered
                ->orderBy($this->orderColumn($request), $this->orderDirection($request))
                ->orderBy('id_berecord') // tie-breaker: paging stabil saat nilai sort kembar
                ->forPage($this->page($request), $this->pageLength($request))
                ->get([
                    'id_berecord', 'kode_sid', 'nama_karyawan', 'perusahaan',
                    'jabatan_fungsional', 'jabatan_struktural',
                    'kategori_berecord', 'tipe_berecord', 'golden_rules',
                    'kategori_kecelakaan', 'tanggal_mulai_berecord', 'tanggal_selesai_berecord',
                    'status_berecord', 'status_proses_berecord', 'status_permit', 'diskripsi',
                ]);

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $this->totalCount(),
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
                'summary' => $summary,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'summary' => $this->emptySummary(),
                'error' => 'Koneksi ke database hse_automation tidak tersedia.',
            ], 503);
        }
    }

    private function baseQuery(): Builder
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE);
    }

    private function totalCount(): int
    {
        return (int) Cache::remember(
            'ohs-score-card.berecord.total',
            self::FILTER_CACHE_TTL,
            fn (): int => $this->baseQuery()->count()
        );
    }

    /**
     * Query dengan seluruh filter terpasang — satu tempat, supaya hitungan,
     * ringkasan, dan baris yang ditampilkan tidak mungkin berbeda dasar.
     */
    private function buildFilteredQuery(Request $request): Builder
    {
        $query = $this->baseQuery();

        foreach (self::FILTERABLE as $column) {
            $value = trim((string) $request->input($column, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $this->applyBannedFilter($query, $request);
        $this->applyDateRange($query, $request);
        $this->applySearch($query, (string) $request->input('search.value', $request->input('search', '')));

        return $query;
    }

    private function applyBannedFilter(Builder $query, Request $request): void
    {
        $value = trim((string) $request->input('banned', ''));

        if ($value === 'banned') {
            $query->whereRaw(self::BANNED_SQL);

            return;
        }

        if ($value === 'not-banned') {
            $query->whereRaw('NOT ' . self::BANNED_SQL);
        }
    }

    private function applyDateRange(Builder $query, Request $request): void
    {
        $from = trim((string) $request->input('tanggal_dari', ''));
        $to = trim((string) $request->input('tanggal_sampai', ''));

        if ($from !== '') {
            $query->whereDate('tanggal_mulai_berecord', '>=', $from);
        }

        if ($to !== '') {
            $query->whereDate('tanggal_mulai_berecord', '<=', $to);
        }
    }

    private function applySearch(Builder $query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        // Escape wildcard supaya "%" / "_" dari pengguna jadi teks biasa.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);

        $query->where(function (Builder $inner) use ($escaped): void {
            foreach (self::SEARCHABLE as $column) {
                // ILIKE: Postgres, pencarian tanpa membedakan huruf besar/kecil.
                $inner->orWhere($column, 'ilike', '%' . $escaped . '%');
            }
        });
    }

    /**
     * Ringkasan atas hasil filter saat ini (bukan hanya halaman aktif).
     *
     * @return array<string, int|float>
     */
    private function summarise(Builder $query): array
    {
        $row = $query->selectRaw(
            'COUNT(*) AS total,'
            . " COUNT(*) FILTER (WHERE status_berecord = 'Masih Berlaku') AS masih_berlaku,"
            . ' COUNT(*) FILTER (WHERE ' . self::BANNED_SQL . ') AS banned,'
            . " COUNT(*) FILTER (WHERE status_permit = 'NOT PASSED') AS permit_gagal"
        )->first();

        $total = (int) ($row->total ?? 0);
        $percent = static fn (int $n): float => $total > 0 ? round($n / $total * 100, 2) : 0.0;

        $masihBerlaku = (int) ($row->masih_berlaku ?? 0);
        $banned = (int) ($row->banned ?? 0);
        $permitGagal = (int) ($row->permit_gagal ?? 0);

        return [
            'total' => $total,
            'masih_berlaku' => $masihBerlaku,
            'banned' => $banned,
            'permit_gagal' => $permitGagal,
            'masih_berlaku_pct' => $percent($masihBerlaku),
            'banned_pct' => $percent($banned),
            'permit_gagal_pct' => $percent($permitGagal),
        ];
    }

    /** @return array<string, int|float> */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'masih_berlaku' => 0,
            'banned' => 0,
            'permit_gagal' => 0,
            'masih_berlaku_pct' => 0.0,
            'banned_pct' => 0.0,
            'permit_gagal_pct' => 0.0,
        ];
    }

    /**
     * Nilai unik tiap kolom filter, untuk mengisi dropdown.
     *
     * @return array<string, array<int, string>>
     */
    private function filterOptions(): array
    {
        return Cache::remember('ohs-score-card.berecord.filters', self::FILTER_CACHE_TTL, function (): array {
            $options = [];

            foreach (self::FILTERABLE as $column) {
                $options[$column] = $this->baseQuery()
                    ->select($column)
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->distinct()
                    ->orderBy($column)
                    ->pluck($column)
                    ->map(static fn ($value): string => trim((string) $value))
                    ->filter(static fn (string $value): bool => $value !== '')
                    ->unique()
                    ->values()
                    ->all();
            }

            return $options;
        });
    }

    private function orderColumn(Request $request): string
    {
        $index = (int) data_get($request->input('order'), '0.column', 7);

        return self::ORDERABLE[$index] ?? 'tanggal_mulai_berecord';
    }

    private function orderDirection(Request $request): string
    {
        $dir = strtolower((string) data_get($request->input('order'), '0.dir', 'desc'));

        return $dir === 'asc' ? 'asc' : 'desc';
    }

    private function pageLength(Request $request): int
    {
        $length = (int) $request->input('length', self::DEFAULT_PAGE_LENGTH);

        if ($length < 1) {
            return self::DEFAULT_PAGE_LENGTH;
        }

        return min($length, self::MAX_PAGE_LENGTH);
    }

    private function page(Request $request): int
    {
        $start = max(0, (int) $request->input('start', 0));

        return (int) floor($start / $this->pageLength($request)) + 1;
    }
}
