<?php

declare(strict_types=1);

return [
    'timezone' => 'Asia/Jakarta',

    'portal_url' => env('PORTAL_URL', rtrim((string) env('APP_URL', ''), '/').'/ohs-dashboard'),

    'filters' => [
        'all_teams' => 'All Teams',
        'all_sites' => 'All Sites',
        'all_departments' => 'All Departments',
        'all_types' => 'All Types',
        'all_status' => 'All Status',
    ],

    'dashboard' => [
        'leaderboard_limit' => 200,
        'upcoming_leave_limit' => 30,
        'email_table_limit' => 100,
        'email_leaderboard_limit' => 10,
        'tracker_page_size' => 10,
    ],

    'scheduler' => [
        'window_minutes' => 75,
        'overdue_hour' => 8,
        'overdue_minute' => 0,
        'overdue_window_days' => 3,
        'hse_sync_weekday' => 1,
        'from_name' => 'OHS Portal Scheduler',
    ],

    'hse' => [
        'api_key' => env('HSE_API_KEY', ''),
        'base' => env('HSE_API_BASE', 'https://hseautomation.beraucoal.co.id'),
        'company_id' => env('HSE_COMPANY_ID', '5194'),
        'company_page_size' => 1000,
        'employee_page_size' => 30000,
        'concurrency' => 8,
        'timeout' => 120,
    ],

    'overdue_reminder_recipients' => [
        'christine@beraucoal.co.id',
        'paian.siregar@beraucoal.co.id',
        'yadi.haryadi@beraucoal.co.id',
        'oscar.whimmy@beraucoal.co.id',
        'yudi@beraucoal.co.id',
        'davi.tantra@beraucoalenergy.co.id',
        'sepriyanto@beraucoal.co.id',
        'dhehave@beraucoal.co.id',
        'budiansyah@beraucoal.co.id',
        'm.firmansyah@beraucoal.co.id',
        'indra.nur@beraucoal.co.id',
        'rahmantha.anggana@beraucoal.co.id',
        'jimmi.idris@beraucoal.co.id',
    ],
];
