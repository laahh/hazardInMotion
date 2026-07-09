<?php

declare(strict_types=1);

namespace App\Enums;

enum AutoBannedReconcileUnbanLogMode: string
{
    case Success = 'success';
    case BelumSukses = 'belum_sukses';
    case UnbanLogOnly = 'unban_log_only';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'SUCCESS — pengajuan Disetujui + log unban SUCCESS',
            self::BelumSukses => 'BLM SUKSES — hanya pengajuan Disetujui (tanpa log unban)',
            self::UnbanLogOnly => 'LOG SAJA — backfill sid_unban_log (pengajuan sudah ada)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Success => 'SUCCESS',
            self::BelumSukses => 'BLM SUKSES',
            self::UnbanLogOnly => 'LOG SAJA',
        };
    }

    public function createsUnbanLog(): bool
    {
        return $this === self::Success || $this === self::UnbanLogOnly;
    }

    public function createsUnbanRequest(): bool
    {
        return $this === self::Success || $this === self::BelumSukses;
    }

    public function requiresExistingRequest(): bool
    {
        return $this === self::UnbanLogOnly;
    }
}
