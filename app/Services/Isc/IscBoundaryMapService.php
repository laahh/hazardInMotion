<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Services\Besigma\BesigmaConnectionService;
use App\Services\Besigma\BesigmaTunnelService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pembaca read-only tabel boundary Besigma untuk peta ISC.
 */
final class IscBoundaryMapService
{
    public const CONNECTION = 'besigma_db';

    public const CACHE_KEY = 'isc.besigma.boundaries.geojson.v2';

    public const CACHE_TTL_SECONDS = 45;

    public const OVERLAY_LIMIT = 40;

    /**
     * @var list<string>
     */
    public const TABLES = [
        'boundaries',
        'boundary_annotations',
        'boundary_annotation_roles',
        'boundary_competencies',
        'boundary_entries',
        'boundary_entry_units',
        'boundary_risks',
        'boundary_risk_levels',
        'boundary_status',
        'boundary_violations',
        'boundary_violation_units',
        'sites',
        'pits',
    ];

    /**
     * @var array<string, string>
     */
    private const BOUNDARY_COLUMN_TYPES = [
        'id' => 'char(36)',
        'name' => 'varchar',
        'code' => 'varchar',
        'type' => 'varchar',
        'polylines' => 'longtext',
        'shadow_polylines' => 'longtext',
        'polyline_center_point' => 'text',
        'shadow_polyline_center_point' => 'longtext',
        'polyline_color_hex' => 'varchar',
        'site_id' => 'char(36)',
        'pit_id' => 'char(36)',
        'site_code_raw' => 'varchar',
        'site_name' => 'varchar',
        'pit_code' => 'varchar',
        'pit_name' => 'varchar',
        'has_competency' => 'tinyint',
        'boundary_status' => 'varchar',
    ];

    public function __construct(
        private readonly BesigmaConnectionService $connection,
        private readonly BesigmaTunnelService $tunnel,
        private readonly IscBoundaryGeometryMapper $geometry,
        private readonly IscHazardBoundaryClassifier $hazard,
        private readonly IscSiteNormalizer $sites,
    ) {}

    public function isUp(): bool
    {
        $this->tunnel->applyRuntimeConfig();

        return $this->connection->isUp();
    }

    /**
     * @return array{connected:bool,tables:array<string,array{exists:bool,row_count:?int,error:?string}>}
     */
    public function tableStatus(): array
    {
        if (! $this->isUp()) {
            $tables = [];
            foreach (self::TABLES as $table) {
                $tables[$table] = ['exists' => false, 'row_count' => null, 'error' => 'besigma_db down'];
            }

            return ['connected' => false, 'tables' => $tables];
        }

        $tables = [];
        foreach (self::TABLES as $table) {
            $tables[$table] = $this->describeTable($table);
        }

        return ['connected' => true, 'tables' => $tables];
    }

