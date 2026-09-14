<?php

declare(strict_types=1);

namespace App\Services\Besigma;

/**
 * Besigma memakai Postgres OLAP lewat tunnel lokal (127.0.0.1:5433),
 * sama seperti pgsql_ssh — bukan MySQL jumphost lama (10.11.58.139:3307).
 */
final class BesigmaTunnelService
{
    public const CONNECTION = 'besigma_db';

    public const WORKING_SSH_HOST = '13.212.87.127';

    public const WORKING_REMOTE_HOST = 'postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com';

    private const DEAD_SSH_HOSTS = [
        '13.250.29.29',
        '52.74.245.15',
    ];

    private const DEAD_REMOTE_HOSTS = [
        '10.11.58.139',
    ];

    /**
     * Paksa koneksi Laravel ke loopback tunnel PG, bukan ke RDS / MySQL private.
     */
    public function applyRuntimeConfig(): void
    {
        $cfg = config('database.connections.'.self::CONNECTION, []);
        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        $remoteHost = (string) ($cfg['remote_host'] ?? $cfg['pg_host'] ?? self::WORKING_REMOTE_HOST);
        $localPort = (int) ($cfg['local_port'] ?? 5433);
        $sshHost = (string) ($cfg['ssh_host'] ?? self::WORKING_SSH_HOST);

        if (in_array($remoteHost, self::DEAD_REMOTE_HOSTS, true) || $remoteHost === '') {
            $remoteHost = self::WORKING_REMOTE_HOST;
            config([
                'database.connections.'.self::CONNECTION.'.remote_host' => $remoteHost,
            ]);
        }

        $mustTunnel = $host === $remoteHost
            || $host === self::WORKING_REMOTE_HOST
            || str_contains($host, 'rds.amazonaws.com')
            || in_array($host, self::DEAD_REMOTE_HOSTS, true)
            || $host === '';

        if ($mustTunnel) {
            config([
                'database.connections.'.self::CONNECTION.'.host' => '127.0.0.1',
                'database.connections.'.self::CONNECTION.'.port' => $localPort,
            ]);
        }

        if ($sshHost === '' || in_array($sshHost, self::DEAD_SSH_HOSTS, true)) {
            config([
                'database.connections.'.self::CONNECTION.'.ssh_host' => self::WORKING_SSH_HOST,
            ]);
        }

        $pkey = $this->resolvePrivateKey((string) ($cfg['ssh_pkey'] ?? ''));
        if ($pkey !== '') {
            config([
                'database.connections.'.self::CONNECTION.'.ssh_pkey' => $pkey,
            ]);
        }

        try {
            \Illuminate\Support\Facades\DB::purge(self::CONNECTION);
        } catch (\Throwable $e) {
            // Koneksi mungkin belum pernah dibuka.
        }
    }

    /**
     * Nyalakan ssh -f -N jika port tunnel belum listen. Hanya Linux, bukan saat test.
     */
    public function ensureListening(): bool
    {
        $this->applyRuntimeConfig();

        $port = (int) config('database.connections.'.self::CONNECTION.'.port', 5433);
        if ($this->isTcpReachable('127.0.0.1', $port)) {
            return true;
        }

        if (PHP_OS_FAMILY !== 'Linux' || app()->runningUnitTests()) {
            return false;
        }

        $this->startBackgroundTunnel();

        $deadline = microtime(true) + 3;
        while (microtime(true) < $deadline) {
            if ($this->isTcpReachable('127.0.0.1', $port)) {
                return true;
            }
            usleep(200000);
        }

        return $this->isTcpReachable('127.0.0.1', $port);
    }

    public function isTcpReachable(string $host, int $port, float $timeout = 0.4): bool
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

    public function resolvePrivateKey(string $configured): string
    {
        $candidates = [
            $configured,
            public_path('JumpHostVPC2.pem'),
            (string) config('database.connections.pgsql_ssh.ssh_pkey', ''),
            public_path('bsigma-jumpserver.pem'),
            public_path('besigma-jumpserver.pem'),
        ];

        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return $configured;
    }

    private function startBackgroundTunnel(): void
    {
        $cfg = config('database.connections.'.self::CONNECTION, []);
        $pkey = $this->resolvePrivateKey((string) ($cfg['ssh_pkey'] ?? ''));
        $sshHost = (string) ($cfg['ssh_host'] ?? self::WORKING_SSH_HOST);
        $sshUser = (string) ($cfg['ssh_user'] ?? 'ubuntu');
        $sshPort = (int) ($cfg['ssh_port'] ?? 22);
        $localPort = (int) ($cfg['local_port'] ?? 5433);
        $remoteHost = (string) ($cfg['remote_host'] ?? $cfg['pg_host'] ?? self::WORKING_REMOTE_HOST);
        $remotePort = (int) ($cfg['remote_port'] ?? $cfg['pg_port'] ?? 5432);

        if (in_array($remoteHost, self::DEAD_REMOTE_HOSTS, true) || $remoteHost === '') {
            $remoteHost = self::WORKING_REMOTE_HOST;
        }

        if ($pkey === '' || ! is_file($pkey) || $sshHost === '') {
            return;
        }

        @chmod($pkey, 0600);

        $forward = sprintf('127.0.0.1:%d:%s:%d', $localPort, $remoteHost, $remotePort);

        $command = sprintf(
            'ssh -f -N -L %s -i %s -p %d -o StrictHostKeyChecking=no -o IdentitiesOnly=yes -o ExitOnForwardFailure=yes -o ServerAliveInterval=30 -o BatchMode=yes %s@%s',
            escapeshellarg($forward),
            escapeshellarg($pkey),
            $sshPort,
            escapeshellarg($sshUser),
            escapeshellarg($sshHost)
        );

        exec($command.' 2>/dev/null');
    }
}
