<?php

declare(strict_types=1);

namespace App\Enums;

enum MonitoringSafetyEngineeringPhaseStatus: string
{
    case NotYet = 'not_yet';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::NotYet => 'Not Yet',
            self::InProgress => 'In Progress',
            self::Done => 'Done',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
