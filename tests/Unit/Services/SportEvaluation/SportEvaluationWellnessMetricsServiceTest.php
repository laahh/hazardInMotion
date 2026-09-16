<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SportEvaluation;

use App\Services\SportEvaluation\BewellConnectionService;
use App\Services\SportEvaluation\SportEvaluationEmployeeExclusionRules;
use App\Services\SportEvaluation\SportEvaluationKaryawanWellSiteResolver;
use App\Services\SportEvaluation\SportEvaluationMitraAssignmentService;
use App\Services\SportEvaluation\SportEvaluationWellnessMetricsService;
use App\Services\SportEvaluation\WorkoutMetricParser;
use Mockery;
use PHPUnit\Framework\TestCase;

final class SportEvaluationWellnessMetricsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_intensity_label_buckets(): void
    {
        $service = $this->makeService();

        $this->assertSame('-', $service->intensityLabel(null));
        $this->assertSame('-', $service->intensityLabel(0.0));
        $this->assertSame('Low', $service->intensityLabel(119.0));
        $this->assertSame('Med', $service->intensityLabel(120.0));
        $this->assertSame('Med', $service->intensityLabel(149.0));
        $this->assertSame('High', $service->intensityLabel(150.0));
    }

    public function test_resolve_week_is_sunday_to_saturday(): void
    {
        $service = $this->makeService();
        $week = $service->resolveWeek('2026-09-16'); // Selasa

        $this->assertSame('2026-09-13', $week['start']); // Minggu
        $this->assertSame('2026-09-19', $week['end']); // Sabtu
        $this->assertSame('2026-09-06', $week['prev_start']);
    }

    private function makeService(): SportEvaluationWellnessMetricsService
    {
        return new SportEvaluationWellnessMetricsService(
            Mockery::mock(BewellConnectionService::class),
            new WorkoutMetricParser(),
            Mockery::mock(SportEvaluationEmployeeExclusionRules::class),
            Mockery::mock(SportEvaluationKaryawanWellSiteResolver::class),
            Mockery::mock(SportEvaluationMitraAssignmentService::class),
        );
    }
}
