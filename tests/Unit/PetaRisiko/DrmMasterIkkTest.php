<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\Master\DrmMasterBarisUnggah;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Flag IKK (Izin Kerja Khusus) per baris, LEVEL AKTIVITAS HIRA (keputusan user 27 Nov 2026):
 * DrmMasterBarisUnggah::ikkPerBaris() mencocokkan AKTIVITAS HIRA baris (bukan lagi Item Sumber OAK) lewat kurasi
 * drm_master_oak_aktivitas (aktivitas OAK -> aktivitas HIRA) ke daftar drm_master_ikk aktif; entri bertanda
 * cek_luar_workshop dikecualikan kalau teks baris sendiri menyebut "workshop"/"pit stop". SELALU SQLite memori di
 * koneksi tersendiri (bukan database bersama).
 */
class DrmMasterIkkTest extends TestCase
{
    private string $koneksiAsal;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite tidak tersedia.');
        }
        $this->koneksiAsal = (string) config('database.default');
        config(['database.connections.uji_ikk' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('uji_ikk');
        (require base_path('database/migrations/2026_11_25_100000_create_drm_master_ikk_table.php'))->up();
        (require base_path('database/migrations/2026_11_18_100000_create_drm_master_oak_aktivitas_table.php'))->up();
        DB::table('drm_master_ikk')->insert([
            ['aktivitas' => 'Bekerja dengan Panas', 'sub_aktivitas' => 'Pemotongan', 'cek_luar_workshop' => false, 'aktif' => true],
            ['aktivitas' => 'Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang', 'sub_aktivitas' => '[CCV] Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang', 'cek_luar_workshop' => true, 'aktif' => true],
            ['aktivitas' => 'Bekerja di Dekat Air', 'sub_aktivitas' => 'Pit Dewatering', 'cek_luar_workshop' => false, 'aktif' => false],
        ]);
        DB::table('drm_master_oak_aktivitas')->insert([
            ['aktivitas_oak' => 'Bekerja dengan Panas', 'aktivitas_hira' => 'Pengelasan Pipa', 'aktif' => true],
            ['aktivitas_oak' => 'Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang', 'aktivitas_hira' => 'Perbaikan Unit Lapangan', 'aktif' => true],
            ['aktivitas_oak' => 'Bekerja di Dekat Air', 'aktivitas_hira' => 'Pit Dewatering', 'aktif' => true],
            ['aktivitas_oak' => 'Bekerja dengan Panas', 'aktivitas_hira' => 'Aktivitas Kurasi Nonaktif', 'aktif' => false],
        ]);
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->koneksiAsal);
        parent::tearDown();
    }

    /** @return DrmMasterBarisUnggah */
    private function unggah(): DrmMasterBarisUnggah
    {
        return new DrmMasterBarisUnggah(new DrmMasterDb());
    }

    public function test_baris_dengan_aktivitas_terkurasi_ditandai_ikk(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            101 => ['aktivitas' => 'Pengelasan Pipa', 'sub' => 'Pengelasan', 'bahaya' => 'Percikan api', 'OAK' => []],
        ]);
        $this->assertSame([101 => true], $hasil);
    }

    public function test_baris_aktivitas_tidak_terkurasi_tidak_ditandai_ikk(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            102 => ['aktivitas' => 'Mengangkat Material', 'sub' => '', 'bahaya' => 'Tertimpa', 'OAK' => []],
        ]);
        $this->assertArrayNotHasKey(102, $hasil);
    }

    public function test_entri_cek_luar_workshop_tetap_ikk_bila_baris_tidak_sebut_workshop(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            103 => ['aktivitas' => 'Perbaikan Unit Lapangan', 'sub' => '', 'bahaya' => 'Tertimpa alat berat', 'OAK' => []],
        ]);
        $this->assertSame([103 => true], $hasil);
    }

    public function test_entri_cek_luar_workshop_dikecualikan_bila_baris_sebut_workshop(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            104 => ['aktivitas' => 'Perbaikan Unit Lapangan di workshop', 'sub' => '', 'bahaya' => 'Tertimpa alat berat', 'OAK' => []],
        ]);
        $this->assertArrayNotHasKey(104, $hasil);
    }

    public function test_entri_cek_luar_workshop_dikecualikan_bila_baris_sebut_pit_stop(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            105 => ['aktivitas' => 'Perbaikan Unit Lapangan', 'sub' => '', 'bahaya' => 'Dikerjakan di Pit Stop BMO1', 'OAK' => []],
        ]);
        $this->assertArrayNotHasKey(105, $hasil);
    }

    /** Entri drm_master_ikk nonaktif (Bekerja di Dekat Air) tidak dipakai meski kurasi aktivitasnya aktif. */
    public function test_entri_ikk_nonaktif_tidak_dipakai(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            106 => ['aktivitas' => 'Pit Dewatering', 'sub' => '', 'bahaya' => 'Genangan air dalam', 'OAK' => []],
        ]);
        $this->assertArrayNotHasKey(106, $hasil);
    }

    /** Baris kurasi drm_master_oak_aktivitas nonaktif tidak dipakai meski aktivitas OAK-nya sendiri aktif. */
    public function test_kurasi_aktivitas_nonaktif_tidak_dipakai(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            107 => ['aktivitas' => 'Aktivitas Kurasi Nonaktif', 'sub' => '', 'bahaya' => 'Tidak relevan', 'OAK' => []],
        ]);
        $this->assertArrayNotHasKey(107, $hasil);
    }

    public function test_tabel_ikk_belum_dimigrasi_mengembalikan_kosong(): void
    {
        DB::statement('DROP TABLE drm_master_ikk');
        $hasil = $this->unggah()->ikkPerBaris([
            108 => ['aktivitas' => 'Pengelasan Pipa', 'sub' => '', 'bahaya' => 'Percikan api', 'OAK' => []],
        ]);
        $this->assertSame([], $hasil);
    }

    public function test_tabel_oak_aktivitas_belum_dimigrasi_mengembalikan_kosong(): void
    {
        DB::statement('DROP TABLE drm_master_oak_aktivitas');
        $hasil = $this->unggah()->ikkPerBaris([
            109 => ['aktivitas' => 'Pengelasan Pipa', 'sub' => '', 'bahaya' => 'Percikan api', 'OAK' => []],
        ]);
        $this->assertSame([], $hasil);
    }
}
