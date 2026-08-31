<?php

declare(strict_types=1);

namespace App\Support\OakCcv;

/**
 * Pemetaan perusahaan pelapor OAK CCV: grup BC vs mitra kerja.
 */
final class OakCcvCompanyClassifier
{
    /** @var list<string> */
    public const ENTITY_ORDER = ['BC', 'BCE', 'Unggul', 'Primac', 'Suprima', 'Yayasan', 'Mitra'];

    /** @var list<string> */
    public const BC_ENTITIES = ['BC', 'BCE', 'Unggul', 'Primac', 'Suprima', 'Yayasan'];

    /** @var array<string, string> */
    public const ENTITY_COMPANIES = [
        'BC' => 'PT Berau Coal',
        'BCE' => 'PT Berau Coal Energy',
        'Unggul' => 'PT Unggul Jaya Berkah',
        'Primac' => 'PT Primac Perkasa Indonesia',
        'Suprima' => 'PT Suprima Mitra Adihusada',
        'Yayasan' => 'Yayasan Dharma Bakti Berau Coal',
        'Mitra' => 'Mitra kerja (selain grup BC)',
    ];

    /** @var array<string, string> */
    public const ENTITY_COLORS = [
        'BC' => '#3952bc',
        'BCE' => '#72479e',
        'Unggul' => '#0057bd',
        'Primac' => '#0d9488',
        'Suprima' => '#d97706',
        'Yayasan' => '#16a34a',
        'Mitra' => '#64748b',
    ];

    /**
     * @return array{group: string, entity: string}
     */
    public static function classify(?string $companyName): array
    {
        $n = mb_strtolower(trim((string) $companyName));
        if ($n === '') {
            return ['group' => 'Mitra', 'entity' => 'Mitra'];
        }
        if (str_contains($n, 'yayasan')) {
            return ['group' => 'BC', 'entity' => 'Yayasan'];
        }
        if (preg_match('/berau\s+coal\s+energy/u', $n) === 1) {
            return ['group' => 'BC', 'entity' => 'BCE'];
        }
        if (preg_match('/berau\s+coal/u', $n) === 1) {
            return ['group' => 'BC', 'entity' => 'BC'];
        }
        if (str_contains($n, 'unggul')) {
            return ['group' => 'BC', 'entity' => 'Unggul'];
        }
        if (str_contains($n, 'primac')) {
            return ['group' => 'BC', 'entity' => 'Primac'];
        }
        if (str_contains($n, 'suprima')) {
            return ['group' => 'BC', 'entity' => 'Suprima'];
        }

        return ['group' => 'Mitra', 'entity' => 'Mitra'];
    }

    public static function isBcEntity(string $entity): bool
    {
        return in_array($entity, self::BC_ENTITIES, true);
    }

    public static function color(string $entity): string
    {
        return self::ENTITY_COLORS[$entity] ?? '#94a3b8';
    }
}
