<?php

declare(strict_types=1);

namespace Database\Seeders\OhsDashboard;

use App\Models\OhsDashboard\EmailSchedulerSetting;
use Illuminate\Database\Seeder;

class EmailSchedulerSettingSeeder extends Seeder
{
    public function run(): void
    {
        if (EmailSchedulerSetting::query()->exists()) {
            return;
        }

        EmailSchedulerSetting::instance();
    }
}
