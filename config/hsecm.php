<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Public base URL untuk link di email (bukan APP_URL lokal)
    |--------------------------------------------------------------------------
    */
    'public_url' => rtrim((string) env('HSECM_PUBLIC_URL', 'https://besentry-dev.beraucoal.co.id'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Nama pengirim email HSECM (From Name)
    |--------------------------------------------------------------------------
    | Terpisah dari MAIL_FROM_NAME global (mis. Auto Banned).
    */
    'mail_from_name' => env('HSECM_MAIL_FROM_NAME', 'Daily Notification'),

    /*
    |--------------------------------------------------------------------------
    | Scheduler (Asia/Makassar) — lihat app/Console/Kernel.php
    |--------------------------------------------------------------------------
    | Midshift 01:00 → batch_slot 00:00 (night)
    | Endshift 07:30 → snapshot latest batch_slot + tasklist (night)
    | Midshift 13:00 → batch_slot 12:00 (day)
    | Endshift 20:30 → snapshot latest batch_slot + tasklist (day)
    | Escalate       → 08 / 14 / 20 / 02
    */

    /*
    |--------------------------------------------------------------------------
    | HSECM WA / Email Recipients
    |--------------------------------------------------------------------------
    | Endshift delivery:
    | - site + perusahaan → email "Akhir Shift — Tasklist Perbaikan & Upload Evidence"
    | - hanya site → 1x "Akhir Shift — Summary" (semua perusahaan di site itu)
    | - tanpa site & tanpa perusahaan → 1x "Akhir Shift — Summary" all site
    | - hanya perusahaan → summary perusahaan (tanpa tasklist token)
    */
    'wa_recipients' => [
        [
            'site' => 'GMO',
            'perusahaan' => 'PT Pamapersada Nusantara',
            'role' => 'PROJECT MANAGER',
            'nama' => 'YOHANES YUDO HARSANTO',
            'no' => '08115900518',
            'email' => 'yohanes.harsanto@pamapersada.com',
        ],
        [
            'site' => 'BMO 1',
            'perusahaan' => 'PT Kaltim Diamond Coal',
            'role' => 'PENANGGUNG JAWAB OPERASIONAL',
            'nama' => 'M HARIS HADIANTO',
            'no' => '081350333006',
            'email' => 'haris_bme@yahoo.com',
        ],
        [
            'site' => 'BMO 1',
            'perusahaan' => 'PT Kaltim Diamond Coal',
            'role' => 'GENERAL MANAGER',
            'nama' => 'JIMMY MART LESTER',
            'no' => '082159245678',
            'email' => 'jimmy.mart.l@kdc.co.id',
        ],
        [
            'site' => 'SMO',
            'perusahaan' => 'PT Madhani Talatah Nusantara',
            'role' => 'PROJECT MANAGER',
            'nama' => 'YUDA ARIANGGA',
            'no' => '08115382450',
            'email' => 'YUDA_ARIANGGA@MADHANI.CO.ID',
        ],
        [
            'site' => 'SMO',
            'perusahaan' => 'PT Madhani Talatah Nusantara',
            'role' => 'PROJECT MANAGER',
            'nama' => 'AFRI EFFENDI',
            'no' => '082155412546',
            'email' => 'afri.effendi@madhani.co.id',
        ],
        [
            'site' => 'SMO',
            'perusahaan' => 'PT Madhani Talatah Nusantara',
            'role' => 'PROJECT MANAGER',
            'nama' => 'AZHAR ABDUL RASDJID',
            'no' => '085250771720',
            'email' => 'admin.059c@madhani.co.id',
        ],
        [
            'site' => 'BMO 3',
            'perusahaan' => 'PT Bumi Artlantis Raya',
            'role' => 'PJO',
            'nama' => 'ZULFIKAR ANSORY',
            'no' => '081347160838',
            'email' => 'fitra.pjo.hseautomation@bumiartlantis.co.id',
        ],
        [
            'site' => 'GMO',
            'perusahaan' => 'PT Kaltim Diamond Coal',
            'role' => 'PENANGGUNG JAWAB OPERASIONAL',
            'nama' => 'AMRY WAHYUDIN',
            'no' => '082350322289',
            'email' => 'amrywahyudin@gmail.com',
        ],
        [
            'site' => null,
            'perusahaan' => 'PT Mutiara Tanjung Lestari',
            'role' => 'PJO',
            'nama' => 'HAMDAN',
            'no' => '08115412188',
            'email' => 'hamdan@beraucoal.co.id',
        ],
        [
            'site' => 'LMO',
            'perusahaan' => 'PT Bukit Makmur Mandiri Utama',
            'role' => 'PROJECT MANAGER',
            'nama' => 'AGUNG INDRIATMOKO',
            'no' => '081247667478',
            'email' => 'indriatmoko.a@gmail.com',
        ],
        [
            'site' => 'LMO',
            'perusahaan' => 'PT Fajar Anugerah Dinamika',
            'role' => 'PROJECT MANAGER',
            'nama' => 'DENNY ROSYADI',
            'no' => '081281070711',
            'email' => 'denny.rosyadi@fad.co.id',
        ],
        [
            'site' => 'BMO 2',
            'perusahaan' => 'PT Pamapersada Nusantara',
            'role' => 'PROJECT MANAGER',
            'nama' => 'B FREDY JUNI PRASETYO',
            'no' => '08115410977',
            'email' => 'fredyjp7@gmail.com',
        ],
        [
            'site' => 'BMO 2',
            'perusahaan' => 'PT Pamapersada Nusantara',
            'role' => 'PROJECT MANAGER',
            'nama' => 'HABUDIN ST',
            'no' => '081325265553',
            'email' => 'habudin@pamapersada.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tasklist Review — mapping nama akun (users.name) → site
    |--------------------------------------------------------------------------
    | User yang terdaftar hanya melihat tasklist site-nya.
    | Admin / user di luar daftar → lihat semua site.
    | Match nama case-insensitive (abaikan spasi berlebih).
    | Opsional: 'sid' untuk catatan identitas (belum dipakai filter DB users).
    */
    'tasklist_reviewers' => [
        [
            'nama' => 'DAVI ADITYA TANTRA',
            'site' => 'GMO',
        ],
        [
            'nama' => 'OSCAR WHIMMY A',
            'site' => 'BMO 1',
        ],
        [
            'nama' => 'PAIAN MHM SIREGAR',
            'site' => 'BMO 1',
        ],
        [
            'nama' => 'WAHYUDI',
            'sid' => 'CPHR5',
            'site' => 'BMO 2',
        ],
        [
            'nama' => 'YADI HARYADI',
            'site' => 'LMO',
        ],
        [
            'nama' => 'SEPRIYANTO',
            'site' => 'BMO 3',
        ],
        [
            'nama' => 'DHEHAVE RIAVIANDHI',
            'site' => 'SMO',
        ],
    ],
];
