<?php

declare(strict_types=1);

namespace App\Services\Isc;

/**
 * Ubah kolom geometry/JSON/lat-lng Besigma menjadi GeoJSON Feature.
 */
final class IscBoundaryGeometryMapper
{
    /**
     * @var list<string>
     */
    private const WRAPPER_KEYS = [
        'paths',
        'path',
        'polyline',
        'polylines',
        'shadow_polylines',
        'coordinates',
        'coordinate',
        'points',
        'latlngs',
        'latLngs',
        'latlng',
        'rings',
        'overlay',
        'shape',
        'geometry',
        'features',
    ];

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
                $parsed = $this->normalizeGeometry($decoded) ?? $this->polygonFromPoints($decoded);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        foreach ($this->geometryColumnNames() as $column) {
            if (! isset($row->{$column}) || $row->{$column} === null || $row->{$column} === '') {
                continue;
            }
            $parsed = $this->geometryFromMixed($row->{$column});
            if ($parsed !== null) {
                return $parsed;
            }
        }

        foreach ($columnTypes as $name => $type) {
            if (! $this->isSpatialType($type)) {
                continue;
            }
            $raw = $row->{$name} ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            $parsed = $this->geometryFromMixed($raw);
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
            'polylines',
            'shadow_polylines',
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
            'polyline_center_point',
            'shadow_polyline_center_point',
        ];
    }

    public function isSpatialType(string $type): bool
    {
        $type = strtolower($type);

        return str_contains($type, 'geometry')
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
            return $this->normalizeGeometry($decoded['geometry'])
                ?? $this->polygonFromPoints($decoded['geometry']);
        }
        if ($type === 'FeatureCollection' && isset($decoded['features']) && is_array($decoded['features'])) {
            $polygons = [];
            foreach ($decoded['features'] as $feature) {
                if (! is_array($feature)) {
                    continue;
                }
                $geometry = $this->normalizeGeometry($feature) ?? $this->polygonFromPoints($feature);
                foreach ($this->geometryToPolygons($geometry) as $polygon) {
                    $polygons[] = $polygon;
                }
            }

            return $this->polygonsToGeometry($polygons);
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
        return $this->geometryFromMixed($raw);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>|null
     */
    public function polygonFromPoints(array $decoded): ?array
    {
        $rings = $this->extractRings($decoded);
        $polygons = [];
        foreach ($rings as $ring) {
            $closed = $this->closeRing($ring);
            if ($closed !== null) {
                $polygons[] = [$closed];
            }
        }

        return $this->polygonsToGeometry($polygons);
    }

    private function geometryFromMixed(mixed $value): ?array
    {
        if ($value instanceof \stdClass) {
            $value = json_decode((string) json_encode($value), true);
        }

        if (is_array($value)) {
            return $this->normalizeGeometry($value) ?? $this->polygonFromPoints($value);
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        $trimmed = preg_replace('/^\xEF\xBB\xBF/', '', $trimmed) ?? $trimmed;
        if ($trimmed === '' || strcasecmp($trimmed, 'null') === 0) {
            return null;
        }

        if (($trimmed[0] ?? '') === '"') {
            $decodedString = json_decode($trimmed, true);
            if (is_string($decodedString) && $decodedString !== '' && $decodedString !== $trimmed) {
                return $this->geometryFromMixed($decodedString);
            }
        }

        if ($this->looksLikeJson($trimmed)) {
            $decoded = json_decode($trimmed, true);
            if (is_string($decoded) && $this->looksLikeJson(trim($decoded))) {
                $decoded = json_decode($decoded, true);
            }
            if (is_array($decoded)) {
                $parsed = $this->normalizeGeometry($decoded) ?? $this->polygonFromPoints($decoded);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        $encoded = $this->decodeGooglePolyline($trimmed);
        if ($encoded !== null) {
            return $encoded;
        }

        return $this->parseWkt($trimmed);
    }

    /**
     * @return list<list<array{0:float,1:float}>>
     */
    private function extractRings(array $decoded): array
    {
        $unwrapped = $this->unwrapWrappers($decoded);
        if ($unwrapped === []) {
            return [];
        }

        if ($this->isPointList($unwrapped)) {
            $ring = $this->ringFromPoints($unwrapped);

            return $ring === [] ? [] : [$ring];
        }

        $rings = [];
        foreach ($unwrapped as $item) {
            if (is_string($item)) {
                $parsed = $this->geometryFromMixed($item);
                foreach ($this->geometryToPolygons($parsed) as $polygon) {
                    $rings[] = $polygon[0];
                }
                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            if ($this->isPointList($item) || $this->pairFromPoint($item) !== null) {
                if ($this->pairFromPoint($item) !== null && ! $this->isPointList($item)) {
                    continue;
                }
                $ring = $this->ringFromPoints($item);
                if ($ring !== []) {
                    $rings[] = $ring;
                }
                continue;
            }
            foreach ($this->extractRings($item) as $ring) {
                $rings[] = $ring;
            }
        }

        if ($rings === [] && $this->pairFromPoint($unwrapped) !== null) {
            $pair = $this->pairFromPoint($unwrapped);

            return $pair === null ? [] : [[$pair]];
        }

        return $rings;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function unwrapWrappers(array $decoded): array
    {
        $current = $this->numericValues($decoded);
        for ($i = 0; $i < 4; $i++) {
            if ($this->isPointList($current) || $this->pairFromPoint($current) !== null) {
                return $current;
            }
            $next = null;
            foreach (self::WRAPPER_KEYS as $key) {
                if (! array_key_exists($key, $current)) {
                    continue;
                }
                $candidate = $current[$key];
                if (is_string($candidate)) {
                    $parsed = $this->geometryFromMixed($candidate);
                    if ($parsed !== null) {
                        foreach ($this->geometryToPolygons($parsed) as $polygon) {
                            return $polygon;
                        }
                    }
                }
                if (is_array($candidate)) {
                    $next = $this->numericValues($candidate);
                    break;
                }
            }
            if ($next === null) {
                return $current;
            }
            $current = $next;
        }

        return $current;
    }

    /**
     * @param  array<int|string, mixed>  $points
     * @return list<array{0:float,1:float}>
     */
    private function ringFromPoints(array $points): array
    {
        $ring = [];
        foreach ($this->numericValues($points) as $point) {
            $pair = $this->pairFromPoint($point);
            if ($pair === null) {
                continue;
            }
            $ring[] = $pair;
        }

        return $ring;
    }

    /**
     * @param  list<array{0:float,1:float}>  $ring
     * @return list<array{0:float,1:float}>|null
     */
    private function closeRing(array $ring): ?array
    {
        if (count($ring) === 1) {
            return $ring;
        }
        if (count($ring) < 3) {
            return null;
        }
        if ($ring[0] !== $ring[array_key_last($ring)]) {
            $ring[] = $ring[0];
        }

        return $ring;
    }

    /**
     * @param  array<int|string, mixed>  $points
     */
    private function isPointList(array $points): bool
    {
        $values = array_values($this->numericValues($points));
        if ($values === []) {
            return false;
        }
        $first = $values[0];

        return $this->pairFromPoint($first) !== null;
    }

    /**
     * @return array<int, mixed>
     */
    private function numericValues(array $decoded): array
    {
        if ($decoded === []) {
            return [];
        }
        if (array_is_list($decoded)) {
            return $decoded;
        }
        $keys = array_keys($decoded);
        $numeric = true;
        foreach ($keys as $key) {
            if (! is_int($key) && ! ctype_digit((string) $key)) {
                $numeric = false;
                break;
            }
        }

        return $numeric ? array_values($decoded) : $decoded;
    }

    /**
     * @return array{0:float,1:float}|null
     */
    private function pairFromPoint(mixed $point): ?array
    {
        if ($point instanceof \stdClass) {
            $point = (array) $point;
        }
        if (! is_array($point)) {
            return null;
        }

        $map = [];
        foreach ($point as $key => $value) {
            $map[strtolower((string) $key)] = $value;
        }

        $lat = $map['lat'] ?? $map['latitude'] ?? $map['y'] ?? null;
        $lng = $map['lng'] ?? $map['lon'] ?? $map['long'] ?? $map['longitude'] ?? $map['x'] ?? null;
        if (is_numeric($lat) && is_numeric($lng)) {
            return [(float) $lng, (float) $lat];
        }

        if (isset($point[0], $point[1]) && is_numeric($point[0]) && is_numeric($point[1])) {
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
     * @param  array<string, mixed>|null  $geometry
     * @return list<list<list<array{0:float,1:float}>>>
     */
    private function geometryToPolygons(?array $geometry): array
    {
        if ($geometry === null) {
            return [];
        }
        $type = (string) ($geometry['type'] ?? '');
        $coords = $geometry['coordinates'] ?? null;
        if (! is_array($coords)) {
            return [];
        }
        if ($type === 'Polygon') {
            return [$coords];
        }
        if ($type === 'MultiPolygon') {
            $out = [];
            foreach ($coords as $polygon) {
                if (is_array($polygon)) {
                    $out[] = $polygon;
                }
            }

            return $out;
        }
        if ($type === 'LineString') {
            $closed = $this->closeRing($coords);

            return $closed === null ? [] : [[$closed]];
        }
        if ($type === 'MultiLineString') {
            $out = [];
            foreach ($coords as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $closed = $this->closeRing($line);
                if ($closed !== null) {
                    $out[] = [$closed];
                }
            }

            return $out;
        }
        if ($type === 'Point' && isset($coords[0], $coords[1])) {
            return [[[$coords]]];
        }

        return [];
    }

    /**
     * @param  list<list<list<array{0:float,1:float}>>>  $polygons
     * @return array<string, mixed>|null
     */
    private function polygonsToGeometry(array $polygons): ?array
    {
        $usable = [];
        foreach ($polygons as $polygon) {
            if (! is_array($polygon) || $polygon === []) {
                continue;
            }
            $ring = $polygon[0] ?? null;
            if (is_array($ring) && count($ring) === 1) {
                return ['type' => 'Point', 'coordinates' => $ring[0]];
            }
            $usable[] = $polygon;
        }
        if ($usable === []) {
            return null;
        }
        if (count($usable) === 1) {
            return ['type' => 'Polygon', 'coordinates' => $usable[0]];
        }

        return ['type' => 'MultiPolygon', 'coordinates' => $usable];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeGooglePolyline(string $encoded): ?array
    {
        $encoded = trim($encoded);
        if ($encoded === '' || strlen($encoded) < 12 || $this->looksLikeJson($encoded) || str_contains($encoded, ' ')) {
            return null;
        }
        if (! preg_match('/^[A-Za-z0-9_\~\.\?\@\`\'\-\+\/\\\\]+$/', $encoded)) {
            return null;
        }

        $coordinates = [];
        $index = 0;
        $lat = 0;
        $lng = 0;
        $length = strlen($encoded);

        try {
            while ($index < $length) {
                $lat += $this->decodePolylineChunk($encoded, $index, $length);
                $lng += $this->decodePolylineChunk($encoded, $index, $length);
                $coordinates[] = [$lng / 1e5, $lat / 1e5];
            }
        } catch (\RuntimeException) {
            return null;
        }

        $closed = $this->closeRing($coordinates);
        if ($closed === null) {
            return count($coordinates) === 1
                ? ['type' => 'Point', 'coordinates' => $coordinates[0]]
                : null;
        }

        return ['type' => 'Polygon', 'coordinates' => [$closed]];
    }

    private function decodePolylineChunk(string $encoded, int &$index, int $length): int
    {
        $result = 0;
        $shift = 0;
        do {
            if ($index >= $length) {
                throw new \RuntimeException('truncated polyline');
            }
            $b = ord($encoded[$index]) - 63;
            $index++;
            $result |= ($b & 0x1F) << $shift;
            $shift += 5;
        } while ($b >= 0x20);

        return ($result & 1) ? ~($result >> 1) : ($result >> 1);
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
                $closed = $this->closeRing($ring);
                if ($closed !== null) {
                    $polygons[] = [$closed];
                }
            }

            return $this->polygonsToGeometry($polygons);
        }

        if (str_starts_with($upper, 'POLYGON')) {
            if (preg_match('/POLYGON\s*\(\s*\((.+)\)\s*\)/is', $value, $m) !== 1) {
                return null;
            }
            $ring = $this->wktPairs($m[1]);
            $closed = $this->closeRing($ring);

            return $closed === null ? null : ['type' => 'Polygon', 'coordinates' => [$closed]];
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
