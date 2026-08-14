<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\MonitoringSafetyEngineering\MonitoringSafetyEngineeringExcelValueMapper as Mapper;
use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use ValueError;

/**
 * Cast enum yang menerima backing value maupun label legacy di database.
 *
 * Daftar di model menggunakan class-string + parameter, contoh:
 * LenientBackedEnumCast::class.':'.SomeEnum::class.',sumber_rekayasa'
 *
 * @template T of BackedEnum
 */
final class LenientBackedEnumCast implements CastsAttributes
{
    /**
     * @param  class-string<T>  $enumClass
     */
    public function __construct(
        private readonly string $enumClass,
        private readonly string $resolverKey = '',
    ) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $this->enumClass) {
            return $value;
        }

        $stringValue = (string) $value;

        try {
            return $this->enumClass::from($stringValue);
        } catch (ValueError) {
            try {
                return $this->resolveLegacy($stringValue);
            } catch (\InvalidArgumentException|ValueError) {
                // Nilai legacy tidak dikenal: jangan crash halaman baca.
                return null;
            }
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $this->enumClass) {
            return $value->value;
        }

        $stringValue = (string) $value;

        try {
            return $this->enumClass::from($stringValue)->value;
        } catch (ValueError) {
            try {
                $resolved = $this->resolveLegacy($stringValue);
            } catch (\InvalidArgumentException|ValueError) {
                return null;
            }

            if ($resolved === null) {
                return null;
            }

            return $resolved instanceof BackedEnum ? $resolved->value : $stringValue;
        }
    }

    private function resolveLegacy(string $value): mixed
    {
        return match ($this->resolverKey) {
            'sumber_rekayasa' => Mapper::resolveSumberRekayasa($value),
            'pelaksana_rekayasa' => Mapper::resolvePelaksana($value),
            'phase_status' => Mapper::resolvePhaseStatus($value, 'Status Fase'),
            default => $this->enumClass::from($value),
        };
    }
}
