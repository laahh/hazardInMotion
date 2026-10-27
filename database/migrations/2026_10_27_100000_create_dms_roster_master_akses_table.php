<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assign Role Master Roster: perusahaan yang boleh di-approve tiap user.
 * Satu baris = satu pasangan (user, perusahaan). User yang punya baris di
 * sini hanya melihat & meng-approve roster perusahaan itu (lihat
 * DmsRosterAksesPerusahaan).
 *
 * Tanpa foreign key ke users: tipe users.id berbeda antar lingkungan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dms_roster_master_akses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('perusahaan', 255);
            $table->string('dibuat_oleh', 150)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'perusahaan'], 'uq_drma_user_pt');
            $table->index('perusahaan', 'idx_drma_pt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dms_roster_master_akses');
    }
};
