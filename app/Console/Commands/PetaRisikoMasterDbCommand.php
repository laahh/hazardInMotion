<?php

namespace App\Console\Commands;

use App\Services\PetaRisiko\Drm\DrmMaster;
use App\Services\PetaRisiko\Drm\DrmSql;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use App\Services\SportEvaluation\RfidClickHouseClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Tabel master mapping DRM 2.1 di database (bisa dikoreksi di /peta-risiko-pja/master).
 *   --isi      isi/timpa semua tabel dari baseline awal: master.json (impor workbook) + regex config
 *              (kelompok aktivitas, tema, kurasi aktivitas OAK) + PJA per lokasi dari obds.aaj_lokasi_dan_pja (ClickHouse).
 *   --isi-oak  isi/timpa HANYA tabel kurasi aktivitas OAK → aktivitas HIRA (drm_master_oak_aktivitas) dari config
 *              oak_aktivitas_hira_usulan; tabel master lain tidak tersentuh.
 *   --turunkan-oak  turunkan kurasi itu menjadi label OAK "aktivitas / sub aktivitas" per baris di drm_master_teks
 *              (Item Sumber OAK master), memakai sub aktivitas aktif dari master OAK (ClickHouse). Kurasi sendiri
 *              tidak dihitung sync; yang dihitung hanya label per baris.
 *   --isi-ikk  isi/timpa HANYA tabel daftar aktivitas/sub aktivitas OAK terkait IKK (drm_master_ikk) dari seed
 *              tetap (DrmMasterDb::DAFTAR_IKK_AWAL); tabel master lain tidak tersentuh. Dikoreksi lebih lanjut
 *              di /peta-risiko-pja/master (tab "Aktivitas IKK").
 * Tanpa opsi: tampilkan status (jumlah baris per tabel, perubahan terakhir).
 * Setelah tabel terisi, peta-risiko:sync memakai database, bukan master.json.
 */
class PetaRisikoMasterDbCommand extends Command
{
    protected $signature = 'peta-risiko:master-db
        {--isi : Isi/timpa semua tabel master dari baseline awal (koreksi manual di database ikut tertimpa)}
        {--isi-oak : Isi/timpa hanya tabel kurasi aktivitas OAK → aktivitas HIRA dari config}
        {--turunkan-oak : Turunkan kurasi aktivitas OAK menjadi label OAK per baris (Item Sumber OAK) di master}
        {--isi-label-baris : Satukan label OBS/OAK/Ketidaksesuaian ke kolom item_sumber_* drm_master_baris (master satu tabel)}
        {--isi-ikk : Isi/timpa hanya tabel daftar aktivitas/sub aktivitas OAK terkait IKK dari seed tetap}
        {--force : Lewati konfirmasi saat menimpa tabel yang sudah berisi}';

    protected $description = 'Status / pengisian awal tabel master mapping Peta Risiko PJA (drm_master_*)';

    private const TABEL = ['drm_master_baris', 'drm_master_kelompok', 'drm_master_aktivitas', 'drm_master_grup', 'drm_master_grup_baris',
        'drm_master_dl', 'drm_master_lokasi_pja', 'drm_master_label', 'drm_master_teks', 'drm_master_tema', 'drm_master_tema_baris', 'drm_master_log'];

