<?php

declare(strict_types=1);

namespace App\Services\PembatasanLV;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Query read-only ke Postgres OLAP.
 * Coba RDS langsung (pgsql_direct), lalu tunnel lokal (pgsql_ssh),
 * supaya autocomplete tetap hidup di mesin yang hanya punya tunnel 5433.
 */
final class PembatasanLVOlapQuery
{
    public const CONNECTION_DIRECT = 'pgsql_direct';

    public const CONNECTION_TUNNEL = 'pgsql_ssh';

    private const UP_CACHE_KEY = 'pembatasan_lv:olap_connection_v2';

    private const UP_CACHE_TTL_SECONDS = 20;

    /**
     * @param  list<mixed>  $bindings
     * @return list<object>
     */
    public function select(string $sql, array $bindings = [], int $timeoutMs = 4000): array
    {
        $name = $this->connectionName();
        if ($name === null) {
            throw new RuntimeException('Postgres OLAP tidak terjangkau (pgsql_direct / pgsql_ssh).');
        }

        $connection = DB::connection($name);
        $safeTimeout = max(500, min($timeoutMs, 8000));

        return $connection->transaction(function () use ($connection, $sql, $bindings, $safeTimeout): array {
            $connection->unprepared("SET LOCAL statement_timeout = '{$safeTimeout}ms'");

            /** @var list<object> $rows */
            $rows = $connection->select($sql, $bindings);

            return $rows;
        });
    }

    public function isReachable(): bool
    {
        return $this->connectionName() !== null;
    }

    public function connectionName(): ?string
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
            Log::warning('PembatasanLVOlapQuery cache: '.$e->getMessage());
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
}
