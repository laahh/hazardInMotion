<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

/**
 * Resolve nama perusahaan mentah BeWell ke label kanonik (mapping alias).
 */
final class SportEvaluationCompanyAliasResolver
{
    /** @var array<string, string>|null UPPER(TRIM(alias)) => canonical label */
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
        $groups = config('evaluasi_well_company_aliases', []);

        foreach ($groups as $canonical => $aliases) {
            if (! is_string($canonical) || ! is_array($aliases)) {
                continue;
            }

            $canonicalTrim = trim($canonical);
            if ($canonicalTrim === '') {
                continue;
            }

            // Canonical sendiri selalu mengarah ke dirinya.
            $map[$this->normalizeKey($canonicalTrim)] = $canonicalTrim;

            foreach ($aliases as $alias) {
                if (! is_string($alias)) {
                    continue;
                }
                $key = $this->normalizeKey($alias);
                if ($key === '' || isset($map[$key])) {
                    continue;
                }
                $map[$key] = $canonicalTrim;
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
     * Nama tampilan; jika tidak termapping, pakai nama asli (trimmed).
     */
    public function resolve(?string $rawCompany): string
    {
        $raw = trim((string) ($rawCompany ?? ''));
        if ($raw === '' || $raw === '-') {
            return '';
        }

        return $this->aliasMap()[$this->normalizeKey($raw)] ?? $raw;
    }

    /**
     * Apakah raw company cocok dengan filter (nama kanonik atau alias-nya).
     */
    public function matchesFilter(?string $rawCompany, string $filterCompany): bool
    {
        $filter = trim($filterCompany);
        if ($filter === '') {
            return true;
        }

        return $this->normalizeKey($this->resolve($rawCompany)) === $this->normalizeKey($this->resolve($filter));
    }
}
