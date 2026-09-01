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
            'coordinate',
            'latlngs',
            'latlng',
            'points',
            'path',
            'wkt',
            'shape',
            'area',
            'area_geojson',
            'boundary_geojson',
            'poly',
            'data',
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
                return $this->normalizeGeometry($decoded) ?? $this->polygonFromPoints($decoded);
            }
        }

        return $this->parseWkt($trimmed);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>|null
     */
    public function polygonFromPoints(array $decoded): ?array
    {
        $points = $decoded;
        if (isset($decoded['coordinates']) && is_array($decoded['coordinates'])) {
            $points = $decoded['coordinates'];
        } elseif (isset($decoded['points']) && is_array($decoded['points'])) {
            $points = $decoded['points'];
        } elseif (isset($decoded['latlngs']) && is_array($decoded['latlngs'])) {
            $points = $decoded['latlngs'];
        }

        if (isset($points[0]) && is_array($points[0]) && isset($points[0][0]) && is_array($points[0][0])) {
            $points = $points[0];
        }

        if ($points === [] || ! array_is_list($points)) {
            return null;
        }

        $ring = [];
        foreach ($points as $point) {
            $pair = $this->pairFromPoint($point);
            if ($pair === null) {
                return null;
            }
            $ring[] = $pair;
        }

        if (count($ring) < 3) {
            if (count($ring) === 1) {
                return ['type' => 'Point', 'coordinates' => $ring[0]];
            }

            return null;
        }

        if ($ring[0] !== $ring[array_key_last($ring)]) {
            $ring[] = $ring[0];
        }

        return [
            'type' => 'Polygon',
            'coordinates' => [$ring],
        ];
    }

    /**
     * @return array{0:float,1:float}|null
     */
    private function pairFromPoint(mixed $point): ?array
    {
        if (is_array($point) && isset($point['lng'], $point['lat'])) {
            return [(float) $point['lng'], (float) $point['lat']];
        }
        if (is_array($point) && isset($point['longitude'], $point['latitude'])) {
            return [(float) $point['longitude'], (float) $point['latitude']];
        }
        if (is_array($point) && isset($point[0], $point[1]) && is_numeric($point[0]) && is_numeric($point[1])) {
            $a = (float) $point[0];
            $b = (float) $point[1];
            if (abs($a) <= 90 && abs($b) > 90) {
                return [$b, $a];
            }

            return [$a, $b];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseWkt(string $raw): ?array
    {
        $value = preg_replace('/^SRID=\d+;/i', '', trim($raw)) ?? $raw;
        $upper = strtoupper(ltrim($value));

        if (str_starts_with($upper, 'POINT')) {
            if (preg_match('/POINT\s*\(\s*([-\d\.]+)\s+([-\d\.]+)\s*\)/i', $value, $m) !== 1) {
                return null;
            }

            return ['type' => 'Point', 'coordinates' => [(float) $m[1], (float) $m[2]]];
        }

        if (str_starts_with($upper, 'LINESTRING')) {
            if (preg_match('/LINESTRING\s*\((.+)\)/is', $value, $m) !== 1) {
                return null;
            }
            $line = $this->wktPairs($m[1]);

            return $line === [] ? null : ['type' => 'LineString', 'coordinates' => $line];
        }

        if (str_starts_with($upper, 'MULTIPOLYGON')) {
            if (preg_match('/MULTIPOLYGON\s*\(\s*(.+)\)\s*$/is', $value, $m) !== 1) {
                return null;
            }
            $polygons = [];
            foreach (preg_split('/\)\s*,\s*\(/', $m[1]) ?: [] as $chunk) {
                $ring = $this->wktPairs(trim($chunk, "() \t\n\r"));
                if (count($ring) >= 3) {
                    if ($ring[0] !== $ring[array_key_last($ring)]) {
                        $ring[] = $ring[0];
                    }
                    $polygons[] = [$ring];
                }
            }

            return $polygons === [] ? null : ['type' => 'MultiPolygon', 'coordinates' => $polygons];
        }

        if (str_starts_with($upper, 'POLYGON')) {
            if (preg_match('/POLYGON\s*\(\s*\((.+)\)\s*\)/is', $value, $m) !== 1) {
                return null;
            }
            $ring = $this->wktPairs($m[1]);
            if (count($ring) < 3) {
                return null;
            }
            if ($ring[0] !== $ring[array_key_last($ring)]) {
                $ring[] = $ring[0];
            }

            return ['type' => 'Polygon', 'coordinates' => [$ring]];
        }

        return null;
    }

    /**
     * @return list<array{0:float,1:float}>
     */
    private function wktPairs(string $body): array
    {
        $pairs = [];
        foreach (preg_split('/\s*,\s*/', $body) ?: [] as $token) {
            if (preg_match('/([-\d\.]+)\s+([-\d\.]+)/', $token, $m) !== 1) {
                continue;
            }
            $pairs[] = [(float) $m[1], (float) $m[2]];
        }

        return $pairs;
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
