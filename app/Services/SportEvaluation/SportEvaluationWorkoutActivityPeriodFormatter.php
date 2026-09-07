<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Carbon;

/**
 * Label periode laporan harian/mingguan (Senin–Minggu). Tanpa akses DB.
 */
final class SportEvaluationWorkoutActivityPeriodFormatter
{
    public const MODE_DAY = 'day';

    public const MODE_WEEK = 'week';

    public static function normalizeMode(string $mode): string
    {
        return $mode === self::MODE_WEEK ? self::MODE_WEEK : self::MODE_DAY;
    }

    public static function weekStartMonday(Carbon $date): Carbon
    {
        return $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /**
     * Ekspresi SQL MySQL untuk awal periode (hari atau Senin minggu itu).
     */
    public static function periodStartSql(string $mode): string
    {
        if (self::normalizeMode($mode) === self::MODE_WEEK) {
            return 'DATE(DATE_SUB(w.created_at, INTERVAL WEEKDAY(w.created_at) DAY))';
        }

        return 'DATE(w.created_at)';
    }

    public static function label(string $mode, string $periodStart): string
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        if (self::normalizeMode($mode) === self::MODE_WEEK) {
            $end = $start->copy()->addDays(6);

            return $start->format('d M Y').' – '.$end->format('d M Y');
        }

        return $start->format('d M Y');
    }
}
