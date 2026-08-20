<?php

declare(strict_types=1);

namespace App\Services\DwhRedshift;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Query read-only ke Amazon Redshift DWH.
 * Hanya SELECT — tidak ada INSERT/UPDATE/DELETE/DDL.
 */
final class DwhRedshiftQueryService
{
    public const CONNECTION = 'redshift';

    public const MAX_TABLES = 500;

    public const DEFAULT_PREVIEW_LIMIT = 50;

    public const MAX_PREVIEW_LIMIT = 100;

    /**
     * @return array{connected: bool, database: string|null, username: string|null, version: string|null, error: string|null}
     */
    public function ping(): array
    {
        try {
            $row = DB::connection(self::CONNECTION)->selectOne(
                'SELECT current_database() AS database, current_user AS username, version() AS version'
            );

            return [
                'connected' => true,
                'database' => $row->database ?? null,
                'username' => $row->username ?? null,
                'version' => $row->version ?? null,
                'error' => null,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'connected' => false,
                'database' => null,
                'username' => null,
                'version' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return list<array{schema: string}>
     */
    public function listSchemas(): array
    {
        $rows = DB::connection(self::CONNECTION)->select("
            SELECT schema_name AS schema
            FROM information_schema.schemata
            WHERE schema_name NOT IN ('pg_catalog', 'information_schema', 'pg_internal', 'pg_toast')
              AND schema_name NOT LIKE 'pg_temp_%'
              AND schema_name NOT LIKE 'pg_toast_temp_%'
            ORDER BY schema_name
        ");

        return array_map(static fn (object $row): array => [
            'schema' => (string) $row->schema,
        ], $rows);
    }

    /**
     * @return list<array{schema: string, table: string, type: string}>
     */
    public function listTables(?string $schema = null): array
    {
        $bindings = [self::MAX_TABLES];
        $schemaFilter = '';

        if ($schema !== null && $schema !== '') {
            $this->assertSafeIdentifier($schema, 'schema');
            $schemaFilter = 'AND table_schema = ?';
            array_unshift($bindings, $schema);
        }

        $rows = DB::connection(self::CONNECTION)->select("
            SELECT table_schema, table_name, table_type
            FROM information_schema.tables
            WHERE table_schema NOT IN ('pg_catalog', 'information_schema', 'pg_internal')
              {$schemaFilter}
            ORDER BY table_schema, table_name
            LIMIT ?
        ", $bindings);

        return array_map(static fn (object $row): array => [
            'schema' => (string) $row->table_schema,
            'table' => (string) $row->table_name,
            'type' => (string) $row->table_type,
        ], $rows);
    }

    /**
     * Preview baris tabel (SELECT * LIMIT n). Identifier di-whitelist.
     *
     * @return list<array<string, mixed>>
     */
    public function previewTable(string $schema, string $table, int $limit = self::DEFAULT_PREVIEW_LIMIT): array
    {
        $this->assertSafeIdentifier($schema, 'schema');
        $this->assertSafeIdentifier($table, 'table');

        $limit = max(1, min($limit, self::MAX_PREVIEW_LIMIT));
        $qualified = $this->quoteIdent($schema).'.'.$this->quoteIdent($table);

        $rows = DB::connection(self::CONNECTION)->select(
            "SELECT * FROM {$qualified} LIMIT {$limit}"
        );

        return array_map(static fn (object $row): array => (array) $row, $rows);
    }

    private function assertSafeIdentifier(string $value, string $field): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("Nama {$field} tidak valid.");
        }
    }

    private function quoteIdent(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
}
