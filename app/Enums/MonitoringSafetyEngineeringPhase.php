<?php

declare(strict_types=1);

namespace App\Enums;

enum MonitoringSafetyEngineeringPhase: string
{
    case KajianTeknis = 'kajian_teknis';
    case Pengadaan = 'pengadaan';
    case UjiCoba = 'uji_coba';
    case Standardisasi = 'standardisasi';

    public function label(): string
    {
        return match ($this) {
            self::KajianTeknis => 'Kajian Teknis',
            self::Pengadaan => 'Pengadaan',
            self::UjiCoba => 'Uji Coba',
            self::Standardisasi => 'Standardisasi',
        };
    }
}
