<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_safety_engineering_pic_assignment_scopes', function (Blueprint $table) {
            $table->id();
            // Kolom & constraint diberi nama pendek: nama default Laravel
            // (nama tabel + nama kolom + "_foreign") melewati batas 64 karakter MySQL.
            $table->unsignedBigInteger('assignment_id');
            $table->string('perusahaan', 255);
            $table->string('site', 100);
            $table->timestamps();

            $table->foreign('assignment_id', 'fk_mse_pic_scope_assignment')
                ->references('id')
                ->on('monitoring_safety_engineering_pic_assignments')
                ->cascadeOnDelete();

            $table->unique(['assignment_id', 'perusahaan', 'site'], 'uq_mse_pic_scope_company_site');
            $table->index(['perusahaan', 'site'], 'idx_mse_pic_scope_company_site');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_safety_engineering_pic_assignment_scopes');
    }
};
