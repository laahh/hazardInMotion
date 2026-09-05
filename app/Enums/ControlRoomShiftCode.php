<?php

declare(strict_types=1);

namespace App\Enums;

enum ControlRoomShiftCode: string
{
    case S1 = 'S1';
    case S2 = 'S2';

    public function label(): string
    {
        return (string) (config("control-room.shifts.{$this->value}.name") ?? $this->value);
    }

    public function start(): string
    {
        return (string) config("control-room.shifts.{$this->value}.start");
    }

    public function end(): string
    {
        return (string) config("control-room.shifts.{$this->value}.end");
    }

    public function crossesMidnight(): bool
    {
        return (bool) config("control-room.shifts.{$this->value}.crosses_midnight", false);
    }
}
