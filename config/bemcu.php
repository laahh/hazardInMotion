<?php

declare(strict_types=1);

/**
 * Konfigurasi akses MCU metabolik via OLAP Postgres (pgsql_ssh / setup-ssh-tunnel.bat).
 * Sumber: bcsid.mv_ftw_mcu — temuan metabolik di JSONB kondisi_kritis / kondisi_non_kritis.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Tabel & identity
    |--------------------------------------------------------------------------
    */
    'table' => env('BEMCU_TABLE', 'mv_ftw_mcu'),

    'exam_date' => env('BEMCU_COL_EXAM_DATE', 'tanggal_mulai'),

    'identity' => [
        'sid' => env('BEMCU_COL_SID', 'kode_sid'),
        'nik' => env('BEMCU_COL_NIK', ''),
        'gender' => env('BEMCU_COL_GENDER', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Field JSONB yang menyimpan daftar kondisi MCU
    |--------------------------------------------------------------------------
    */
    'json_fields' => [
        'kondisi_kritis',
        'kondisi_non_kritis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapping lab → nama_kondisi di JSON (exact match, case-insensitive)
    |--------------------------------------------------------------------------
    | Bukan kolom numerik: flag abnormal dari is_yes / note=abnormal.
    */
    'labs' => [
        'glucose' => [
            'Gula Darah Puasa (GDP) tinggi',
            'Terkonfirmasi Diabetes Militus',
        ],
        'cholesterol' => [
            'Kolestrol Total',
            'LDL',
        ],
        'triglyceride' => [
            'Trigliserida',
        ],
        'uric_acid' => [
            'Asam Urat',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nama kondisi yang selalu dianggap severity high jika positif
    |--------------------------------------------------------------------------
    */
    'high_severity_conditions' => [
        'Terkonfirmasi Diabetes Militus',
        'Gula Darah Puasa (GDP) tinggi',
        'Memiliki Sindrome Metabolik',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ambang numerik (fallback; mv_ftw_mcu memakai flag JSON)
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'glucose_fpg_warn' => (float) env('BEMCU_TH_GLUCOSE_WARN', 100),
        'glucose_fpg_high' => (float) env('BEMCU_TH_GLUCOSE_HIGH', 126),
        'chol_warn' => (float) env('BEMCU_TH_CHOL_WARN', 200),
        'chol_high' => (float) env('BEMCU_TH_CHOL_HIGH', 240),
        'trig_warn' => (float) env('BEMCU_TH_TRIG_WARN', 150),
        'trig_high' => (float) env('BEMCU_TH_TRIG_HIGH', 200),
        'uric_male' => (float) env('BEMCU_TH_URIC_MALE', 7.0),
        'uric_female' => (float) env('BEMCU_TH_URIC_FEMALE', 6.0),
    ],

    'purine_keywords' => [
        'jeroan', 'hati', 'limpa', 'usus', 'babat', 'otak',
        'udang', 'kerang', 'cumi', 'kepiting', 'sarden', 'ikan asin',
        'seafood', 'anchovy', 'teri',
    ],

    'discovery_keywords' => [
        'glucose' => ['gula', 'glukosa', 'glucose', 'gdp', 'diabetes'],
        'cholesterol' => ['kolesterol', 'kolestrol', 'cholesterol', 'ldl'],
        'triglyceride' => ['trigliserida', 'triglyceride', 'trig'],
        'uric_acid' => ['asam urat', 'asam_urat', 'uric'],
        'sid' => ['kode_sid', 'sid'],
        'nik' => ['nik', 'no_ktp', 'ktp'],
        'exam_date' => ['tanggal_mulai', 'tanggal', 'exam_date', 'mcu_date'],
        'gender' => ['gender', 'jenis_kelamin', 'sex', 'jk'],
    ],
];
