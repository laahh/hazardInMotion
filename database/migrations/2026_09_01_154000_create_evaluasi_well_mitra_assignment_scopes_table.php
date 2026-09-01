<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluasi_well_mitra_assignment_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluasi_well_mitra_assignment_id')
                ->constrained('evaluasi_well_mitra_assignments')
                ->cascadeOnDelete();
            $table->string('perusahaan', 255);
            $table->string('site', 100);
            $table->timestamps();

            $table->unique(
                ['evaluasi_well_mitra_assignment_id', 'perusahaan', 'site'],
                'uq_ew_mitra_asgn_scope_company_site'
            );
            $table->index(['perusahaan', 'site'], 'idx_ew_mitra_asgn_scope_company_site');
        });

        $now = now();
        $rows = DB::table('evaluasi_well_mitra_assignments')
            ->get(['id', 'site', 'perusahaan', 'created_at', 'updated_at']);

        foreach ($rows as $row) {
            $site = trim((string) $row->site);
            $perusahaan = trim((string) $row->perusahaan);
            if ($site === '' || $perusahaan === '') {
                continue;
            }

            DB::table('evaluasi_well_mitra_assignment_scopes')->insert([
                'evaluasi_well_mitra_assignment_id' => (int) $row->id,
                'perusahaan' => $perusahaan,
                'site' => $site,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluasi_well_mitra_assignment_scopes');
    }
};
