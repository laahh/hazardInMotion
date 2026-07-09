<?php

declare(strict_types=1);

namespace App\Enums;

enum AutoBannedPipelineBanScope: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function isWeekly(): bool
    {
        return $this === self::Weekly;
    }

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
        };
    }

    public function bannedLogTableLabel(): string
    {
        return $this->isWeekly() ? 'sid_banned_log_weekly' : 'sid_banned_log';
    }

    public function scrRefColumn(): string
    {
        return $this->isWeekly() ? 'scr_weekly_banned_id' : 'scr_daily_banned_id';
    }
}
