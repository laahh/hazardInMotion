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

    public static function hasSidUnbannedLogTable(): bool
    {
        try {
            return Schema::hasTable('sid_unbanned_log');
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
