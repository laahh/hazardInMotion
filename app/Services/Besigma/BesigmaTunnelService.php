<?php

declare(strict_types=1);

namespace App\Services\Besigma;

/**
 * Besigma: direct RDS (PG_HOST:PG_PORT), sama seperti RFID.
 * applyRuntimeConfig() menolak host loopback / port tunnel lama (3307, 5433).
 */
final class BesigmaTunnelService
{
    public const CONNECTION = 'besigma_db';

    private const RDS_HOST = 'postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com';

    private const LEGACY_TUNNEL_PORTS = [3307, 5433];

    private const LOOPBACK_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    private const SOCKET_TIMEOUT_SECONDS = 3;

    /**
     * Paksa direct RDS + schema besigma_db jika config masih tunnel lama / search_path salah.
     */
    public function applyRuntimeConfig(): void
    {
        $cfg = config('database.connections.'.self::CONNECTION, []);
        $host = strtolower(trim((string) ($cfg['host'] ?? '')));
        $port = (int) ($cfg['port'] ?? 0);
        $searchPath = (string) ($cfg['search_path'] ?? '');

        $isLegacyTunnel = in_array($host, self::LOOPBACK_HOSTS, true)
            || in_array($port, self::LEGACY_TUNNEL_PORTS, true);

        $updates = [];

        if ($isLegacyTunnel) {
            $updates['database.connections.'.self::CONNECTION.'.host'] = (string) (env('BESIGMA_DB_HOST') ?: env('PG_HOST', self::RDS_HOST));
            $updates['database.connections.'.self::CONNECTION.'.port'] = (int) (env('BESIGMA_DB_PORT') ?: env('PG_PORT', 5432));
        }

        // Tabel live ada di schema Postgres `besigma_db` (bukan public).
        if ($searchPath === '' || $searchPath === 'public' || ! str_contains($searchPath, 'besigma_db')) {
            $updates['database.connections.'.self::CONNECTION.'.search_path'] = (string) (
                env('BESIGMA_DB_SEARCH_PATH') ?: 'besigma_db,public'
            );
        }

        if ($updates === []) {
            return;
        }

        config($updates);

        try {
            \Illuminate\Support\Facades\DB::purge(self::CONNECTION);
        } catch (\Throwable $e) {
            // Koneksi mungkin belum pernah dibuka.
        }
    }

    public function ensureListening(): bool
    {
        $this->applyRuntimeConfig();

        return true;
    }

    public function isTcpReachable(string $host, int $port, float $timeout = self::SOCKET_TIMEOUT_SECONDS): bool
    {
        if ($host === '' || $port < 1) {
            return false;
        }

        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (is_resource($connection)) {
            fclose($connection);

            return true;
        }

        return false;
    }
}
