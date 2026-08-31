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
 * Kolom geometry dideteksi dari information_schema (bukan hardcode satu skema).
 */
final class IscBoundaryMapService
{
    public const CONNECTION = 'besigma_db';

    public const BOUNDARY_LIMIT = 400;

    public const LIST_LIMIT = 80;

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
    ];

    /**
     * @var array<string, list<array<string, mixed>>>|null
     */
    private ?array $lookupCache = null;

    public function __construct(
        private readonly BesigmaConnectionService $connection,
        private readonly BesigmaTunnelService $tunnel,
        private readonly IscBoundaryGeometryMapper $geometry,
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
     * @return array{type:string,features:list<array<string,mixed>>}
     */
    public function boundariesGeoJson(): array
    {
        if (! $this->isUp() || ! $this->tableExists('boundaries')) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        try {
            $columnTypes = $this->columnTypes('boundaries');
            $selects = $this->selectList($columnTypes);
            $sql = 'SELECT '.$selects.' FROM `boundaries` LIMIT '.self::BOUNDARY_LIMIT;
            $rows = DB::connection(self::CONNECTION)->select($sql);

            $features = [];
            foreach ($rows as $row) {
                $feature = $this->geometry->featureFromRow($row, $columnTypes);
                if ($feature === null) {
                    continue;
                }
                $features[] = $this->enrichBoundaryFeature($feature);
            }

            return ['type' => 'FeatureCollection', 'features' => $features];
        } catch (Throwable $e) {
            report($e);

            return ['type' => 'FeatureCollection', 'features' => []];
        }
    }

    /**
     * @return array{
     *     statuses:list<array<string,mixed>>,
     *     risk_levels:list<array<string,mixed>>,
     *     entries:list<array<string,mixed>>,
     *     violations:list<array<string,mixed>>,
     *     annotations:list<array<string,mixed>>,
     *     competencies:list<array<string,mixed>>
     * }
     */
    public function overlayData(): array
    {
        return [
            'statuses' => $this->rows('boundary_status'),
            'risk_levels' => $this->rows('boundary_risk_levels'),
            'entries' => $this->rows('boundary_entries'),
            'violations' => $this->rows('boundary_violations'),
            'annotations' => $this->rows('boundary_annotations'),
            'competencies' => $this->rows('boundary_competencies'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lookup(string $table): array
    {
        $this->lookupCache ??= [];
        if (! isset($this->lookupCache[$table])) {
            $this->lookupCache[$table] = $this->rows($table);
        }

        return $this->lookupCache[$table];
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
            if (! $this->tableExists($table)) {
                return ['exists' => false, 'row_count' => null, 'error' => null];
            }
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

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $table): array
    {
        if (! $this->isUp() || ! $this->tableExists($table)) {
            return [];
        }

        try {
            $columnTypes = $this->columnTypes($table);
            $selects = $this->selectList($columnTypes, includeGeojson: false);
            $order = $this->orderClause($columnTypes);
            $sql = 'SELECT '.$selects.' FROM `'.$table.'`'.$order.' LIMIT '.self::LIST_LIMIT;
            $rows = DB::connection(self::CONNECTION)->select($sql);

            $out = [];
            foreach ($rows as $row) {
                $out[] = $this->geometry->scalarProperties($row, $columnTypes);
            }

            return $out;
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>
     */
    private function enrichBoundaryFeature(array $feature): array
    {
        $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $statusId = $props['status_id'] ?? $props['boundary_status_id'] ?? null;
        $riskId = $props['risk_level_id'] ?? $props['risk_id'] ?? null;

        $statuses = $this->lookup('boundary_status');
        $levels = $this->lookup('boundary_risk_levels');

        if ($statusId !== null) {
            foreach ($statuses as $status) {
                if ((string) ($status['id'] ?? '') === (string) $statusId) {
                    $props['status_name'] = $status['name'] ?? $status['status'] ?? $status['label'] ?? null;
                    $props['status_color'] = $status['color'] ?? $status['colour'] ?? null;
                    break;
                }
            }
        }

        if ($riskId !== null) {
            foreach ($levels as $level) {
                if ((string) ($level['id'] ?? '') === (string) $riskId) {
                    $props['risk_name'] = $level['name'] ?? $level['label'] ?? null;
                    $props['risk_color'] = $level['color'] ?? $level['colour'] ?? null;
                    break;
                }
            }
        }

        $feature['properties'] = $props;

        return $feature;
    }

    /**
     * @param  array<string, string>  $columnTypes
     */
    private function selectList(array $columnTypes, bool $includeGeojson = true): string
    {
        $parts = [];
        $geojsonAdded = false;

        foreach ($columnTypes as $name => $type) {
            if ($includeGeojson && $this->geometry->isSpatialType($type) && ! $geojsonAdded) {
                $parts[] = 'ST_AsGeoJSON(`'.$name.'`) AS `_geojson`';
                $geojsonAdded = true;
                continue;
            }
            if ($this->geometry->isSpatialType($type)) {
                continue;
            }
            $parts[] = '`'.$name.'`';
        }

        if ($parts === []) {
            $parts[] = '*';
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, string>  $columnTypes
     */
    private function orderClause(array $columnTypes): string
    {
        foreach (['created_at', 'updated_at', 'occurred_at', 'entered_at', 'violated_at', 'id'] as $column) {
            if (isset($columnTypes[$column])) {
                return ' ORDER BY `'.$column.'` DESC';
            }
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function columnTypes(string $table): array
    {
        if (! $this->isAllowedTable($table)) {
            return [];
        }

        return Cache::remember('isc:besigma_cols:'.$table, 300, function () use ($table): array {
            $rows = DB::connection(self::CONNECTION)->select('SHOW COLUMNS FROM `'.$table.'`');
            $types = [];
            foreach ($rows as $row) {
                $field = (string) ($row->Field ?? '');
                if ($field === '') {
                    continue;
                }
                $types[$field] = strtolower((string) ($row->Type ?? ''));
            }

            return $types;
        });
    }

    private function tableExists(string $table): bool
    {
        if (! $this->isAllowedTable($table)) {
            return false;
        }

        try {
            return (bool) Cache::remember('isc:besigma_exists:'.$table, 300, function () use ($table): bool {
                $rows = DB::connection(self::CONNECTION)->select(
                    'SHOW TABLES LIKE ?',
                    [$table]
                );

                return $rows !== [];
            });
        } catch (Throwable $e) {
            return false;
        }
    }

    private function isAllowedTable(string $table): bool
    {
        return in_array($table, self::TABLES, true);
    }
}
