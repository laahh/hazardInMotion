<?php

declare(strict_types=1);

namespace Tests\Unit\SportEvaluation;

use App\Services\SportEvaluation\SportEvaluationWorkoutActivityPeriodFormatter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class SportEvaluationWorkoutActivityPeriodFormatterTest extends TestCase
{
    public function test_normalize_mode_defaults_to_day(): void
    {
        $this->assertSame('day', SportEvaluationWorkoutActivityPeriodFormatter::normalizeMode(''));
        $this->assertSame('day', SportEvaluationWorkoutActivityPeriodFormatter::normalizeMode('day'));
        $this->assertSame('week', SportEvaluationWorkoutActivityPeriodFormatter::normalizeMode('week'));
    }

    public function test_week_start_is_monday(): void
    {
        $wednesday = Carbon::parse('2026-09-09');
        $monday = SportEvaluationWorkoutActivityPeriodFormatter::weekStartMonday($wednesday);

        $this->assertSame('2026-09-07', $monday->format('Y-m-d'));
        $this->assertTrue($monday->isMonday());
    }

    public function test_period_labels(): void
    {
        $this->assertSame(
            '09 Sep 2026',
            SportEvaluationWorkoutActivityPeriodFormatter::label('day', '2026-09-09'),
        );
        $this->assertSame(
            '07 Sep 2026 – 13 Sep 2026',
            SportEvaluationWorkoutActivityPeriodFormatter::label('week', '2026-09-07'),
        );
    }

    public function test_sql_period_expression_is_date_or_weekday(): void
    {
        $this->assertSame(
            'DATE(w.created_at)',
            SportEvaluationWorkoutActivityPeriodFormatter::periodStartSql('day'),
        );
        $this->assertStringContainsString(
            'WEEKDAY',
            SportEvaluationWorkoutActivityPeriodFormatter::periodStartSql('week'),
        );
    }
}
