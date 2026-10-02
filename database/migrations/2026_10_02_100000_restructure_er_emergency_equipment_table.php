<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyesuaikan struktur emergency equipment dengan format register BA
 * (Berita Acara): perusahaan pemilik, UUID peralatan yang digenerate dari
 * site-kategori-nama-urutan, dan kolom-kolom kondisi/BA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('er_emergency_equipment', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('site_id');
            $table->unsignedInteger('sequence_number')->nullable()->after('code');

            $table->string('registration_number')->nullable()->after('serial_number');
            $table->string('classification')->nullable()->after('registration_number');
            $table->text('equipment_detail')->nullable()->after('classification');

            $table->text('equipment_remarks')->nullable()->after('operational_status');
            $table->text('damage_remarks')->nullable()->after('equipment_remarks');
            $table->string('position_status')->nullable()->after('damage_remarks');

            $table->string('ba_progress')->nullable()->after('position_status');
            $table->text('ba_remarks')->nullable()->after('ba_progress');
            $table->string('item_status')->nullable()->after('ba_remarks');
            $table->date('ba_closed_at')->nullable()->after('item_status');

            $table->foreign('company_id', 'er_eq_company_id_foreign')->references('id')->on('companies')->nullOnDelete();

            $table->index('company_id', 'er_eq_company_id_index');
            $table->index('registration_number', 'er_eq_registration_number_index');
            $table->index('position_status', 'er_eq_position_status_index');
            $table->index('ba_progress', 'er_eq_ba_progress_index');
            $table->index('item_status', 'er_eq_item_status_index');
            $table->index(['site_id', 'equipment_category_id', 'name'], 'er_eq_uuid_group_index');
        });
    }

   
};
