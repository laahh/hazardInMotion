<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membangun ulang `er_emergency_equipment` secara utuh setelah tabelnya
 * terhapus manual dari database.
 *
 * Tiga migrasi sebelumnya (create -> add_location_and_area_name -> restructure)
 * sudah tercatat selesai di tabel `migrations`, jadi tidak akan jalan lagi dan
 * tabelnya tidak ikut terbangun. Migrasi ini mengisi lubang itu: strukturnya
 * sama persis dengan hasil akhir ketiga migrasi tersebut, termasuk nama foreign
 * key dan index-nya, supaya database yang dibangun ulang lewat migrasi ini
 * identik dengan database hasil install dari nol.
 *
 * Pada install dari nol tabelnya sudah ada ketika migrasi ini dijalankan,
 * sehingga `up()` sengaja tidak melakukan apa pun.
 */
return new class extends Migration
{
    private const TABLE = 'er_emergency_equipment';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            // Identitas. `code` menyimpan UUID peralatan yang digenerate dari
            // site-kategori-nama-urutan; `sequence_number` adalah urutannya.
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->string('name');
            $table->uuid('equipment_category_id')->nullable();
            $table->string('type_model')->nullable();
            $table->string('brand')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('classification')->nullable();
            $table->text('equipment_detail')->nullable();

            // Lokasi & kepemilikan.
            $table->uuid('site_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->uuid('location_id')->nullable();
            $table->string('location_name')->nullable();
            $table->uuid('area_id')->nullable();
            $table->string('area_name')->nullable();
            $table->text('position_detail')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->uuid('department_id')->nullable();
            $table->uuid('emergency_unit_id')->nullable();

            $table->date('purchased_at')->nullable();
            $table->date('commissioned_at')->nullable();

            // Kondisi & status barang.
            $table->string('condition')->default('baik'); // baik, perlu_perbaikan, rusak, maintenance, tidak_aktif
            $table->string('operational_status')->default('available'); // available, in_use, maintenance, out_of_service
            $table->text('equipment_remarks')->nullable();
            $table->text('damage_remarks')->nullable();
            $table->string('position_status')->nullable(); // di_tempat, dipindahkan, dibawa_keluar, hilang

            // Berita Acara.
            $table->string('ba_progress')->nullable(); // belum_ada, draft, proses_ttd, selesai
            $table->text('ba_remarks')->nullable();
            $table->string('item_status')->nullable(); // aktif, proses_perbaikan, proses_penggantian, dihapuskan
            $table->date('ba_closed_at')->nullable();

            // Inspeksi, kalibrasi & sertifikasi.
            $table->date('last_inspection_at')->nullable();
            $table->date('next_inspection_at')->nullable();
            $table->date('last_calibration_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('certificate_number')->nullable();
            $table->date('certificate_expires_at')->nullable();

            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('equipment_category_id')->references('id')->on('er_equipment_categories')->nullOnDelete();
            $table->foreign('site_id')->references('id')->on('er_sites')->nullOnDelete();
            $table->foreign('location_id')->references('id')->on('er_locations')->nullOnDelete();
            $table->foreign('area_id')->references('id')->on('er_areas')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('er_departments')->nullOnDelete();
            $table->foreign('emergency_unit_id')->references('id')->on('er_emergency_units')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('condition');
            $table->index('operational_status');
            $table->index('next_inspection_at');
            $table->index('certificate_expires_at');

            // Nama eksplisit, sama seperti di migrasi restructure, karena nama
            // bawaan Laravel untuk tabel ini menembus batas 64 karakter MySQL.
            $table->foreign('company_id', 'er_eq_company_id_foreign')->references('id')->on('companies')->nullOnDelete();
            $table->index('company_id', 'er_eq_company_id_index');
            $table->index('registration_number', 'er_eq_registration_number_index');
            $table->index('position_status', 'er_eq_position_status_index');
            $table->index('ba_progress', 'er_eq_ba_progress_index');
            $table->index('item_status', 'er_eq_item_status_index');
            $table->index(['site_id', 'equipment_category_id', 'name'], 'er_eq_uuid_group_index');
        });
    }

    /**
     * Sengaja tidak menghapus tabel: yang berhak melakukannya adalah `down()`
     * milik migrasi create aslinya. Kalau migrasi ini ikut men-drop tabel,
     * rollback pada database hasil install dari nol akan menghapus tabel yang
     * bukan dibuat olehnya.
     */
    public function down(): void
    {
        //
    }
};
