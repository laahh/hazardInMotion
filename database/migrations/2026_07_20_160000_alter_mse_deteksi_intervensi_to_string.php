<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('monitoring_safety_engineering_records')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE monitoring_safety_engineering_records MODIFY deteksi_deviasi VARCHAR(80) NULL');
            DB::statement('ALTER TABLE monitoring_safety_engineering_records MODIFY intervensi_deviasi VARCHAR(80) NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE monitoring_safety_engineering_records ALTER COLUMN deteksi_deviasi TYPE VARCHAR(80) USING deteksi_deviasi::text');
            DB::statement('ALTER TABLE monitoring_safety_engineering_records ALTER COLUMN intervensi_deviasi TYPE VARCHAR(80)');

            return;
        }

        // SQLite / lainnya: recreate via temporary approach tidak dilakukan otomatis.
        // Pastikan environment production memakai MySQL.
    }

    public function down(): void
    {
        if (! Schema::hasTable('monitoring_safety_engineering_records')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Nilai non-numerik akan menjadi 0 saat cast balik.
            DB::statement('ALTER TABLE monitoring_safety_engineering_records MODIFY deteksi_deviasi INT UNSIGNED NULL');
            DB::statement('ALTER TABLE monitoring_safety_engineering_records MODIFY intervensi_deviasi VARCHAR(30) NULL');
        }
    }
};
