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
