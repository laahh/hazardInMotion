<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sumber Hazard & Inspeksi Control Room: bcbeats.aaj_car_all_year_from_dav
 * (pengganti bcbeats.mv_inspeksi_hazard).
 *
 * Skema dibaca dari pg_attribute dan di-cache, lalu tiap field memilih nama
 * kolom pertama yang benar-benar ada. Kolom opsional yang tidak ada menjadi
 * NULL sehingga query tidak error; kolom wajib yang hilang membuat sumber
 * dianggap tidak tersedia (query tidak dijalankan).
 */
final class ControlRoomAajCarSource
{
    public const TABLE = 'bcbeats.aaj_car_all_year_from_dav';

    public const TIMEZONE = 'Asia/Makassar';

    /**
     * Kandidat nama kolom per field, urut prioritas. Nama pertama mengikuti
     * pemakaian tabel yang sama di ClickHouse (MapBaseController dkk).
     */
    private const CANDIDATES = [
        'report_id' => ['id', 'id_laporan', 'task_number'],
        'sid' => ['sid_pelapor', 'kode_sid_pelapor'],
        'at' => ['tanggal_pembuatan', 'tanggal_laporan'],
        'at_draft' => ['bedraft_date'],
        'jenis' => ['jenis_laporan'],
        'status' => ['status', 'status_laporan'],
        'description' => ['deskripsi', 'deskripsi_temuan'],
        'subcategory' => ['subketidaksesuaian', 'sub_ketidaksesuaian'],
        'category' => ['ketidaksesuaian', 'nama_kategori'],
        'golden_rule' => ['nama_goldenrule', 'nama_golden_rule', 'golden_rule', 'goldenrule'],
        'tools' => ['name_tools_observation', 'tools_observasi'],
        'lokasi' => ['nama_lokasi', 'lokasi'],
        'detil_lokasi' => ['nama_detail_lokasi', 'detil_lokasi'],
        'latitude' => ['latitude'],
        'longitude' => ['longitude'],
        'nama_pelapor' => ['nama_pelapor'],
        'jabatan_fungsional_pelapor' => ['jabatan_fungsional_pelapor'],
        'perusahaan_pelapor' => ['perusahaan_pelapor'],
        'nama_pic' => ['nama_pic'],
        'jabatan_fungsional_pic' => ['jabatan_fungsional_pic'],
        'perusahaan_pic' => ['perusahaan_pic'],
        'url_foto' => ['url_photo', 'url_foto'],
    ];

    private const REQUIRED = ['report_id', 'sid', 'at', 'jenis'];

    private const CATALOG_CACHE_KEY = 'control-room:aaj-car-columns:v1';

    private const CATALOG_CACHE_SECONDS = 21600;

    private const CATALOG_FAIL_SECONDS = 60;

    /** @var array<string, string>|null|false  false = belum dibaca */
    private array|null|false $catalog = false;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * False hanya bila skema sudah terbaca dan kolom wajib tidak ada /
     * tabel tidak ditemukan. Skema yang belum bisa dibaca (OBDS putus)
     * dianggap usable — query-nya sendiri yang akan gagal secara aman.
     */
    public function isUsable(): bool
    {
        $catalog = $this->catalog();
        if ($catalog === null) {
            return true;
        }
        foreach (self::REQUIRED as $field) {
            if ($this->column($field) === null) {
                Log::warning(sprintf('ControlRoom %s: kolom wajib "%s" tidak ditemukan (kandidat: %s).', self::TABLE, $field, implode(', ', self::CANDIDATES[$field])));

                return false;
            }
        }

        return true;
    }

    /**
     * Nilai field sebagai text, atau NULL::text bila kolom tidak ada.
     */
    public function text(string $field): string
    {
        $column = $this->column($field);

        return $column === null ? 'CAST(NULL AS text)' : 'CAST('.$column.' AS text)';
    }

    /**
     * Ekspresi boolean "field terisi".
     */
    public function present(string ...$fields): string
    {
        $parts = [];
        foreach ($fields as $field) {
            $column = $this->column($field);
            if ($column === null) {
                return 'FALSE';
            }
            $parts[] = $column.' IS NOT NULL';
        }

        return '('.implode(' AND ', $parts).')';
    }

    public function reportIdExpr(): string
    {
        return "REPLACE(BTRIM({$this->text('report_id')}), ',', '')";
    }

    public function sidExpr(): string
    {
        return "UPPER(BTRIM({$this->text('sid')}))";
    }

    public function jenisExpr(): string
    {
        return "UPPER(BTRIM(COALESCE({$this->text('jenis')}, '')))";
    }

    /**
     * Waktu laporan (WITA). Draft tanpa tanggal_pembuatan memakai bedraft_date,
     * sama seperti query ClickHouse untuk tabel ini.
     */
    public function atExpr(): string
    {
        $at = $this->timestamp('at');
        $draft = $this->timestamp('at_draft');

        return $draft === null ? (string) $at : "COALESCE({$at}, {$draft})";
    }

