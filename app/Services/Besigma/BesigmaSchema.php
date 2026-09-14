<?php

declare(strict_types=1);

namespace App\Services\Besigma;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Schema Postgres untuk tabel Besigma di OLAP.
 * Database: besigma — schema: besigma_db — contoh: besigma_db.boundaries
 */
final class BesigmaSchema
{
    public const SCHEMA = 'besigma_db';

    public const CONNECTION = 'besigma_db';

    /**
     * Qualify nama tabel: users → besigma_db.users
     * Alias: "users as u" → "besigma_db.users as u"
     */
    public static function table(string $table): string
    {
        $table = trim($table);
        if ($table === '') {
            return self::SCHEMA;
        }

        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s+as\s+([a-zA-Z_][a-zA-Z0-9_]*)$/i', $table, $m) === 1) {
            return self::qualify($m[1]).' as '.$m[2];
        }

        return self::qualify($table);
    }

    public static function qualify(string $table): string
    {
        $table = trim($table);
        if ($table === '') {
            return self::SCHEMA;
        }
        if (str_contains($table, '.')) {
            return $table;
        }

        return self::SCHEMA.'.'.$table;
    }

    public static function hasTable(string $table, string $connection = self::CONNECTION): bool
    {
        $name = trim($table);
        if (str_contains($name, '.')) {
            [$schema, $name] = explode('.', $name, 2);
        } else {
            $schema = self::SCHEMA;
        }

        try {
            $row = DB::connection($connection)->selectOne(
                'SELECT EXISTS (
                    SELECT 1
                    FROM information_schema.tables
                    WHERE table_schema = ?
                      AND table_name = ?
                ) AS e',
                [$schema, $name]
            );

            return (bool) ($row->e ?? false);
        } catch (Throwable) {
            return false;
        }
    }
}
