<?php

declare(strict_types=1);

namespace Database\Seeders\OhsDashboard;

use App\Models\OhsDashboard\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            '2025-01-01' => 'Tahun Baru Masehi',
            '2025-01-27' => 'Isra Mikraj',
            '2025-01-29' => 'Tahun Baru Imlek',
            '2025-03-29' => 'Hari Raya Nyepi',
            '2025-03-31' => 'Hari Raya Idulfitri',
            '2025-04-01' => 'Hari Raya Idulfitri',
            '2025-04-18' => 'Wafat Isa Almasih',
            '2025-05-01' => 'Hari Buruh Internasional',
            '2025-05-12' => 'Hari Raya Waisak',
            '2025-05-29' => 'Kenaikan Isa Almasih',
            '2025-06-01' => 'Hari Lahir Pancasila',
            '2025-06-06' => 'Hari Raya Iduladha',
            '2025-06-27' => 'Tahun Baru Islam',
            '2025-08-17' => 'Hari Kemerdekaan RI',
            '2025-09-05' => 'Maulid Nabi Muhammad SAW',
            '2025-12-25' => 'Hari Raya Natal',
            '2026-01-01' => 'Tahun Baru Masehi',
            '2026-01-16' => 'Isra Mikraj',
            '2026-02-17' => 'Tahun Baru Imlek',
            '2026-03-19' => 'Hari Raya Nyepi',
            '2026-03-21' => 'Hari Raya Idulfitri',
            '2026-03-22' => 'Hari Raya Idulfitri',
            '2026-04-03' => 'Wafat Isa Almasih',
            '2026-05-01' => 'Hari Buruh Internasional',
            '2026-05-14' => 'Kenaikan Isa Almasih',
            '2026-05-27' => 'Hari Raya Iduladha',
            '2026-05-31' => 'Hari Raya Waisak',
            '2026-06-01' => 'Hari Lahir Pancasila',
            '2026-06-16' => 'Tahun Baru Islam',
            '2026-08-17' => 'Hari Kemerdekaan RI',
            '2026-08-25' => 'Maulid Nabi Muhammad SAW',
            '2026-12-25' => 'Hari Raya Natal',
        ];

        foreach ($holidays as $date => $name) {
            Holiday::query()->firstOrCreate(
                ['date' => $date],
                ['name' => $name],
            );
        }
    }
}
