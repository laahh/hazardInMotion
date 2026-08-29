<?php

declare(strict_types=1);

namespace App\Services\PembatasanLV;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Query read-only ke Postgres OLAP (pgsql_direct / PG_HOST).
 * SET LOCAL statement_timeout di dalam transaksi agar koneksi pool tidak
 * tertinggal timeout, dan request web gagal cepat kalau query berat.
 */
final class PembatasanLVOlapQuery
{
    public const CONNECTION = 'pgsql_direct';

    /**
     * @param  list<mixed>  $bindings
     * @return list<object>
     */
    public function select(string $sql, array $bindings = [], int $timeoutMs = 2500): array
    {
        $connection = DB::connection(self::CONNECTION);

        return $connection->transaction(function () use ($connection, $sql, $bindings, $timeoutMs): array {
            $safeTimeout = max(500, min($timeoutMs, 8000));
            $connection->unprepared("SET LOCAL statement_timeout = '{$safeTimeout}ms'");

            /** @var list<object> $rows */
            $rows = $connection->select($sql, $bindings);

            return $rows;
        });
    }

    public function isReachable(): bool
    {
        try {
            $host = config('database.connections.'.self::CONNECTION.'.host');
            $port = config('database.connections.'.self::CONNECTION.'.port');
            if (! is_string($host) || $host === '' || ! is_numeric($port) || (int) $port <= 0) {
                return true;
            }

            $socket = @fsockopen($host, (int) $port, $errno, $errstr, 2);
            if (! is_resource($socket)) {
                return false;
            }

            fclose($socket);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
