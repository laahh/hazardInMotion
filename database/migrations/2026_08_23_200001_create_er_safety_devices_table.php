<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_safety_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->uuid('safety_device_type_id')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();

            $table->uuid('site_id')->nullable();
            $table->uuid('location_id')->nullable();
            $table->uuid('area_id')->nullable();
            $table->text('position_detail')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->uuid('department_id')->nullable();

            $table->date('installed_at')->nullable();

            $table->string('condition')->default('baik');
            $table->string('operational_status')->default('available');

            $table->date('last_inspection_at')->nullable();
            $table->date('next_inspection_at')->nullable();
            $table->date('last_calibration_at')->nullable();
            $table->date('next_calibration_at')->nullable();
            $table->date('certificate_expires_at')->nullable();

            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('safety_device_type_id')->references('id')->on('er_safety_device_types')->nullOnDelete();
            $table->foreign('site_id')->references('id')->on('er_sites')->nullOnDelete();
            $table->foreign('location_id')->references('id')->on('er_locations')->nullOnDelete();
            $table->foreign('area_id')->references('id')->on('er_areas')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('er_departments')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('condition');
            $table->index('operational_status');
            $table->index('next_inspection_at');
            $table->index('next_calibration_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_safety_devices');
    }
};
