<?php

declare(strict_types=1);

namespace App\Support\AutoBanned;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

final class AutoBannedSchema
{
    public static function hasScrDailyBannedTable(): bool
    {
        try {
            return Schema::hasTable('scr_daily_banned');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasUnbanRequestsTable(): bool
    {
        try {
            return Schema::hasTable('auto_banned_unban_requests');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasSnapshotsTable(): bool
    {
        try {
            return Schema::hasTable('auto_banned_status_snapshots');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasScrapTable(): bool
    {
        try {
            return Schema::hasTable('scr_auto_banned_tbc_sap');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasSidBannedLogTable(): bool
    {
        try {
            return Schema::hasTable('sid_banned_log');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasSidUnbanLogTable(): bool
    {
        try {
            return Schema::hasTable('sid_unban_log');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasScrWeeklyBannedTable(): bool
    {
        try {
            return Schema::hasTable('scr_weekly_banned');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasSidBannedLogWeeklyTable(): bool
    {
        try {
            return Schema::hasTable('sid_banned_log_weekly');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasUnbanRequestScrWeeklyBannedColumn(): bool
    {
        try {
            return Schema::hasTable('auto_banned_unban_requests')
                && Schema::hasColumn('auto_banned_unban_requests', 'scr_weekly_banned_id');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasSidUnbanLogScrWeeklyBannedColumn(): bool
    {
        try {
            return Schema::hasTable('sid_unban_log')
                && Schema::hasColumn('sid_unban_log', 'scr_weekly_banned_id');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasTableauFlowHistoryTable(): bool
    {
        try {
            return Schema::hasTable('tableau_flow_history');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isMissingTableException(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        return $code === '42S02'
            || str_contains($exception->getMessage(), 'Base table or view not found');
    }
}
