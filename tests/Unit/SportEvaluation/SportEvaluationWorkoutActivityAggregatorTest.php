<?php

declare(strict_types=1);

namespace Tests\Unit\SportEvaluation;

use App\Services\SportEvaluation\SportEvaluationWorkoutActivityAggregator;
use App\Services\SportEvaluation\WorkoutMetricParser;
use Tests\TestCase;

final class SportEvaluationWorkoutActivityAggregatorTest extends TestCase
{
    public function test_aggregate_frequency_duration_run_walk_distance_and_calories(): void
    {
        $aggregator = new SportEvaluationWorkoutActivityAggregator(new WorkoutMetricParser);
        $from = '2026-09-01';
        $to = '2026-09-14';

        $parsed = [
            $aggregator->parseWorkoutRow([
                'id' => 1,
                'user_id' => 10,
                'nama' => 'Andi',
                'kode_sid' => 'A1',
                'nama_perusahaan' => 'PT Satu',
                'divisi' => 'Ops',
                'activity_type' => 'Lari pagi',
                'calories_kcal' => 300,
                'workout_time' => '40 min',
                'distance' => '5 km',
                'created_at' => '2026-09-02 07:00:00',
            ], 'GMO'),
            $aggregator->parseWorkoutRow([
                'id' => 2,
                'user_id' => 10,
                'nama' => 'Andi',
                'kode_sid' => 'A1',
                'nama_perusahaan' => 'PT Satu',
                'divisi' => 'Ops',
                'activity_type' => 'Yoga',
                'calories_kcal' => 80,
                'workout_time' => '30 min',
                'distance' => '2 km',
                'created_at' => '2026-09-08 07:00:00',
            ], 'GMO'),
            $aggregator->parseWorkoutRow([
                'id' => 3,
                'user_id' => 20,
                'nama' => 'Budi',
                'kode_sid' => 'B2',
                'nama_perusahaan' => 'PT Dua',
                'divisi' => 'HR',
                'activity_type' => 'Jalan kaki',
                'calories_kcal' => null,
                'active_kilocalories' => '120 kcal',
                'workout_time' => '1:00:00',
                'distance' => '4 km',
                'created_at' => '2026-09-03 18:00:00',
            ], 'SMO'),
        ];

        $result = $aggregator->aggregate(
            $parsed,
            [10 => 1800.0, 20 => 900.0],
            ['2026-09-02' => 1800.0, '2026-09-03' => 900.0],
            14,
            $from,
            $to,
        );

        $this->assertSame(3, $result['kpi']['total_sessions']);
        $this->assertSame(2, $result['kpi']['active_users']);
        $this->assertSame(130, $result['kpi']['total_minutes']);
        $this->assertSame(9.0, $result['kpi']['total_km']);
        $this->assertSame(500, $result['kpi']['kcal_out']);
        $this->assertSame(2700, $result['kpi']['kcal_in']);
        $this->assertSame(1.5, $result['kpi']['avg_sessions_per_week']);
        $this->assertSame(1.5, $result['kpi']['avg_sessions_per_user']);

        $andi = collect($result['users'])->firstWhere('id', 10);
        $this->assertNotNull($andi);
        $this->assertSame(2, $andi['sesi']);
        $this->assertSame(70.0, $andi['duration_minutes']);
        $this->assertSame(5.0, $andi['distance_km']);
        $this->assertSame(380.0, $andi['kcal_out']);
        $this->assertSame(1800.0, $andi['kcal_in']);
        $this->assertSame(1420.0, $andi['kcal_net']);
        $this->assertSame(1.0, $andi['sessions_per_week']);

        $budi = collect($result['users'])->firstWhere('id', 20);
        $this->assertNotNull($budi);
        $this->assertSame(4.0, $budi['distance_km']);
        $this->assertSame(120.0, $budi['kcal_out']);

        $this->assertContains('Lari pagi', $result['distribution']['labels']);
        $this->assertContains('Yoga', $result['distribution']['labels']);
        $this->assertCount(14, $result['trendDaily']['labels']);
        $this->assertSame(1, $result['trendDaily']['sesi'][1]);
        $this->assertSame(1800.0, $result['trendDaily']['kcal_in'][1]);
        $this->assertNull($parsed[1]['distance_km']);
        $this->assertFalse($parsed[1]['is_run_or_walk']);
        $this->assertTrue($parsed[0]['is_run_or_walk']);
    }

    public function test_yoga_distance_is_not_counted_even_if_raw_distance_exists(): void
    {
        $aggregator = new SportEvaluationWorkoutActivityAggregator(new WorkoutMetricParser);
        $row = $aggregator->parseWorkoutRow([
            'id' => 9,
            'user_id' => 1,
            'nama' => 'Cici',
            'activity_type' => 'Yoga',
            'calories_kcal' => 50,
            'workout_time' => '20 min',
            'distance' => '3 km',
            'created_at' => '2026-09-01 06:00:00',
        ], 'GMO');

        $result = $aggregator->aggregate([$row], [1 => 400.0], ['2026-09-01' => 400.0], 7, '2026-09-01', '2026-09-07');

        $this->assertNull($row['distance_km']);
        $this->assertSame(0.0, $result['kpi']['total_km']);
        $this->assertSame(50, $result['kpi']['kcal_out']);
        $this->assertSame(400, $result['kpi']['kcal_in']);
    }
}
