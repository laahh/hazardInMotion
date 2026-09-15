<?php

declare(strict_types=1);

namespace App\Services\Besigma;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pemeriksa koneksi Postgres Besigma langsung ke RDS (PG_HOST:PG_PORT).
 * Database target: `besigma` — pola sama dengan RFID (pgsql_direct).
 * Nama connection Laravel tetap `besigma_db`.
 */
final class BesigmaConnectionService
{
    public const CONNECTION = 'besigma_db';

    private const CACHE_KEY = 'besigma:is_up_v1';

    private const CIRCUIT_KEY = 'besigma:circuit_v1';

    private const UP_TTL_SECONDS = 30;

    private const DOWN_TTL_SECONDS = 120;

    private const BLOCKED_TTL_SECONDS = 900;

    private const SOCKET_TIMEOUT_SECONDS = 3;

    private ?bool $requestCache = null;

    /**
     * Siapkan koneksi seperti /besigma/connection-test (probe):
     * applyRuntimeConfig (+ purge saat fresh). Opsional hapus circuit/cache agar
     * maps tidak tertahan status "down" dari error SQL lama.
     */
    public function prepareForUse(bool $fresh = false): void
    {
        if ($fresh) {
            $this->forgetCachedStatus();
        }

        app(BesigmaTunnelService::class)->applyRuntimeConfig();

        if ($fresh) {
            DB::purge(self::CONNECTION);
        }
    }

    public function isUp(): bool
    {
        if ($this->requestCache !== null) {
            return $this->requestCache;
        }

        app(BesigmaTunnelService::class)->applyRuntimeConfig();

        if ($this->circuitIsOpen()) {
            return $this->requestCache = false;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_bool($cached)) {
            return $this->requestCache = $cached;
        }

        $target = $this->targetMeta();
        if (! $this->isTcpReachable($target['host'], $target['port'])) {
            Cache::put(self::CACHE_KEY, false, self::DOWN_TTL_SECONDS);

            return $this->requestCache = false;
        }

        try {
            DB::connection(self::CONNECTION)->select('SELECT 1');
            $this->rememberSuccess();

            return $this->requestCache = true;
        } catch (Throwable $e) {
            $this->rememberFailure($e);

            return $this->requestCache = false;
        }
    }

