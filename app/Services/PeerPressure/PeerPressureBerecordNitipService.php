<?php

declare(strict_types=1);

namespace App\Services\PeerPressure;

use App\Services\ClickHouseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Membaca view Postgres OLAP bcsid.bep_vw_berecord
 * (pgsql_direct = PG_HOST, fallback pgsql_ssh = tunnel lokal PG_SSH_*).
 */
final class PeerPressureBerecordNitipService
{
    private const MIN_YEAR = 2025;

    private const MAX_YEAR = 2026;

    public const CONNECTION_DIRECT = 'pgsql_direct';

    public const CONNECTION_TUNNEL = 'pgsql_ssh';

    public const TABLE = 'bcsid.bep_vw_berecord';

    private const UP_CACHE_KEY = 'peer_pressure:berecord_pg_connection_v1';

    private const UP_CACHE_TTL_SECONDS = 20;

    /** Baris dengan nilai ini dianggap tidak masuk baseline pelaksanaan (tidak ada pelanggaran GR). */
    private const GOLDEN_RULES_NO_VIOLATION = 'Tidak Melanggar Golden Rules';

    /** Kolom SELECT untuk tabel baca-saja (urutan tampilan). */
    private const VIEW_COLUMNS = [
        'id',
        'nama',
        'BeRecord',
        'kode_sid',
        'diskripsi',
        'perusahaan',
        'j_strutural',
        'work_permit',
        'golden_rules',
        'j_fungsional',
        'pic_approval',
        'status_permit',
        'tipe_berecord',
        'pic_verifikasi',
        'alamat_province',
        'status_berecord',
        'kategori_berecord',
        'end_date_be_record',
        'id_status_karyawan',
        'kategori_kecelakaan',
        'start_date_be_record',
        'status_proses_berecord',
    ];

    /**
     * Daftar kolom untuk header Blade (label singkat opsional).
     *
     * @return array<string, string> key = nama kolom, value = label UI
     */
    public static function columnLabels(): array
    {
        return [
            'id' => 'id',
            'nama' => 'nama',
            'BeRecord' => 'BeRecord',
            'kode_sid' => 'kode_sid',
            'diskripsi' => 'diskripsi',
            'perusahaan' => 'perusahaan',
            'j_strutural' => 'j_strutural',
            'work_permit' => 'work_permit',
            'golden_rules' => 'golden_rules',
            'j_fungsional' => 'j_fungsional',
            'pic_approval' => 'pic_approval',
            'status_permit' => 'status_permit',
            'tipe_berecord' => 'tipe_berecord',
            'pic_verifikasi' => 'pic_verifikasi',
            'alamat_province' => 'alamat_province',
            'status_berecord' => 'status_berecord',
            'kategori_berecord' => 'kategori_berecord',
            'end_date_be_record' => 'end_date_be_record',
            'id_status_karyawan' => 'id_status_karyawan',
            'kategori_kecelakaan' => 'kategori_kecelakaan',
            'start_date_be_record' => 'start_date_be_record',
            'status_proses_berecord' => 'status_proses_berecord',
        ];
    }

    public function isConnected(): bool
    {
        return $this->connectionName() !== null;
    }

