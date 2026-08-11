<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluasi_well_mitra_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('site', 100);
            $table->string('perusahaan', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('user_id', 'uq_evaluasi_well_mitra_user');
            $table->index(['site', 'perusahaan'], 'idx_evaluasi_well_mitra_site_perusahaan');
            $table->index('is_active', 'idx_evaluasi_well_mitra_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluasi_well_mitra_assignments');
    }
};
