<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_emergency_equipment', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->uuid('equipment_category_id')->nullable();
            $table->string('type_model')->nullable();
            $table->string('brand')->nullable();
            $table->string('serial_number')->nullable();

            $table->uuid('site_id')->nullable();
            $table->uuid('location_id')->nullable();
            $table->uuid('area_id')->nullable();
            $table->text('position_detail')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->uuid('department_id')->nullable();
            $table->uuid('emergency_unit_id')->nullable();

            $table->date('purchased_at')->nullable();
            $table->date('commissioned_at')->nullable();

            $table->string('condition')->default('baik'); // baik, perlu_perbaikan, rusak, maintenance, tidak_aktif
            $table->string('operational_status')->default('available'); // available, in_use, maintenance, out_of_service

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_emergency_equipment');
    }
};
