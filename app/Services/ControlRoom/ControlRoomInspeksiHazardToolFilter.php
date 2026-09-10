<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

/**
 * Tools inspeksi/hazard yang dihitung di dashboard Control Room.
 */
final class ControlRoomInspeksiHazardToolFilter
{
    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        $tools = config('control-room.inspeksi_hazard_tools', []);
        $allowed = [];
        foreach (is_array($tools) ? $tools : [] as $tool) {
            $label = trim((string) $tool);
            if ($label !== '') {
                $allowed[$label] = $label;
            }
        }

        return array_values($allowed);
    }

    public static function matches(?string $tools): bool
    {
        $normalized = mb_strtolower(trim((string) $tools));
        if ($normalized === '') {
            return false;
        }

        foreach (self::allowed() as $allowed) {
            if (mb_strtolower($allowed) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{sql: string, bindings: list<string>}
     */
    public static function sqlPredicate(string $column = 'tools_observasi'): array
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column) !== 1) {
            return ['sql' => 'FALSE', 'bindings' => []];
        }
        $allowed = self::allowed();
        if ($allowed === []) {
            return ['sql' => 'FALSE', 'bindings' => []];
        }

        $placeholders = implode(',', array_fill(0, count($allowed), '?'));

        return [
            'sql' => $column.' IN ('.$placeholders.')',
            'bindings' => $allowed,
        ];
    }
}