    public function componentCase(): string
    {
        $jenis = $this->jenisExpr();

        return "CASE
                    WHEN POSITION('INSPEKSI' IN {$jenis}) > 0 THEN 'inspeksi'
                    WHEN POSITION('HAZARD' IN {$jenis}) > 0 THEN 'hazard'
                    ELSE NULL
                END";
    }

    /**
     * Kategori temuan: subketidaksesuaian, lalu ketidaksesuaian.
     */
    public function categoryExpr(): string
    {
        return "COALESCE(
                    NULLIF(BTRIM(COALESCE({$this->text('subcategory')}, '')), ''),
                    NULLIF(BTRIM(COALESCE({$this->text('category')}, '')), ''),
                    ''
                )";
    }

    /**
     * Filter tools OCR. Kolom tools tidak ada → tidak difilter (dicatat di log).
     *
     * @return array{sql: string, bindings: list<string>}
     */
    public function toolsPredicate(): array
    {
        $column = $this->column('tools');
        if ($column === null) {
            if ($this->catalog() !== null) {
                Log::warning('ControlRoom '.self::TABLE.': kolom tools tidak ditemukan — Hazard/Inspeksi tidak difilter per tools.');
            }

            return ['sql' => 'TRUE', 'bindings' => []];
        }

        $predicate = ControlRoomInspeksiHazardToolFilter::sqlPredicate('tools_text');

        return [
            'sql' => str_replace('tools_text', 'BTRIM(CAST('.$column.' AS text))', $predicate['sql']),
            'bindings' => $predicate['bindings'],
        ];
    }

    /**
     * WHERE SID IN (...) + rentang waktu [start, end).
     */
    public function sidRangeWhere(int $sidCount): string
    {
        $placeholders = implode(',', array_fill(0, max(1, $sidCount), '?'));

        return "{$this->sidExpr()} IN ({$placeholders})
                  AND {$this->atExpr()} >= CAST(? AS timestamp)
                  AND {$this->atExpr()} < CAST(? AS timestamp)";
    }

    public function column(string $field): ?string
    {
        $candidates = self::CANDIDATES[$field] ?? [];
        $catalog = $this->catalog();
        if ($catalog === null) {
            // Skema belum terbaca: pakai nama utama, kecuali field opsional
            // yang tidak dikenal pasti ada.
            return in_array($field, [...self::REQUIRED, 'status', 'description', 'lokasi', 'detil_lokasi', 'tools', 'nama_pelapor', 'perusahaan_pelapor'], true)
                ? ($candidates[0] ?? null)
                : null;
        }
        foreach ($candidates as $name) {
            if (isset($catalog[$name])) {
                return '"'.$name.'"';
            }
        }

        return null;
    }

    private function timestamp(string $field): ?string
    {
        $column = $this->column($field);
        if ($column === null) {
            return null;
        }
        $type = $this->catalog()[trim($column, '"')] ?? '';
        if (str_contains($type, 'with time zone')) {
            return "({$column} AT TIME ZONE '".self::TIMEZONE."')";
        }

        return "CAST({$column} AS timestamp)";
    }

    /**
     * @return array<string, string>|null  nama kolom => tipe; null = belum terbaca
     */
    private function catalog(): ?array
    {
        if ($this->catalog !== false) {
            return $this->catalog;
        }

        $cached = Cache::get(self::CATALOG_CACHE_KEY);
        if (is_array($cached)) {
            return $this->catalog = $cached;
        }
        if ($cached === 'fail' || ! $this->olap->isReachable()) {
            return $this->catalog = null;
        }

        try {
            $rows = $this->olap->select(
                'SELECT a.attname AS name, format_type(a.atttypid, a.atttypmod) AS type
                 FROM pg_attribute a
                 WHERE a.attrelid = to_regclass(?) AND a.attnum > 0 AND NOT a.attisdropped',
                [self::TABLE],
                4000,
            );
        } catch (Throwable $e) {
            Log::warning('ControlRoom '.self::TABLE.' skema gagal dibaca: '.$e->getMessage());
            Cache::put(self::CATALOG_CACHE_KEY, 'fail', self::CATALOG_FAIL_SECONDS);

            return $this->catalog = null;
        }

        $catalog = [];
        foreach ($rows as $row) {
            $catalog[(string) $row->name] = strtolower((string) $row->type);
        }
        if ($catalog === []) {
            Log::warning('ControlRoom '.self::TABLE.' tidak ditemukan di OBDS.');
        }
        Cache::put(self::CATALOG_CACHE_KEY, $catalog, self::CATALOG_CACHE_SECONDS);

        return $this->catalog = $catalog;
    }
}
