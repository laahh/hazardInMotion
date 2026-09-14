<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Main Data IKK untuk PNC Monitoring. TIDAK dieksekusi otomatis —
 * jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_ikk_records', function (Blueprint $table) {
            $table->id();
            $table->string('upsert_key', 191);
            $table->string('jenis', 100)->nullable();
            $table->string('nomor', 100);
            $table->string('pekerjaan', 255)->nullable();
            $table->date('tanggal')->nullable();
            $table->unsignedTinyInteger('minggu')->nullable();
            $table->unsignedTinyInteger('bulan')->nullable();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->string('site', 100)->nullable();
            $table->string('mine_contractor', 150)->nullable();
            $table->string('perusahaan', 150)->nullable();
            $table->unsignedInteger('finding_ia')->default(0);
            $table->unsignedInteger('finding_verlap')->default(0);
            $table->unsignedTinyInteger('ia')->nullable();
            $table->unsignedTinyInteger('ipk')->nullable();
            $table->unsignedInteger('plan_okk')->default(0);
            $table->unsignedInteger('okk_1')->default(0);
            $table->unsignedInteger('okk_2')->default(0);
            $table->unsignedInteger('okk_3')->default(0);
            $table->unsignedInteger('okk_layer_2')->default(0);
            $table->unsignedInteger('okk_layer_3')->default(0);
            $table->unsignedInteger('okk_layer_4')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique('upsert_key', 'pnc_monitoring_ikk_records_upsert_key_unique');
            $table->index(['tahun', 'minggu'], 'idx_pnc_ikk_tahun_minggu');
            $table->index('site', 'idx_pnc_ikk_site');
            $table->index('perusahaan', 'idx_pnc_ikk_perusahaan');
            $table->index('jenis', 'idx_pnc_ikk_jenis');
            $table->index('tanggal', 'idx_pnc_ikk_tanggal');
            $table->index('nomor', 'idx_pnc_ikk_nomor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_ikk_records');
    }
};
