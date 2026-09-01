<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Actions\Isc\IscPobClassifyAction;
use App\Services\Isc\IscHazardBoundaryClassifier;
use App\Services\Isc\IscPobSnapshotService;
use ReflectionMethod;
use Tests\TestCase;

final class IscPobSnapshotServiceTest extends TestCase
{
    public function test_violations_override_gps_and_fill_kind_counts(): void
    {
        $service = app(IscPobSnapshotService::class);
        $apply = new ReflectionMethod($service, 'applyViolations');
        $apply->setAccessible(true);
        $summarize = new ReflectionMethod($service, 'summarize');
        $summarize->setAccessible(true);

        $classified = [[
            'key' => 'sid:A',
            'user_id' => 'u1',
            'sid' => 'A',
            'name' => 'Ali',
            'presence' => IscPobClassifyAction::PRESENCE_IN,
            'safety' => IscPobClassifyAction::SAFETY_SAFE,
            'site_code' => 'BMO',
            'stale' => false,
            'lat' => 2.08,
            'lng' => 117.18,
        ]];
        $peopleViolations = [
            [
                'user_id' => 'u1',
                'sid' => 'A',
                'name' => 'Ali',
                'hazard_kind' => IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE,
                'boundary_id' => 'b1',
                'hazard_name' => 'Zona kompetensi',
                'site_code' => 'BMO',
            ],
            [
                'user_id' => 'u2',
                'sid' => 'B',
                'name' => 'Budi',
                'hazard_kind' => IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER,
                'boundary_id' => 'b2',
                'hazard_name' => 'Zona bahaya',
                'site_code' => 'LMO',
            ],
        ];
        $unitViolations = [[
            'unit_id' => 'un1',
            'sid' => 'DT-01',
            'name' => 'DT-01',
            'hazard_kind' => IscHazardBoundaryClassifier::KIND_UNIT_DANGER,
            'site_code' => 'LMO',
        ]];

        $merged = $apply->invoke($service, $classified, $peopleViolations, $unitViolations);

        $this->assertCount(3, $merged);
        $this->assertSame(IscPobClassifyAction::SAFETY_UNSAFE, $merged[0]['safety']);
        $this->assertSame(IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE, $merged[0]['hazard_kind']);
        $this->assertFalse($merged[0]['roster_only']);
        $this->assertTrue($merged[1]['roster_only']);
        $this->assertSame('unit:un1', $merged[2]['key']);
        $this->assertSame('unit', $merged[2]['entity']);

        $summary = $summarize->invoke($service, $merged, [], [
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER => 1,
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE => 1,
            IscHazardBoundaryClassifier::KIND_UNIT_DANGER => 1,
        ]);

        $this->assertSame(1, $summary['in']);
        $this->assertSame(1, $summary['unsafe']);
        $this->assertSame(0, $summary['safe']);
        $this->assertSame(1, $summary['unsafe_by_kind'][IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER]);
        $this->assertSame(1, $summary['unsafe_by_kind'][IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE]);
        $this->assertSame(1, $summary['unsafe_by_kind'][IscHazardBoundaryClassifier::KIND_UNIT_DANGER]);
    }

    public function test_hud_contract_keys_exist_on_demo_snapshot(): void
    {
        $data = app(IscPobSnapshotService::class)->snapshot(true, true);

        $this->assertSame('demo', $data['source']);
        $this->assertArrayHasKey('unsafe_by_kind', $data['summary']);
        $this->assertArrayHasKey(IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER, $data['summary']['unsafe_by_kind']);
        $this->assertArrayHasKey(IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE, $data['summary']['unsafe_by_kind']);
        $this->assertArrayHasKey(IscHazardBoundaryClassifier::KIND_UNIT_DANGER, $data['summary']['unsafe_by_kind']);
        $this->assertNotEmpty($data['people']);
        $this->assertArrayHasKey('site_code', $data['people'][0]);
        $this->assertArrayHasKey('hazard_features', $data);
    }
}