    public function forgetCachedStatus(): void
    {
        $this->requestCache = null;
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CIRCUIT_KEY);
    }

    public function rememberSuccess(): void
    {
        Cache::forget(self::CIRCUIT_KEY);
        Cache::put(self::CACHE_KEY, true, self::UP_TTL_SECONDS);
    }

    public function rememberFailure(Throwable $e): void
    {
        // Hanya error koneksi/jaringan yang membuka circuit.
        // Error SQL (kolom/tabel) tidak boleh memaksa /isc/maps ke mode demo.
        if (! $this->isConnectionFailure($e)) {
            return;
        }

        $ttl = $this->isHostBlockedError($e) ? self::BLOCKED_TTL_SECONDS : self::DOWN_TTL_SECONDS;
        Cache::put(self::CACHE_KEY, false, $ttl);
        Cache::put(self::CIRCUIT_KEY, [
            'until' => now()->addSeconds($ttl)->toIso8601String(),
            'error' => $e->getMessage(),
        ], $ttl);
    }

    public function isConnectionFailure(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return $this->isHostBlockedError($e)
            || str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'could not connect')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'connection to server')
            || str_contains($message, 'server closed the connection')
            || str_contains($message, 'no route to host')
            || str_contains($message, 'name or service not known')
            || str_contains($message, 'sqlstate[08006]')
            || str_contains($message, 'sqlstate[08001]')
            || str_contains($message, 'sqlstate[57p01]');
    }

    /**
     * Tes koneksi nyata (tanpa cache) untuk halaman diagnostik direct RDS.
     *
     * @return array{
     *     connected: bool,
     *     tcp_reachable: bool,
     *     latency_ms: float|null,
     *     database: string|null,
     *     username: string|null,
     *     version: string|null,
     *     server_time: string|null,
     *     table_count: int|null,
     *     tables: list<string>,
     *     schema: list<array<string, mixed>>,
     *     error: string|null,
     *     hint: string|null,
     *     target: array{host:string,port:int,database:string,username:string,driver:string,search_path:string,mode:string}
     * }
     */
    public function probe(): array
    {
        $this->prepareForUse(true);

        $target = $this->targetMeta();
        $tcpReachable = $this->isTcpReachable($target['host'], $target['port']);

        $base = [
            'connected' => false,
            'tcp_reachable' => $tcpReachable,
            'latency_ms' => null,
            'database' => null,
            'username' => null,
            'version' => null,
            'server_time' => null,
            'table_count' => null,
            'tables' => [],
            'schema' => [],
            'error' => null,
            'hint' => null,
            'target' => $target,
        ];

        if (! $tcpReachable) {
            $base['error'] = sprintf(
                'Host %s:%d tidak merespons.',
                $target['host'],
                $target['port']
            );
            $base['hint'] = 'Besigma memakai koneksi direct RDS (sama seperti RFID). Pastikan app server bisa reach PG_HOST:PG_PORT (security group / VPN). Tunnel SSH 5433 tidak diperlukan.';

            return $base;
        }

        $started = microtime(true);

        try {
            $row = DB::connection(self::CONNECTION)->selectOne(
                'SELECT current_database() AS db_name, current_user AS db_user, version() AS db_version, NOW() AS db_time'
            );

            $latencyMs = round((microtime(true) - $started) * 1000, 1);

            $schema = $this->describeAllTables();
            $tables = array_map(
                static fn (array $table): string => $table['name'],
                $schema
            );

            $this->requestCache = true;
            $this->rememberSuccess();

            return [
                'connected' => true,
                'tcp_reachable' => true,
                'latency_ms' => $latencyMs,
                'database' => isset($row->db_name) ? (string) $row->db_name : null,
                'username' => isset($row->db_user) ? (string) $row->db_user : null,
                'version' => isset($row->db_version) ? (string) $row->db_version : null,
                'server_time' => isset($row->db_time) ? (string) $row->db_time : null,
                'table_count' => count($tables),
                'tables' => $tables,
                'schema' => $schema,
                'error' => null,
                'hint' => null,
                'target' => $target,
            ];
        } catch (Throwable $e) {
            report($e);
            $this->rememberFailure($e);

            $base['error'] = $e->getMessage();
            $base['hint'] = $this->hintForProbeFailure($e, $tcpReachable);
            $base['latency_ms'] = round((microtime(true) - $started) * 1000, 1);

            return $base;
        }
    }

    /**
     * @return array{host:string,port:int,database:string,username:string,driver:string,search_path:string,mode:string}
     */
    public function targetMeta(): array
    {
        $cfg = config('database.connections.'.self::CONNECTION, []);

        return [
            'host' => (string) ($cfg['host'] ?? env('PG_HOST', '')),
            'port' => (int) ($cfg['port'] ?? env('PG_PORT', 5432)),
            'database' => (string) ($cfg['database'] ?? 'besigma'),
            'username' => (string) ($cfg['username'] ?? 'safety_evaluator_2'),
            'driver' => (string) ($cfg['driver'] ?? 'pgsql'),
            'search_path' => (string) ($cfg['search_path'] ?? 'besigma_db,public'),
            'mode' => 'direct',
        ];
    }

    private function hintForProbeFailure(Throwable $e, bool $tcpReachable): string
    {
        $message = $e->getMessage();

        if (! $tcpReachable) {
            return 'RDS tidak terjangkau dari app server. Periksa PG_HOST, PG_PORT, dan security group (sama seperti koneksi RFID).';
        }

        if (str_contains($message, 'password authentication failed') || str_contains($message, '28P01')) {
            return 'RDS terjangkau, tetapi login ditolak. Periksa BESIGMA_DB_USERNAME / BESIGMA_DB_PASSWORD / BESIGMA_DB_DATABASE di .env.';
        }

        if (str_contains($message, 'does not exist') || str_contains($message, '3D000')) {
            return 'RDS terjangkau, tetapi database tidak ditemukan. Coba BESIGMA_DB_DATABASE=besigma (bukan besigma_db — itu nama connection Laravel, bukan nama DB di RDS).';
        }

        if (
            str_contains($message, 'timeout expired')
            || str_contains($message, 'Connection refused')
            || str_contains($message, '08006')
        ) {
            return 'RDS tidak merespons dalam batas waktu. Periksa PG_HOST/PG_PORT dan akses jaringan (security group), bukan tunnel SSH 5433.';
        }

        return 'RDS terjangkau, tetapi query Postgres gagal. Periksa user, database `besigma`, search_path, dan log RDS.';
    }

    /**
     * Bandingkan dengan koneksi RFID (pgsql_direct / hse_automation).
     */
    public function rfidDirectProbe(): ?array
    {
        try {
            DB::connection('pgsql_direct')->select('SELECT current_database() AS db_name, 1 AS ok');

            return [
                'ok' => true,
                'database' => (string) config('database.connections.pgsql_direct.database'),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function circuitIsOpen(): bool
    {
        return Cache::has(self::CIRCUIT_KEY);
    }

    private function isHostBlockedError(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'too many connections');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function describeAllTables(): array
    {
        $tableRows = DB::connection(self::CONNECTION)->select(
            "SELECT
                n.nspname AS table_schema,
                c.relname AS table_name,
                CASE c.relkind
                    WHEN 'r' THEN 'BASE TABLE'
                    WHEN 'p' THEN 'BASE TABLE'
                    WHEN 'v' THEN 'VIEW'
                    WHEN 'm' THEN 'MATERIALIZED VIEW'
                    ELSE c.relkind::text
                END AS table_type,
                NULL::text AS engine,
                GREATEST(c.reltuples, 0)::bigint AS approx_rows,
                COALESCE(obj_description(c.oid), '') AS table_comment
             FROM pg_class c
             INNER JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')
               AND n.nspname NOT LIKE 'pg\\_temp\\_%'
               AND n.nspname NOT LIKE 'pg\\_toast\\_temp\\_%'
               AND c.relkind IN ('r', 'p', 'v', 'm')
               AND NOT c.relispartition
             ORDER BY n.nspname, c.relname"
        );

        $columnRows = DB::connection(self::CONNECTION)->select(
            "SELECT
                cols.table_schema,
                cols.table_name,
                cols.column_name,
                cols.data_type AS column_type,
                cols.is_nullable,
                CASE
                    WHEN pk.column_name IS NOT NULL THEN 'PRI'
                    ELSE ''
                END AS column_key,
                cols.column_default,
                '' AS extra,
                '' AS column_comment
             FROM information_schema.columns cols
             LEFT JOIN (
                SELECT
                    n.nspname AS table_schema,
                    c.relname AS table_name,
                    a.attname AS column_name
                FROM pg_index i
                INNER JOIN pg_class c ON c.oid = i.indrelid
                INNER JOIN pg_namespace n ON n.oid = c.relnamespace
                INNER JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = ANY (i.indkey)
                WHERE i.indisprimary
             ) pk ON pk.table_schema = cols.table_schema
                 AND pk.table_name = cols.table_name
                 AND pk.column_name = cols.column_name
             WHERE cols.table_schema NOT IN ('pg_catalog', 'information_schema')
             ORDER BY cols.table_schema, cols.table_name, cols.ordinal_position"
        );

        $schema = [];
        foreach ($tableRows as $tableRow) {
            $tableSchema = (string) ($tableRow->table_schema ?? 'public');
            $name = (string) ($tableRow->table_name ?? '');
            if ($name === '') {
                continue;
            }
            $key = $tableSchema === 'public' ? $name : $tableSchema.'.'.$name;
            $schema[$key] = [
                'name' => $key,
                'schema' => $tableSchema,
                'type' => (string) ($this->rowAttr($tableRow, 'table_type') ?? 'BASE TABLE'),
                'engine' => ($engine = $this->rowAttr($tableRow, 'engine')) !== null && $engine !== ''
                    ? (string) $engine
                    : null,
                'approx_rows' => ($rows = $this->rowAttr($tableRow, 'approx_rows')) !== null
                    ? (int) $rows
                    : null,
                'comment' => (string) ($this->rowAttr($tableRow, 'table_comment') ?? ''),
                'columns' => [],
            ];
        }

        foreach ($columnRows as $columnRow) {
            $tableSchema = (string) ($columnRow->table_schema ?? 'public');
            $table = (string) ($columnRow->table_name ?? '');
            if ($table === '') {
                continue;
            }
            $key = $tableSchema === 'public' ? $table : $tableSchema.'.'.$table;
            if (! isset($schema[$key])) {
                $schema[$key] = [
                    'name' => $key,
                    'schema' => $tableSchema,
                    'type' => 'BASE TABLE',
                    'engine' => null,
                    'approx_rows' => null,
                    'comment' => '',
                    'columns' => [],
                ];
            }
            $schema[$key]['columns'][] = [
                'name' => (string) ($this->rowAttr($columnRow, 'column_name') ?? ''),
                'type' => (string) ($this->rowAttr($columnRow, 'column_type') ?? ''),
                'nullable' => strtoupper((string) ($this->rowAttr($columnRow, 'is_nullable') ?? '')) === 'YES',
                'key' => (string) ($this->rowAttr($columnRow, 'column_key') ?? ''),
                'default' => $this->rowAttr($columnRow, 'column_default'),
                'extra' => (string) ($this->rowAttr($columnRow, 'extra') ?? ''),
                'comment' => (string) ($this->rowAttr($columnRow, 'column_comment') ?? ''),
            ];
        }

        return array_values($schema);
    }

    /**
     * @param  list<array<string, mixed>>  $schema
     */
    public function schemaAsText(array $schema): string
    {
        $lines = [];
        foreach ($schema as $table) {
            $name = (string) ($table['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $meta = array_filter([
                (string) ($table['type'] ?? ''),
                (string) ($table['engine'] ?? ''),
                isset($table['approx_rows']) ? '~'.(int) $table['approx_rows'].' rows' : null,
                isset($table['columns']) && is_array($table['columns']) ? count($table['columns']).' cols' : null,
            ]);
            $lines[] = $name.( $meta !== [] ? ' ('.implode(', ', $meta).')' : '');
            foreach ($table['columns'] ?? [] as $column) {
                if (! is_array($column)) {
                    continue;
                }
                $parts = array_filter([
                    (string) ($column['name'] ?? ''),
                    (string) ($column['type'] ?? ''),
                    (string) ($column['key'] ?? ''),
                    ! empty($column['nullable']) ? 'NULL' : 'NOT NULL',
                    (string) ($column['extra'] ?? ''),
                ], static fn (string $part): bool => $part !== '');
                $lines[] = '  '.implode(' ', $parts);
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    private function rowAttr(object $row, string $name): mixed
    {
        if (isset($row->{$name})) {
            return $row->{$name};
        }
        $upper = strtoupper($name);
        if (isset($row->{$upper})) {
            return $row->{$upper};
        }

        return null;
    }

    public function isTcpReachable(string $host, int $port): bool
    {
        return app(BesigmaTunnelService::class)->isTcpReachable($host, $port, self::SOCKET_TIMEOUT_SECONDS);
    }
}
