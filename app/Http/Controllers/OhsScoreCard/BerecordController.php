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

    /**
     * mv_berecord TIDAK punya kolom site, jadi site diambil dengan join
     * ke master karyawan lewat kode_sid. Dua sumber dipakai berurutan:
     *
     *   crontable_bep_vw_m_karyawan_aktif  -> 2.088 baris terisi
     *   bep_vw_safety_all_karyawan         -> +308 baris yang tidak
     *                                         tertutup sumber pertama
     *
     * Hasilnya 2.396 dari 2.731 baris (87,7%) punya site; sisanya memang
     * tidak ada datanya di master, ditampilkan sebagai '-'.
     *
     * Keduanya sudah diperiksa unik per kode_sid (24.750 dan 63.900 baris,
     * nol duplikat), jadi LEFT JOIN ini tidak menggandakan baris beRecord:
     * jumlahnya tetap 2.731 sesudah join.
     */
    private const SITE_KARYAWAN_TABLE = 'bcsid.crontable_bep_vw_m_karyawan_aktif';

    private const SITE_SAFETY_TABLE = 'bcsid.bep_vw_safety_all_karyawan';

    private const SITE_SQL = "COALESCE(NULLIF(TRIM(k.site_dedicated), ''), NULLIF(TRIM(s.site_dedicated), ''))";

    private const DEFAULT_PAGE_LENGTH = 25;
    private const MAX_PAGE_LENGTH = 200;

    /** Opsi dropdown jarang berubah; DISTINCT-nya tak perlu diulang tiap request. */
    private const FILTER_CACHE_TTL = 600;

    /**
     * Kolom yang difilter persis dari query string: nama parameter => kolom SQL.
     * Kolom WAJIB berprefix alias sejak ada join, kalau tidak ambigu.
     */
    private const FILTERABLE = [
        'perusahaan' => 'b.perusahaan',
        'kategori_berecord' => 'b.kategori_berecord',
        'tipe_berecord' => 'b.tipe_berecord',
        'golden_rules' => 'b.golden_rules',
        'status_berecord' => 'b.status_berecord',
        'status_proses_berecord' => 'b.status_proses_berecord',
        'status_permit' => 'b.status_permit',
        'jabatan_fungsional' => 'b.jabatan_fungsional',
    ];

    /** Index kolom DataTable -> kolom SQL. Whitelist, supaya order tak bisa diinjeksi. */
    private const ORDERABLE = [
        0 => 'b.kode_sid',
        1 => 'b.nama_karyawan',
        2 => 'b.perusahaan',
        3 => self::SITE_SQL,
        4 => 'b.jabatan_fungsional',
        5 => 'b.kategori_berecord',
        6 => 'b.tipe_berecord',
        7 => 'b.golden_rules',
        8 => 'b.tanggal_mulai_berecord',
        9 => 'b.tanggal_selesai_berecord',
        10 => 'b.status_berecord',
        11 => 'b.status_proses_berecord',
        12 => 'b.status_permit',
    ];

    /** Kolom yang ikut kena kotak search bebas. */
    private const SEARCHABLE = [
        'b.kode_sid',
        'b.nama_karyawan',
        'b.perusahaan',
        'b.jabatan_fungsional',
        'b.jabatan_struktural',
        'b.kategori_berecord',
        'b.tipe_berecord',
        'b.golden_rules',
        'b.diskripsi',
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
    private const BANNED_SQL = "(b.tipe_berecord ILIKE '%banned%' AND b.tipe_berecord NOT ILIKE '%not banned%')";

    public function index(): View
    {
        $connectionUp = true;

        try {
            // Sekaligus jadi probe koneksi: hasilnya di-cache, jadi murah.
            $this->totalCount();
        } catch (Throwable $e) {
            // RDS tidak selalu terjangkau (mis. dari jaringan lokal tanpa tunnel).
            // Halaman tetap tampil dengan peringatan, bukan error 500.
            report($e);
            $connectionUp = false;
        }

        return view('ohs-score-card.peer-pressure.index', [
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
                ->orderByRaw($this->orderExpression($request))
                ->orderBy('b.id_berecord') // tie-breaker: paging stabil saat nilai sort kembar
                ->forPage($this->page($request), $this->pageLength($request))
                // Kolom HARUS dipasang lewat select(), bukan lewat argumen get():
                // get($columns) hanya berlaku kalau select masih kosong, dan
                // selectRaw() di bawah sudah mengisinya — argumen get() akan
                // diabaikan diam-diam sehingga query cuma menyeleksi "site".
                ->select([
                    'b.id_berecord', 'b.kode_sid', 'b.nama_karyawan', 'b.perusahaan',
                    'b.jabatan_fungsional', 'b.jabatan_struktural',
                    'b.kategori_berecord', 'b.tipe_berecord', 'b.golden_rules',
                    'b.kategori_kecelakaan', 'b.tanggal_mulai_berecord', 'b.tanggal_selesai_berecord',
                    'b.status_berecord', 'b.status_proses_berecord', 'b.status_permit', 'b.diskripsi',
                ])
                ->selectRaw(self::SITE_SQL . ' AS site')
                ->get();

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
        return DB::connection(self::CONNECTION)
            ->table(self::TABLE . ' as b')
            ->leftJoin(self::SITE_KARYAWAN_TABLE . ' as k', 'k.kode_sid', '=', 'b.kode_sid')
            ->leftJoin(self::SITE_SAFETY_TABLE . ' as s', 's.kode_sid', '=', 'b.kode_sid');
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

        foreach (self::FILTERABLE as $parameter => $column) {
            $value = trim((string) $request->input($parameter, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $site = trim((string) $request->input('site', ''));

        if ($site !== '') {
            $query->whereRaw(self::SITE_SQL . ' = ?', [$site]);
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
            $query->whereDate('b.tanggal_mulai_berecord', '>=', $from);
        }

        if ($to !== '') {
            $query->whereDate('b.tanggal_mulai_berecord', '<=', $to);
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
            . " COUNT(*) FILTER (WHERE b.status_berecord = 'Masih Berlaku') AS masih_berlaku,"
            . ' COUNT(*) FILTER (WHERE ' . self::BANNED_SQL . ') AS banned,'
            . " COUNT(*) FILTER (WHERE b.status_permit = 'NOT PASSED') AS permit_gagal"
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

    private function orderColumn(Request $request): string
    {
        // Default index 8 = tanggal_mulai_berecord (lihat ORDERABLE).
        // Angka ini bergeser kalau ada kolom disisipkan — kolom Site di index 3
        // sudah menggesernya sekali dari 7 ke 8.
        $index = (int) data_get($request->input('order'), '0.column', 8);

        return self::ORDERABLE[$index] ?? 'b.tanggal_mulai_berecord';
    }

    /**
     * Ekspresi ORDER BY, selalu dengan NULLS LAST.
     *
     * Postgres menaruh NULL di DEPAN untuk DESC (dan di belakang untuk ASC).
     * Tanpa NULLS LAST, urutan bawaan halaman ini (tanggal_mulai_berecord DESC)
     * menaruh baris tanpa tanggal di paling atas — pengguna membuka halaman dan
     * melihat kolom tanggal kosong. Sama untuk golden_rules yang 1.087 barisnya
     * NULL. Baris tanpa nilai sekarang selalu jatuh ke belakang.
     *
     * Nama kolom berasal dari whitelist ORDERABLE, bukan input mentah, jadi
     * aman disisipkan langsung ke SQL.
     */
    private function orderExpression(Request $request): string
    {
        return sprintf(
            '%s %s NULLS LAST',
            $this->orderColumn($request),
            $this->orderDirection($request)
        );
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
