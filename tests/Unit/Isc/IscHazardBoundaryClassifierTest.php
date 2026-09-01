<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Services\Isc\IscHazardBoundaryClassifier;
use Tests\TestCase;

final class IscHazardBoundaryClassifierTest extends TestCase
{
    public function test_explicit_kind_wins_over_danger_competency_type(): void
    {
        $classifier = new IscHazardBoundaryClassifier();

        $this->assertSame(
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER,
            $classifier->kind([
                'type' => 'DANGER_COMPETENCY',
                'hazard_kind' => IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER,
                'name' => 'Zona blasting',
            ])
        );
        $this->assertSame(
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE,
            $classifier->kind([
                'type' => 'DANGER_COMPETENCY',
                'hazard_kind' => IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE,
            ])
        );
    }

    public function test_danger_competency_type_is_not_inferred_as_competence(): void
    {
        $classifier = new IscHazardBoundaryClassifier();

        $this->assertNull($classifier->kind([
            'type' => 'DANGER_COMPETENCY',
            'name' => 'Zona pit A',
        ]));
    }

    public function test_inverse_type_is_not_hazardous(): void
    {
        $classifier = new IscHazardBoundaryClassifier();

        $this->assertNull($classifier->kind([
            'type' => 'INVERSE',
            'name' => 'Safe zone',
            'risk_color' => '#dc2626',
        ]));
        $this->assertFalse($classifier->isHazardous(['type' => 'INVERSE']));
    }
}
