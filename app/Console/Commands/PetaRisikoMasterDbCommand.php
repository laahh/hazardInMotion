<?php

namespace App\Console\Commands;

use App\Services\PetaRisiko\Drm\DrmMaster;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use App\Services\SportEvaluation\RfidClickHouseClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tabel master mapping DRM 2.1 di database (bisa dikoreksi di /peta-risiko-pja/master).
 *   --isi    isi/timpa semua tabel dari baseline awal: master.json (impor workbook) + regex config
 *            (kelompok aktivitas, tema) + PJA per lokasi dari obds.aaj_lokasi_dan_pja (ClickHouse).
 * Tanpa opsi: tampilkan status (jumlah baris per tabel, perubahan terakhir).
 * Setelah tabel terisi, peta-risiko:sync memakai database, bukan master.json.
 */
class PetaRisikoMasterDbCommand extends Command
{
    protected $signature = 'peta-risiko:master-db
        {--isi : Isi/timpa semua tabel master dari baseline awal (koreksi manual di database ikut tertimpa)}
        {--force : Lewati konfirmasi saat menimpa tabel yang sudah berisi}';

    protected $description = 'Status / pengisian awal tabel master mapping Peta Risiko PJA (drm_master_*)';

    private const TABEL = ['drm_master_baris', 'drm_master_kelompok', 'drm_master_aktivitas', 'drm_master_grup', 'drm_master_grup_baris',
        'drm_master_dl', 'drm_master_lokasi_pja', 'drm_master_label', 'drm_master_teks', 'drm_master_tema', 'drm_master_tema_baris', 'drm_master_log'];

    public function handle(DrmMasterDb $db, RfidClickHouseClient $ch): int
    {
        if (! DrmMasterDb::siap()) {
            $this->error('Tabel master belum ada. Jalankan migrasi 2026_11_17_100000_create_drm_master_tables lebih dulu.');

            return self::FAILURE;
        }
        if (! $this->option('isi')) {
            return $this->status();
        }
        if (DrmMasterDb::terisi() && ! $this->option('force')
            && ! $this->confirm('Tabel master sudah berisi. Semua koreksi manual akan tertimpa baseline awal. Lanjutkan?')) {
            return self::INVALID;
        }

        try {
            $master = DrmMaster::muatJson();
            $this->line('Mengambil PJA per lokasi dari obds.aaj_lokasi_dan_pja…');
            $lokasiPja = array_map(fn ($r) => [(string) $r['site'], (string) $r['lokasi'], (string) $r['pja']], $ch->select('
SELECT trim(site) AS site, trim(lokasi) AS lokasi, trim(nama_pja) AS pja
FROM obds.aaj_lokasi_dan_pja
WHERE ifNull(nama_pja, \'\') != \'\' AND ifNull(site, \'\') != \'\' AND ifNull(lokasi, \'\') != \'\'
GROUP BY site, lokasi, pja
ORDER BY site, lokasi, pja', 120));
            $jumlah = $db->isi($master, (array) config('peta_risiko_drm'), $lokasiPja);
        } catch (Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Tabel master terisi dari baseline awal:');
        $this->table(['Tabel', 'Baris'], array_map(fn ($t, $n) => [$t, number_format($n, 0, ',', '.')], array_keys($jumlah), $jumlah));
        $this->line('Mulai sekarang peta-risiko:sync memakai tabel database. Koreksi di /peta-risiko-pja/master, lalu jalankan peta-risiko:sync.');

        return self::SUCCESS;
    }

    private function status(): int
    {
        $this->table(['Tabel', 'Baris'], array_map(fn ($t) => [$t, number_format(DB::table($t)->count(), 0, ',', '.')], self::TABEL));
        $terakhir = DB::table('drm_master_log')->orderByDesc('id')->first();
        $this->line(DrmMasterDb::terisi()
            ? 'Sumber sync: DATABASE. Perubahan terakhir: '.($terakhir ? "{$terakhir->dibuat} ({$terakhir->aksi} {$terakhir->tabel}, {$terakhir->user_nama})" : '-')
            : 'Sumber sync: master.json (tabel database masih kosong — isi dengan --isi).');

        return self::SUCCESS;
    }
}
