<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmMaster;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DrmMasterDb::isi() mengisi drm_master_lokasi_pja.perusahaan dari DrmSql::perusahaanPerPja() (personil), dicocokkan
 * ke nama_pja per baris — bukan nama PJA itu sendiri (keputusan user 24 Nov 2026). SELALU SQLite memori tersendiri.
 */
class DrmMasterDbIsiPerusahaanTest extends TestCase
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
        foreach (['2026_11_17_100000_create_drm_master_tables', '2026_11_24_100000_add_perusahaan_to_drm_master_lokasi_pja_table'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
    }

    protected function tearDown(): void
    {
        DB::purge('uji_drm');
        DB::setDefaultConnection($this->koneksiAsal);
        parent::tearDown();
    }

    public function test_perusahaan_dicocokkan_ke_nama_pja_bukan_disimpan_sebagai_nama_pja(): void
    {
        $master = DrmMaster::dariArray([
            'baris' => [['ri' => 1, 'aid' => 1, 'aktivitas' => 'Jalan Tambang', 'sub' => '', 'bahaya' => 'Jalan licin', 'kb' => 'High', 'kelompokM01' => '', 'umum' => false, 'idh' => []]],
            'grup' => [1 => 'Loading Point'],
            'grupBaris' => [1 => [1]],
            'dl' => [['id' => 1, 'site' => 'LMO', 'lokasi' => 'Pit A', 'detail' => 'Front', 'gid' => 1]],
            'label' => [], 'obs' => [], 'oak' => [],
        ]);
        // Nama PJA di aaj_lokasi_dan_pja beda kapitalisasi/spasi dengan nama di personil — harus tetap cocok (normNamaPja).
        $lokasiPja = [['LMO', 'Pit A', 'PJA   Pit A BC']];
        $perusahaanPerPja = [
            ['pja' => 'pja pit a bc', 'perusahaan' => 'BC'],
            ['pja' => 'pja pit a bc', 'perusahaan' => 'PT Mitra Jaya'],
            ['pja' => 'pja pit a bc', 'perusahaan' => 'BC'], // duplikat, tidak boleh dobel
            ['pja' => 'PJA Lain Tidak Terkait', 'perusahaan' => 'PT Lain'],
        ];

        (new DrmMasterDb())->isi($master, (array) config('peta_risiko_drm'), $lokasiPja, $perusahaanPerPja);

        $baris = DB::table('drm_master_lokasi_pja')->first();
        $this->assertSame('PJA   Pit A BC', $baris->nama_pja);
        $this->assertSame("BC\nPT Mitra Jaya", $baris->perusahaan);
    }

    public function test_tanpa_data_perusahaan_kolom_tetap_kosong_bukan_null_error(): void
    {
        $master = DrmMaster::dariArray([
            'baris' => [], 'grup' => [], 'grupBaris' => [], 'dl' => [], 'label' => [], 'obs' => [], 'oak' => [],
        ]);
        (new DrmMasterDb())->isi($master, (array) config('peta_risiko_drm'), [['LMO', 'Pit A', 'PJA Tanpa Personil']]);

        $this->assertSame('', DB::table('drm_master_lokasi_pja')->first()->perusahaan);
    }
}
