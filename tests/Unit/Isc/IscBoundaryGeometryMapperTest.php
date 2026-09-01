<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Services\Isc\IscBoundaryGeometryMapper;
use Tests\TestCase;

final class IscBoundaryGeometryMapperTest extends TestCase
{
    public function test_polygon_json_becomes_feature(): void
    {
        $row = (object) [
            'id' => 7,
            'name' => 'Pit A',
            'geojson' => json_encode([
                'type' => 'Polygon',
                'coordinates' => [[[117.1, 2.0], [117.2, 2.0], [117.2, 2.1], [117.1, 2.0]]],
            ]),
        ];

        $feature = (new IscBoundaryGeometryMapper())->featureFromRow($row, [
            'id' => 'bigint',
            'name' => 'varchar(255)',
            'geojson' => 'json',
        ]);

        $this->assertIsArray($feature);
        $this->assertSame('Feature', $feature['type']);
        $this->assertSame('Polygon', $feature['geometry']['type']);
        $this->assertSame(7, $feature['properties']['id']);
        $this->assertSame('Pit A', $feature['properties']['name']);
        $this->assertArrayNotHasKey('geojson', $feature['properties']);
    }

    public function test_lat_lng_becomes_point(): void
    {
        $row = (object) [
            'id' => 3,
            'latitude' => '1.95',
            'longitude' => '117.4',
        ];

        $feature = (new IscBoundaryGeometryMapper())->featureFromRow($row, [
            'id' => 'int',
            'latitude' => 'decimal',
            'longitude' => 'decimal',
        ]);

        $this->assertSame('Point', $feature['geometry']['type']);
        $this->assertEqualsWithDelta(117.4, $feature['geometry']['coordinates'][0], 0.0001);
        $this->assertEqualsWithDelta(1.95, $feature['geometry']['coordinates'][1], 0.0001);
    }

    public function test_wkt_polygon_becomes_feature(): void
    {
        $row = (object) [
            'id' => 4,
            'name' => 'Pit B',
            'wkt' => 'POLYGON((117.1 2.0, 117.2 2.0, 117.2 2.1, 117.1 2.0))',
        ];

        $feature = (new IscBoundaryGeometryMapper())->featureFromRow($row, [
            'id' => 'int',
            'name' => 'varchar',
            'wkt' => 'text',
        ]);

        $this->assertSame('Polygon', $feature['geometry']['type']);
        $this->assertCount(4, $feature['geometry']['coordinates'][0]);
    }

    public function test_latlng_array_becomes_polygon(): void
    {
        $row = (object) [
            'id' => 5,
            'coordinates' => json_encode([
                ['lat' => 2.0, 'lng' => 117.1],
                ['lat' => 2.0, 'lng' => 117.2],
                ['lat' => 2.1, 'lng' => 117.2],
            ]),
        ];

        $feature = (new IscBoundaryGeometryMapper())->featureFromRow($row, [
            'id' => 'int',
            'coordinates' => 'json',
        ]);

        $this->assertSame('Polygon', $feature['geometry']['type']);
    }

