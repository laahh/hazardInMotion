<?php

declare(strict_types=1);

namespace App\Support\MonitoringSafetyEngineering;

/**
 * Definisi kolom grid Handsontable untuk monitoring_safety_engineering_records.
 */
final class MonitoringSafetyEngineeringRecordGridDefinition
{
    /**
     * @return list<string>
     */
    public static function editableFields(): array
    {
        return [
            'site',
            'perusahaan',
            'aktivitas',
            'sumber_rekayasa',
            'pelaksana_rekayasa',
            'pengendalian_rekayasa',
            'tanggal_ideation',
            'kajian_teknis_due_date',
            'kajian_teknis_status',
            'pengadaan_due_date',
            'pengadaan_status',
            'uji_coba_due_date',
            'uji_coba_status',
            'standardisasi_due_date',
            'standardisasi_status',
            'replikasi_due_date',
            'replikasi_total_populasi',
            'replikasi_satuan',
            'replikasi_target_komitmen',
            'replikasi_diusulkan_pjo',
            'replikasi_ditinjau',
            'replikasi_disetujui',
            'replikasi_aktual',
            'deteksi_deviasi',
            'intervensi_deviasi',
            'prediksi_penurunan_tangga_risiko',
            'terkait_hazard',
            'terkait_insiden',
            'brief_analysis_challenge',
            'next_to_do',
            'potensi_peningkatan_efektivitas',
            'pengendalian_peningkatan_efektivitas',
        ];
    }

