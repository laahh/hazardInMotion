<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_response_personnel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('incident_id');
            $table->uuid('response_unit_id')->nullable();
            // Referensi ke users.id untuk saat ini (personel yang punya akun login).
            // Fase 7 (Manpower) akan menambah kolom employee_id opsional utk personel tanpa akun.
            $table->unsignedBigInteger('user_id');
            $table->string('role_in_response')->nullable(); // mis. "Team Leader", "Medic"
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('er_incidents')->cascadeOnDelete();
            $table->foreign('response_unit_id')->references('id')->on('er_response_units')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_response_personnel');
    }
};
