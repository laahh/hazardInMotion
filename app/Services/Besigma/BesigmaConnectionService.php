<?php

declare(strict_types=1);

namespace App\Services\Besigma;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pemeriksa koneksi MySQL Besigma lewat SSH jumphost (setup-ssh-tunnel-besigma.bat).
 * Laravel connect ke 127.0.0.1:3307; tunnel harus sudah berjalan secara manual.
 */
final class BesigmaConnectionService
{
    public const CONNECTION = 'besigma_db';

    private const CACHE_KEY = 'besigma:is_up_v1';

    private const CACHE_TTL_SECONDS = 20;

    private ?bool $requestCache = null;

    public function isUp(): bool
    {
        if ($this->requestCache !== null) {
            return $this->requestCache;
        }

        try {
            $this->requestCache = (bool) Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL_SECONDS,
                function (): bool {
                    try {
                        DB::connection(self::CONNECTION)->select('SELECT 1');

                        return true;
                    } catch (Throwable $e) {
                        return false;
                    }
                }
            );
        } catch (Throwable $e) {
            report($e);
            $this->requestCache = false;
        }

        return $this->requestCache;
    }

    public function forgetCachedStatus(): void
    {
        $this->requestCache = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Tes koneksi nyata (tanpa cache) untuk halaman diagnostik jumphost.
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
        $keyExists = is_file($tunnel['ssh_pkey']);
        $tcpReachable = $tunnelService->isTcpReachable($tunnel['local_host'], $tunnel['local_port']);

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
            'tunnel' => $tunnel,
        ];

        $usesLoopback = in_array($tunnel['local_host'], ['127.0.0.1', 'localhost', '::1'], true);

        if (! $keyExists) {
            $base['hint'] = 'File private key jumphost tidak ditemukan. Pastikan public/bsigma-jumpserver.pem ada, atau set BESIGMA_SSH_PKEY ke path Linux di server.';
        }

        if (! $tcpReachable) {
            $base['error'] = sprintf(
                'Host %s:%d tidak merespons.',
                $tunnel['local_host'],
                $tunnel['local_port']
            );
            $base['hint'] = $usesLoopback
                ? 'Tunnel SSH belum aktif di 127.0.0.1:3307. Di server, pastikan proses ssh -L ke jumphost 52.74.245.15 masih jalan (setup-ssh-tunnel-besigma.sh).'
                : 'App server tidak bisa tembus MySQL langsung. Laravel harus memakai 127.0.0.1:3307 lewat jumphost.';

            return $base;
        }

        $started = microtime(true);

        try {
            $row = DB::connection(self::CONNECTION)->selectOne(
                'SELECT DATABASE() AS db_name, USER() AS db_user, VERSION() AS db_version, NOW() AS db_time'
            );

            $latencyMs = round((microtime(true) - $started) * 1000, 1);

            $schema = $this->describeAllTables();
            $tables = array_map(
                static fn (array $table): string => $table['name'],
                $schema
            );

            $this->requestCache = true;
            Cache::put(self::CACHE_KEY, true, self::CACHE_TTL_SECONDS);

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
                'tunnel' => $tunnel,
            ];
        } catch (Throwable $e) {
            report($e);

            $base['error'] = $e->getMessage();
            $base['hint'] = $tcpReachable
                ? 'Tunnel terbuka, tetapi login MySQL gagal. Periksa BESIGMA_DB_USERNAME / BESIGMA_DB_PASSWORD / BESIGMA_DB_DATABASE di .env.'
                : 'Jalankan setup-ssh-tunnel-besigma.bat terlebih dahulu.';
            $base['latency_ms'] = round((microtime(true) - $started) * 1000, 1);

            return $base;
        }
    }

    /**
     * Katalog read-only semua tabel + kolom di DATABASE() saat ini.
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
            'SELECT
                TABLE_NAME AS table_name,
                TABLE_TYPE AS table_type,
                ENGINE AS engine,
                TABLE_ROWS AS approx_rows,
                TABLE_COMMENT AS table_comment
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME'
        );

        $columnRows = DB::connection(self::CONNECTION)->select(
            'SELECT
                TABLE_NAME AS table_name,
                COLUMN_NAME AS column_name,
                COLUMN_TYPE AS column_type,
                IS_NULLABLE AS is_nullable,
                COLUMN_KEY AS column_key,
                COLUMN_DEFAULT AS column_default,
                EXTRA AS extra,
                COLUMN_COMMENT AS column_comment
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME, ORDINAL_POSITION'
        );

        $schema = [];
        foreach ($tableRows as $tableRow) {
            $name = (string) ($tableRow->table_name ?? $tableRow->TABLE_NAME ?? '');
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
            $table = (string) ($columnRow->table_name ?? $columnRow->TABLE_NAME ?? '');
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
        $fallbackPkey = public_path('bsigma-jumpserver.pem');
        if ($pkey === '' || ! is_file($pkey)) {
            $pkey = $fallbackPkey;
        }

        return [
            'local_host' => (string) ($cfg['host'] ?? '127.0.0.1'),
            'local_port' => (int) ($cfg['port'] ?? $cfg['local_port'] ?? 3307),
            'ssh_host' => (string) ($cfg['ssh_host'] ?? ''),
            'ssh_port' => (int) ($cfg['ssh_port'] ?? 22),
            'ssh_user' => (string) ($cfg['ssh_user'] ?? ''),
            'ssh_pkey' => $pkey,
            'remote_host' => (string) ($cfg['remote_host'] ?? ''),
            'remote_port' => (int) ($cfg['remote_port'] ?? 3306),
        ];
    }

    public function isTcpReachable(string $host, int $port): bool
    {
        return app(BesigmaTunnelService::class)->isTcpReachable($host, $port);
    }
}
