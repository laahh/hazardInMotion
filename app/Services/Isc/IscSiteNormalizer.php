<?php

declare(strict_types=1);

namespace App\Services\Isc;

final class IscSiteNormalizer
{
    /**
     * @var array<string, string>
     */
    public const SITES = [
        'BMO' => 'Binungan',
        'LMO' => 'Lati',
        'GMO' => 'Gurimbang',
        'SMO' => 'Sambarata',
        'PUNAN' => 'Punan',
    ];

    public function codeFrom(mixed ...$labels): ?string
    {
        $haystack = mb_strtolower(trim(implode(' ', array_map(
            static fn (mixed $label): string => trim((string) $label),
            $labels
        ))), 'UTF-8');
        if ($haystack === '') {
            return null;
        }

        if (str_contains($haystack, 'punan') || str_contains($haystack, 'pun')) {
            if (str_contains($haystack, 'punan') || preg_match('/\bpun\b/', $haystack) === 1) {
                return 'PUNAN';
            }
        }
        if (str_contains($haystack, 'binungan') || str_contains($haystack, 'bmo')) {
            return 'BMO';
        }
        if (str_contains($haystack, 'lati') || str_contains($haystack, 'lmo')) {
            return 'LMO';
        }
        if (str_contains($haystack, 'gurimbang') || str_contains($haystack, 'gmo')) {
            return 'GMO';
        }
        if (str_contains($haystack, 'sambarata') || str_contains($haystack, 'smo')) {
            return 'SMO';
        }

        return null;
    }

    public function label(string $code): string
    {
        return self::SITES[$code] ?? $code;
    }

    /**
     * @return list<array{code:string,label:string}>
     */
    public function catalog(): array
    {
        $out = [];
        foreach (self::SITES as $code => $label) {
            $out[] = ['code' => $code, 'label' => $label];
        }

        return $out;
    }
}