    public function handle(DrmMasterDb $db, RfidClickHouseClient $ch): int
    {
        if ($this->option('isi-oak')) {
            return $this->isiOak($db);
        }
        if ($this->option('turunkan-oak')) {
            return $this->turunkanOak($db, $ch);
        }
        if ($this->option('isi-label-baris')) {
            return $this->isiLabelBaris($db);
        }
        if ($this->option('isi-ikk')) {
            return $this->isiIkk($db);
        }
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
            $this->line('Mengambil hierarki PJA (BC/Mitra) dan perusahaan per PJA dari personil…');
            $sql = new DrmSql((array) config('peta_risiko_drm'), now()->toImmutable(), []);
            $pjaList = $ch->select($sql->pja(), 120);
            $perusahaanPerPja = $ch->select($sql->perusahaanPerPja(), 120);
            $this->line('Mengambil PIC resmi per detail lokasi dari sheet "Coverage Area All Site"…');
            $picMentah = $ch->select($sql->picDetailLokasi(), 120);
            $singkatanPerusahaan = $ch->select($sql->singkatanPerusahaan(), 120);
            $jumlah = $db->isi($master, (array) config('peta_risiko_drm'), $lokasiPja, $pjaList, $perusahaanPerPja, $picMentah, $singkatanPerusahaan);
        } catch (Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Tabel master terisi dari baseline awal:');
        $this->table(['Tabel', 'Baris'], array_map(fn ($t, $n) => [$t, number_format($n, 0, ',', '.')], array_keys($jumlah), $jumlah));
        $this->line('Mulai sekarang peta-risiko:sync memakai tabel database. Koreksi di /peta-risiko-pja/master, lalu jalankan peta-risiko:sync.');

        return self::SUCCESS;
    }

