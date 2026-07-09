<?php

declare(strict_types=1);

namespace App\Enums;

enum AutoBannedReconcileGapType: string
{
    case NoRequest = 'no_request';
    case MissingUnbanLog = 'missing_unban_log';

    public function label(): string
    {
        return match ($this) {
            self::NoRequest => 'Tanpa pengajuan',
            self::MissingUnbanLog => 'Tanpa log unban',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NoRequest => 'Banned ada, belum ada pengajuan unban di sistem.',
            self::MissingUnbanLog => 'Pengajuan sudah ada (scr_daily_banned_id sama), log unban SUCCESS belum ada.',
        };
    }

    public function defaultMinDaysOld(): int
    {
        return match ($this) {
            self::NoRequest => 3,
            self::MissingUnbanLog => 0,
        };
    }

    /**
     * @return array<int, AutoBannedReconcileUnbanLogMode>
     */
    public function allowedUnbanLogModes(): array
    {
        return match ($this) {
            self::NoRequest => [
                AutoBannedReconcileUnbanLogMode::Success,
                AutoBannedReconcileUnbanLogMode::BelumSukses,
            ],
            self::MissingUnbanLog => [
                AutoBannedReconcileUnbanLogMode::UnbanLogOnly,
            ],
        };
    }

    public function defaultUnbanLogMode(): AutoBannedReconcileUnbanLogMode
    {
        return match ($this) {
            self::NoRequest => AutoBannedReconcileUnbanLogMode::Success,
            self::MissingUnbanLog => AutoBannedReconcileUnbanLogMode::UnbanLogOnly,
        };
    }
}
