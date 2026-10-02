<?php

declare(strict_types=1);

namespace App\Support\EmergencyResponse;

use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\MasterData\EquipmentCategory;
use App\Models\EmergencyResponse\MasterData\Site;

/**
 * Membangun UUID peralatan yang bisa dibaca manusia:
 * {SITE}-{KATEGORI}-{NAMA}-{URUTAN}, mis. "PMO-Fire Equipment-Hose-1".
 *
 * Urutan dihitung per kombinasi site + kategori + nama, sehingga Hose ke-2
 * di PMO/Fire Equipment menjadi "PMO-Fire Equipment-Hose-2".
 */
class EquipmentUuidGenerator
{
    public const SEPARATOR = '-';

    /** Dipakai bila site/kategori belum diisi, supaya UUID tetap terbaca. */
    public const MISSING_PART = 'NN';

    /** @var array<string, string> cache nama site/kategori per request */
    private array $labelCache = [];

    /**
     * @return array{0: int, 1: string} urutan dan UUID yang sudah unik
     */
    public function generate(EmergencyEquipment $equipment): array
    {
        $sequence = $this->nextSequence($equipment);

        while ($this->isTaken($uuid = $this->compose($equipment, $sequence), $equipment)) {
            $sequence++;
        }

        return [$sequence, $uuid];
    }

    public function compose(EmergencyEquipment $equipment, int $sequence): string
    {
        $parts = [
            $this->siteLabel($equipment->site_id),
            $this->categoryLabel($equipment->equipment_category_id),
            $this->sanitize((string) $equipment->name) ?: self::MISSING_PART,
            (string) $sequence,
        ];

        return implode(self::SEPARATOR, $parts);
    }

    private function nextSequence(EmergencyEquipment $equipment): int
    {
        $highest = (int) $this->groupQuery($equipment)->max('sequence_number');

        return $highest + 1;
    }

    private function isTaken(string $uuid, EmergencyEquipment $equipment): bool
    {
        return EmergencyEquipment::withTrashed()
            ->where('code', $uuid)
            ->when($equipment->exists, fn ($query) => $query->whereKeyNot($equipment->getKey()))
            ->exists();
    }

    /**
     * Soft-deleted ikut dihitung supaya UUID tidak pernah dipakai ulang.
     */
    private function groupQuery(EmergencyEquipment $equipment)
    {
        return EmergencyEquipment::withTrashed()
            ->when(
                $equipment->site_id === null,
                fn ($query) => $query->whereNull('site_id'),
                fn ($query) => $query->where('site_id', $equipment->site_id),
            )
            ->when(
                $equipment->equipment_category_id === null,
                fn ($query) => $query->whereNull('equipment_category_id'),
                fn ($query) => $query->where('equipment_category_id', $equipment->equipment_category_id),
            )
            ->where('name', $equipment->name)
            ->when($equipment->exists, fn ($query) => $query->whereKeyNot($equipment->getKey()));
    }

    private function siteLabel(?string $siteId): string
    {
        return $this->cachedLabel('site', $siteId, fn () => Site::withTrashed()->find($siteId)?->name);
    }

    private function categoryLabel(?string $categoryId): string
    {
        return $this->cachedLabel('category', $categoryId, fn () => EquipmentCategory::withTrashed()->find($categoryId)?->name);
    }

    private function cachedLabel(string $prefix, ?string $id, callable $resolver): string
    {
        if ($id === null) {
            return self::MISSING_PART;
        }

        $key = $prefix.':'.$id;

        if (! array_key_exists($key, $this->labelCache)) {
            $this->labelCache[$key] = $this->sanitize((string) $resolver()) ?: self::MISSING_PART;
        }

        return $this->labelCache[$key];
    }

    /**
     * Separator dibuang dari tiap bagian agar UUID tetap bisa dipecah kembali;
     * slash juga dibuang karena UUID ikut dipakai sebagai segmen URL QR/scan.
     */
    private function sanitize(string $value): string
    {
        $clean = str_replace([self::SEPARATOR, '/', '\\'], ' ', $value);
        $clean = (string) preg_replace('/\s+/', ' ', $clean);

        return trim($clean);
    }
}
