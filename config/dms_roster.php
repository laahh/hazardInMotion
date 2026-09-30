<?php

declare(strict_types=1);

/**
 * Parameter Kepatuhan Roster Karyawan (/dms/roster-compliance).
 *
 * Ambang di sini SENGAJA mereplikasi kode referensi safety-roster (bukan label
 * UI-nya) supaya angka dashboard live bisa diadu langsung dengan snapshot
 * statis di /dms/roster-compliance-static untuk validasi. Label vs kode di
 * referensi memang berbeda — lihat docs/safety-roster-perhitungan-rumus.md
 * temuan T-1 (on-site >70 vs kode >71) dan T-2 (cuti <14 vs kode <12).
 *
 * Kalau nanti disepakati memakai ambang regulasi yang sebenarnya, ubah
 * 'onsite' => 70 dan 'cuti_min' => 14 di sini saja; tidak ada ambang yang
 * di-hardcode di service atau view.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Ambang rule
    |--------------------------------------------------------------------------
    | Semua perbandingan ditulis apa adanya seperti di referensi supaya tidak
    | ada off-by-one tersembunyi: nilai di sini adalah angka PEMBANDING, bukan
    | angka pelanggaran. Contoh: kerja_beruntun = 13 berarti "run > 13".
    */
    'ambang' => [
        // Run 'o' (tanpa check-in) LEBIH dari nilai ini dianggap fase Cuti.
        'off_ke_cuti' => 5,

        // REG-1: run kerja (P/M tanpa off) > nilai ini = pelanggaran (>13 → ≥14 hari).
        'kerja_beruntun' => 13,

        // REG-2: run on-site (P/M/o, belum cuti) > nilai ini = pelanggaran.
        'onsite' => 71,

        // REG-3: blok cuti < nilai ini = pelanggaran.
        'cuti_min' => 12,

        // Status "Overshift": sudah bekerja >= nilai ini hari beruntun sampai hari terakhir.
        'overshift' => 8,

        // Kartu "Wajib cuti": on-site BERJALAN > nilai ini per hari terakhir periode.
        'wajib_cuti' => 71,
    ],

    /*
    |--------------------------------------------------------------------------
    | Kategori jabatan
    |--------------------------------------------------------------------------
    | 'longgar' = dibebaskan dari REG-1/2/3 dan MAP-1, persis seperti
    | isLonggar() di referensi.
    */
    'kategori_longgar' => [
        'Operator Transportasi Massal',
        'Mekanik',
    ],

    /*
    |--------------------------------------------------------------------------
    | Populasi
    |--------------------------------------------------------------------------
    | Sumber karyawan aktif = bcsid.bep_vw_safety_karyawan_aktif, BUKAN
    | crontable_bep_vw_m_karyawan_aktif. Cron table punya filter tak
    | terdokumentasi yang diam-diam membuang sebagian karyawan aktif (mis. staf
    | HO) — lihat catatan di app/Models/OhsDashboard/Employee.php.
    |
    | PENTING: view ini rename tipis di atas bep_vw_m_karyawan_aktif yang
    | bersumber dari m_karyawan (6 GB). Menaruh WHERE pada view membuat
    | predikat terdorong sampai ke tabel dasar dan query jadi >30 detik,
    | sedangkan SELECT tanpa WHERE selesai cepat. Karena itu penyaringan
    | jabatan/status dikerjakan di PHP, dan 'regex_jabatan' di bawah adalah
    | badan regex PHP (dipakai preg_match), bukan operator ~ Postgres.
    */
    'populasi' => [
        'view_karyawan' => 'bcsid.bep_vw_safety_karyawan_aktif',
        'regex_jabatan' => '(OPERATOR|DRIVER|MECHANIC|MEKANIK|WELDER|TYREMAN|FITTER)',
        // Kosongkan ('') untuk mematikan filter status yang bersangkutan.
        'status_karyawan' => 'AKTIF',
        // Filter paling menentukan besar populasi: view safety menandai 5.294
        // orang NOT PASSED sementara cron table hanya 1.538. Dengan filter ini
        // populasi roster ±7.900 orang; tanpa filter ini ±11.900.
        'status_permit' => 'PASSED',
        'status_lolos_scan' => 'PASSED',
        // Menit batas Pagi/Malam: check-in pertama < nilai ini = Pagi (720 = 12:00).
        'batas_menit_pagi' => 720,
    ],

    /*
    |--------------------------------------------------------------------------
    | Urutan site di menu & tabel ringkasan
    |--------------------------------------------------------------------------
    */
    'site_order' => ['BMO 1', 'LMO', 'SMO', 'BMO 2', 'BMO 3', 'GMO'],

    /*
    |--------------------------------------------------------------------------
    | Parameter per perusahaan
    |--------------------------------------------------------------------------
    | Key = nama_perusahaan apa adanya dari bcsid.bep_vw_safety_karyawan_aktif.
    | 'thr' hanya dipakai MAP-1: blok roster = thr - 1, flag kuning bila run
    | kerja > (thr - 1). 'map' = 'pama' (wajib off setiap ganti Pagi↔Malam)
    | atau 'block' (tidak boleh Pagi setelah Malam dalam satu run).
    */
    'thr_default' => 7,
    'map_default' => 'block',

    'perusahaan' => [
        'PT Pamapersada Nusantara' => [
            'kode' => 'PAMA',
            'roster' => '10:2 Minggu',
            'shift' => '6:1 6:1',
            'shift_detail' => '6 Pagi - 1 Off 6 Malam - 1 Off',
            'thr' => 7,
            'map' => 'pama',
        ],
        'PT Madhani Talatah Nusantara' => [
            'kode' => 'MTN',
            'roster' => '10:2 Minggu',
            'shift' => '6:6:1',
            'shift_detail' => '6 Siang - 6 Malam - 1 Off',
            'thr' => 13,
            'map' => 'block',
        ],
        'PT Bukit Makmur Mandiri Utama' => [
            'kode' => 'BUMA',
            'roster' => '10:2 Minggu',
            'shift' => '3:3:1',
            'shift_detail' => '3 Siang - 3 Malam - 1 Off',
            'thr' => 7,
            'map' => 'block',
        ],
        'PT Bumi Artlantis Raya' => [
            'kode' => 'BAR',
            'roster' => '70:12 hari',
            'shift' => '3:3:1',
            'shift_detail' => '3 Siang - 3 Malam - 1 Off',
            'thr' => 7,
            'map' => 'block',
        ],
        'PT Kaltim Diamond Coal' => [
            'kode' => 'KDC',
            'roster' => '10:2 Minggu',
            'shift' => '7:6:1',
            'shift_detail' => '7 Siang - 6 Malam - 1 Off',
            'thr' => 14,
            'map' => 'block',
        ],
        'PT Fajar Anugerah Dinamika' => [
            'kode' => 'FAD',
            'roster' => '10:2 Minggu',
            'shift' => '3:3:1',
            'shift_detail' => '3 Siang - 3 Malam - 1 Off',
            'thr' => 7,
            'map' => 'block',
        ],
        'PT Mutiara Tanjung Lestari' => [
            'kode' => 'MTL',
            'roster' => '10:2 Minggu',
            'shift' => '6:6:1',
            'shift_detail' => '6 Siang - 6 Malam - 1 Off',
            'thr' => 13,
            'map' => 'block',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sinkronisasi RFID
    |--------------------------------------------------------------------------
    | bcsid.mv_checkinout_rfid = 4,6 juta baris / 758 MB, terindeks pada
    | kode_sid dan tanggal_checkinout. Aggregate satu tahun penuh memindai
    | hampir seluruh MV (puluhan detik), jadi HANYA dipakai saat backfill.
    | Jadwal rutin cukup menarik beberapa hari terakhir (indeks tanggal
    | terpakai, ±18 ribu baris/hari).
    */
    'sync' => [
        'hari_incremental' => 3,
        'timeout_incremental_ms' => 20000,
        'timeout_backfill_ms' => 20000,
        'chunk_hari_backfill' => 14,
        'chunk_upsert' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache dashboard
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'pola_ttl' => 300,
        'evaluasi_ttl' => 300,
    ],

    'per_page' => 30,
];
