<?php

declare(strict_types=1);

return [
    'sites' => [
        'BMO',
        'GMO',
        'BMO 1',
        'BMO 2',
        'BMO 3',
    ],

    'perusahaan' => [
        'PAMA',
        'BUMA',
        'KPC',
        'MTN',
        'SAP',
    ],

    'sumber_rekayasa' => [
        'pmr_2023' => 'PMR 2023',
        'pmr_2024' => 'PMR 2024',
        'replikasi_2024' => 'Replikasi 2024',
        'pmr_2025' => 'PMR 2025',
        'replikasi_2025' => 'Replikasi 2025',
        'safety_engineering' => 'Safety Engineering',
        'additional_engineering' => 'Additional Safety Engineering',
        'replikasi_2026' => 'Replikasi 2026',
        'rekom_insiden' => 'Rekom Insiden',
        'rekom_gr' => 'Rekom GR',
    ],

    'pelaksana_rekayasa' => [
        'inisiator' => 'Inisiator',
        'replikasi' => 'Replikasi',
    ],

    'phase_status' => [
        'not_yet' => 'Not Yet',
        'in_progress' => 'In Progress',
        'done' => 'Done',
    ],

    'status_compliance' => [
        'on_target' => 'On Target',
        'overdue' => 'Overdue',
        'no_due_date' => 'Tanpa Due Date',
    ],

    'trace_phases' => [
        'kajian_teknis' => [
            'label' => 'Kajian Teknis',
            'status' => 'kajian_teknis_status',
            'due' => 'kajian_teknis_due_date',
            'changed_at' => 'kajian_teknis_status_changed_at',
            'compliance' => 'kajian_teknis_status_compliance',
        ],
        'pengadaan' => [
            'label' => 'Pengadaan',
            'status' => 'pengadaan_status',
            'due' => 'pengadaan_due_date',
            'changed_at' => 'pengadaan_status_changed_at',
            'compliance' => 'pengadaan_status_compliance',
        ],
        'uji_coba' => [
            'label' => 'Uji Coba',
            'status' => 'uji_coba_status',
            'due' => 'uji_coba_due_date',
            'changed_at' => 'uji_coba_status_changed_at',
            'compliance' => 'uji_coba_status_compliance',
        ],
        'standardisasi' => [
            'label' => 'Standardisasi',
            'status' => 'standardisasi_status',
            'due' => 'standardisasi_due_date',
            'changed_at' => 'standardisasi_status_changed_at',
            'compliance' => 'standardisasi_status_compliance',
        ],
    ],

    'intervensi_deviasi' => [
        'menahan_mengurangi' => 'Menahan/mengurangi dampak',
        'eliminasi' => 'Eliminasi',
        'alat' => 'Alat',
        'manusia' => 'Manusia',
        // Legacy combined format (deteksi -> intervensi) untuk data lama
        'eliminasi_eliminasi' => 'Eliminasi -> Eliminasi',
        'eliminasi_alat' => 'Eliminasi -> Alat',
        'eliminasi_manusia' => 'Eliminasi -> Manusia',
        'alat_eliminasi' => 'Alat -> Eliminasi',
        'alat_alat' => 'Alat -> Alat',
        'alat_manusia' => 'Alat -> Manusia',
        'manusia_eliminasi' => 'Manusia -> Eliminasi',
        'manusia_alat' => 'Manusia -> Alat',
        'manusia_manusia' => 'Manusia -> Manusia',
    ],

    'deteksi_deviasi' => [
        'tidak_mendeteksi' => 'Tidak mendeteksi',
        'eliminasi' => 'Eliminasi',
        'alat' => 'Alat',
        'manusia' => 'Manusia',
    ],

    'efektivitas_rekayasa' => [
        'l1_eliminasi' => 'L1 - Eliminasi',
        'l2_mencegah' => 'L2 - Mencegah',
        'l3_deteksi_intervensi_manusia' => 'L3 - Mendeteksi & Intervensi Manusia',
        'l4_mitigasi_pasif' => 'L4 - Mitigasi Pasif',
        'l5_deteksi_manual' => 'L5 - Deteksi Manual',
    ],

    'replikasi_satuan' => [
        'Titik/Lokasi',
        'Unit',
        'Sistem',
        'Kegiatan',
        'Pcs',
        'Meter',
    ],

    'evidence' => [
        'disk' => 'local',
        'directory' => 'monitoring-safety-engineering/evidence',
        'max_size_kb' => 10240,
        'allowed_mimes' => [
            'pdf',
            'jpg',
            'jpeg',
            'png',
            'webp',
            'xlsx',
            'xls',
            'doc',
            'docx',
        ],
    ],

    /** @deprecated Legacy dashboard filter — gunakan site/perusahaan di records */
    'bars' => [
        'BAP BMO 3',
        'BAP BMO 1',
        'BAP BMO 2',
    ],

    /** @deprecated Legacy dashboard filter */
    'companies' => [
        '' => 'Semua Perusahaan',
        'pama' => 'PAMA',
        'kpc' => 'KPC',
        'mitra-a' => 'Mitra A',
        'mitra-b' => 'Mitra B',
    ],

    /** @deprecated Legacy dashboard grouping */
    'categories' => [
        'replikasi' => 'Replikasi',
        'safety_engineering' => 'Safety Engineering',
        'additional_safety_engineering' => 'Additional Safety Engineering',
    ],

    'dashboard_categories' => [
        'replikasi' => [
            'sumber_rekayasa' => [
                'replikasi_2026',
            ],
        ],
        'safety_engineering' => [
            'sumber_rekayasa' => [
                'safety_engineering',
            ],
        ],
        'additional_safety_engineering' => [
            'sumber_rekayasa' => [
                'additional_engineering',
            ],
        ],
    ],

    /**
     * Sumber rekayasa yang dihitung pada KPI "Total Pengendalian" di dashboard komitmen.
     * Arahan Manajemen = PMR (lihat outside_commitment_categories.arahan_manajemen).
     */
    'total_pengendalian_sumber_rekayasa' => [
        'safety_engineering',
        'additional_engineering',
        'replikasi_2026',
        'pmr_2023',
        'pmr_2024',
        'pmr_2025',
    ],

    'outside_commitment_categories' => [
        'arahan_manajemen' => [
            'label' => 'Arahan Manajemen',
            'color' => '#1e3a5f',
            'sumber_rekayasa' => [
                'pmr_2023',
                'pmr_2024',
                'pmr_2025',
            ],
        ],
        'rekom_insiden' => [
            'label' => 'Rekom. Insiden',
            'color' => '#0891b2',
            'sumber_rekayasa' => [
                'rekom_insiden',
            ],
        ],
        'rekom_gr' => [
            'label' => 'Rekom Pelanggaran GR',
            'color' => '#b45309',
            'sumber_rekayasa' => [
                'rekom_gr',
            ],
        ],
    ],

    'pmr_evaluation' => [
        'sumber_rekayasa' => [
            'pmr_2023',
            'pmr_2024',
            'pmr_2025',
        ],
        'groups' => [
            'PMR 2023' => ['pmr_2023'],
            'PMR 2024' => ['pmr_2024'],
            'PMR 2025' => ['pmr_2025'],
        ],
        'group_colors' => [
            'PMR 2023' => '#7366FF',
            'PMR 2024' => '#CFC8FF',
            'PMR 2025' => '#51BB25',
        ],
    ],

    'company_overview_sumber_program' => [
        'Komitmen',
        'Di Luar Komitmen',
        'Safety Engineering',
        'Additional Safety Engineering',
        'Rekomendasi Insiden',
        'Arahan Manajemen',
        'Pelanggaran Golden Rules',
    ],

    /**
     * Matriks penurunan risiko: baris dari Deteksi->Intervensi, kolom dari prediksi tangga.
     */
    'risk_reduction_matrix' => [
        'rows' => [
            'eliminasi' => 'Eliminasi',
            'full_automasi' => 'Full Automasi (Deteksi & Intervensi Alat)',
            'hybrid' => 'Hybrid (Alat & Manusia)',
            'manusia' => 'Deteksi & Intervensi Manusia',
            'menahan_mengurangi' => 'Menahan & Mengurangi',
        ],
        'columns' => [
            3 => 'Turun 3 Tangga',
            2 => 'Turun 2 Tangga',
            1 => 'Turun 1 Tangga',
        ],
    ],
];
