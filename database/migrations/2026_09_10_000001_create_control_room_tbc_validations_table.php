<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Validasi TBC Control Room. TIDAK dieksekusi otomatis — jalankan
 * `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_room_tbc_validations', function (Blueprint $table) {
            $table->id();
            $table->string('no_alert', 50)->nullable();
            $table->string('validator', 255)->nullable();
            $table->string('tasklist', 191);
            $table->string('to_be_concerned_hazard', 255)->nullable();
            $table->string('gr', 255)->nullable();
            $table->text('catatan')->nullable();
            $table->string('nomor_gr_valid', 255)->nullable();
            $table->string('kategori_gr_valid_kpi', 255)->nullable();
            $table->text('blindspot_terlapor_bc')->nullable();
            $table->text('kronologi_singkat')->nullable();
            $table->string('rootcause_aktual', 255)->nullable();
            $table->text('detail_rootcause_aktual')->nullable();
            $table->string('sid_pekerja_terlibat', 255)->nullable();
            $table->string('sid_pengawas_aktual', 255)->nullable();
            $table->text('tindakan_perbaikan_aktual')->nullable();
            $table->string('no_item_pspp', 255)->nullable();
            $table->string('kategori_gr', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique('tasklist', 'control_room_tbc_validations_tasklist_unique');
            $table->index('validator', 'control_room_tbc_validations_validator_index');
            $table->index('to_be_concerned_hazard', 'control_room_tbc_validations_tbc_hazard_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_room_tbc_validations');
    }
};