    /**
     * WHERE untuk kartu/tab deviasi BeRecord: periode (jika ada) + filter {@see baselineBeRecordWhereAndAppendParam} pada `golden_rules`.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function deviationModalBeRecordWhere(?int $year = null, ?int $month = null): array
    {
        [$wherePeriod, $params] = $this->periodWhereAndParams($year, $month);
        $where = $wherePeriod !== '' ? $wherePeriod : 'WHERE 1=1';

        return $this->baselineBeRecordWhereAndAppendParam($where, $params);
    }

    /**
     * Jumlah `id` unik untuk kartu deviasi BeRecord (sama filter golden_rules dengan baseline pelaksanaan).
     */
    public function countDistinctIdsGoldenRulesBaseline(?int $year = null, ?int $month = null): int
    {
        if (! $this->isConnected()) {
            return 0;
        }

        [$where, $params] = $this->deviationModalBeRecordWhere($year, $month);

        try {
            $sql = 'SELECT COUNT(DISTINCT id) AS c FROM '.self::TABLE.' '.$where;
            $rows = $this->select($sql, $params);

            return $this->intFromFirstRow($rows, 'c');
        } catch (Throwable $e) {
            Log::warning('PeerPressureBerecordNitipService: countDistinctIdsGoldenRulesBaseline gagal', [
                'message' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Jumlah nilai unik kolom `id` pada view `bcsid.bep_vw_berecord`.
     * Jika tahun & bulan diisi, dibatasi ke baris yang `start_date_be_record` jatuh di bulan tersebut.
     */
    public function countDistinctIds(?int $year = null, ?int $month = null): int
    {
        if (! $this->isConnected()) {
            return 0;
        }

        [$whereSql, $params] = $this->periodWhereAndParams($year, $month);

        try {
            $sql = 'SELECT COUNT(DISTINCT id) AS c FROM '.self::TABLE.' '.$whereSql;
            $rows = $this->select($sql, $params);

            return $this->intFromFirstRow($rows, 'c');
        } catch (Throwable $e) {
            Log::warning('PeerPressureBerecordNitipService: countDistinctIds gagal', [
                'message' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Paginasi baris `bcsid.bep_vw_berecord` untuk modal deviasi: periode + filter `golden_rules` sama {@see countDistinctIdsGoldenRulesBaseline}.
     *
     * @return array{rows: list<array<string, string|null>>, total: int, connected: bool, error?: string}
     */
    public function paginateDeviationModal(?int $year = null, ?int $month = null, int $page = 1, int $perPage = 10): array
    {
        if (! $this->isConnected()) {
            return ['rows' => [], 'total' => 0, 'connected' => false];
        }

        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 50);
        $offset = ($page - 1) * $perPage;
        [$whereSql, $params] = $this->deviationModalBeRecordWhere($year, $month);
        $selectList = $this->buildSelectListSql();

        try {
            $countSql = 'SELECT COUNT(*) AS c FROM '.self::TABLE.' '.$whereSql;
            $total = $this->intFromFirstRow($this->select($countSql, $params), 'c');

            $dataSql = 'SELECT '.$selectList.' FROM '.self::TABLE.' '.$whereSql
                .' ORDER BY start_date_be_record DESC NULLS LAST, id DESC'
                .' LIMIT '.(int) $perPage.' OFFSET '.(int) $offset;

            return [
                'rows' => $this->normalizeSelectRows($this->select($dataSql, $params)),
                'total' => $total,
                'connected' => true,
            ];
        } catch (Throwable $e) {
            Log::warning('PeerPressureBerecordNitipService: paginateDeviationModal gagal', [
                'message' => $e->getMessage(),
            ]);

            return ['rows' => [], 'total' => 0, 'connected' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function periodWhereAndParams(?int $year, ?int $month): array
    {
        if ($year === null || $month === null) {
            return ['', []];
        }

        $y = max(self::MIN_YEAR, min(self::MAX_YEAR, $year));
        $m = max(1, min(12, $month));
        $start = Carbon::create($y, $m, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        return [
            'WHERE start_date_be_record >= ?::date AND start_date_be_record <= ?::date',
            [$start->toDateString(), $end->toDateString()],
        ];
    }

    /**
     * Baseline BeRecord memakai kolom `golden_rules`: terisi (bukan null/kosong) dan bukan {@see GOLDEN_RULES_NO_VIOLATION}.
     *
     * @param  list<mixed>  $params  parameter query (akan ditambah satu nilai untuk perbandingan teks)
     * @return array{0: string, 1: list<mixed>}
     */
    private function baselineBeRecordWhereAndAppendParam(string $where, array $params): array
    {
        $where .= ' AND golden_rules IS NOT NULL'
            .' AND length(trim(golden_rules)) > 0'
            .' AND lower(trim(golden_rules)) <> lower(?)';
        $params[] = self::GOLDEN_RULES_NO_VIOLATION;

        return [$where, $params];
    }

    /**
     * Baseline BeRecord: nilai unik ter-normalisasi (lower+trim) kolom `BeRecord` non-kosong, filter periode sama seperti KPI lain.
     *
     * @return list<string>
     */
    public function distinctNormalizedBeRecordValues(?int $year = null, ?int $month = null): array
    {
        if (! $this->isConnected()) {
            return [];
        }

        [$where, $params] = $this->beRecordNonEmptyWhere($year, $month);

        try {
            $sql = 'SELECT DISTINCT lower(trim("BeRecord"::text)) AS b FROM '.self::TABLE.' '.$where.' ORDER BY b';
            $rows = $this->select($sql, $params);
            $out = [];
            foreach ($rows as $row) {
                $b = $this->cell($row, 'b');
                if ($b === null || trim($b) === '') {
                    continue;
                }
                $out[] = strtolower(trim($b));
            }

            return array_values(array_unique($out));
        } catch (Throwable $e) {
            Log::warning('PeerPressureBerecordNitipService: distinctNormalizedBeRecordValues gagal', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function countDistinctNormalizedBeRecord(?int $year = null, ?int $month = null): int
    {
        if (! $this->isConnected()) {
            return 0;
        }

        [$where, $params] = $this->beRecordNonEmptyWhere($year, $month);

        try {
            $sql = 'SELECT COUNT(DISTINCT lower(trim("BeRecord"::text))) AS c FROM '.self::TABLE.' '.$where;
            $rows = $this->select($sql, $params);

            return $this->intFromFirstRow($rows, 'c');
        } catch (Throwable $e) {
            Log::warning('PeerPressureBerecordNitipService: countDistinctNormalizedBeRecord gagal', [
                'message' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Pemetaan BeRecord ter-normalisasi → label perusahaan (satu nilai per grup).
     * Hanya baris baseline: {@see baselineBeRecordWhereAndAppendParam} (`golden_rules` terisi dan bukan “Tidak Melanggar Golden Rules”).
     *
     * @return array<string, string>
     */
    public function mapNormalizedBeRecordToCompany(?int $year = null, ?int $month = null): array
    {
        if (! $this->isConnected()) {
            return [];
        }

        [$where, $params] = $this->beRecordNonEmptyWhere($year, $month);

        try {
            $sql = 'SELECT lower(trim("BeRecord"::text)) AS b, MAX(trim(COALESCE(perusahaan, \'\'))) AS co'
                .' FROM '.self::TABLE.' '.$where.' GROUP BY lower(trim("BeRecord"::text)) ORDER BY b';
            $rows = $this->select($sql, $params);
            $out = [];
            foreach ($rows as $row) {
                $b = $this->cell($row, 'b');
                if ($b === null || trim($b) === '') {
                    continue;
                }
                $out[strtolower(trim($b))] = trim((string) $this->cell($row, 'co'));
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('PeerPressureBerecordNitipService: mapNormalizedBeRecordToCompany gagal', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Pemetaan BeRecord ter-normalisasi → kode_sid (satu nilai per grup, sama seperti {@see mapNormalizedBeRecordToCompany}).
     *
     * @return array<string, string>
     */
    public function mapNormalizedBeRecordToKodeSid(?int $year = null, ?int $month = null): array
    {
        if (! $this->isConnected()) {
            return [];
        }

        [$where, $params] = $this->beRecordNonEmptyWhere($year, $month);

        try {
            $sql = 'SELECT lower(trim("BeRecord"::text)) AS b, MAX(trim(COALESCE(kode_sid, \'\'))) AS ks'
                .' FROM '.self::TABLE.' '.$where.' GROUP BY lower(trim("BeRecord"::text)) ORDER BY b';
            $rows = $this->select($sql, $params);
            $out = [];
            foreach ($rows as $row) {
                $b = $this->cell($row, 'b');
                if ($b === null || trim($b) === '') {
                    continue;
                }
                $out[strtolower(trim($b))] = trim((string) $this->cell($row, 'ks'));
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('PeerPressureBerecordNitipService: mapNormalizedBeRecordToKodeSid gagal', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Site terbaru (berdasarkan _airbyte_extracted_at) per kode_sid dari nitip.bep_vw_wp_karyawan.
     *
     * @param  list<string>  $lowerTrimmedKodeSids
     * @return array<string, string> lower kode_sid => site (non-kosong)
     */
    public function mapKodeSidLowerToSiteFromWpKaryawan(array $lowerTrimmedKodeSids): array
    {
        $ch = new ClickHouseService('clickhouse_nitip');
        if (! $ch->isConnected()) {
            return [];
        }

        $unique = [];
        foreach ($lowerTrimmedKodeSids as $s) {
            $t = strtolower(trim((string) $s));
            if ($t !== '') {
                $unique[$t] = true;
            }
        }
        $keys = array_keys($unique);
        if ($keys === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($keys, 400) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = 'SELECT lowerUTF8(trim(toString(kode_sid))) AS ks, argMax(trim(toString(ifNull(site, \'\'))), _airbyte_extracted_at) AS site'
                .' FROM bep_vw_wp_karyawan'
                .' WHERE length(trim(toString(kode_sid))) > 0'
                .' AND lowerUTF8(trim(toString(kode_sid))) IN ('.$placeholders.')'
                .' GROUP BY ks';

            try {
                $rows = $ch->query($sql, $chunk);
                if (! is_array($rows)) {
                    continue;
                }
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $ks = $row['ks'] ?? $row['KS'] ?? null;
                    $site = $row['site'] ?? $row['SITE'] ?? null;
                    if ($ks === null) {
                        continue;
                    }
                    $ksK = strtolower(trim((string) $ks));
                    $siteT = trim((string) ($site ?? ''));
                    if ($ksK !== '' && $siteT !== '') {
                        $out[$ksK] = $siteT;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('PeerPressureBerecordNitipService: mapKodeSidLowerToSiteFromWpKaryawan gagal', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $out;
    }

    /**
     * Paginasi baris dari bcsid.bep_vw_berecord (pencarian substring pada beberapa kolom teks).
     *
     * @return array{rows: list<array<string, string|null>>, total: int, connected: bool, error?: string}
     */
    public function paginateView(int $page, int $perPage, string $q): array
    {
        if (! $this->isConnected()) {
            return ['rows' => [], 'total' => 0, 'connected' => false];
        }

        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);
        $offset = ($page - 1) * $perPage;

        $selectList = $this->buildSelectListSql();
        $whereSql = '';
        $params = [];
        $qTrim = trim($q);
        if ($qTrim !== '') {
            $whereSql = 'WHERE '.$this->buildSearchPredicate();
            $params[] = '%'.$qTrim.'%';
        }

        try {
            $countSql = 'SELECT COUNT(*) AS c FROM '.self::TABLE.' '.$whereSql;
            $total = $this->intFromFirstRow($this->select($countSql, $params), 'c');

            $dataSql = 'SELECT '.$selectList.' FROM '.self::TABLE.' '.$whereSql
                .' ORDER BY start_date_be_record DESC NULLS LAST, id DESC'
                .' LIMIT '.(int) $perPage.' OFFSET '.(int) $offset;

            return [
                'rows' => $this->normalizeSelectRows($this->select($dataSql, $params)),
                'total' => $total,
                'connected' => true,
            ];
        } catch (Throwable $e) {
            Log::warning('PeerPressureBerecordNitipService: paginateView gagal', [
                'message' => $e->getMessage(),
            ]);

            return ['rows' => [], 'total' => 0, 'connected' => true, 'error' => $e->getMessage()];
        }
    }

    private function buildSelectListSql(): string
    {
        $parts = [];
        foreach (self::VIEW_COLUMNS as $col) {
            if ($col === 'BeRecord') {
                $parts[] = '"BeRecord"::text AS "BeRecord"';
                continue;
            }
            $safe = str_replace('"', '', $col);
            $parts[] = $safe.'::text AS '.$safe;
        }

        return implode(', ', $parts);
    }

    private function buildSearchPredicate(): string
    {
        $cols = [
            'nama', 'kode_sid', 'diskripsi', 'perusahaan', 'work_permit', 'golden_rules',
            'j_strutural', 'j_fungsional', 'pic_approval', 'status_permit', 'tipe_berecord',
            'pic_verifikasi', 'alamat_province', 'status_berecord', 'kategori_berecord',
            'status_proses_berecord', 'kategori_kecelakaan',
        ];
        $args = [];
        foreach ($cols as $c) {
            $args[] = 'COALESCE('.$c.', \'\')';
        }
        $args[] = 'COALESCE("BeRecord"::text, \'\')';
        $args[] = 'COALESCE(id::text, \'\')';

        return 'CONCAT_WS(\' \', '.implode(', ', $args).') ILIKE ?';
    }

    /**
     * Satu baris terbaru berdasarkan kode_sid (dipetakan ke SID pelanggar di MySQL).
     *
     * @return array<string, string|null>|null
     */
    public function findLatestByKodeSid(string $kodeSid): ?array
    {
        $sid = trim($kodeSid);
        if ($sid === '' || $sid === '-') {
            return null;
        }

        if (! $this->isConnected()) {
            Log::info('PeerPressureBerecordNitipService: Postgres OLAP tidak terhubung');

            return null;
        }

        try {
            $sql = <<<'SQL'
SELECT
  id::text AS id,
  nama AS nama,
  "BeRecord"::text AS be_record,
  "BeRecord"::text AS "BeRecord",
  kode_sid AS kode_sid,
  diskripsi AS diskripsi,
  perusahaan AS perusahaan,
  j_strutural AS j_strutural,
  work_permit AS work_permit,
  golden_rules AS golden_rules,
  j_fungsional AS j_fungsional,
  pic_approval AS pic_approval,
  status_permit AS status_permit,
  tipe_berecord AS tipe_berecord,
  pic_verifikasi AS pic_verifikasi,
  alamat_province AS alamat_province,
  status_berecord AS status_berecord,
  kategori_berecord AS kategori_berecord,
  end_date_be_record::text AS end_date_be_record,
  id_status_karyawan::text AS id_status_karyawan,
  kategori_kecelakaan AS kategori_kecelakaan,
  start_date_be_record::text AS start_date_be_record,
  status_proses_berecord AS status_proses_berecord
FROM bcsid.bep_vw_berecord
WHERE lower(trim(kode_sid)) = lower(?)
ORDER BY start_date_be_record DESC NULLS LAST, id DESC
LIMIT 1
SQL;

            $rows = $this->normalizeSelectRows($this->select($sql, [$sid]));

            return $rows[0] ?? null;
        } catch (Throwable $e) {
            Log::warning('PeerPressureBerecordNitipService: query gagal', [
                'sid' => $sid,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function beRecordNonEmptyWhere(?int $year, ?int $month): array
    {
        [$wherePeriod, $params] = $this->periodWhereAndParams($year, $month);
        $where = 'WHERE length(trim(COALESCE("BeRecord"::text, \'\'))) > 0';
        if ($wherePeriod !== '') {
            $where .= ' AND '.substr($wherePeriod, strlen('WHERE '));
        }

        return $this->baselineBeRecordWhereAndAppendParam($where, $params);
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<object>
     */
    private function select(string $sql, array $bindings = []): array
    {
        $name = $this->connectionName();
        if ($name === null) {
            return [];
        }

        /** @var list<object> $rows */
        $rows = DB::connection($name)->select($sql, $bindings);

        return $rows;
    }

    private function connectionName(): ?string
    {
        try {
            $cached = Cache::remember(self::UP_CACHE_KEY, self::UP_CACHE_TTL_SECONDS, function (): string {
                foreach ([self::CONNECTION_DIRECT, self::CONNECTION_TUNNEL] as $name) {
                    if ($this->ping($name)) {
                        return $name;
                    }
                }

                return '';
            });
        } catch (Throwable $e) {
            Log::warning('PeerPressureBerecordNitipService cache koneksi: '.$e->getMessage());
            $cached = $this->ping(self::CONNECTION_DIRECT)
                ? self::CONNECTION_DIRECT
                : ($this->ping(self::CONNECTION_TUNNEL) ? self::CONNECTION_TUNNEL : '');
        }

        return is_string($cached) && $cached !== '' ? $cached : null;
    }

    private function ping(string $connection): bool
    {
        if (! $this->isHostReachable($connection)) {
            return false;
        }

        try {
            DB::connection($connection)->select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function isHostReachable(string $connection): bool
    {
        $host = config("database.connections.{$connection}.host");
        $port = config("database.connections.{$connection}.port");

        if (! is_string($host) || $host === '' || ! is_numeric($port) || (int) $port <= 0) {
            return true;
        }

        $socket = @fsockopen($host, (int) $port, $errno, $errstr, 2);
        if (! is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /**
     * @param  list<object>  $rows
     */
    private function intFromFirstRow(array $rows, string $key): int
    {
        if ($rows === []) {
            return 0;
        }
        $c = $this->cell($rows[0], $key);

        return is_numeric($c) ? (int) $c : 0;
    }

    private function cell(object|array $row, string $key): ?string
    {
        if (is_object($row)) {
            $v = $row->{$key} ?? null;
        } else {
            $v = $row[$key] ?? $row[strtolower($key)] ?? null;
        }

        if ($v === null) {
            return null;
        }

        return (string) $v;
    }

    /**
     * @param  list<object>  $rows
     * @return list<array<string, string|null>>
     */
    private function normalizeSelectRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $arr = is_object($row) ? get_object_vars($row) : $row;
            if (is_array($arr)) {
                $out[] = $this->normalizeRow($arr);
            }
        }

        return $out;
    }

    /**
     * @param  array<string|int, mixed>  $row
     * @return array<string, string|null>
     */
    private function normalizeRow(array $row): array
    {
        $out = [];
        foreach ($row as $key => $v) {
            $k = is_string($key) ? $key : (string) $key;
            if ($v === null) {
                $out[$k] = null;
            } elseif (is_scalar($v) || $v instanceof \Stringable) {
                $out[$k] = trim((string) $v) === '' ? null : trim((string) $v);
            } else {
                $out[$k] = json_encode($v, JSON_UNESCAPED_UNICODE) ?: null;
            }
        }

        return $out;
    }
}
