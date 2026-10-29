<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Departemen OHS Divisi
    |--------------------------------------------------------------------------
    |
    | Angka & daftar Check-in RFID di halaman Monitoring OHS Divisi hanya
    | menghitung karyawan yang kolom `departement`-nya (view karyawan aktif,
    | dms_roster.populasi.view_karyawan) sama dengan salah satu nama di bawah.
    | Dicocokkan tanpa membedakan huruf besar/kecil & spasi di ujung; ejaan
    | lain tetap harus ditulis persis (mis. "ASSSURANCE" memang tiga S di data).
    |
    */
    'departemen' => [
        'HEALTH & SAFETY PERFORMANCE ASSSURANCE',
        'EMERGENCY RESPONSES & SAFETY SERVICES DEPARTMENT',
        'OPERATIONS COMPLIANCE DEPARTMENT',
        'SAFETY OPERATIONS DEPARTMENT',
        'SDCER',
    ],

    // Lama daftar SID per departemen disimpan (detik).
    'cache_detik' => 6 * 3600,

    // Batas waktu query ke view karyawan aktif (ms).
    'timeout_ms' => 20000,
];
