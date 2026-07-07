<?php

declare(strict_types=1);

namespace App\Enums;

enum AutoBannedReconcileUnbanLogMode: string
{
    case Success = 'success';
    case BelumSukses = 'belum_sukses';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'SUCCESS — pengajuan Disetujui + log unban SUCCESS',
            self::BelumSukses => 'BLM SUKSES — hanya pengajuan Disetujui (tanpa log unban)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Success => 'SUCCESS',
            self::BelumSukses => 'BLM SUKSES',
        };
    }

    public function createsUnbanLog(): bool
    {
        return $this === self::Success;
    }
}
