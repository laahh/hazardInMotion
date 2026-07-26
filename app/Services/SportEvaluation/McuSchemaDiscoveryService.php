<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Discovery read-only skema MCU OLAP (bcsid.mv_ftw_mcu) untuk verifikasi mapping.
 */
final class McuSchemaDiscoveryService
{
    public function __construct(
        private readonly McuConnectionService $connection,
    ) {}

    /**
     * @return array{
     *     up:bool,
     *     suggestions:array{
     *         table:?string,
     *         exam_date:?string,
     *         identity:array{sid:?string,nik:?string,gender:?string},
     *         labs:array{glucose:?string,cholesterol:?string,triglyceride:?string,uric_acid:?string},
     *         json_fields:array<int,string>
     *     },
     *     candidate_tables:array<int,string>,
     *     candidate_columns:array<string,array<int,string>>,
     *     message:string
     * }
     */
    public function discover(): array
    {
        $empty = [
            'up' => false,
            'suggestions' => [
                'table' => null,
                'exam_date' => null,
                'identity' => ['sid' => null, 'nik' => null, 'gender' => null],
                'labs' => [
                    'glucose' => null,
                    'cholesterol' => null,
                    'triglyceride' => null,
                    'uric_acid' => null,
                ],
                'json_fields' => [],
            ],
            'candidate_tables' => [],
            'candidate_columns' => [],
            'message' => 'Koneksi MCU tidak tersedia. Jalankan setup-ssh-tunnel.bat (localhost:5433).',
        ];

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $configuredTable = trim((string) config('bemcu.table', 'mv_ftw_mcu'));
            $cols = DB::connection(McuConnectionService::CONNECTION)->select("
                SELECT a.attname AS column_name
                FROM pg_catalog.pg_attribute a
                JOIN pg_catalog.pg_class c ON a.attrelid = c.oid
                JOIN pg_catalog.pg_namespace n ON c.relnamespace = n.oid
                WHERE n.nspname = 'bcsid'
                  AND c.relname = ?
                  AND a.attnum > 0
                  AND NOT a.attisdropped
                ORDER BY a.attnum
            ", [$configuredTable === 'mv_ftw_mcu' || $configuredTable === 'bcsid.mv_ftw_mcu' ? 'mv_ftw_mcu' : $configuredTable]);

            $columns = array_map(static fn ($r): string => (string) $r->column_name, $cols);

            $tables = DB::connection(McuConnectionService::CONNECTION)->select("
                SELECT c.relname AS table_name
                FROM pg_catalog.pg_class c
                JOIN pg_catalog.pg_namespace n ON c.relnamespace = n.oid
                WHERE n.nspname = 'bcsid'
                  AND c.relkind IN ('r', 'm', 'v')
                  AND c.relname ILIKE '%mcu%'
                ORDER BY c.relname
            ");
            $candidateTables = array_map(static fn ($t): string => (string) $t->table_name, $tables);

            $suggestions = [
                'table' => in_array('mv_ftw_mcu', $candidateTables, true) || $columns !== []
                    ? 'mv_ftw_mcu'
                    : null,
                'exam_date' => in_array('tanggal_mulai', $columns, true) ? 'tanggal_mulai' : null,
                'identity' => [
                    'sid' => in_array('kode_sid', $columns, true) ? 'kode_sid' : null,
                    'nik' => in_array('nik', $columns, true) ? 'nik' : null,
                    'gender' => null,
                ],
                'labs' => [
                    'glucose' => 'Gula Darah Puasa (GDP) tinggi / Terkonfirmasi Diabetes Militus',
                    'cholesterol' => 'Kolestrol Total / LDL',
                    'triglyceride' => 'Trigliserida',
                    'uric_acid' => 'Asam Urat',
                ],
                'json_fields' => array_values(array_filter(
                    ['kondisi_kritis', 'kondisi_non_kritis'],
                    static fn (string $f): bool => in_array($f, $columns, true)
                )),
            ];

            return [
                'up' => true,
                'suggestions' => $suggestions,
                'candidate_tables' => $candidateTables,
                'candidate_columns' => ['bcsid.mv_ftw_mcu' => $columns],
                'message' => $suggestions['table'] && $suggestions['json_fields'] !== []
                    ? 'MCU OLAP OK. Sumber: bcsid.mv_ftw_mcu (kondisi JSONB). Mapping default di config/bemcu.php.'
                    : 'MCU up, tetapi mv_ftw_mcu / kolom JSON belum terdeteksi.',
            ];
        } catch (Throwable $e) {
            report($e);

            return array_merge($empty, [
                'up' => false,
                'message' => 'Discovery gagal: '.$e->getMessage(),
            ]);
        }
    }
}
