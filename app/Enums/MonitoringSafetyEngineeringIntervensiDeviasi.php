<?php

declare(strict_types=1);

namespace App\Enums;

enum MonitoringSafetyEngineeringIntervensiDeviasi: string
{
    case Eliminasi = 'eliminasi';
    case Alat = 'alat';
    case Manusia = 'manusia';

    public function label(): string
    {
        return match ($this) {
            self::Eliminasi => 'Eliminasi',
            self::Alat => 'Alat',
            self::Manusia => 'Manusia',
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
