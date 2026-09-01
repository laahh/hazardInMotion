<?php

declare(strict_types=1);

namespace App\Services\Isc;

final class IscHazardBoundaryClassifier
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function isHazardous(array $properties): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) ($properties['risk'] ?? ''),
            (string) ($properties['risk_level'] ?? ''),
            (string) ($properties['riskLevel'] ?? ''),
            (string) ($properties['risk_name'] ?? ''),
            (string) ($properties['tipe'] ?? ''),
            (string) ($properties['type'] ?? ''),
            (string) ($properties['jenis'] ?? ''),
            (string) ($properties['kategori'] ?? ''),
            (string) ($properties['category'] ?? ''),
            (string) ($properties['flag'] ?? ''),
            (string) ($properties['status'] ?? ''),
            (string) ($properties['status_name'] ?? ''),
            (string) ($properties['nama'] ?? ''),
            (string) ($properties['name'] ?? ''),
            (string) ($properties['aktivitas'] ?? ''),
            (string) ($properties['activity'] ?? ''),
        ], static fn (string $v): bool => $v !== '')), 'UTF-8');

        if ($haystack === '') {
            return $this->isHazardColor((string) ($properties['risk_color'] ?? $properties['color'] ?? ''));
        }

        if (preg_match('/\b(tinggi|high|bahaya|hazard|unsafe|restricted|danger)\b/u', $haystack) === 1) {
            return true;
        }

        return $this->isHazardColor((string) ($properties['risk_color'] ?? $properties['color'] ?? ''));
    }

    private function isHazardColor(string $color): bool
    {
        $c = strtolower(trim($color));

        return in_array($c, ['#dc2626', '#ef4444', '#b91c1c', '#991b1b', '#c5221f', 'red', '#ff0000', '#e53935'], true);
    }
}
