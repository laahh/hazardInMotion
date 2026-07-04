<?php

declare(strict_types=1);

namespace App\Enums;

enum MonitoringSafetyEngineeringStatusCompliance: string
{
    case OnTarget = 'on_target';
    case Overdue = 'overdue';
    case NoDueDate = 'no_due_date';

    public function label(): string
    {
        return match ($this) {
            self::OnTarget => 'On Target',
            self::Overdue => 'Overdue',
            self::NoDueDate => 'Tanpa Due Date',
        };
    }
}
