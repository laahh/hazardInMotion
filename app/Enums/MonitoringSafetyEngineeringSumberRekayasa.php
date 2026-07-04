<?php

declare(strict_types=1);

namespace App\Enums;

enum MonitoringSafetyEngineeringSumberRekayasa: string
{
    case Pmr2023 = 'pmr_2023';
    case Pmr2024 = 'pmr_2024';
    case Replikasi2024 = 'replikasi_2024';
    case Pmr2025 = 'pmr_2025';
    case Replikasi2025 = 'replikasi_2025';
    case SafetyEngineering = 'safety_engineering';
    case AdditionalEngineering = 'additional_engineering';
    case Replikasi2026 = 'replikasi_2026';
    case RekomInsiden = 'rekom_insiden';
    case RekomGr = 'rekom_gr';

    public function label(): string
    {
        return match ($this) {
            self::Pmr2023 => 'PMR 2023',
            self::Pmr2024 => 'PMR 2024',
            self::Replikasi2024 => 'Replikasi 2024',
            self::Pmr2025 => 'PMR 2025',
            self::Replikasi2025 => 'Replikasi 2025',
            self::SafetyEngineering => 'Safety Engineering',
            self::AdditionalEngineering => 'Additional Safety Engineering',
            self::Replikasi2026 => 'Replikasi 2026',
            self::RekomInsiden => 'Rekom Insiden',
            self::RekomGr => 'Rekom GR',
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
