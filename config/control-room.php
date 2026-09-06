<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Konfigurasi modul Control Room (Dashboard Monitoring Pengawasan OCR)
|--------------------------------------------------------------------------
|
| Lihat plan-OCR.md untuk konteks lengkap. Nilai site/shift di sini adalah
| PLACEHOLDER — belum dikonfirmasi user (Lampiran D #17-#19 di plan-OCR.md).
| sap_sources.*.view SUDAH TERVERIFIKASI via T0.1 2026-09-06 (lihat plan-OCR.md
| 0.6) — mv_inspeksi_hazard/mv_observasi/mv_oak/mv_coaching benar ada sebagai
| materialized view di Postgres bcbeats.
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
    | STATUS: 'view' di bawah SUDAH TERVERIFIKASI via T0.1 2026-09-06
    | (plan-OCR.md 0.6) — keempatnya materialized view valid (ispopulated=true),
    | struktur kolom & volume terkonfirmasi lewat query langsung.
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

    'coverage_weight' => ['normal' => 1, 'critical' => 2],

    /*
    | Keyword penentu "area kritis" — bcbeats terbukti tidak punya kolom flag
    | kritis (lihat plan-OCR.md 0.5 poin 7, pertanyaan #22/#26). User memberikan
    | rumus Tableau existing: CONTAINS([Lokasi],"Kritis") OR CONTAINS([Lokasi],"Risk")
    | OR CONTAINS([Detil Lokasi], <keyword area spesifik>) — divalidasi terhadap
    | data nyata 2026-09-06 (1.482/8.684 baris di bep_vw_site_lokasi_detil_lokasi
    | cocok). Dipakai di App\Services\ControlRoom\Reference\LocationReader::isCritical()
    | sebagai string CONTAINS (case-insensitive), bukan query DB tambahan.
    */
    'critical_area_keywords' => [
        'lokasi' => ['kritis', 'risk'],
        'detil_lokasi' => [
            'eksplorasi',
            'survey',
            'area pengeboran',
            'area kritis',
            'workshop dbm',
            'workshop kemakmuran',
        ],
    ],

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
