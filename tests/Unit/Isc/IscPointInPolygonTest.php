<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Services\Isc\IscPointInPolygon;
use Tests\TestCase;

final class IscPointInPolygonTest extends TestCase
{
    public function test_point_inside_square_is_true(): void
    {
        $this->assertTrue((new IscPointInPolygon())->contains(117.15, 2.05, $this->square()));
    }

    public function test_point_outside_square_is_false(): void
    {
        $this->assertFalse((new IscPointInPolygon())->contains(118.0, 3.0, $this->square()));
    }

    public function test_hole_is_excluded(): void
    {
        $pip = new IscPointInPolygon();
        $geometry = [
            'type' => 'Polygon',
            'coordinates' => [
                [[117.0, 2.0], [117.3, 2.0], [117.3, 2.3], [117.0, 2.3], [117.0, 2.0]],
                [[117.1, 2.1], [117.2, 2.1], [117.2, 2.2], [117.1, 2.2], [117.1, 2.1]],
            ],
        ];
        $this->assertFalse($pip->contains(117.15, 2.15, $geometry));
        $this->assertTrue($pip->contains(117.05, 2.05, $geometry));
    }

    /**
     * @return array{type:string,coordinates:list<list<array{0:float,1:float}>>}
     */
    private function square(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[[117.1, 2.0], [117.2, 2.0], [117.2, 2.1], [117.1, 2.1], [117.1, 2.0]]],
        ];
    }
}
