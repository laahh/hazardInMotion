<?php

declare(strict_types=1);

namespace App\Services\Besigma;

/**
 * Besigma memakai koneksi direct RDS (PG_HOST:PG_PORT), bukan SSH tunnel.
 * Kelas ini tetap ada agar injeksi di service ISC tidak perlu diubah;
 * applyRuntimeConfig / ensureListening tidak melakukan apa-apa.
 */
final class BesigmaTunnelService
{
    public const CONNECTION = 'besigma_db';

    private const SOCKET_TIMEOUT_SECONDS = 3;

    public function applyRuntimeConfig(): void
    {
        // Direct RDS — tidak ada rewrite host/port ke loopback tunnel.
    }

    public function ensureListening(): bool
    {
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
