<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

/**
 * Resolve divisi mentah BeWell ke label grup kanonik (mapping manual).
 */
final class SportEvaluationDivisiGroupResolver
{
    /** @var array<string, string>|null UPPER(TRIM(alias)) => group label */
    private ?array $aliasMap = null;

    /**
     * @return array<string, string>
     */
    private function aliasMap(): array
    {
        if ($this->aliasMap !== null) {
            return $this->aliasMap;
        }

        $map = [];
        /** @var array<string, list<string>> $groups */
        $groups = config('evaluasi_well_divisi_groups', []);

        foreach ($groups as $groupLabel => $aliases) {
            if (! is_string($groupLabel) || ! is_array($aliases)) {
                continue;
            }
            foreach ($aliases as $alias) {
                if (! is_string($alias)) {
                    continue;
                }
                $key = $this->normalizeKey($alias);
                if ($key === '' || isset($map[$key])) {
                    continue;
                }
                $map[$key] = $groupLabel;
            }
        }

        $this->aliasMap = $map;

        return $this->aliasMap;
    }

    public function normalizeKey(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    /**
     * Label grup; jika tidak termapping, pakai nama asli (trimmed) atau "Tidak diketahui".
     */
    public function resolve(?string $rawDivisi): string
    {
        $raw = trim((string) ($rawDivisi ?? ''));
        if ($raw === '' || $raw === '-') {
            return 'Tidak diketahui';
        }

        $group = $this->aliasMap()[$this->normalizeKey($raw)] ?? null;

        return $group !== null && $group !== '' ? $group : $raw;
    }

    /**
     * @return list<string>
     */
    public function groupLabels(): array
    {
        /** @var array<string, list<string>> $groups */
        $groups = config('evaluasi_well_divisi_groups', []);
        $labels = array_keys($groups);
        sort($labels, SORT_STRING);

        return $labels;
    }

    /**
     * Semua alias mentah untuk satu label grup (untuk filter SQL/IN).
     *
     * @return list<string>
     */
    public function aliasesForGroup(string $groupLabel): array
    {
        /** @var array<string, list<string>> $groups */
        $groups = config('evaluasi_well_divisi_groups', []);
        $aliases = $groups[$groupLabel] ?? null;
        if (! is_array($aliases)) {
            return [];
        }

        $out = [];
        foreach ($aliases as $alias) {
            if (! is_string($alias)) {
                continue;
            }
            $trimmed = trim($alias);
            if ($trimmed !== '') {
                $out[] = $trimmed;
            }
        }

        return $out;
    }

    /**
     * Apakah $rawDivisi termasuk grup $groupLabel.
     */
    public function belongsToGroup(?string $rawDivisi, string $groupLabel): bool
    {
        return $this->resolve($rawDivisi) === $groupLabel;
    }
}
