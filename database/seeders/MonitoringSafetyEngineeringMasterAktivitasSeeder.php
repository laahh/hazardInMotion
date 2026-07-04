<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MonitoringSafetyEngineeringMasterAktivitas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class MonitoringSafetyEngineeringMasterAktivitasSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('monitoring_safety_engineering_master_aktivitas')) {
            return;
        }

        if (MonitoringSafetyEngineeringMasterAktivitas::query()->exists()) {
            return;
        }

        $defaults = [
            'BMO' => [
                'Hauling',
                'Loading',
                'Drilling',
                'Blasting',
                'Support',
                'Workshop',
            ],
            'GMO' => [
                'Plant Operation',
                'Maintenance',
                'Stockpile',
                'Fuel Management',
            ],
        ];

        $sortOrder = 1;

        foreach ($defaults as $site => $aktivitasList) {
            foreach ($aktivitasList as $name) {
                MonitoringSafetyEngineeringMasterAktivitas::query()->create([
                    'site' => $site,
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }
    }
}
