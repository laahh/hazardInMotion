<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_token_cctv' => env('TELEGRAM_BOT_CCTV'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],
    'ffmpeg' => [
        'path' => 'C:/ffmpeg/bin/ffmpeg.exe',
    ],

    'clickhouse_custom' => [
        'host' => env('CLICKHOUSE_CUSTOM_HOST', '10.10.10.38'),
        'port' => env('CLICKHOUSE_CUSTOM_PORT', 8123),
        'username' => env('CLICKHOUSE_CUSTOM_USERNAME', 'default'),
        'password' => env('CLICKHOUSE_CUSTOM_PASSWORD', 'Zxcdsaqwe321:;'),
        'timeout' => (int) env('CLICKHOUSE_CUSTOM_TIMEOUT', 60),
    ],

    'fonnte' => [
        'token' => env('FONNTE_API_TOKEN', ''),
    ],

    'wwebjs' => [
        'url' => env('WWEBJS_URL', 'http://localhost:3001'),
        'api_key' => env('WWEBJS_API_KEY', 'wa-service-secret-key'),
        'timeout' => (int) env('WWEBJS_TIMEOUT', 30),
    ],

    'whatsapp' => [
        'default_provider' => env('WHATSAPP_PROVIDER', 'fonnte'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Evaluasi Well — HSE Employee API (sync append-only)
    |--------------------------------------------------------------------------
    */
    'evaluasi_well_hse' => [
        'base_url' => env('EVALUASI_WELL_HSE_BASE_URL', 'https://hseautomation.beraucoal.co.id'),
        'login_path' => env('EVALUASI_WELL_HSE_LOGIN_PATH', '/beats/api/mobile/login'),
        'username' => env('EVALUASI_WELL_HSE_USERNAME'),
        'password' => env('EVALUASI_WELL_HSE_PASSWORD'),
        'company_page_size' => (int) env('EVALUASI_WELL_HSE_COMPANY_SIZE', 1000),
        'employee_page_size' => (int) env('EVALUASI_WELL_HSE_EMPLOYEE_SIZE', 30000),
        'timeout' => (int) env('EVALUASI_WELL_HSE_TIMEOUT', 120),
        'detail_delay_ms' => (int) env('EVALUASI_WELL_HSE_DETAIL_DELAY_MS', 50),
    ],

];
