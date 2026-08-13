<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

/**
 * Matriks Level Efektivitas → prediksi penurunan tangga risiko.
 *
 * Eliminasi → 3 | Alat+Alat → 2 | Hybrid / Manusia+Manusia / fallback → 1
 */
final class MonitoringSafetyEngineeringRiskReductionCalculator
{
    /**
     * Klasifikasi baris matriks dari DETEKSI + INTERVENSI.
     * Mendukung format legacy "Deteksi -> Intervensi" di kolom intervensi saja.
     */
    public function resolveControlRowKey(?string $deteksi, ?string $intervensi): string
    {
        [$deteksiLevel, $intervensiLevel] = $this->resolveLevels($deteksi, $intervensi);

        if ($deteksiLevel === 'eliminasi' || $intervensiLevel === 'eliminasi') {
            return 'eliminasi';
        }

        if ($deteksiLevel === 'alat' && $intervensiLevel === 'alat') {
            return 'full_automasi';
        }

        if (
            ($deteksiLevel === 'alat' && $intervensiLevel === 'manusia')
            || ($deteksiLevel === 'manusia' && $intervensiLevel === 'alat')
        ) {
            return 'hybrid';
        }

        if ($deteksiLevel === 'manusia' && $intervensiLevel === 'manusia') {
            return 'manusia';
        }

        if (
            $deteksiLevel === 'tidak_mendeteksi'
            || $intervensiLevel === 'menahan_mengurangi'
            || $intervensiLevel === 'menahan'
        ) {
            return 'menahan_mengurangi';
        }

        if ($deteksiLevel === '' && $intervensiLevel === '') {
            return 'menahan_mengurangi';
        }

        return match ($intervensiLevel !== '' ? $intervensiLevel : $deteksiLevel) {
            'alat' => 'full_automasi',
            'manusia' => 'manusia',
            default => 'menahan_mengurangi',
        };
    }

    /**
     * Prediksi tangga dari kombinasi deteksi × intervensi (tanpa nilai tersimpan).
     */
    public function predictSteps(?string $deteksi, ?string $intervensi): ?int
    {
        [$deteksiLevel, $intervensiLevel] = $this->resolveLevels($deteksi, $intervensi);

        if ($deteksiLevel === '' && $intervensiLevel === '') {
            return null;
        }

        return $this->defaultPrediksiForRiskRow(
            $this->resolveControlRowKey($deteksi, $intervensi)
        );
    }

    /**
     * Pakai nilai DB jika sudah terisi; jika kosong hitung dari matriks.
     */
    public function resolveEffectivePrediksi(
        ?int $stored,
        ?string $deteksi,
        ?string $intervensi,
    ): ?int {
        if ($stored !== null && $stored > 0) {
            return $stored;
        }

        return $this->predictSteps($deteksi, $intervensi);
    }

    public function isDerivedPrediksi(
        ?int $stored,
        ?string $deteksi,
        ?string $intervensi,
    ): bool {
        if ($stored !== null && $stored > 0) {
            return false;
        }

        return $this->predictSteps($deteksi, $intervensi) !== null;
    }

    /**
     * Fallback prediksi tangga berdasarkan baris matriks (selaras Matriks Level Efektivitas).
     */
    public function defaultPrediksiForRiskRow(string $rowKey): ?int
    {
        return match ($rowKey) {
            'eliminasi' => 3,
            'full_automasi' => 2,
            'hybrid', 'manusia', 'menahan_mengurangi' => 1,
            default => null,
        };
    }

    public function normalizeRiskControlLevel(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['–', '—', '→', '⇒'], '->', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = str_replace([' ', '-', '/'], '_', $normalized);

        return match ($normalized) {
            'eliminasi', 'eliminate', 'elimination' => 'eliminasi',
            'alat', 'machine', 'equipment', 'tool' => 'alat',
            'manusia', 'human', 'people' => 'manusia',
            'tidak_mendeteksi', 'tidak_ada_deteksi', 'tidakmendeteksi' => 'tidak_mendeteksi',
            'menahan_mengurangi', 'menahan_mengurangi_dampak', 'menahan_mengurangi_dampak_' => 'menahan_mengurangi',
            'menahan', 'mengurangi' => 'menahan_mengurangi',
            default => $normalized,
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveLevels(?string $deteksi, ?string $intervensi): array
    {
        $deteksiLevel = $this->normalizeRiskControlLevel((string) ($deteksi ?? ''));
        $intervensiLevel = $this->normalizeRiskControlLevel((string) ($intervensi ?? ''));

        if (
            ($deteksiLevel === '' || $deteksiLevel === '0')
            && preg_match('/^(.+?)\s*->\s*(.+)$/u', (string) ($intervensi ?? ''), $matches) === 1
        ) {
            $deteksiLevel = $this->normalizeRiskControlLevel($matches[1]);
            $intervensiLevel = $this->normalizeRiskControlLevel($matches[2]);
        }

        return [$deteksiLevel, $intervensiLevel];
    }
}
