<?php

declare(strict_types=1);

namespace App\Support\MonitoringSafetyEngineering;

use App\Enums\MonitoringSafetyEngineeringPelaksanaRekayasa;
use App\Enums\MonitoringSafetyEngineeringPhaseStatus;
use App\Enums\MonitoringSafetyEngineeringSumberRekayasa;

final class MonitoringSafetyEngineeringExcelValueMapper
{
    /**
     * @return array<string, string>
     */
    public static function sumberRekayasaLabelToValue(): array
    {
        $map = [];
        foreach (config('monitoring_safety_engineering.sumber_rekayasa', []) as $value => $label) {
            $map[self::normalizeKey((string) $label)] = (string) $value;
            $map[self::normalizeKey((string) $value)] = (string) $value;
        }

        foreach (self::sumberRekayasaLegacyAliases() as $alias => $value) {
            $map[self::normalizeKey($alias)] = $value;
        }

        return $map;
    }

    /**
     * Alias label lama / nilai mentah yang mungkin tersimpan di database.
     *
     * @return array<string, string>
     */
    public static function sumberRekayasaLegacyAliases(): array
    {
        return [
            'Additional Safety Engineering' => 'additional_engineering',
            'Additional Engineering' => 'additional_engineering',
            'additional_safety_engineering' => 'additional_engineering',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function pelaksanaLabelToValue(): array
    {
        $map = [];
        foreach (config('monitoring_safety_engineering.pelaksana_rekayasa', []) as $value => $label) {
            $map[self::normalizeKey((string) $label)] = (string) $value;
            $map[self::normalizeKey((string) $value)] = (string) $value;
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public static function phaseStatusLabelToValue(): array
    {
        $map = [];
        foreach (config('monitoring_safety_engineering.phase_status', []) as $value => $label) {
            $map[self::normalizeKey((string) $label)] = (string) $value;
            $map[self::normalizeKey(str_replace('_', ' ', (string) $value))] = (string) $value;
            $map[self::normalizeKey((string) $value)] = (string) $value;
        }

        $map[self::normalizeKey('not yet')] = MonitoringSafetyEngineeringPhaseStatus::NotYet->value;
        $map[self::normalizeKey('in progress')] = MonitoringSafetyEngineeringPhaseStatus::InProgress->value;

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public static function intervensiLabelToValue(): array
    {
        $map = [];
        foreach (config('monitoring_safety_engineering.intervensi_deviasi', []) as $value => $label) {
            $map[self::normalizeKey((string) $label)] = (string) $value;
            $map[self::normalizeKey((string) $value)] = (string) $value;
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public static function deteksiLabelToValue(): array
    {
        $map = [];
        foreach (config('monitoring_safety_engineering.deteksi_deviasi', []) as $value => $label) {
            $map[self::normalizeKey((string) $label)] = (string) $value;
            $map[self::normalizeKey((string) $value)] = (string) $value;
        }

        // Alias umum dari spreadsheet
        $map[self::normalizeKey('tidak mendeteksi')] = 'tidak_mendeteksi';
        $map[self::normalizeKey('tidak ada deteksi')] = 'tidak_mendeteksi';

        return $map;
    }

    /**
     * Normalisasi nilai deteksi deviasi ke label kanonik (kolom terpisah dari intervensi).
     */
    public static function resolveDeteksi(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $trimmed = trim($input);

        // Legacy: kolom pernah bertipe integer (jumlah hazard)
        if (is_numeric($trimmed)) {
            return null;
        }

        $config = config('monitoring_safety_engineering.deteksi_deviasi', []);
        $key = self::normalizeKey($trimmed);

        foreach ($config as $configKey => $label) {
            if (self::normalizeKey((string) $label) === $key || self::normalizeKey((string) $configKey) === $key) {
                return (string) $label;
            }
        }

        $map = self::deteksiLabelToValue();
        if (isset($map[$key], $config[$map[$key]])) {
            return (string) $config[$map[$key]];
        }

        throw new \InvalidArgumentException('DETEKSI DEVIASI tidak valid: "' . $input . '".');
    }

    /**
     * Normalisasi nilai intervensi deviasi ke label kanonik untuk disimpan/ditampilkan.
     * Tidak menggabungkan dengan deteksi — kolom tetap terpisah.
     */
    public static function resolveIntervensi(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $trimmed = trim($input);
        $config = config('monitoring_safety_engineering.intervensi_deviasi', []);
        $key = self::normalizeKey($trimmed);

        foreach ($config as $configKey => $label) {
            if (self::normalizeKey((string) $label) === $key || self::normalizeKey((string) $configKey) === $key) {
                return (string) $label;
            }
        }

        // Alias spreadsheet
        $aliases = [
            'menahan/mengurangi dampak' => 'menahan_mengurangi',
            'menahan mengurangi dampak' => 'menahan_mengurangi',
            'menahan & mengurangi' => 'menahan_mengurangi',
            'menahan dan mengurangi' => 'menahan_mengurangi',
        ];
        if (isset($aliases[$key], $config[$aliases[$key]])) {
            return (string) $config[$aliases[$key]];
        }

        $transitionKey = self::intervensiTransitionKeyFromLabel($trimmed);
        if ($transitionKey !== null && isset($config[$transitionKey])) {
            return (string) $config[$transitionKey];
        }

        if ($transitionKey !== null && self::isKnownIntervensiTransition($trimmed)) {
            return self::formatIntervensiTransitionLabel($trimmed);
        }

        throw new \InvalidArgumentException('INTERVENSI DEVIASI tidak valid: "' . $input . '".');
    }

    private static function intervensiTransitionKeyFromLabel(string $input): ?string
    {
        if (! preg_match('/^(.+?)\s*->\s*(.+)$/u', trim($input), $matches)) {
            return null;
        }

        $from = self::normalizeIntervensiLevel($matches[1]);
        $to = self::normalizeIntervensiLevel($matches[2]);

        if ($from === null || $to === null) {
            return null;
        }

        return $from . '_' . $to;
    }

    private static function formatIntervensiTransitionLabel(string $input): string
    {
        if (! preg_match('/^(.+?)\s*->\s*(.+)$/u', trim($input), $matches)) {
            return trim($input);
        }

        return self::titleCaseIntervensiLevel($matches[1]) . ' -> ' . self::titleCaseIntervensiLevel($matches[2]);
    }

    private static function isKnownIntervensiTransition(string $input): bool
    {
        if (! preg_match('/^(.+?)\s*->\s*(.+)$/u', trim($input), $matches)) {
            return false;
        }

        return self::normalizeIntervensiLevel($matches[1]) !== null
            && self::normalizeIntervensiLevel($matches[2]) !== null;
    }

    private static function normalizeIntervensiLevel(string $level): ?string
    {
        $key = self::normalizeKey($level);
        $key = str_replace(['/', '-'], ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        return match ($key) {
            'eliminasi' => 'eliminasi',
            'alat' => 'alat',
            'manusia' => 'manusia',
            'menahan mengurangi dampak', 'menahan mengurangi', 'menahan' => 'menahan_mengurangi',
            'tidak mendeteksi' => 'tidak_mendeteksi',
            default => null,
        };
    }

    private static function titleCaseIntervensiLevel(string $level): string
    {
        $normalized = self::normalizeIntervensiLevel($level);

        return match ($normalized) {
            'eliminasi' => 'Eliminasi',
            'alat' => 'Alat',
            'manusia' => 'Manusia',
            default => trim($level),
        };
    }

    public static function resolveSumberRekayasa(?string $input): MonitoringSafetyEngineeringSumberRekayasa
    {
        $key = self::normalizeKey($input ?? '');
        $map = self::sumberRekayasaLabelToValue();

        if ($key === '' || ! isset($map[$key])) {
            throw new \InvalidArgumentException('SUMBER REKAYASA tidak valid: "' . ($input ?? '') . '".');
        }

        return MonitoringSafetyEngineeringSumberRekayasa::from($map[$key]);
    }

    public static function resolvePelaksana(?string $input): MonitoringSafetyEngineeringPelaksanaRekayasa
    {
        $key = self::normalizeKey($input ?? '');
        $map = self::pelaksanaLabelToValue();

        if ($key === '' || ! isset($map[$key])) {
            throw new \InvalidArgumentException('PELAKSANA REKAYASA tidak valid: "' . ($input ?? '') . '".');
        }

        return MonitoringSafetyEngineeringPelaksanaRekayasa::from($map[$key]);
    }

    public static function resolvePhaseStatus(?string $input, string $fieldLabel): MonitoringSafetyEngineeringPhaseStatus
    {
        if ($input === null || trim($input) === '') {
            return MonitoringSafetyEngineeringPhaseStatus::NotYet;
        }

        $key = self::normalizeKey($input);
        $map = self::phaseStatusLabelToValue();

        if (! isset($map[$key])) {
            throw new \InvalidArgumentException($fieldLabel . ' tidak valid: "' . $input . '".');
        }

        return MonitoringSafetyEngineeringPhaseStatus::from($map[$key]);
    }

    public static function resolveBoolean(?string $input, string $fieldLabel): bool
    {
        if ($input === null || trim($input) === '') {
            return false;
        }

        $key = self::normalizeKey($input);

        return match ($key) {
            'ya', 'yes', 'y', '1', 'true' => true,
            'tidak', 'no', 'n', '0', 'false' => false,
            default => throw new \InvalidArgumentException($fieldLabel . ' harus Ya atau Tidak, ditemukan: "' . $input . '".'),
        };
    }

    public static function normalizeKey(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized;
    }
}
