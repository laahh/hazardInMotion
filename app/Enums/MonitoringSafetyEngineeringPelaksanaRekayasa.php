<?php

declare(strict_types=1);

namespace App\Enums;

enum MonitoringSafetyEngineeringPelaksanaRekayasa: string
{
    case Inisiator = 'inisiator';
    case Replikasi = 'replikasi';

    public function label(): string
    {
        return match ($this) {
            self::Inisiator => 'Inisiator',
            self::Replikasi => 'Replikasi',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
