<?php

declare(strict_types=1);

namespace Database\Seeders\OhsDashboard;

use Illuminate\Database\Seeder;

class OhsDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LeaveTypeSeeder::class,
            HolidaySeeder::class,
            EmailSchedulerSettingSeeder::class,
        ]);
    }
}
