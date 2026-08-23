<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('employee_number')->unique();
            // Tidak semua personel emergency punya akun login (mis. teknisi vendor, anggota tim
            // lapangan tanpa akses sistem) — user_id nullable, boleh dihubungkan belakangan.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('full_name');
            $table->string('photo_path')->nullable();
            $table->string('position')->nullable();
            $table->uuid('department_id')->nullable();
            $table->uuid('emergency_unit_id')->nullable();
            $table->uuid('site_id')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('employment_status')->default('permanent'); // permanent, contract, vendor
            $table->text('skills')->nullable();
            $table->string('emergency_role')->nullable(); // mis. Fire Warden, First Aider, Team Leader
            $table->boolean('is_active')->default(true);
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('er_departments')->nullOnDelete();
            $table->foreign('emergency_unit_id')->references('id')->on('er_emergency_units')->nullOnDelete();
            $table->foreign('site_id')->references('id')->on('er_sites')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_employees');
    }
};
