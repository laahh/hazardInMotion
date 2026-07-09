<?php

declare(strict_types=1);

namespace App\Enums;

enum AutoBannedSidAutomationStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Success = 'SUCCESS';
    case Failed = 'FAILED';
    case Skipped = 'SKIPPED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Processing => 'Diproses',
            self::Success => 'Berhasil',
            self::Failed => 'Gagal',
            self::Skipped => 'Dilewati',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'ab-badge--wait',
            self::Processing => 'ab-badge--info',
            self::Success => 'ab-badge--ok',
            self::Failed => 'ab-badge--danger',
            self::Skipped => 'ab-badge--muted',
        };
    }

    /**
     * Status banned yang boleh direkonsiliasi (termasuk SKIPPED = ban sudah ada, automasi lewati duplikat).
     *
     * @return array<int, string>
     */
    public static function reconcileEligibleValues(): array
    {
        return [
            self::Success->value,
            self::Skipped->value,
        ];
    }

    public function isReconcileEligible(): bool
    {
        return in_array($this->value, self::reconcileEligibleValues(), true);
    }
}