    public function test_besigma_polylines_json_becomes_polygon(): void
    {
        $row = (object) [
            'id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'name' => 'Zona BMO',
            'polylines' => json_encode([
                ['lat' => 2.0, 'lng' => 117.1],
                ['lat' => 2.0, 'lng' => 117.2],
                ['lat' => 2.1, 'lng' => 117.2],
                ['lat' => 2.1, 'lng' => 117.1],
            ]),
        ];

        $feature = (new IscBoundaryGeometryMapper())->featureFromRow($row, [
            'id' => 'char(36)',
            'name' => 'varchar(255)',
            'polylines' => 'longtext',
        ]);

        $this->assertIsArray($feature);
        $this->assertSame('Polygon', $feature['geometry']['type']);
        $this->assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $feature['properties']['id']);
        $this->assertArrayNotHasKey('polylines', $feature['properties']);
        $ring = $feature['geometry']['coordinates'][0];
        $this->assertGreaterThanOrEqual(5, count($ring));
        $this->assertEqualsWithDelta(117.1, $ring[0][0], 0.0001);
        $this->assertEqualsWithDelta(2.0, $ring[0][1], 0.0001);
        $this->assertSame($ring[0], $ring[array_key_last($ring)]);
    }

    public function test_besigma_paths_wrapper_and_lon_keys(): void
    {
        $row = (object) [
            'id' => 'path-1',
            'polylines' => json_encode([
                'paths' => [[
                    ['Lat' => 2.0, 'Lon' => 117.1],
                    ['Lat' => 2.0, 'Lon' => 117.2],
                    ['Lat' => 2.1, 'lng' => 117.2],
                    ['lat' => 2.1, 'lon' => 117.1],
                ]],
            ]),
        ];

        $feature = (new IscBoundaryGeometryMapper())->featureFromRow($row, [
            'id' => 'char(36)',
            'polylines' => 'longtext',
        ]);

        $this->assertSame('Polygon', $feature['geometry']['type']);
        $this->assertGreaterThanOrEqual(5, count($feature['geometry']['coordinates'][0]));
    }

    public function test_nested_rings_and_invalid_vertex_are_skipped(): void
    {
        $row = (object) [
            'id' => 'nested-1',
            'polylines' => json_encode([
                [
                    ['lat' => 2.0, 'lng' => 117.1],
                    ['bad' => true],
                    ['lat' => 2.0, 'lng' => 117.2],
                    ['lat' => 2.1, 'lng' => 117.2],
                ],
            ]),
        ];

        $feature = (new IscBoundaryGeometryMapper())->featureFromRow($row, [
            'id' => 'char(36)',
            'polylines' => 'longtext',
        ]);

        $this->assertSame('Polygon', $feature['geometry']['type']);
    }

    public function test_double_encoded_json_becomes_polygon(): void
    {
        $inner = json_encode([
            ['lat' => 2.02, 'lng' => 117.11],
            ['lat' => 2.02, 'lng' => 117.22],
            ['lat' => 2.12, 'lng' => 117.22],
        ]);
        $row = (object) [
            'id' => 'dbl-1',
            'polylines' => json_encode($inner),
        ];

        $feature = (new IscBoundaryGeometryMapper())->featureFromRow($row, [
            'id' => 'char(36)',
            'polylines' => 'longtext',
        ]);

        $this->assertSame('Polygon', $feature['geometry']['type']);
    }

    public function test_center_point_used_when_polylines_unusable(): void
    {
        $row = (object) [
            'id' => 'center-1',
            'polylines' => '[]',
            'polyline_center_point' => json_encode(['lat' => 2.05, 'lng' => 117.4]),
        ];

        $feature = (new IscBoundaryGeometryMapper())->featureFromRow($row, [
            'id' => 'char(36)',
            'polylines' => 'longtext',
            'polyline_center_point' => 'text',
        ]);

        $this->assertSame('Point', $feature['geometry']['type']);
        $this->assertEqualsWithDelta(117.4, $feature['geometry']['coordinates'][0], 0.0001);
    }

    public function test_shadow_polylines_used_when_polylines_empty(): void
    {
        $row = (object) [
            'id' => 'shadow-1',
            'polylines' => null,
            'shadow_polylines' => json_encode([
                ['lat' => 1.9, 'lng' => 117.0],
                ['lat' => 1.9, 'lng' => 117.05],
                ['lat' => 1.95, 'lng' => 117.05],
            ]),
        ];

        $feature = (new IscBoundaryGeometryMapper())->featureFromRow($row, [
            'id' => 'char(36)',
            'polylines' => 'longtext',
            'shadow_polylines' => 'longtext',
        ]);

        $this->assertSame('Polygon', $feature['geometry']['type']);
    }

    public function test_row_without_geometry_returns_null(): void
    {
        $row = (object) ['id' => 1, 'name' => 'No geom'];

        $feature = (new IscBoundaryGeometryMapper())->featureFromRow($row, [
            'id' => 'int',
            'name' => 'varchar',
        ]);

        $this->assertNull($feature);
    }
}
