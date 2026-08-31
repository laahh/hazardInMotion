<?php

declare(strict_types=1);

namespace App\Services\Isc;

/**
 * Ubah kolom geometry/JSON/lat-lng Besigma menjadi GeoJSON Feature.
 */
final class IscBoundaryGeometryMapper
{
    /**
     * @param  array<string, string>  $columnTypes  name => mysql type
     * @return array{type:string,properties:array<string,mixed>,geometry:array<string,mixed>}|null
     */
    public function featureFromRow(object $row, array $columnTypes): ?array
    {
        $properties = $this->scalarProperties($row, $columnTypes);
        $geometry = $this->geometryFromRow($row, $columnTypes);

        if ($geometry === null) {
            return null;
        }

        return [
            'type' => 'Feature',
            'properties' => $properties,
            'geometry' => $geometry,
        ];
    }

    /**
     * @param  array<string, string>  $columnTypes
     * @return array<string, mixed>
     */
    public function scalarProperties(object $row, array $columnTypes): array
    {
        $props = [];
        foreach ($columnTypes as $name => $type) {
            if ($this->isSpatialType($type) || in_array($name, $this->geometryColumnNames(), true)) {
                continue;
            }
            $value = $row->{$name} ?? null;
            if (is_string($value) && $this->looksLikeJson($value)) {
                continue;
            }
            $props[$name] = $this->normalizeScalar($value);
        }

        return $props;
    }

    /**
     * @param  array<string, string>  $columnTypes
     * @return array<string, mixed>|null
     */
    public function geometryFromRow(object $row, array $columnTypes): ?array
    {
        if (isset($row->_geojson) && is_string($row->_geojson) && $row->_geojson !== '') {
            $decoded = json_decode($row->_geojson, true);
            if (is_array($decoded)) {
                return $this->normalizeGeometry($decoded);
            }
        }

        foreach ($this->geometryColumnNames() as $column) {
            if (! isset($row->{$column}) || $row->{$column} === null || $row->{$column} === '') {
                continue;
            }
            $parsed = $this->parseGeometryValue((string) $row->{$column});
            if ($parsed !== null) {
                return $parsed;
            }
        }

        foreach ($columnTypes as $name => $type) {
            if (! $this->isSpatialType($type)) {
                continue;
            }
            $raw = $row->{$name} ?? null;
            if (! is_string($raw) || $raw === '') {
                continue;
            }
            $parsed = $this->parseGeometryValue($raw);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        $lat = $this->firstNumeric($row, ['latitude', 'lat', 'y']);
        $lng = $this->firstNumeric($row, ['longitude', 'lng', 'lon', 'x']);
        if ($lat !== null && $lng !== null) {
            return [
                'type' => 'Point',
                'coordinates' => [$lng, $lat],
            ];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function geometryColumnNames(): array
    {
        return [
            'geojson',
            'geometry',
            'geom',
            'polygon',
            'coordinates',
            'wkt',
            'shape',
            'area_geojson',
            'boundary_geojson',
        ];
    }

    public function isSpatialType(string $type): bool
    {
        $type = strtolower($type);

        return str_contains($type, 'geometry')
            || str_contains($type, 'point')
            || str_contains($type, 'polygon')
            || str_contains($type, 'linestring')
            || str_contains($type, 'multipolygon');
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>|null
     */
    public function normalizeGeometry(array $decoded): ?array
    {
        $type = (string) ($decoded['type'] ?? '');
        if ($type === 'Feature' && isset($decoded['geometry']) && is_array($decoded['geometry'])) {
            return $this->normalizeGeometry($decoded['geometry']);
        }
        if ($type === 'FeatureCollection' && isset($decoded['features'][0]['geometry']) && is_array($decoded['features'][0]['geometry'])) {
            return $this->normalizeGeometry($decoded['features'][0]['geometry']);
        }
        if (in_array($type, ['Polygon', 'MultiPolygon', 'LineString', 'MultiLineString', 'Point', 'MultiPoint'], true)
            && isset($decoded['coordinates'])
        ) {
            return [
                'type' => $type,
                'coordinates' => $decoded['coordinates'],
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseGeometryValue(string $raw): ?array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if ($this->looksLikeJson($trimmed)) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return $this->normalizeGeometry($decoded);
            }
        }

        return null;
    }

    private function looksLikeJson(string $value): bool
    {
        $first = $value[0] ?? '';

        return $first === '{' || $first === '[';
    }

    /**
     * @param  list<string>  $keys
     */
    private function firstNumeric(object $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (! isset($row->{$key}) || $row->{$key} === null || $row->{$key} === '') {
                continue;
            }
            if (is_numeric($row->{$key})) {
                return (float) $row->{$key};
            }
        }

        return null;
    }

    private function normalizeScalar(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        return $value;
    }
}
