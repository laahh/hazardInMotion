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

    /*
    |--------------------------------------------------------------------------
    | Total karyawan untuk kartu ringkasan di halaman Ringkasan Roster
    |--------------------------------------------------------------------------
    | Angka ini TIDAK memakai populasi rule engine di atas. Ia memakai definisi
    | terpisah yang disepakati untuk validasi SIMPER vs Working Permit unit:
    | karyawan AKTIF di bcsid.bep_vw_wp_karyawan yang jabatan strukturalnya
    | ada di daftar putih di bawah.
    |
    | Daftar jabatan sengaja berupa WHITELIST string persis (bukan regex),
    | mengikuti query yang diberikan — jabatan dengan ejaan berbeda tidak
    | ikut terhitung. Perbandingan dilakukan setelah UPPER + TRIM.
    */
    'total_karyawan' => [
        // id_work_permit unit: Operator/Driver A2B (34, 638), Hauler
        // (35, 648, 2522), Angkutan Massal (161, 2520).
        'wp_unit_ids' => [34, 638, 35, 648, 2522, 161, 2520],

        /*
        | Pengelompokan karyawan memakai Working Permit unit, bukan tebakan
        | dari nama jabatan. Label diambil apa adanya dari kolom work_permit:
        |   34   TENAGA TEKNIS - OPERATOR / DRIVER - A2B
        |   638  TENAGA TEKNIS - OPERATOR / DRIVER - A2B - CPP to PORT
        |   35   TENAGA TEKNIS - OPERATOR / DRIVER - HAULER
        |   648  TENAGA TEKNIS - OPERATOR / DRIVER - HAULER - CPP to PORT
        |   2522 TENAGA TEKNIS - OPERATOR ELECTRICAL HEAVY EQUIPMENT - HAULER
        |   161  TENAGA TEKNIS - OPERATOR / DRIVER - SARANA ANGKUTAN MASSAL
        |   2520 TENAGA TEKNIS - DRIVER ELECTRICAL VEHICLE - SARANA ANGKUTAN MASSAL
        |
        | PENTING: 442 orang memegang A2B DAN Hauler sekaligus, dan 1.086 orang
        | ber-SIMPER aktif tidak punya WP unit sama sekali. Karena itu tiap
        | orang dimasukkan ke SATU kelompok mengikuti 'wp_prioritas' di bawah,
        | dan kelompok 'tanpa' tetap ditampilkan supaya jumlah batang chart
        | selalu sama dengan angka kartu Total Karyawan.
        */
        'wp_grup' => [
            'a2b' => ['label' => 'A2B', 'ids' => [34, 638]],
            'hauler' => ['label' => 'Hauler', 'ids' => [35, 648, 2522]],
            'massal' => ['label' => 'Angkutan Massal', 'ids' => [161, 2520]],
        ],

        // Urutan menang saat satu orang memegang lebih dari satu WP unit.
        'wp_prioritas' => ['a2b', 'hauler', 'massal'],

        'wp_grup_tanpa_label' => 'Tanpa WP unit',

        // SIMPER aktif = id_status_sid_dokumen 1; tipe F, P, T, L1, L2.
        'simper_status_aktif' => 1,
        'simper_tipe_ids' => [370, 415, 1795, 1809, 75933],

        'cache_ttl' => 600,
        'timeout_ms' => 20000,

        'jabatan' => [
            'BASIC OPERATOR', 'DRIVER - FUEL TRUCK', 'DRIVER - LUBE TRUCK', 'DRIVER - PLANT SERVICES',
            'FITTER - TYRE', 'MECHANIC', 'MECHANIC - ANCILLARY EQUIPMENT', 'MECHANIC - BIG DIGGERS REPAIR',
            'MECHANIC - BIG DIGGERS SHUTDOWN', 'MECHANIC - COAL HAULERS', 'MECHANIC - COAL HAULERS REPAIR',
            'MECHANIC - COAL HAULERS WORKSHOP', 'MECHANIC - COAL TRANSPORT REPAIR',
            'MECHANIC - COAL TRANSPORT WORKSHOP', 'MECHANIC - DOZER & GRADER', 'MECHANIC - DRILLS & PUMPS',
            'MECHANIC - INSPECTOR', 'MECHANIC - INTERNAL REPAIR', 'MECHANIC - LOADER REPAIR',
            'MECHANIC - OB HAULERS FIELD', 'MECHANIC - OB HAULERS PIT STOP', 'MECHANIC - OB HAULERS PIT STOP BIN',
            'MECHANIC - OB HAULERS WORKSHOP', 'MECHANIC - OB LOADER REPAIR', 'MECHANIC - OB LOADER SHUTDOWN',
            'MECHANIC - SMALL LOADER', 'OPERATOR', 'OPERATOR - LIFTING EQUIPMENT', 'OPERATOR - PRODUCTION',
            'OPERATOR WELDING TRUCK', 'SISWA OPERATOR', 'TYREMAN - REPAIR', 'WELDER', 'DRIVER DT',
            'DRIVER FUEL TRUCK', 'DRIVER LUBE DAN CRANE TRUCK', 'DRIVER WT', 'JR MEKANIK', 'MEKANIK',
            'OPERATOR COMPACTOR', 'OPERATOR DOZER', 'OPERATOR EXCAVATOR', 'OPERATOR MOTOR GRADER',
            'DRIVER DT HINO', 'DRIVER SUPPORT', 'OPERATOR A2B', 'OPERATOR CRANE TRUCK', 'OPERATOR DRILLING',
            'OPERATOR EXCA', 'OPERATOR GRADER', 'OPERATOR HD', 'OPERATOR OHT 777', 'OPERATOR PC', 'DRIVER FUEL',
            'DRIVER LUBE TRUCK', 'DRIVER TRUCK SERVICE', 'DRIVER WATER TRUCK', 'FUEL TRUCK DRIVER',
            'MECHANIC A2B', 'OPERATOR CRANE', 'OPERATOR DRILL', 'OPERATOR DT', 'OPT WHEEL LOADER', 'OPT. ADT',
            'OPT. BULLDOZER', 'OPT. EXCAVATOR', 'OPT. GRADER', 'OPT. OHT', 'OPT. WATER TRUCK OHT',
            'SENIOR MECHANIC', 'SENIOR MECHANIC A2B', 'ACT MECHANIC FOREMAN', 'DRIVER', 'DRIVER ADT 40T',
            'DRIVER ANFO TRUCK', 'DRIVER CRANE TRUCK MAINTENANCE', 'DRIVER DT 30T', 'DRIVER DT 40T',
            'DRIVER LIGHT VEHICLE', 'DRIVER LOWBOY', 'DRIVER LV', 'DRIVER MANHAUL', 'DRIVER RDT 100T',
            'DRIVER RDT 60T', 'DRIVER SERVICE TRUCK', 'DRIVER WASHING TRUCK', 'DRIVER WT 30KL', 'DRIVER WT 50KL',
            'FTO OHT 777D', 'MECHANIC 1-4A', 'MECHANIC 2-3A', 'MECHANIC 2-3B', 'MECHANIC 2-3C', 'MECHANIC 3-2A',
            'MECHANIC 3-2B', 'MECHANIC FOREMAN', 'MECHANIC SUPERVISOR', 'MECHANIC TRAINER',
            'MECHANIC TRAINER SUPERVISOR', 'OPERATOR BULLDOZER 200HP', 'OPERATOR BULLDOZER 300HP',
            'OPERATOR COMPACTOR 10T', 'OPERATOR CRANE 60T', 'OPERATOR DOZER 200HP', 'OPERATOR DOZER 250HP',
            'OPERATOR DOZER 300HP', 'OPERATOR DOZER 400HP', 'OPERATOR DRILLING DM45/D245',
            'OPERATOR DRILLING MD 6290', 'OPERATOR DRILLING T45/T50', 'OPERATOR EXCAVATOR 120T',
            'OPERATOR EXCAVATOR 200T', 'OPERATOR EXCAVATOR 20T', 'OPERATOR EXCAVATOR 250T',
            'OPERATOR EXCAVATOR 30T', 'OPERATOR EXCAVATOR 350T', 'OPERATOR EXCAVATOR 40T',
            'OPERATOR EXCAVATOR 80T', 'OPERATOR GRADER 14FT', 'OPERATOR GRADER 16FT', 'OPERATOR HD 465',
            'OPERATOR LEGRA PUMP', 'OPERATOR LOADER 30T', 'OPERATOR TRAINER', 'OPERATOR TRAINER JR',
            'OPERATOR TRAINING SUPERVISOR', 'OPERATOR WHELLOADER', 'OPT FT', 'TRAINER OPERATOR', 'TYREMAN 2-3C',
            'WELDER 1-4A', 'BULLDOZER OPERATOR', 'DOUBLE TRAILER OPERATOR', 'DRIVER DUMP TRUCK', 'DRIVER LT/WT',
            'EXCAVATOR OPERATOR', 'MEKANIK WHEEL PS & BACKLOG',
            'OPERATOR BACK HOE LOADER / EXCAVATOR 200 / WT / ELF', 'OPERATOR BOMAG', 'OPERATOR BULLDOZER 85',
            'OPERATOR CHIP SEAL / ELF / LV', 'OPERATOR CHIPSELER', 'OPERATOR DOUBLE TRAILER',
            'OPERATOR EXCAVATOR 200', 'OPERATOR EXCAVATOR 200 / 400', 'OPERATOR GRADER 511 / 705',
            'OPERATOR WHEEL LOADER 500', 'SUPPORT OPERATOR - DUMP TRUCK', 'TRACK SHUTDOWN MECHANIC',
            'WHEEL LOADER OPERATOR', 'BIG DIGGER EQUIP. MECHANIC', 'COAL HAULER EQUIP. MECHANIC',
            'DRILLING EQUIP. MECHANIC', 'OPERATOR LD/DT', 'OPERATOR TP', 'SITE SUPPORT EQUIP. MECHANIC',
            'TRACK TYPE MECHANIC', 'TYRE REPAIR MAN', 'WHEEL TYPE MECHANIC',
        ],
    ],
];
