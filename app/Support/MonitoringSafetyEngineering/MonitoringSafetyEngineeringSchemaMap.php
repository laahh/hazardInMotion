<?php

declare(strict_types=1);

namespace App\Support\MonitoringSafetyEngineering;

/**
 * Pemetaan kolom spreadsheet Monitoring Safety Engineering ke struktur database.
 */
final class MonitoringSafetyEngineeringSchemaMap
{
    /**
     * @return array<string, string>
     */
    public static function recordColumns(): array
    {
        return [
            'NO' => 'row_no',
            'SITE' => 'site',
            'PERUSAHAAN' => 'perusahaan',
            'SUMBER REKAYASA' => 'sumber_rekayasa',
            'PELAKSANA REKAYASA' => 'pelaksana_rekayasa',
            'PENGENDALIAN REKAYASA' => 'pengendalian_rekayasa',
            'TANGGAL IDEATION' => 'tanggal_ideation',
            'KAJIAN TEKNIS — Due Date' => 'kajian_teknis_due_date',
            'KAJIAN TEKNIS — Status' => 'kajian_teknis_status',
            'KAJIAN TEKNIS — Evidence' => 'evidences.kajian_teknis',
            'PENGADAAN — Due Date' => 'pengadaan_due_date',
            'PENGADAAN — Status' => 'pengadaan_status',
            'PENGADAAN — Evidence' => 'evidences.pengadaan',
            'UJI COBA — Due Date' => 'uji_coba_due_date',
            'UJI COBA — Status' => 'uji_coba_status',
            'UJI COBA — Evidence' => 'evidences.uji_coba',
            'STANDARISASI — Due Date' => 'standardisasi_due_date',
            'STANDARISASI — Status' => 'standardisasi_status',
            'STANDARISASI — Evidence' => 'evidences.standardisasi',
            'REPLIKASI — Due Date' => 'replikasi_due_date',
            'REPLIKASI — Total Populasi' => 'replikasi_total_populasi',
            'REPLIKASI — Satuan Rekayasa' => 'replikasi_satuan',
            'REPLIKASI — Target Replikasi by Komitmen' => 'replikasi_target_komitmen',
            'REPLIKASI — Diusulkan Oleh PJO' => 'replikasi_diusulkan_pjo',
            'REPLIKASI — Ditinjau' => 'replikasi_ditinjau',
            'REPLIKASI — Disetujui' => 'replikasi_disetujui',
            'REPLIKASI — Aktual Replikasi' => 'replikasi_aktual',
            'DETEKSI DEVIASI' => 'deteksi_deviasi',
            'INTERVENSI DEVIASI' => 'intervensi_deviasi',
            'PREDIKSI PENURUNAN TANGGA NILAI RISIKO' => 'prediksi_penurunan_tangga_risiko',
            'TERKAIT HAZARD' => 'terkait_hazard',
            'TERKAIT INSIDEN' => 'terkait_insiden',
            'EFEKTIVITAS REKAYASA' => 'efektivitas_rekayasa',
            'NEXT TO DO' => 'next_to_do',
            'POTENSI PENINGKATAN LEVEL EFEKTIVITAS' => 'potensi_peningkatan_efektivitas',
            'PENGENDALIAN REKAYASA (PENINGKATAN LEVEL EFEKTIVITAS)' => 'pengendalian_peningkatan_efektivitas',
            'AKTIVITAS' => 'aktivitas',
            'TOTAL RISIKO SIGNIFIKAN' => 'total_risiko_signifikan',
            'LINK LIST RISIKO SIGNIFIKAN' => 'link_list_risiko_signifikan',
            'JUMLAH RISIKO SIGNIFIKAN POTENSI TERCOVER REKAYASA' => 'jumlah_risiko_signifikan_tercover_rekayasa',
            'LINK RISIKO SIGNIFIKAN POTENSI TERCOVER REKAYASA' => 'link_risiko_signifikan_tercover_rekayasa',
            // BRIEF ANALYSIS/CHALLENGE tidak lagi bagian dari template Excel — kolom dipertahankan
            // di database & grid edit karena masih dipakai oleh Dashboard/PMR Evaluation/dll.
            'BRIEF ANALYSIS/CHALLENGE (grid only)' => 'brief_analysis_challenge',
        ];
    }
}
