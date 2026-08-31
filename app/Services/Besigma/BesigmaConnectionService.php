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

    private const TABLE_SAMPLE_LIMIT = 30;

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

            $tableCountRow = DB::connection(self::CONNECTION)->selectOne(
                'SELECT COUNT(*) AS table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
            );

            $tableRows = DB::connection(self::CONNECTION)->select(
                'SELECT TABLE_NAME AS table_name
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                 ORDER BY TABLE_NAME
                 LIMIT '.self::TABLE_SAMPLE_LIMIT
            );

            $tables = [];
            foreach ($tableRows as $tableRow) {
                $name = (string) ($tableRow->table_name ?? $tableRow->TABLE_NAME ?? '');
                if ($name !== '') {
                    $tables[] = $name;
                }
            }

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
                'table_count' => isset($tableCountRow->table_count) ? (int) $tableCountRow->table_count : count($tables),
                'tables' => $tables,
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
