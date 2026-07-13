<?php

declare(strict_types=1);

namespace App\Enums;

enum AutoBannedManualBanScope: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
        };
    }

    public function isWeekly(): bool
    {
        return $this === self::Weekly;
    }

    public function defaultBannedStatus(): string
    {
        return match ($this) {
            self::Daily => 'BANNED RFID',
            self::Weekly => 'BANNED SAP & TBC',
        };
    }
}
