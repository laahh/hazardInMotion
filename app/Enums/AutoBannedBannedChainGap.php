<?php

declare(strict_types=1);

namespace App\Enums;

enum AutoBannedBannedChainGap: string
{
    case Complete = 'complete';
    case MissingRequest = 'missing_request';
    case MissingUnban = 'missing_unban';
    case RequestPending = 'request_pending';
    case RequestRejected = 'request_rejected';

    public function label(): string
    {
        return match ($this) {
            self::Complete => 'Lengkap',
            self::MissingRequest => 'Belum ada pengajuan',
            self::MissingUnban => 'Belum ada log unban',
            self::RequestPending => 'Pengajuan menunggu SOD',
            self::RequestRejected => 'Pengajuan ditolak',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Complete => 'bg-emerald-100 text-emerald-800',
            self::MissingRequest => 'bg-amber-100 text-amber-900',
            self::MissingUnban => 'bg-sky-100 text-sky-900',
            self::RequestPending => 'bg-violet-100 text-violet-900',
            self::RequestRejected => 'bg-red-100 text-red-800',
        };
    }

    public function isIncomplete(): bool
    {
        return $this !== self::Complete;
    }

    public function reconcileGapType(bool $isWeekly): ?AutoBannedReconcileGapType
    {
        return match ($this) {
            self::MissingRequest => $isWeekly
                ? AutoBannedReconcileGapType::WeeklyNoRequest
                : AutoBannedReconcileGapType::NoRequest,
            self::MissingUnban => $isWeekly
                ? AutoBannedReconcileGapType::WeeklyMissingUnbanLog
                : AutoBannedReconcileGapType::MissingUnbanLog,
            default => null,
        };
    }
}
