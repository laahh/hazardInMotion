<?php

declare(strict_types=1);

namespace App\Enums;

enum AutoBannedPipelineStage: string
{
    case Unbanned = 'unbanned';
    case NoRequest = 'no_request';
    case RequestPending = 'request_pending';
    case AwaitingUnban = 'awaiting_unban';
    case RequestRejected = 'request_rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unbanned => 'Sudah Unban',
            self::NoRequest => 'Belum Pengajuan',
            self::RequestPending => 'Menunggu Review SOD',
            self::AwaitingUnban => 'Menunggu Automasi Unban',
            self::RequestRejected => 'Pengajuan Ditolak',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Unbanned => 'ab-pipeline-badge--ok',
            self::NoRequest => 'ab-pipeline-badge--warn',
            self::RequestPending => 'ab-pipeline-badge--info',
            self::AwaitingUnban => 'ab-pipeline-badge--wait',
            self::RequestRejected => 'ab-pipeline-badge--danger',
        };
    }
}