    private function isiOak(DrmMasterDb $db): int
    {
        if (! Schema::hasTable(DrmMasterDb::TABEL_OAK_AKTIVITAS)) {
            $this->error('Tabel '.DrmMasterDb::TABEL_OAK_AKTIVITAS.' belum ada. Jalankan migrasi 2026_11_18_100000_create_drm_master_oak_aktivitas_table lebih dulu.');

            return self::FAILURE;
        }
        if (DB::table(DrmMasterDb::TABEL_OAK_AKTIVITAS)->exists() && ! $this->option('force')
            && ! $this->confirm('Tabel kurasi aktivitas OAK sudah berisi. Isinya akan ditimpa dari config. Lanjutkan?')) {
            return self::INVALID;
        }
        try {
            $n = $db->isiOakAktivitas((array) config('peta_risiko_drm'));
        } catch (Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("Tabel kurasi aktivitas OAK terisi: {$n} pasangan aktivitas OAK → aktivitas HIRA.");
        $this->line('Sesuaikan di /peta-risiko-pja/master (tab "Aktivitas OAK → aktivitas HIRA"), lalu jalankan peta-risiko:sync.');

        return self::SUCCESS;
    }

    private function isiIkk(DrmMasterDb $db): int
    {
        if (! Schema::hasTable(DrmMasterDb::TABEL_IKK)) {
            $this->error('Tabel '.DrmMasterDb::TABEL_IKK.' belum ada. Jalankan migrasi 2026_11_25_100000_create_drm_master_ikk_table lebih dulu.');

            return self::FAILURE;
        }
        if (DB::table(DrmMasterDb::TABEL_IKK)->exists() && ! $this->option('force')
            && ! $this->confirm('Tabel daftar IKK sudah berisi. Isinya akan ditimpa dari seed tetap (koreksi manual ikut tertimpa). Lanjutkan?')) {
            return self::INVALID;
        }
        try {
            $n = $db->isiIkk();
        } catch (Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("Tabel daftar IKK terisi: {$n} pasangan aktivitas / sub aktivitas.");
        $this->line('Koreksi di /peta-risiko-pja/master (tab "Aktivitas IKK") kalau ada perubahan; flag IKK di /peta-risiko-pja/master/baris dihitung langsung, tidak perlu sync.');

        return self::SUCCESS;
    }

    private function isiLabelBaris(DrmMasterDb $db): int
    {
        if (! DrmMasterDb::terisi()) {
            $this->error('Tabel master belum berisi. Jalankan peta-risiko:master-db --isi lebih dulu.');

            return self::FAILURE;
        }
        if (DrmMasterDb::labelDiBaris() && ! $this->option('force')
            && ! $this->confirm('Kolom Item Sumber di drm_master_baris sudah berisi. Isinya akan ditimpa dari drm_master_teks / drm_master_label. Lanjutkan?')) {
            return self::INVALID;
        }
        try {
            $j = $db->isiLabelBaris();
        } catch (Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("Master satu tabel: {$j['baris']} baris HIRA · label Observasi {$j['OBS']} · OAK {$j['OAK']} · Ketidaksesuaian {$j['KET']}.");
        $this->line('Mulai sekarang sync membaca label dari kolom item_sumber_* drm_master_baris. Ubah di /peta-risiko-pja/master/baris, lalu jalankan peta-risiko:sync.');

        return self::SUCCESS;
    }

    private function turunkanOak(DrmMasterDb $db, RfidClickHouseClient $ch): int
    {
        if (! DrmMasterDb::terisi()) {
            $this->error('Tabel master belum berisi. Jalankan peta-risiko:master-db --isi lebih dulu (master per baris = acuan utama).');

            return self::FAILURE;
        }
        $cfg = (array) config('peta_risiko_drm');
        $kurasi = DrmMasterDb::oakAktivitas() ?? (array) (($cfg['oak_aktivitas_hira'] ?? []) ?: ($cfg['oak_aktivitas_hira_usulan'] ?? []));
        if ($kurasi === []) {
            $this->warn('Tidak ada kurasi aktivitas OAK (tabel drm_master_oak_aktivitas kosong dan config usulan kosong).');

            return self::SUCCESS;
        }
        try {
            $this->line('Mengambil sub aktivitas aktif dari master OAK (obds.m_oak_activity / m_oak_sub_activity)…');
            $subPerAkt = [];
            foreach ($ch->select('
SELECT a.name AS akt, s.name AS sub
FROM (SELECT toInt64(id) AS id, any(name) AS name, any(id_act_observe) AS ida FROM obds.m_oak_sub_activity FINAL WHERE toString(is_active) = \'1\' GROUP BY id) s
JOIN (SELECT toInt64(id) AS id, any(name) AS name FROM obds.m_oak_activity FINAL WHERE toString(is_active) = \'1\' GROUP BY id) a ON a.id = toInt64(s.ida)
ORDER BY akt, sub', 120) as $r) {
                $subPerAkt[(string) $r['akt']][] = (string) $r['sub'];
            }
            $hasil = $db->turunkanOak($kurasi, $subPerAkt);
        } catch (Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("Label OAK turunan kurasi: {$hasil['label']} label di {$hasil['baris']} baris HIRA (sumber \"".DrmMasterDb::SUMBER_TURUNAN_OAK.'").');
        $this->line('Periksa & hapus yang tidak sesuai di /peta-risiko-pja/master/baris, lalu jalankan peta-risiko:sync.');

        return self::SUCCESS;
    }

    private function status(): int
    {
        $tabel = [...self::TABEL, ...(Schema::hasTable(DrmMasterDb::TABEL_OAK_AKTIVITAS) ? [DrmMasterDb::TABEL_OAK_AKTIVITAS] : []),
            ...(Schema::hasTable(DrmMasterDb::TABEL_IKK) ? [DrmMasterDb::TABEL_IKK] : []),
            ...(Schema::hasTable(DrmMasterDb::TABEL_LOKASI_PIC) ? [DrmMasterDb::TABEL_LOKASI_PIC] : [])];
        $this->table(['Tabel', 'Baris'], array_map(fn ($t) => [$t, number_format(DB::table($t)->count(), 0, ',', '.')], $tabel));
        $terakhir = DB::table('drm_master_log')->orderByDesc('id')->first();
        $this->line(DrmMasterDb::terisi()
            ? 'Sumber sync: DATABASE. Perubahan terakhir: '.($terakhir ? "{$terakhir->dibuat} ({$terakhir->aksi} {$terakhir->tabel}, {$terakhir->user_nama})" : '-')
            : 'Sumber sync: master.json (tabel database masih kosong — isi dengan --isi).');

        return self::SUCCESS;
    }
}
