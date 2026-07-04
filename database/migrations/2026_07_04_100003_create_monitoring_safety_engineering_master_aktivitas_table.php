<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_safety_engineering_master_aktivitas', function (Blueprint $table): void {
            $table->id();
            $table->string('site', 50);
            $table->string('name', 255);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('site', 'idx_mse_mst_akt_site');
            $table->index('is_active', 'idx_mse_mst_akt_active');
            $table->index('sort_order', 'idx_mse_mst_akt_sort');
            $table->unique(['site', 'name'], 'uniq_mse_mst_akt_site_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_safety_engineering_master_aktivitas');
    }
};