    /**
     * Jumlah kolom kiri yang di-freeze (grup Identitas).
     */
    public static function fixedColumnsLeft(): int
    {
        $groups = self::nestedHeaderGroups()[0] ?? [];

        foreach ($groups as $group) {
            if (($group['label'] ?? '') === 'Identitas') {
                return (int) ($group['colspan'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * @return list<array{key: string, label: string, type: string, read_only?: bool, width?: int}>
     */
    public static function columns(): array
    {
        return [
            ['key' => 'site', 'label' => 'Site', 'type' => 'dropdown', 'width' => 90],
            ['key' => 'perusahaan', 'label' => 'Perusahaan', 'type' => 'dropdown', 'width' => 110],
            ['key' => 'aktivitas', 'label' => 'Aktivitas', 'type' => 'text', 'width' => 140],
            ['key' => 'sumber_rekayasa', 'label' => 'Sumber Rekayasa', 'type' => 'dropdown', 'width' => 170],
            ['key' => 'pelaksana_rekayasa', 'label' => 'Pelaksana', 'type' => 'dropdown', 'width' => 110],
            ['key' => 'pengendalian_rekayasa', 'label' => 'Pengendalian Rekayasa', 'type' => 'text', 'width' => 260],
            ['key' => 'tanggal_ideation', 'label' => 'Tanggal Ideation', 'type' => 'date', 'width' => 120],
            ['key' => 'kajian_teknis_due_date', 'label' => 'KT Due Date', 'type' => 'date', 'width' => 115],
            ['key' => 'kajian_teknis_status', 'label' => 'KT Status', 'type' => 'dropdown', 'width' => 110],
            ['key' => 'pengadaan_due_date', 'label' => 'Pengadaan Due', 'type' => 'date', 'width' => 115],
            ['key' => 'pengadaan_status', 'label' => 'Pengadaan Status', 'type' => 'dropdown', 'width' => 120],
            ['key' => 'uji_coba_due_date', 'label' => 'Uji Coba Due', 'type' => 'date', 'width' => 115],
            ['key' => 'uji_coba_status', 'label' => 'Uji Coba Status', 'type' => 'dropdown', 'width' => 115],
            ['key' => 'standardisasi_due_date', 'label' => 'Std Due', 'type' => 'date', 'width' => 110],
            ['key' => 'standardisasi_status', 'label' => 'Std Status', 'type' => 'dropdown', 'width' => 115],
            ['key' => 'replikasi_due_date', 'label' => 'Repl Due', 'type' => 'date', 'width' => 110],
            ['key' => 'replikasi_total_populasi', 'label' => 'Total Populasi', 'type' => 'numeric', 'width' => 105],
            ['key' => 'replikasi_satuan', 'label' => 'Satuan', 'type' => 'dropdown', 'width' => 100],
            ['key' => 'replikasi_target_komitmen', 'label' => 'Target', 'type' => 'numeric', 'width' => 75],
            ['key' => 'replikasi_diusulkan_pjo', 'label' => 'Diusulkan PJO', 'type' => 'text', 'width' => 110],
            ['key' => 'replikasi_ditinjau', 'label' => 'Ditinjau', 'type' => 'text', 'width' => 90],
            ['key' => 'replikasi_disetujui', 'label' => 'Disetujui', 'type' => 'text', 'width' => 90],
            ['key' => 'replikasi_aktual', 'label' => 'Aktual', 'type' => 'numeric', 'width' => 75],
            ['key' => 'deteksi_deviasi', 'label' => 'Deteksi Deviasi', 'type' => 'numeric', 'width' => 105],
            ['key' => 'intervensi_deviasi', 'label' => 'Intervensi', 'type' => 'dropdown', 'width' => 160],
            ['key' => 'prediksi_penurunan_tangga_risiko', 'label' => 'Prediksi Risiko', 'type' => 'numeric', 'width' => 105],
            ['key' => 'terkait_hazard', 'label' => 'Hazard', 'type' => 'dropdown', 'width' => 80],
            ['key' => 'terkait_insiden', 'label' => 'Insiden', 'type' => 'dropdown', 'width' => 80],
            ['key' => 'brief_analysis_challenge', 'label' => 'Brief Analysis', 'type' => 'text', 'width' => 200],
            ['key' => 'next_to_do', 'label' => 'Next To Do', 'type' => 'text', 'width' => 200],
            ['key' => 'potensi_peningkatan_efektivitas', 'label' => 'Potensi Efektivitas', 'type' => 'dropdown', 'width' => 120],
            ['key' => 'pengendalian_peningkatan_efektivitas', 'label' => 'Pengendalian Efektivitas', 'type' => 'text', 'width' => 200],
        ];
    }

    /**
     * @return list<list<array{label: string, colspan: int}>>
     */
    public static function nestedHeaderGroups(): array
    {
        return [
            [
                ['label' => 'Identitas', 'colspan' => 6],
                ['label' => 'Ideation', 'colspan' => 1],
                ['label' => 'Kajian Teknis', 'colspan' => 2],
                ['label' => 'Pengadaan', 'colspan' => 2],
                ['label' => 'Uji Coba', 'colspan' => 2],
                ['label' => 'Standardisasi', 'colspan' => 2],
                ['label' => 'Replikasi', 'colspan' => 8],
                ['label' => 'Deviasi & Risiko', 'colspan' => 5],
                ['label' => 'Analisis', 'colspan' => 4],
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function dropdownSources(): array
    {
        return [
            'site' => array_values(config('monitoring_safety_engineering.sites', [])),
            'perusahaan' => array_values(config('monitoring_safety_engineering.perusahaan', [])),
            'sumber_rekayasa' => array_values(config('monitoring_safety_engineering.sumber_rekayasa', [])),
            'pelaksana_rekayasa' => array_values(config('monitoring_safety_engineering.pelaksana_rekayasa', [])),
            'kajian_teknis_status' => array_values(config('monitoring_safety_engineering.phase_status', [])),
            'pengadaan_status' => array_values(config('monitoring_safety_engineering.phase_status', [])),
            'uji_coba_status' => array_values(config('monitoring_safety_engineering.phase_status', [])),
            'standardisasi_status' => array_values(config('monitoring_safety_engineering.phase_status', [])),
            'replikasi_satuan' => array_values(config('monitoring_safety_engineering.replikasi_satuan', [])),
            'intervensi_deviasi' => array_values(config('monitoring_safety_engineering.intervensi_deviasi', [])),
            'terkait_hazard' => ['Ya', 'Tidak'],
            'terkait_insiden' => ['Ya', 'Tidak'],
            'potensi_peningkatan_efektivitas' => ['Ya', 'Tidak'],
        ];
    }
}
