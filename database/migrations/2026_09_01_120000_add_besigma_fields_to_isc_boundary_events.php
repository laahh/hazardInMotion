<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('isc_boundary_events', function (Blueprint $table): void {
            $table->string('entity', 16)->default('person')->after('person_key')->index();
            $table->string('besigma_violation_id', 64)->nullable()->unique();
            $table->string('user_id', 64)->nullable()->index();
            $table->string('unit_id', 64)->nullable()->index();
            $table->string('hazard_kind', 48)->nullable()->index();
            $table->string('besigma_status', 24)->nullable();
            $table->index(['status', 'entity'], 'idx_isc_events_status_entity');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE isc_boundary_events MODIFY lat DECIMAL(10, 7) NULL');
            DB::statement('ALTER TABLE isc_boundary_events MODIFY lng DECIMAL(10, 7) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE isc_boundary_events SET lat = 0 WHERE lat IS NULL");
            DB::statement("UPDATE isc_boundary_events SET lng = 0 WHERE lng IS NULL");
            DB::statement('ALTER TABLE isc_boundary_events MODIFY lat DECIMAL(10, 7) NOT NULL');
            DB::statement('ALTER TABLE isc_boundary_events MODIFY lng DECIMAL(10, 7) NOT NULL');
        }

        Schema::table('isc_boundary_events', function (Blueprint $table): void {
            $table->dropIndex('idx_isc_events_status_entity');
            $table->dropUnique(['besigma_violation_id']);
            $table->dropIndex(['entity']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['unit_id']);
            $table->dropIndex(['hazard_kind']);
            $table->dropColumn([
                'entity',
                'besigma_violation_id',
                'user_id',
                'unit_id',
                'hazard_kind',
                'besigma_status',
            ]);
        });
    }
};
