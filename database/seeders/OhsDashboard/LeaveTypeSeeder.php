<?php

declare(strict_types=1);

namespace Database\Seeders\OhsDashboard;

use App\Models\OhsDashboard\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['leave_type' => 'Cuti Tahunan', 'available_days' => '12'],
            ['leave_type' => 'Cuti Sakit', 'available_days' => ''],
            ['leave_type' => 'Izin', 'available_days' => ''],
            ['leave_type' => 'Cuti Melahirkan', 'available_days' => '90'],
            ['leave_type' => 'Cuti Khusus', 'available_days' => ''],
            ['leave_type' => 'Unpaid Leave', 'available_days' => ''],
        ];

        foreach ($types as $type) {
            LeaveType::query()->firstOrCreate(
                ['leave_type' => $type['leave_type']],
                ['available_days' => $type['available_days']],
            );
        }
    }
}
