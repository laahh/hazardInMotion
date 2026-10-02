<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyelaraskan kolom validasi_tbc dengan header sumber:
     * Tasklist, TobeConcernedHazard, GR, Catatan, Blindspot terlapor BC,
     * No Item PSPP, Kategori GR, SID Pekerja Terlibat, Nama (pelaku/pelanggar).
     */
    public function up(): void
    {
        Schema::table('validasi_tbc', function (Blueprint $table) {
            $table->dropIndex('validasi_tbc_validator_index');
            $table->dropIndex('validasi_tbc_gr_pspp_index');
        });

        Schema::table('validasi_tbc', function (Blueprint $table) {
            $table->dropColumn([
                'validator',
                'kategori_gr_valid_kpi',
                'pic_aktual',
                'kronologi_singkat',
                'rootcause_aktual',
                'detail_rootcause_aktual',
                'tindakan_perbaikan_aktual',
            ]);
        });

        Schema::table('validasi_tbc', function (Blueprint $table) {
            $table->renameColumn('gr_pspp', 'gr');
        });

        Schema::table('validasi_tbc', function (Blueprint $table) {
            /** Urutan fisik kolom mengikuti urutan header sumber. */
            $table->text('blindspot_terlapor_bc')->nullable()->after('catatan')->change();
            $table->string('sid_pekerja_terlibat', 255)->nullable()->after('kategori_gr');
            $table->string('nama_pekerja_terlibat', 255)->nullable()->after('sid_pekerja_terlibat');

            $table->index('gr', 'validasi_tbc_gr_index');
            $table->index('sid_pekerja_terlibat', 'validasi_tbc_sid_index');
        });
    }

    public function down(): void
    {
        Schema::table('validasi_tbc', function (Blueprint $table) {
            $table->dropIndex('validasi_tbc_gr_index');
            $table->dropIndex('validasi_tbc_sid_index');
            $table->dropColumn(['sid_pekerja_terlibat', 'nama_pekerja_terlibat']);
        });

        Schema::table('validasi_tbc', function (Blueprint $table) {
            $table->renameColumn('gr', 'gr_pspp');
        });

        Schema::table('validasi_tbc', function (Blueprint $table) {
            $table->string('validator', 255)->nullable()->after('id');
            $table->string('kategori_gr_valid_kpi', 255)->nullable()->after('kategori_gr');
            $table->text('blindspot_terlapor_bc')->nullable()->after('kategori_gr_valid_kpi')->change();
            $table->string('pic_aktual', 500)->nullable()->after('blindspot_terlapor_bc');
            $table->text('kronologi_singkat')->nullable()->after('pic_aktual');
            $table->text('rootcause_aktual')->nullable()->after('kronologi_singkat');
            $table->text('detail_rootcause_aktual')->nullable()->after('rootcause_aktual');
            $table->text('tindakan_perbaikan_aktual')->nullable()->after('detail_rootcause_aktual');

            $table->index('validator', 'validasi_tbc_validator_index');
            $table->index('gr_pspp', 'validasi_tbc_gr_pspp_index');
        });
    }
};
