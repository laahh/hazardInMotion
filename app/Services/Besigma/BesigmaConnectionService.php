<?php

declare(strict_types=1);

namespace App\Services\Besigma;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pemeriksa koneksi Postgres Besigma lewat tunnel OLAP (127.0.0.1:5433).
 * Database target: `besigma_db` (terpisah dari hse_automation di pgsql_ssh).
 */
final class BesigmaConnectionService
{
    public const CONNECTION = 'besigma_db';

    private const CACHE_KEY = 'besigma:is_up_v1';

    private const CIRCUIT_KEY = 'besigma:circuit_v1';

    private const UP_TTL_SECONDS = 30;

    private const DOWN_TTL_SECONDS = 120;

    private const BLOCKED_TTL_SECONDS = 900;

    private ?bool $requestCache = null;

    public function isUp(): bool
    {
        if ($this->requestCache !== null) {
            return $this->requestCache;
        }

        if ($this->circuitIsOpen()) {
            return $this->requestCache = false;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_bool($cached)) {
            return $this->requestCache = $cached;
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
        $ttl = $this->isHostBlockedError($e) ? self::BLOCKED_TTL_SECONDS : self::DOWN_TTL_SECONDS;
        Cache::put(self::CACHE_KEY, false, $ttl);
        Cache::put(self::CIRCUIT_KEY, [
            'until' => now()->addSeconds($ttl)->toIso8601String(),
            'error' => $e->getMessage(),
        ], $ttl);
    }

    /**
     * Tes koneksi nyata (tanpa cache) untuk halaman diagnostik tunnel OLAP.
     *
     * @return array{
     *     connected: bool,
     *     tcp_reachable: bool,
     *     key_exists: bool,
     *     latency_ms: float|null,
     *     database: string|null,
     *     username: string|null,
     *     version: string|null,
     *     server_time: string|null,
     *     table_count: int|null,
     *     tables: list<string>,
     *     schema: list<array{
     *         name:string,
     *         type:string,
     *         engine:?string,
     *         approx_rows:?int,
     *         comment:string,
     *         columns:list<array{
     *             name:string,
     *             type:string,
     *             nullable:bool,
     *             key:string,
     *             default:mixed,
     *             extra:string,
     *             comment:string
     *         }>
     *     }>,
     *     error: string|null,
     *     hint: string|null,
     *     tunnel: array{
     *         local_host: string,
     *         local_port: int,
     *         ssh_host: string,
     *         ssh_port: int,
     *         ssh_user: string,
     *         ssh_pkey: string,
     *         remote_host: string,
     *         remote_port: int
     *     }
     * }
     */
    public function probe(): array
    {
        $this->forgetCachedStatus();

        $tunnelService = app(BesigmaTunnelService::class);
        $tunnelService->applyRuntimeConfig();
        $tunnelService->ensureListening();

        DB::purge(self::CONNECTION);

        $tunnel = $this->tunnelMeta();
        $target = $this->targetMeta();
        $keyExists = $tunnel['ssh_pkey'] !== '' && is_file($tunnel['ssh_pkey']);
        $tcpReachable = $tunnelService->isTcpReachable($target['host'], $target['port']);

        $base = [
            'connected' => false,
            'tcp_reachable' => $tcpReachable,
            'key_exists' => $keyExists,
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
            'tunnel' => $tunnel,
        ];

        $usesLoopback = in_array($target['host'], ['127.0.0.1', 'localhost', '::1'], true);

        if (! $tcpReachable) {
            $base['error'] = sprintf(
                'Host %s:%d tidak merespons.',
                $target['host'],
                $target['port']
            );
            $base['hint'] = $usesLoopback
                ? 'Tunnel SSH OLAP belum aktif di 127.0.0.1:5433. Pastikan tunnel pgsql_ssh / JumpHost VPC2 ke RDS sudah jalan. BESIGMA_SSH_* MySQL lama tidak dipakai.'
                : 'Laravel harus connect ke 127.0.0.1:5433 (tunnel OLAP), bukan langsung ke RDS.';

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
                'key_exists' => $keyExists,
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
                'tunnel' => $tunnel,
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
     * Target koneksi Laravel ke Postgres OLAP (tanpa password).
     *
     * @return array{host:string,port:int,database:string,username:string,driver:string,search_path:string}
     */
    public function targetMeta(): array
    {
        $cfg = config('database.connections.'.self::CONNECTION, []);

        return [
            'host' => (string) ($cfg['host'] ?? '127.0.0.1'),
            'port' => (int) ($cfg['port'] ?? 5433),
            'database' => (string) ($cfg['database'] ?? 'besigma_db'),
            'username' => (string) ($cfg['username'] ?? 'safety_evaluator_2'),
            'driver' => (string) ($cfg['driver'] ?? 'pgsql'),
            'search_path' => (string) ($cfg['search_path'] ?? 'public'),
        ];
    }

    private function hintForProbeFailure(Throwable $e, bool $tcpReachable): string
    {
        $message = $e->getMessage();

        if (! $tcpReachable) {
            return 'Jalankan SSH tunnel OLAP ke port 5433 terlebih dahulu (sama dengan pgsql_ssh).';
        }

        if (str_contains($message, 'password authentication failed') || str_contains($message, '28P01')) {
            return 'Tunnel terbuka, tetapi login ditolak. Periksa BESIGMA_DB_USERNAME / BESIGMA_DB_PASSWORD / BESIGMA_DB_DATABASE di .env.';
        }

        if (str_contains($message, 'does not exist') || str_contains($message, '3D000')) {
            return 'Tunnel terbuka, tetapi database tidak ditemukan. Pastikan BESIGMA_DB_DATABASE=besigma_db.';
        }

        if (
            str_contains($message, 'timeout expired')
            || str_contains($message, 'Connection refused')
            || str_contains($message, '08006')
        ) {
            return 'Port 5433 terbuka, tetapi Postgres tidak merespons. Biasanya tunnel SSH OLAP belum jalan atau sudah mati. Tutup proses lama di 5433, lalu jalankan setup-ssh-tunnel.bat (bukan setup-ssh-tunnel-besigma.bat MySQL). Pastikan PG_PORT=5432 di .env.';
        }

        return 'Tunnel terbuka, tetapi query Postgres gagal. Periksa user, database `besigma_db`, search_path, dan log RDS.';
    }

    /**
     * Cek apakah tunnel OLAP (pgsql_ssh) bisa query — membantu bedakan masalah tunnel vs database besigma_db.
     */
    public function olapTunnelProbe(): ?array
    {
        try {
            DB::connection('pgsql_ssh')->select('SELECT current_database() AS db_name, 1 AS ok');

            return ['ok' => true, 'database' => (string) config('database.connections.pgsql_ssh.database')];
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

        return str_contains($message, '1129')
            || str_contains($message, 'is blocked because of many connection errors')
            || str_contains($message, 'too many connections');
    }

    /**
     * Katalog read-only semua tabel + kolom di search_path saat ini.
     *
     * @return list<array{
     *     name:string,
     *     type:string,
     *     engine:?string,
     *     approx_rows:?int,
     *     comment:string,
     *     columns:list<array{
     *         name:string,
     *         type:string,
     *         nullable:bool,
     *         key:string,
     *         default:mixed,
     *         extra:string,
     *         comment:string
     *     }>
     * }>
     */
    public function describeAllTables(): array
    {
        $tableRows = DB::connection(self::CONNECTION)->select(
            "SELECT
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
             WHERE n.nspname = ANY (current_schemas(false))
               AND c.relkind IN ('r', 'p', 'v', 'm')
               AND NOT c.relispartition
             ORDER BY c.relname"
        );

        $columnRows = DB::connection(self::CONNECTION)->select(
            "SELECT
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
             WHERE cols.table_schema = ANY (current_schemas(false))
             ORDER BY cols.table_name, cols.ordinal_position"
        );

        $schema = [];
        foreach ($tableRows as $tableRow) {
            $name = (string) ($tableRow->table_name ?? '');
            if ($name === '') {
                continue;
            }
            $schema[$name] = [
                'name' => $name,
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
            $table = (string) ($columnRow->table_name ?? '');
            if ($table === '') {
                continue;
            }
            if (! isset($schema[$table])) {
                $schema[$table] = [
                    'name' => $table,
                    'type' => 'BASE TABLE',
                    'engine' => null,
                    'approx_rows' => null,
                    'comment' => '',
                    'columns' => [],
                ];
            }
            $schema[$table]['columns'][] = [
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
     * Ringkasan teks semua tabel+kolom, mudah disalin ke chat.
     *
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

    /**
     * @return array{
     *     local_host: string,
     *     local_port: int,
     *     ssh_host: string,
     *     ssh_port: int,
     *     ssh_user: string,
     *     ssh_pkey: string,
     *     remote_host: string,
     *     remote_port: int
     * }
     */
    public function tunnelMeta(): array
    {
        $cfg = config('database.connections.'.self::CONNECTION, []);
        $pkey = (string) ($cfg['ssh_pkey'] ?? '');
        $fallbackPkey = public_path('JumpHostVPC2.pem');
        if ($pkey === '' || ! is_file($pkey)) {
            $pkey = app(BesigmaTunnelService::class)->resolvePrivateKey($pkey !== '' ? $pkey : $fallbackPkey);
        }

        return [
            'local_host' => (string) ($cfg['host'] ?? '127.0.0.1'),
            'local_port' => (int) ($cfg['port'] ?? $cfg['local_port'] ?? 5433),
            'ssh_host' => (string) ($cfg['ssh_host'] ?? ''),
            'ssh_port' => (int) ($cfg['ssh_port'] ?? 22),
            'ssh_user' => (string) ($cfg['ssh_user'] ?? ''),
            'ssh_pkey' => $pkey,
            'remote_host' => (string) ($cfg['remote_host'] ?? $cfg['pg_host'] ?? ''),
            'remote_port' => (int) ($cfg['remote_port'] ?? $cfg['pg_port'] ?? 5432),
        ];
    }

    public function isTcpReachable(string $host, int $port): bool
    {
        return app(BesigmaTunnelService::class)->isTcpReachable($host, $port);
    }
}
