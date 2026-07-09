<?php

declare(strict_types=1);

namespace App\Enums;

enum AutoBannedReconcileGapType: string
{
    case NoRequest = 'no_request';
    case MissingUnbanLog = 'missing_unban_log';
    case WeeklyNoRequest = 'weekly_no_request';
    case WeeklyMissingUnbanLog = 'weekly_missing_unban_log';

    public function isWeekly(): bool
    {
        return match ($this) {
            self::WeeklyNoRequest, self::WeeklyMissingUnbanLog => true,
            default => false,
        };
    }

    public function isMissingUnbanLog(): bool
    {
        return match ($this) {
            self::MissingUnbanLog, self::WeeklyMissingUnbanLog => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::NoRequest => 'Daily · Tanpa pengajuan',
            self::MissingUnbanLog => 'Daily · Tanpa log unban',
            self::WeeklyNoRequest => 'Weekly · Tanpa pengajuan',
            self::WeeklyMissingUnbanLog => 'Weekly · Tanpa log unban',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NoRequest => 'Banned daily SUCCESS wajib punya pengajuan Disetujui + log unban SUCCESS (per scr_daily_banned_id). Tab ini: belum ada pengajuan sama sekali.',
            self::MissingUnbanLog => 'Banned daily SUCCESS wajib punya pengajuan Disetujui + log unban SUCCESS. Tab ini: pengajuan Disetujui sudah ada, log unban belum.',
            self::WeeklyNoRequest => 'Banned weekly SUCCESS wajib punya pengajuan Disetujui + log unban SUCCESS (per scr_weekly_banned_id). Tab ini: belum ada pengajuan sama sekali.',
            self::WeeklyMissingUnbanLog => 'Banned weekly SUCCESS wajib punya pengajuan Disetujui + log unban SUCCESS. Tab ini: pengajuan Disetujui sudah ada, log unban belum.',
        };
    }

    public function scopeLabel(): string
    {
        return $this->isWeekly() ? 'Weekly' : 'Daily';
    }

    public function bannedLogTableLabel(): string
    {
        return $this->isWeekly() ? 'sid_banned_log_weekly' : 'sid_banned_log';
    }

    public function scrRefColumn(): string
    {
        return $this->isWeekly() ? 'scr_weekly_banned_id' : 'scr_daily_banned_id';
    }

    public function defaultMinDaysOld(): int
    {
        return match ($this) {
            self::NoRequest, self::WeeklyNoRequest => 3,
            self::MissingUnbanLog, self::WeeklyMissingUnbanLog => 0,
        };
    }

    /**
     * @return array<int, AutoBannedReconcileUnbanLogMode>
     */
    public function allowedUnbanLogModes(): array
    {
        return match ($this) {
            self::NoRequest, self::WeeklyNoRequest => [
                AutoBannedReconcileUnbanLogMode::Success,
                AutoBannedReconcileUnbanLogMode::BelumSukses,
            ],
            self::MissingUnbanLog, self::WeeklyMissingUnbanLog => [
                AutoBannedReconcileUnbanLogMode::UnbanLogOnly,
            ],
        };
    }

    public function defaultUnbanLogMode(): AutoBannedReconcileUnbanLogMode
    {
        return match ($this) {
            self::NoRequest, self::WeeklyNoRequest => AutoBannedReconcileUnbanLogMode::Success,
            self::MissingUnbanLog, self::WeeklyMissingUnbanLog => AutoBannedReconcileUnbanLogMode::UnbanLogOnly,
        };
    }
}
