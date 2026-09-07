<?php

declare(strict_types=1);

namespace Tests\Unit\SportEvaluation;

use App\Services\SportEvaluation\WorkoutMetricParser;
use Tests\TestCase;

final class WorkoutMetricParserTest extends TestCase
{
    private WorkoutMetricParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new WorkoutMetricParser;
    }

    public function test_distance_to_meters_reads_km_and_m(): void
    {
        $this->assertSame(5200.0, $this->parser->distanceToMeters('5,2 km'));
        $this->assertSame(800.0, $this->parser->distanceToMeters('800 m'));
        $this->assertSame(5000.0, $this->parser->distanceToMeters('5'));
        $this->assertNull($this->parser->distanceToMeters(''));
        $this->assertNull($this->parser->distanceToMeters(null));
    }

    public function test_duration_to_seconds_reads_clock_and_words(): void
    {
        $this->assertSame(3900, $this->parser->durationToSeconds('1:05:00'));
        $this->assertSame(2700, $this->parser->durationToSeconds('45 min'));
        $this->assertSame(3900, $this->parser->durationToSeconds('1 jam 5 menit'));
        $this->assertSame(5400, $this->parser->durationToSeconds('90'));
        $this->assertNull($this->parser->durationToSeconds('abc'));
    }

    public function test_calories_from_string_and_resolve_priority(): void
    {
        $this->assertSame(245.0, $this->parser->caloriesFromString('245 kcal'));
        $this->assertSame(180.5, $this->parser->caloriesFromString('180,5 kkal'));
        $this->assertNull($this->parser->caloriesFromString(''));

        $this->assertSame(120.0, $this->parser->resolveCalories(120, '90 kcal', '200 kcal'));
        $this->assertSame(90.0, $this->parser->resolveCalories(0, '90 kcal', '200 kcal'));
        $this->assertSame(200.0, $this->parser->resolveCalories(null, null, '200 kkal'));
    }

    public function test_is_run_or_walk_classifier(): void
    {
        $this->assertTrue($this->parser->isRunOrWalk('Lari pagi'));
        $this->assertTrue($this->parser->isRunOrWalk('Running'));
        $this->assertTrue($this->parser->isRunOrWalk('Jalan kaki 5k'));
        $this->assertTrue($this->parser->isRunOrWalk('Hiking'));
        $this->assertTrue($this->parser->isRunOrWalk('walk'));
        $this->assertFalse($this->parser->isRunOrWalk('Yoga'));
        $this->assertFalse($this->parser->isRunOrWalk('Bersepeda'));
        $this->assertFalse($this->parser->isRunOrWalk(''));
        $this->assertFalse($this->parser->isRunOrWalk(null));
    }

    public function test_run_walk_distance_km_skips_non_run_walk(): void
    {
        $this->assertSame(5.2, $this->parser->runWalkDistanceKm('Lari', '5,2 km'));
        $this->assertNull($this->parser->runWalkDistanceKm('Yoga', '5,2 km'));
        $this->assertNull($this->parser->runWalkDistanceKm('Lari', ''));
    }
}
