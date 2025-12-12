<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oak_tabel', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('mobile_uuid', 100)->nullable();

            $table->string('activity', 255)->nullable();
            $table->string('sub_activity', 255)->nullable();
            $table->string('material', 255)->nullable();
            $table->string('tool_type', 255)->nullable();
            $table->string('conveyance_type', 255)->nullable();
            $table->string('lifting_equipment', 255)->nullable();

            $table->string('site', 255)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('detail_location', 255)->nullable();
            $table->text('location_description')->nullable();

            $table->string('shift', 100)->nullable();
            $table->string('conclusion', 255)->nullable();

            $table->string('company_submit_by', 255)->nullable();
            $table->string('kode_sid_pelapor', 100)->nullable();
            $table->string('kode_sid', 100)->nullable();
            $table->string('submit_by', 255)->nullable();
            $table->unsignedBigInteger('submit_id')->nullable();
            $table->string('jabatan_fungsional_submiter', 255)->nullable();

            $table->timestamp('submit_date')->nullable();

            $table->unsignedBigInteger('sib_register')->nullable();
            $table->string('code_sib', 100)->nullable();

            $table->string('tools_observasi', 255)->nullable();

            $table->unsignedBigInteger('id_employee_team')->nullable();
            $table->string('nama_team', 255)->nullable();
            $table->string('kode_sid_team', 100)->nullable();
            $table->string('jabatan_fungsional_team', 255)->nullable();
            $table->string('tipe', 50)->nullable();

            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();

            $table->string('file_foto', 500)->nullable();

            $table->string('platform', 50)->nullable();
            $table->smallInteger('is_be_draft')->nullable();
            $table->timestamp('bedraft_date')->nullable();

            $table->string('versi_apk', 50)->nullable();
            $table->string('apk', 50)->nullable();
            $table->string('method', 50)->nullable();

            $table->string('url_photo', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oak_tabel');
    }
};


