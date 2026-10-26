<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanda tangan digital approver Master Roster: file gambar (disk local,
 * roster-master/{id}/ttd-approval.png|jpg) dan asalnya — digambar di canvas
 * (signature_pad) atau di-upload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dms_roster_master_upload', function (Blueprint $table) {
            if (! Schema::hasColumn('dms_roster_master_upload', 'ttd_path')) {
                $table->string('ttd_path', 500)->nullable()->after('catatan_review');
            }
            if (! Schema::hasColumn('dms_roster_master_upload', 'ttd_sumber')) {
                $table->string('ttd_sumber', 10)->nullable()->after('ttd_path');   // GAMBAR | UPLOAD
            }
        });
    }

    public function down(): void
    {
        Schema::table('dms_roster_master_upload', function (Blueprint $table) {
            foreach (['ttd_sumber', 'ttd_path'] as $kolom) {
                if (Schema::hasColumn('dms_roster_master_upload', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
