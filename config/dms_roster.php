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
    /*
    | Master Roster (Overview cakupan & template roster): jabatan yang tidak
    | dihitung sebagai karyawan aktif. Dicocokkan tanpa membedakan huruf besar
    | kecil ("VISITOR" = "Visitor"). Lihat DmsRosterKaryawanAktif.
    */
    'master' => [
        'jabatan_dikecualikan' => ['VISITOR'],
    ],

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
        // Master karyawan: satu baris per id, sudah diringkas di SQL.
        'view_karyawan' => 'bcsid.bep_vw_wp_karyawan',
        'status_karyawan' => 'AKTIF',

        // Jendela 3 hari, bukan 1 — sekaligus menambal scan yang datang
        // terlambat tanpa perlu menarik ulang rentang panjang.
        'hari_incremental' => 3,

        // Berapa hari terakhir TIDAK ikut dikompilasi ke pola. Hari berjalan
        // scan-nya baru terkumpul sebagian, jadi kalau ikut dihitung seisi
        // populasi akan terbaca "Off". 1 = pola berhenti di kemarin.
        'lag_hari' => 1,

        'timeout_master_ms' => 30000,
        'timeout_incremental_ms' => 20000,
        'timeout_backfill_ms' => 20000,

        // Backfill dipecah 14 hari per query supaya indeks tanggal terpakai.
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

    /*
    |--------------------------------------------------------------------------
    | Kartu REG-1/2/3 per hari ini (DmsRosterRegLiveService)
    |--------------------------------------------------------------------------
    | Query langsung ke bcsid.mv_checkinout_rfid, tidak lewat dms_roster_pola.
    | jendela_hari harus cukup panjang untuk menemukan cuti sebelum masa
    | on-site berjalan; run yang lebih panjang dari jendela ditandai "≥".
    */
    'reg_live' => [
        'jendela_hari' => 180,
        // > 60 menit: hasil yang disimpan command terjadwal dms:sync-roster-rfid
        // (tiap 1 jam) tetap dipakai sampai putaran berikutnya selesai.
        'cache_ttl' => 4500,
        'timeout_ms' => 20000,
        // Salinan lengkap karyawan aktif (Tahap 8, CLI): view-nya bisa > 30 dtk,
        // jadi batasnya lebih longgar dari query halaman (timeout_cari_ms).
        'timeout_lengkap_ms' => 180000,
        // Jendela dipecah per N hari: satu query 180 hari bisa melewati
        // statement_timeout saat server sibuk; potongan 30 hari ±0,5 dtk.
        'potongan_hari' => 30,
        // Memori kerja per query agregasi (bawaan server 4 MB → tumpah ke disk).
        'work_mem' => '64MB',

        // Batasi kartu aturan banned ke kode PT tertentu (dms_roster_karyawan.kode_pt).
        // Kosong = SEMUA perusahaan (permintaan user 2026-10-26; sebelumnya hanya
        // tujuh PT ber-desain roster: PAMA, BUMA, FAD, KDC, MTN, MTL, BAR).
        // PT di luar blok 'perusahaan' dinilai mapping dengan thr_default/map_default.
        'kode_pt' => [],

        // Karyawan yang punya kasus AKTIF di sid_roster_banned_master (belum
        // di-unban) tidak dimunculkan lagi. Dicocokkan lewat SID/NIK. Kasus
        // yang sudah di-unban tidak menghalangi: orangnya boleh di-banned lagi.
        'kecualikan_master_banned' => true,

        // Status sid_roster_unban_log yang menandakan kasus banned SELESAI.
        // SKIPPED = "BAN SID sudah tidak ada di Work Permit (dianggap sudah
        // ter-unban)". FAILED/PROCESSING sengaja tidak termasuk.
        'status_unban_selesai' => ['SUCCESS', 'SKIPPED'],

        // Peringatan dini, belum melanggar: periode kerja hari ke-onsite_dari
        // s/d onsite_maks (60–70) dan kerja beruntun hari ke-beruntun_dari
        // s/d beruntun_maks (10–13). Tiap putaran dms:sync-roster-rfid
        // menyimpannya ke dms_roster_peringatan (satu baris per aturan per SID
        // per hari) sebagai sumber notifikasi.
        'peringatan' => [
            'onsite_dari' => 60,
            'beruntun_dari' => 10,
            'simpan' => (bool) env('DMS_PERINGATAN_SIMPAN', true),
        ],

        // Penyisipan otomatis "Pelanggaran periode kerja > 70 hari" (baru di hari ke-74, aturan.onsite_banned_hari_ke) ke
        // sid_roster_banned_master (menu Master Roster Banned), dijalankan
        // command dms:sync-roster-rfid tiap 30 menit.
        //
        // PERHATIAN: master banned dibaca modul AutoBanned yang mengeksekusi
        // banned SID sungguhan di work permit. Karena itu MATI secara bawaan —
        // pratinjau dulu dengan `php artisan dms:roster-auto-banned --uji`,
        // lalu nyalakan dengan DMS_AUTO_BANNED=true di .env.
        'auto_banned' => [
            'aktif' => (bool) env('DMS_AUTO_BANNED', false),
            // Sama dengan isi baris yang sudah ada di master, supaya
            // penyaringan/otomasi yang bergantung pada teks ini tetap jalan.
            'alasan' => 'BAN SID - Kepatuhan Roster Karyawan',
            'created_by' => 'Sistem (RFID otomatis)',

            // Ikut masukkan "Bekerja > 13 hari berturut-turut" (tanggal
            // pelanggaran = hari ke-14). Saklar terpisah & MATI bawaan: begitu
            // dinyalakan, putaran sync berikutnya membuat banned SID sungguhan.
            // Pratinjau: php artisan dms:roster-auto-banned --beruntun
            'beruntun' => (bool) env('DMS_AUTO_BANNED_BERUNTUN', false),
            // Teks alasan sengaja sama dengan aturan > 70 hari (lihat catatan
            // 'alasan' di atas); ganti bila modul AutoBanned tidak bergantung pada teksnya.
            'alasan_beruntun' => 'BAN SID - Kepatuhan Roster Karyawan',
        ],

        // Tabel "Penegakan Aturan & Alur Banned/Unban". Angka di sini angka
        // ATURAN (bukan replikasi kode referensi seperti blok 'ambang' di
        // atas), jadi on-site memakai > 70, bukan > 71.
        'aturan' => [
            // Periode kerja > 70 hari → banned 14 hari (cuti 14 hari), jatuh
            // di hari ke-74 (1 hari perjalanan keluar mess + 1 hari induksi).
            'onsite_maks' => 70,
            'onsite_banned_hari_ke' => 74,
            'onsite_banned_lama' => 14,

            // Bekerja > 13 hari berturut-turut → banned 1 hari off di hari ke-14.
            'beruntun_maks' => 13,
            'beruntun_banned_hari_ke' => 14,
            // Lama banned yang ditulis ke sid_roster_banned_master.lama_banned_hari.
            'beruntun_banned_lama' => 1,
            // Hari ke-14 yang total check-in → check-out-nya <= N menit dianggap
            // datang sebentar: tidak dihitung kerja, run tidak putus, hari
            // kerja berikutnya jadi hari ke-14 (DmsRosterHariSingkat).
            'beruntun_hari_singkat_menit' => 120,

            // Waktu kerja (check-in → check-out gate) maksimal 12 jam → highlight.
            // Dinilai pada shift lengkap terakhir yang check-in-nya dalam
            // N hari terakhir; jumlah shift > 12 jam dihitung dalam jendela.
            'jam_kerja_maks' => 12,
            'jam_kerja_hari_terkini' => 3,
            'jam_kerja_jendela' => 7,
        ],
    ],

    'per_page' => 30,

    // Halaman Ringkasan Roster memakai angka lebih kecil: tiap baris
    // scorecard membawa panel collapse berisi heatmap setahun (±280 sel),
    // jadi 30 baris sekaligus akan menggelembungkan HTML hasil render.
    'overview_per_page' => 10,

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

    /*
    |--------------------------------------------------------------------------
    | Long Shift pengawas (DmsLongShiftService) — docs/overshift.md
    |--------------------------------------------------------------------------
    | Long Shift = kelanjutan ke shift berikutnya: satu blok kehadiran RFID
    | (sesi IN→OUT yang jedanya <= jeda_maks_menit) hadir >= min_shift_menit di
    | shift terjadwal DAN >= min_lanjut_menit di 12 jam sesudahnya.
    |
    | Jadwal shift per orang TIDAK tersedia di database, jadi shift terjadwal
    | diturunkan dari RFID: shift 12 jam (mulai shift_mulai_jam dan +12 jam)
    | PALING AWAL yang terisi >= min_shift_menit oleh blok tersebut.
    |
    | Baseline = karyawan level jabatan PENGAWAS berstatus AKTIF di
    | view_karyawan. Dihitung oleh dms:sync-roster-rfid (Tahap 7) lalu dibaca
    | halaman dari cache — request web di server tidak bisa ke OLAP.
    */
    'long_shift' => [
        'view_karyawan' => 'bcsid.bep_sid_karyawan',
        'level_jabatan' => 'PENGAWAS',
        'status_karyawan' => 'AKTIF',
        // Shift yang dinilai: yang mulai dalam N hari terakhir dan sudah selesai.
        'jendela_hari' => 7,
        'shift_mulai_jam' => 7,          // Shift 1 07:00–19:00, Shift 2 19:00–07:00
        'duplikat_menit' => 2,           // duplicate_window
        'jeda_maks_menit' => 60,         // max_continuity_gap
        'pasangan_maks_jam' => 36,       // max_valid_pair
        'min_shift_menit' => 360,        // min_shift_minutes
        'min_lanjut_menit' => 30,        // min_next_shift_minutes
        // > 60 menit: hasil command terjadwal (tiap jam) dipakai sampai putaran berikutnya.
        'cache_ttl' => 4500,
        'timeout_ms' => 20000,
    ],

    /*
    | Plan Kesesuaian Roster (tab ketiga Scorecard Ringkasan Roster).
    |
    | Plan roster yang DISETUJUI (Master Roster) disambung riwayat tap RFID ke
    | belakang, lalu dinilai dengan aturan banned reg_live.aturan (> 70 hari
    | on-site, > 13 hari kerja berturut-turut): sudah melanggar, akan melanggar
    | menurut plan (beserta tanggalnya), aman, atau plan segera habis. Hari
    | lampau juga dicocokkan plan vs tap (kepatuhan). Dihitung dms:sync-roster-rfid
    | (Tahap 9) — hanya tampilan, tidak memicu banned.
    |
    | Kode plan → deret: D1–D13 / DS = P, N1–N7 / NS = M, OFF/S/I = off, CT = cuti. Off
    | > ambang.off_ke_cuti hari berturut tetap dianggap cuti, sama dengan RFID.
    */
    'plan_kesesuaian' => [
        // Plan yang berakhir dalam N hari dari hari ini = perlu plan lanjutan.
        'segera_habis_hari' => 7,
        // Timeline per karyawan mundur sampai awal masa on-site berjalan,
        // minimal timeline_mundur_hari, maksimal timeline_mundur_maks hari.
        'timeline_mundur_hari' => 21,
        'timeline_mundur_maks' => 90,
        // Aturan jam kerja tab ini: tap IN→OUT (hari lampau, dalam rentang
        // timeline) dan jam kerja plan (hari kerja ke depan) > N jam = melanggar.
        // Sengaja terpisah dari reg_live.aturan.jam_kerja_maks (12) kartu live.
        'jam_kerja_maks' => 9,
        'cache_ttl' => 4500,
    ],
];