    /**
     * @return array{
     *     type:string,
     *     features:list<array<string,mixed>>,
     *     records:list<array<string,mixed>>,
     *     columns:list<string>,
     *     connected:bool,
     *     error:?string
     * }
     */
    public function boundariesGeoJson(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => $this->buildBoundariesGeoJson());
    }

    /**
     * @return array{
     *     type:string,
     *     features:list<array<string,mixed>>,
     *     records:list<array<string,mixed>>,
     *     columns:list<string>,
     *     connected:bool,
     *     error:?string
     * }
     */
    private function buildBoundariesGeoJson(): array
    {
        $empty = [
            'type' => 'FeatureCollection',
            'features' => [],
            'records' => [],
            'columns' => [],
            'connected' => $this->isUp(),
            'error' => null,
        ];

        if (! $empty['connected']) {
            $empty['error'] = 'besigma_db tidak terhubung';

            return $empty;
        }

        try {
            $rows = DB::connection(self::CONNECTION)->select($this->boundariesSql());
            $violationCounts = $this->activeViolationCounts();

            $features = [];
            $records = [];
            foreach ($rows as $row) {
                $feature = $this->geometry->featureFromRow($row, self::BOUNDARY_COLUMN_TYPES);
                $props = $feature['properties'] ?? $this->geometry->scalarProperties($row, self::BOUNDARY_COLUMN_TYPES);
                $props = $this->enrichBoundaryProperties($props, $row, $violationCounts);
                $props['has_geometry'] = isset($feature['geometry']);
                $records[] = $props;

                if ($feature === null) {
                    continue;
                }
                $feature['properties'] = $props;
                $features[] = $feature;
            }

            return [
                'type' => 'FeatureCollection',
                'features' => $features,
                'records' => $records,
                'columns' => [
                    'id', 'name', 'code', 'type', 'hazard_kind', 'site_code',
                    'violations_count', 'is_active',
                ],
                'connected' => true,
                'error' => null,
            ];
        } catch (Throwable $e) {
            report($e);
            $this->connection->rememberFailure($e);
            $empty['error'] = $e->getMessage();

            return $empty;
        }
    }

    /**
     * Overlay ringan tanpa kolom geometri.
     *
     * @return array{
     *     violation_counts:array<string,int>,
     *     violations:list<array<string,mixed>>,
     *     violation_units:list<array<string,mixed>>
     * }
     */
    public function overlayData(): array
    {
        return [
            'violation_counts' => $this->kindCounts(),
            'violations' => $this->recentScalarRows('boundary_violations'),
            'violation_units' => $this->recentScalarRows('boundary_violation_units'),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function kindCounts(): array
    {
        $counts = [
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER => 0,
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE => 0,
            IscHazardBoundaryClassifier::KIND_UNIT_DANGER => 0,
        ];
        if (! $this->isUp()) {
            return $counts;
        }

        try {
            $people = DB::connection(self::CONNECTION)->select("
                SELECT is_competency, COUNT(DISTINCT user_id) AS c
                FROM boundary_violations
                WHERE is_deleted = 0
                  AND deleted_at IS NULL
                  AND status IN ('WARNING', 'STANDBY', 'DANGER')
                GROUP BY is_competency
            ");
            foreach ($people as $row) {
                $kind = ((int) ($row->is_competency ?? 0)) === 1
                    ? IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE
                    : IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER;
                $counts[$kind] = (int) ($row->c ?? 0);
            }

            $units = DB::connection(self::CONNECTION)->selectOne("
                SELECT COUNT(DISTINCT unit_id) AS c
                FROM boundary_violation_units
                WHERE is_deleted = 0
                  AND deleted_at IS NULL
                  AND status IN ('WARNING', 'STANDBY', 'DANGER')
            ");
            $counts[IscHazardBoundaryClassifier::KIND_UNIT_DANGER] = (int) ($units->c ?? 0);
        } catch (Throwable $e) {
            report($e);
            $this->connection->rememberFailure($e);
        }

        return $counts;
    }

    private function boundariesSql(): string
    {
        return "
            SELECT
                b.id,
                b.name,
                b.code,
                b.type,
                b.polylines,
                b.shadow_polylines,
                b.polyline_center_point,
                b.shadow_polyline_center_point,
                b.polyline_color_hex,
                b.site_id,
                b.pit_id,
                s.code AS site_code_raw,
                s.name AS site_name,
                p.code AS pit_code,
                p.name AS pit_name,
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM boundary_competencies bc
                    WHERE bc.boundary_id = b.id
                      AND bc.is_deleted = 0
                      AND bc.deleted_at IS NULL
                ) THEN 1 ELSE 0 END AS has_competency,
                (
                    SELECT bs.status
                    FROM boundary_status bs
                    WHERE bs.boundary_id = b.id
                      AND bs.is_deleted = 0
                    ORDER BY bs.created_at DESC
                    LIMIT 1
                ) AS boundary_status
            FROM boundaries b
            LEFT JOIN sites s ON s.id = b.site_id
            LEFT JOIN pits p ON p.id = b.pit_id
            WHERE b.is_deleted = 0
              AND b.is_active = 1
        ";
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, int>  $violationCounts
     * @return array<string, mixed>
     */
    private function enrichBoundaryProperties(array $props, object $row, array $violationCounts): array
    {
        $type = strtoupper(trim((string) ($row->type ?? '')));
        $hasCompetency = (int) ($row->has_competency ?? 0) === 1;
        $kind = null;
        if ($type !== 'INVERSE') {
            if ($hasCompetency) {
                $kind = IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE;
            } elseif ($type === 'DANGER_COMPETENCY') {
                $kind = IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER;
            }
        }

        $siteCode = $this->sites->codeFrom(
            $row->site_code_raw ?? null,
            $row->site_name ?? null,
        );

        $props['id'] = (string) ($row->id ?? '');
        $props['name'] = (string) ($row->name ?? '');
        $props['code'] = (string) ($row->code ?? '');
        $props['type'] = $type;
        $props['hazard_kind'] = $kind;
        $props['hazard_kind_label'] = $this->hazard->label($kind);
        $props['site_code'] = $siteCode;
        $props['site_label'] = $siteCode !== null ? $this->sites->label($siteCode) : ($row->site_name ?? null);
        $props['pit_name'] = $row->pit_name ?? null;
        $props['risk_color'] = $this->nullableString($row->polyline_color_hex ?? null) ?? '#c5221f';
        $props['status_name'] = $row->boundary_status ?? null;
        $props['violations_count'] = $violationCounts[(string) ($row->id ?? '')] ?? 0;
        $props['is_active'] = 1;

        return $props;
    }

    /**
     * @return array<string, int>
     */
    private function activeViolationCounts(): array
    {
        if (! $this->isUp()) {
            return [];
        }

        try {
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT boundary_id AS bid, COUNT(*) AS c
                FROM boundary_violations
                WHERE is_deleted = 0
                  AND deleted_at IS NULL
                  AND status IN ('WARNING', 'STANDBY', 'DANGER')
                GROUP BY boundary_id
            ");
            $out = [];
            foreach ($rows as $row) {
                $out[(string) ($row->bid ?? '')] = (int) ($row->c ?? 0);
            }

            return $out;
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentScalarRows(string $table): array
    {
        if (! $this->isAllowedTable($table) || ! $this->isUp()) {
            return [];
        }

        $columns = $table === 'boundary_violation_units'
            ? 'id, unit_id, user_id, boundary_id, site_id, status, is_competency, created_at'
            : 'id, user_id, boundary_id, site_id, status, is_competency, created_at';

        try {
            $rows = DB::connection(self::CONNECTION)->select(
                'SELECT '.$columns.'
                 FROM `'.$table.'`
                 WHERE is_deleted = 0
                 ORDER BY created_at DESC
                 LIMIT '.self::OVERLAY_LIMIT
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (string) ($row->id ?? ''),
                'user_id' => isset($row->user_id) ? (string) $row->user_id : null,
                'unit_id' => isset($row->unit_id) ? (string) $row->unit_id : null,
                'boundary_id' => isset($row->boundary_id) ? (string) $row->boundary_id : null,
                'site_id' => isset($row->site_id) ? (string) $row->site_id : null,
                'status' => $row->status ?? null,
                'is_competency' => isset($row->is_competency) ? (int) $row->is_competency : null,
                'created_at' => isset($row->created_at) ? (string) $row->created_at : null,
            ];
        }

        return $out;
    }

    /**
     * @return array{exists:bool,row_count:?int,error:?string}
     */
    private function describeTable(string $table): array
    {
        if (! $this->isAllowedTable($table)) {
            return ['exists' => false, 'row_count' => null, 'error' => 'not allowed'];
        }

        try {
            $count = DB::connection(self::CONNECTION)->selectOne(
                'SELECT COUNT(*) AS c FROM `'.$table.'`'
            );

            return [
                'exists' => true,
                'row_count' => isset($count->c) ? (int) $count->c : 0,
                'error' => null,
            ];
        } catch (Throwable $e) {
            return ['exists' => false, 'row_count' => null, 'error' => $e->getMessage()];
        }
    }

    private function isAllowedTable(string $table): bool
    {
        return in_array($table, self::TABLES, true);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
