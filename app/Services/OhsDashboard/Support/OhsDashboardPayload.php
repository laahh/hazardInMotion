<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard\Support;

use Illuminate\Http\Request;

final class OhsDashboardPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Request $request): array
    {
        $json = $request->json()?->all();

        return is_array($json) && $json !== [] ? $json : $request->all();
    }

    public static function string(array $payload, string $pascal, ?string $snake = null, string $default = ''): string
    {
        $value = self::raw($payload, $pascal, $snake);

        if ($value === null) {
            return $default;
        }

        return trim((string) $value);
    }

    public static function nullableString(array $payload, string $pascal, ?string $snake = null): ?string
    {
        $value = self::string($payload, $pascal, $snake);

        return $value === '' ? null : $value;
    }

    public static function int(array $payload, string $pascal, ?string $snake = null, ?int $default = null): ?int
    {
        $value = self::raw($payload, $pascal, $snake);

        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    /**
     * @return array<int, mixed>
     */
    public static function array(array $payload, string $pascal, ?string $snake = null): array
    {
        $value = self::raw($payload, $pascal, $snake);

        return is_array($value) ? $value : [];
    }

    public static function raw(array $payload, string $pascal, ?string $snake = null): mixed
    {
        if (array_key_exists($pascal, $payload)) {
            return $payload[$pascal];
        }

        $snake ??= self::toSnake($pascal);

        if (array_key_exists($snake, $payload)) {
            return $payload[$snake];
        }

        return null;
    }

    public static function toSnake(string $pascal): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $pascal));
    }
}
