<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmMaster;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use App\Services\PetaRisiko\Drm\Master\DrmMasterSkema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kurasi aktivitas OAK → aktivitas HIRA di database (drm_master_oak_aktivitas). Test ini SELALU memakai SQLite memori
 * di koneksi tersendiri, tidak pernah database dari .env (database bersama).
 */
class DrmMasterOakAktivitasTest extends TestCase
{
    private string $koneksiAsal;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite tidak tersedia.');
        }
        $this->koneksiAsal = (string) config('database.default');
        config(['database.connections.uji_drm' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('uji_drm');
        (require base_path('database/migrations/2026_11_17_100000_create_drm_master_tables.php'))->up();
        (require base_path('database/migrations/2026_11_18_100000_create_drm_master_oak_aktivitas_table.php'))->up();
        (require base_path('database/migrations/2026_11_18_110000_add_sumber_to_drm_master_teks_table.php'))->up();
        DrmMasterDb::lupakanCacheOak();
    }

    protected function tearDown(): void
    {
        DB::purge('uji_drm');
        DB::setDefaultConnection($this->koneksiAsal);
        DrmMasterDb::lupakanCacheOak();
        parent::tearDown();
    }

    public function test_kurasi_dari_tabel_menggantikan_config_dan_bisa_dinonaktifkan(): void
    {
        $this->assertSame('uji_drm', DB::getDefaultConnection());
        $cfg = config('peta_risiko_drm');
        $master = DrmMaster::dariArray(['baris' => []]);

        // Tabel kosong → tetap config.
        $this->assertNull(DrmMasterDb::oakAktivitas());
        $this->assertSame($cfg['oak_aktivitas_hira'], $master->cfgEfektif($cfg)['oak_aktivitas_hira']);

        // Isi awal dari config.
        $n = app(DrmMasterDb::class)->isiOakAktivitas(['oak_aktivitas_hira' => ['Dumping' => ['Dumping OB', 'Soil Dumping'], 'Loading' => ['Coal Loading']]]);
        $this->assertSame(3, $n);
        $this->assertSame(['Dumping' => ['Dumping OB', 'Soil Dumping'], 'Loading' => ['Coal Loading']], DrmMasterDb::oakAktivitas());
        // Kurasi TIDAK ikut perhitungan (hanya sumber penurunan label per baris).
        $this->assertSame($cfg['oak_aktivitas_hira'], $master->cfgEfektif($cfg)['oak_aktivitas_hira']);
        $this->assertSame(1, DB::table('drm_master_log')->where('tabel', 'drm_master_oak_aktivitas')->count());

        // Tautan yang dinonaktifkan tidak dihitung.
        DB::table('drm_master_oak_aktivitas')->where('aktivitas_hira', 'Soil Dumping')->update(['aktif' => false]);
        DrmMasterDb::lupakanCacheOak();
        $this->assertSame(['Dumping' => ['Dumping OB'], 'Loading' => ['Coal Loading']], DrmMasterDb::oakAktivitas());

        // Tab editor muncul karena tabel sudah ada.
        $this->assertArrayHasKey('oak_aktivitas', DrmMasterSkema::semua());
    }

    public function test_kurasi_diturunkan_jadi_label_oak_per_baris_tanpa_duplikat(): void
    {
        $kini = now();
        DB::table('drm_master_baris')->insert([
            ['ri' => 1, 'aid' => 1, 'aktivitas' => 'Dumping OB', 'sub' => 'a', 'bahaya' => 'x', 'kb' => 'High', 'umum' => false, 'idh' => '', 'aktif' => true, 'created_at' => $kini, 'updated_at' => $kini],
            ['ri' => 2, 'aid' => 1, 'aktivitas' => 'Dumping OB', 'sub' => 'b', 'bahaya' => 'y', 'kb' => 'High', 'umum' => false, 'idh' => '', 'aktif' => true, 'created_at' => $kini, 'updated_at' => $kini],
            ['ri' => 3, 'aid' => 2, 'aktivitas' => 'Workshop', 'sub' => 'c', 'bahaya' => 'z', 'kb' => 'High', 'umum' => false, 'idh' => '', 'aktif' => true, 'created_at' => $kini, 'updated_at' => $kini],
        ]);
        // Label M12 asli yang sudah ada (alias "Risk Intervention Center" = "Langsung") tidak boleh diduplikasi.
        DB::table('drm_master_teks')->insert(['jenis' => 'OAK', 'teks' => 'Dumping / Risk Intervention Center - Penimbunan (Dumping)', 'ri' => 1, 'aktif' => true, 'created_at' => $kini, 'updated_at' => $kini]);

        $kurasi = ['Dumping' => ['Dumping OB']];
        $sub = ['Dumping' => ['Langsung - Penimbunan (Dumping)', 'CCV Dumping di ROM']];
        $db = app(DrmMasterDb::class);
        $this->assertSame(['baris' => 2, 'label' => 3], $db->turunkanOak($kurasi, $sub));
        $turunan = DB::table('drm_master_teks')->where('sumber', DrmMasterDb::SUMBER_TURUNAN_OAK)->orderBy('ri')->orderBy('teks')->get(['ri', 'teks'])
            ->map(fn ($t) => $t->ri.':'.$t->teks)->all();
        $this->assertSame(['1:Dumping / CCV Dumping di ROM', '2:Dumping / CCV Dumping di ROM', '2:Dumping / Langsung - Penimbunan (Dumping)'], $turunan);

        // Dijalankan ulang: turunan lama diganti, tidak menumpuk; Workshop tidak tersentuh.
        $db->turunkanOak($kurasi, $sub);
        $this->assertSame(3, DB::table('drm_master_teks')->where('sumber', DrmMasterDb::SUMBER_TURUNAN_OAK)->count());
        $this->assertSame(0, DB::table('drm_master_teks')->where('ri', 3)->count());
        $this->assertSame(4, DB::table('drm_master_teks')->where('jenis', 'OAK')->count());
    }
}
