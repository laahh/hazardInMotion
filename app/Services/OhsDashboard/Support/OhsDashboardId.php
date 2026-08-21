<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard\Support;

use Illuminate\Support\Str;

final class OhsDashboardId
{
    public static function leave(): string
    {
        return self::make('LR-', 8);
    }

    public static function event(): string
    {
        return self::make('EV-', 8);
    }

    public static function project(): string
    {
        return self::make('PRJ-', 8);
    }

    public static function issue(): string
    {
        return self::make('ISS-', 8);
    }

    public static function subTask(): string
    {
        return self::make('TSK-', 10);
    }

    public static function updateLog(): string
    {
        return self::make('UPD-', 10);
    }

    public static function subTaskUpdateLog(): string
    {
        return self::make('TUP-', 10);
    }

    public static function attendance(): string
    {
        return self::make('ATT-', 8);
    }

    public static function actionItem(): string
    {
        return self::make('AI-', 8);
    }

    public static function make(string $prefix, int $hexLength): string
    {
        $hex = strtoupper(str_replace('-', '', Str::uuid()->toString()));

        return $prefix.substr($hex, 0, $hexLength);
    }
}
