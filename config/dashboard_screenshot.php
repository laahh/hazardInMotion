<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard URL untuk screenshot
    |--------------------------------------------------------------------------
    */
    'url' => env('DASHBOARD_SCREENSHOT_URL', 'https://besentry.beraucoal.co.id/dopmikk/dopm/dashboard'),

    /*
    |--------------------------------------------------------------------------
    | Token rahasia untuk URL screenshot (tanpa login)
    |--------------------------------------------------------------------------
    | Set di .env (DASHBOARD_SCREENSHOT_TOKEN). Jika diisi, gunakan URL:
    | {app_url}/dopmikk/dopm/dashboard/screenshot?token={token}
    | agar Browsershot mendapat halaman dashboard, bukan halaman login.
    */
    'token' => env('DASHBOARD_SCREENSHOT_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | User ID yang dipakai untuk render dashboard saat akses via token
    |--------------------------------------------------------------------------
    */
    'user_id' => (int) env('DASHBOARD_SCREENSHOT_USER_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | Email penerima (pisahkan dengan koma di .env)
    |--------------------------------------------------------------------------
    */
    'emails' => array_filter(array_map('trim', explode(',', env('DASHBOARD_SCREENSHOT_EMAILS', '')))),

    /*
    |--------------------------------------------------------------------------
    | Direktori penyimpanan sementara screenshot
    |--------------------------------------------------------------------------
    */
    'storage_path' => storage_path('app/dashboard-screenshots'),

    /*
    |--------------------------------------------------------------------------
    | Timeout Browsershot (detik)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('DASHBOARD_SCREENSHOT_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Lebar viewport (px)
    |--------------------------------------------------------------------------
    */
    'width' => (int) env('DASHBOARD_SCREENSHOT_WIDTH', 1920),

    /*
    |--------------------------------------------------------------------------
    | Tinggi viewport (px) - full page akan diambil jika perlu
    |--------------------------------------------------------------------------
    */
    'height' => (int) env('DASHBOARD_SCREENSHOT_HEIGHT', 1080),

];
