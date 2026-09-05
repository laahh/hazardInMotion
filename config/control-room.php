<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Konfigurasi modul Control Room (Dashboard Monitoring Pengawasan OCR)
|--------------------------------------------------------------------------
|
| Lihat plan-OCR.md untuk konteks lengkap. Nilai site/shift di sini adalah
| PLACEHOLDER — belum dikonfirmasi user (Lampiran D #17-#19 di plan-OCR.md).
| sap_sources.*.view masih menunggu verifikasi T0.1 (apakah mv_inspeksi_hazard
| dkk benar ada di Postgres bcbeats) — lihat 0.5 poin 2 di plan-OCR.md.
|
*/

return [

    'sites' => [
        // TODO: konfirmasi daftar site final ke user. Placeholder di bawah
        // memakai 13 site yang terdeteksi di temuan #5 plan-OCR.md.
        'HO' => ['name' => 'Head Office', 'source_key' => 'HO', 'timezone' => 'Asia/Makassar'],
        'BMO1' => ['name' => 'BMO 1', 'source_key' => 'BMO 1', 'timezone' => 'Asia/Makassar'],
        'BMO2' => ['name' => 'BMO 2', 'source_key' => 'BMO 2', 'timezone' => 'Asia/Makassar'],
        'BMO3' => ['name' => 'BMO 3', 'source_key' => 'BMO 3', 'timezone' => 'Asia/Makassar'],
        'GMO' => ['name' => 'GMO', 'source_key' => 'GMO', 'timezone' => 'Asia/Makassar'],
        'LMO' => ['name' => 'LMO', 'source_key' => 'LMO', 'timezone' => 'Asia/Makassar'],
        'PMO' => ['name' => 'PMO', 'source_key' => 'PMO', 'timezone' => 'Asia/Makassar'],
        'SMO' => ['name' => 'SMO', 'source_key' => 'SMO', 'timezone' => 'Asia/Makassar'],
        'MARINE' => ['name' => 'Marine', 'source_key' => 'MARINE', 'timezone' => 'Asia/Makassar'],
        'EKSPLORASI' => ['name' => 'Eksplorasi', 'source_key' => 'EKSPLORASI', 'timezone' => 'Asia/Makassar'],
        'JAKARTA' => ['name' => 'Jakarta', 'source_key' => 'JAKARTA', 'timezone' => 'Asia/Jakarta'],
    ],

    // TODO [Lampiran D #17]: jam mulai/selesai shift sebenarnya belum dikonfirmasi user.
    // Placeholder di bawah pakai asumsi umum tambang (06:00-18:00 / 18:00-06:00).
    'shifts' => [
        'S1' => ['name' => 'Shift 1', 'start' => '06:00', 'end' => '18:00', 'crosses_midnight' => false],
        'S2' => ['name' => 'Shift 2', 'start' => '18:00', 'end' => '06:00', 'crosses_midnight' => true],
    ],

    // TODO [Lampiran D #18]: H+1 dihitung dari tanggal pengawasan atau akhir shift?
    // Placeholder di bawah: akhir shift + 24 jam (asumsi awal plan-OCR.md T2.1).
    'submission_window_hours' => 24,

    /*
    | sap_sources — pemetaan 4 objek sumber SAP ke komponen metrik.
    | STATUS: 'view' di bawah BELUM TERVERIFIKASI (T0.1 tidak bisa dijalankan
    | dari environment ini — lihat plan-OCR.md 0.5 poin 2). Kalau T0.1
    | membuktikan mv_* tidak ada, ganti 'view' ke fallback bcbeats.car_register
    | dan sesuaikan SapNormalizer terkait.
    */
    'sap_sources' => [
        'inspeksi_hazard' => [
            'view' => 'bcbeats.mv_inspeksi_hazard',
            'components' => ['hazard', 'inspeksi'],
            'counts_tbc' => true,
            'is_bonus' => false,
        ],
        'observasi' => [
            'view' => 'bcbeats.mv_observasi',
            'components' => ['observasi'],
            'counts_tbc' => false,
            'is_bonus' => false,
        ],
        'oak' => [
            'view' => 'bcbeats.mv_oak',
            'components' => ['observasi'],
            'counts_tbc' => false,
            'is_bonus' => false,
        ],
        'coaching' => [
            'view' => 'bcbeats.mv_coaching',
            'components' => [],
            'counts_tbc' => false,
            'is_bonus' => true,
        ],
    ],

    'sap_target_components' => ['hazard', 'inspeksi', 'observasi'],

    // TODO [Lampiran D #22]: bobot area kritis x2 dari mana kalau bcbeats.m_lokasi
    // ternyata tidak punya flag kritis (lihat plan-OCR.md 0.5 poin 4, pertanyaan #26).
    'coverage_weight' => ['normal' => 1, 'critical' => 2],

    /*
    | Google Sheet tasklist TBC (lihat plan-OCR.md T1.5).
    | BELUM DIISI — menunggu Sheet ID/gid dari pemilik sheet (Lampiran D #23).
    | Jangan hardcode ID di sini sampai dikonfirmasi; GSheetTbcReader akan
    | menolak berjalan (exception jelas) selama nilai ini kosong.
    */
    'gsheet_tbc' => [
        'sheet_id' => env('CONTROL_ROOM_GSHEET_TBC_ID'),
        'gid' => env('CONTROL_ROOM_GSHEET_TBC_GID', '0'),
    ],
];
