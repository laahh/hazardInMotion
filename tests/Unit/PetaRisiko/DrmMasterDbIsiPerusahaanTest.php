<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmMaster;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DrmMasterDb::isi() mengisi drm_master_lokasi_pja.perusahaan dari DrmSql::pja() (hierarki BC/Mitra) +
 * DrmSql::perusahaanPerPja() (personil per ID): PJA itu sendiri DIGABUNG dengan Mitra anaknya (keputusan user
 * 24 Nov 2026: "parent atau BC atau Mitra Kerja semuanya masuk"), dicocokkan ke nama_pja via normNamaPja.
 * SELALU SQLite memori tersendiri.
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

    private function master(): DrmMaster
    {
        return DrmMaster::dariArray([
            'baris' => [['ri' => 1, 'aid' => 1, 'aktivitas' => 'Jalan Tambang', 'sub' => '', 'bahaya' => 'Jalan licin', 'kb' => 'High', 'kelompokM01' => '', 'umum' => false, 'idh' => []]],
            'grup' => [1 => 'Loading Point'],
            'grupBaris' => [1 => [1]],
            'dl' => [['id' => 1, 'site' => 'LMO', 'lokasi' => 'Pit A', 'detail' => 'Front', 'gid' => 1]],
            'label' => [], 'obs' => [], 'oak' => [],
        ]);
    }

    public function test_perusahaan_pja_bc_digabung_dengan_mitra_anaknya(): void
    {
        // Nama PJA di aaj_lokasi_dan_pja beda kapitalisasi/spasi dengan m_pja.name — harus tetap cocok (normNamaPja).
        $lokasiPja = [['LMO', 'Pit A', 'PJA   Pit A BC']];
        $pjaList = [
            ['id' => '10', 'nama' => 'PJA Pit A BC', 'parent' => '0', 'tipe' => 'BC', 'id_site' => '1'],
            ['id' => '11', 'nama' => 'PJA Mitra Pit A', 'parent' => '10', 'tipe' => 'Mitra Kerja', 'id_site' => '1'],
            ['id' => '99', 'nama' => 'PJA Lain Tidak Terkait', 'parent' => '0', 'tipe' => 'BC', 'id_site' => '1'],
        ];
        $perusahaanPerPja = [
            ['pja_id' => '10', 'perusahaan' => 'BC'],
            ['pja_id' => '10', 'perusahaan' => 'BC'], // duplikat, tidak boleh dobel
            ['pja_id' => '11', 'perusahaan' => 'PT Mitra Jaya'],
            ['pja_id' => '99', 'perusahaan' => 'PT Lain'],
        ];

        (new DrmMasterDb())->isi($this->master(), (array) config('peta_risiko_drm'), $lokasiPja, $pjaList, $perusahaanPerPja);

        $baris = DB::table('drm_master_lokasi_pja')->first();
        $this->assertSame('PJA   Pit A BC', $baris->nama_pja);
        // Personil langsung PJA Pit A BC (BC) + personil anak Mitra-nya (PT Mitra Jaya) — bukan "PT Lain" (tidak terkait).
        $this->assertSame("BC\nPT Mitra Jaya", $baris->perusahaan);
    }

    public function test_tanpa_data_personil_kolom_tetap_kosong_bukan_error(): void
    {
        $master = DrmMaster::dariArray([
            'baris' => [], 'grup' => [], 'grupBaris' => [], 'dl' => [], 'label' => [], 'obs' => [], 'oak' => [],
        ]);
        (new DrmMasterDb())->isi($master, (array) config('peta_risiko_drm'), [['LMO', 'Pit A', 'PJA Tanpa Personil']]);

        $this->assertSame('', DB::table('drm_master_lokasi_pja')->first()->perusahaan);
    }

    public function test_perusahaan_per_nama_pja_murni_tanpa_db(): void
    {
        $pjaList = [
            ['id' => '1', 'nama' => 'Induk A', 'parent' => '0'],
            ['id' => '2', 'nama' => 'Anak A1', 'parent' => '1'],
            ['id' => '3', 'nama' => 'Tanpa Personil', 'parent' => '0'],
        ];
        $perusahaanPerPja = [
            ['pja_id' => '1', 'perusahaan' => 'BC'],
            ['pja_id' => '2', 'perusahaan' => 'PT Anak'],
        ];

        $hasil = DrmMasterDb::perusahaanPerNamaPja($pjaList, $perusahaanPerPja);

        $this->assertSame(['BC', 'PT Anak'], $hasil[DrmMasterDb::normNamaPja('Induk A')]);
        // Anak sendiri hanya punya perusahaannya sendiri (rollup satu arah, turun ke anak — bukan naik ke induk).
        $this->assertSame(['PT Anak'], $hasil[DrmMasterDb::normNamaPja('Anak A1')]);
        $this->assertArrayNotHasKey(DrmMasterDb::normNamaPja('Tanpa Personil'), $hasil);
    }
}
