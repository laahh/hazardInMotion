<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Actions\Isc\IscPobClassifyAction;
use App\Services\Isc\IscHazardBoundaryClassifier;
use App\Services\Isc\IscPointInPolygon;
use Tests\TestCase;

final class IscPobClassifyActionTest extends TestCase
{
    public function test_inside_iupk_without_hazard_is_safe(): void
    {
        $person = $this->action()->classifyOne(
            $this->person(117.18, 2.08),
            $this->iupk(),
            [$this->hazardFeature()],
        );

        $this->assertSame(IscPobClassifyAction::PRESENCE_IN, $person['presence']);
        $this->assertSame(IscPobClassifyAction::SAFETY_SAFE, $person['safety']);
        $this->assertSame('BMO', $person['iupk_site']);
        $this->assertFalse($person['stale']);
    }

    public function test_inside_hazard_is_unsafe(): void
    {
        $person = $this->action()->classifyOne(
            $this->person(117.125, 2.025),
            $this->iupk(),
            [$this->hazardFeature()],
        );

        $this->assertSame(IscPobClassifyAction::PRESENCE_IN, $person['presence']);
        $this->assertSame(IscPobClassifyAction::SAFETY_UNSAFE, $person['safety']);
        $this->assertSame('Pit berbahaya', $person['hazard_name']);
    }

    public function test_outside_iupk_is_out(): void
    {
        $person = $this->action()->classifyOne(
            $this->person(118.0, 3.0),
            $this->iupk(),
            [$this->hazardFeature()],
        );

        $this->assertSame(IscPobClassifyAction::PRESENCE_OUT, $person['presence']);
        $this->assertNull($person['safety']);
    }

    public function test_stale_gps_is_unknown_not_in(): void
    {
        $person = $this->action()->classifyOne(
            $this->person(117.18, 2.08, now()->subMinutes(20)->toDateTimeString()),
            $this->iupk(),
            [$this->hazardFeature()],
        );

        $this->assertSame(IscPobClassifyAction::PRESENCE_UNKNOWN, $person['presence']);
        $this->assertTrue($person['stale']);
        $this->assertNull($person['safety']);
    }

    private function action(): IscPobClassifyAction
    {
        return new IscPobClassifyAction(new IscPointInPolygon(), new IscHazardBoundaryClassifier());
    }

    /**
     * @return array<string, mixed>
     */
    private function person(float $lng, float $lat, ?string $updatedAt = null): array
    {
        return [
            'key' => 'sid:TEST',
            'sid' => 'TEST',
            'name' => 'Tes',
            'lat' => $lat,
            'lng' => $lng,
            'gps_updated_at' => $updatedAt ?? now()->toDateTimeString(),
        ];
    }

    /**
     * @return array{type:string,features:list<array<string,mixed>>}
     */
    private function iupk(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['Site' => 'BMO', 'Layer' => 'IUPK'],
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[[117.1, 2.0], [117.2, 2.0], [117.2, 2.1], [117.1, 2.1], [117.1, 2.0]]],
                ],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hazardFeature(): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['id' => 9, 'name' => 'Pit berbahaya', 'risk_name' => 'tinggi'],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[[117.1, 2.0], [117.15, 2.0], [117.15, 2.05], [117.1, 2.05], [117.1, 2.0]]],
            ],
        ];
    }
}
