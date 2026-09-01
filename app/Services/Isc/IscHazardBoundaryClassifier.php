<?php

declare(strict_types=1);

namespace App\Services\Isc;

final class IscHazardBoundaryClassifier
{
    public const KIND_EMPLOYEE_DANGER = 'employee_danger';

    public const KIND_EMPLOYEE_COMPETENCE = 'employee_competence';

    public const KIND_UNIT_DANGER = 'unit_danger';

    /**
     * @var array<string, string>
     */
    public const KIND_LABELS = [
        self::KIND_EMPLOYEE_DANGER => 'Pelanggaran Batas Bahaya Karyawan',
        self::KIND_EMPLOYEE_COMPETENCE => 'Pelanggaran Batas Kompetensi Karyawan',
        self::KIND_UNIT_DANGER => 'Pelanggaran Batas Bahaya Unit',
    ];

    /**
     * @param  array<string, mixed>  $properties
     */
    public function isHazardous(array $properties): bool
    {
        return $this->kind($properties) !== null;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function kind(array $properties): ?string
    {
        $explicit = strtolower(trim((string) ($properties['hazard_kind'] ?? $properties['kind'] ?? '')));
        if (isset(self::KIND_LABELS[$explicit])) {
            return $explicit;
        }

        $type = strtoupper(trim((string) ($properties['type'] ?? '')));
        if ($type === 'INVERSE') {
            return null;
        }

        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) ($properties['risk'] ?? ''),
            (string) ($properties['risk_level'] ?? ''),
            (string) ($properties['riskLevel'] ?? ''),
            (string) ($properties['risk_name'] ?? ''),
            (string) ($properties['tipe'] ?? ''),
            $type === 'DANGER_COMPETENCY' ? '' : (string) ($properties['type'] ?? ''),
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

        if (preg_match('/kompeten|competence|competency/u', $haystack) === 1) {
            return self::KIND_EMPLOYEE_COMPETENCE;
        }
        if (preg_match('/bahaya unit|unit_danger|unit danger|alat berat|\bunit\b/u', $haystack) === 1) {
            return self::KIND_UNIT_DANGER;
        }
        if (preg_match('/\b(tinggi|high|bahaya|hazard|unsafe|restricted|danger|blasting|peledakan)\b/u', $haystack) === 1) {
            return self::KIND_EMPLOYEE_DANGER;
        }
        if ($this->isHazardColor((string) ($properties['risk_color'] ?? $properties['color'] ?? ''))) {
            return self::KIND_EMPLOYEE_DANGER;
        }

        return null;
    }

    public function label(?string $kind): ?string
    {
        if ($kind === null || $kind === '') {
            return null;
        }

        return self::KIND_LABELS[$kind] ?? $kind;
    }

    private function isHazardColor(string $color): bool
    {
        $c = strtolower(trim($color));

        return in_array($c, ['#dc2626', '#ef4444', '#b91c1c', '#991b1b', '#c5221f', 'red', '#ff0000', '#e53935'], true);
    }
}
