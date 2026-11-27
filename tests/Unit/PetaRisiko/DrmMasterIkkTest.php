<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\Master\DrmMasterBarisUnggah;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Flag IKK (Izin Kerja Khusus) per baris: DrmMasterBarisUnggah::ikkPerBaris() mencocokkan Item Sumber OAK baris
 * dengan daftar drm_master_ikk; entri bertanda cek_luar_workshop dikecualikan kalau teks baris sendiri menyebut
 * "workshop"/"pit stop". SELALU SQLite memori di koneksi tersendiri (bukan database bersama).
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
        DB::table('drm_master_ikk')->insert([
            ['aktivitas' => 'Bekerja dengan Panas', 'sub_aktivitas' => 'Pemotongan', 'cek_luar_workshop' => false, 'aktif' => true],
            ['aktivitas' => 'Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang', 'sub_aktivitas' => '[CCV] Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang', 'cek_luar_workshop' => true, 'aktif' => true],
            ['aktivitas' => 'Bekerja di Dekat Air', 'sub_aktivitas' => 'Pit Dewatering', 'cek_luar_workshop' => false, 'aktif' => false],
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

    public function test_baris_dengan_oak_cocok_ditandai_ikk(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            101 => ['aktivitas' => 'Pengelasan pipa', 'sub' => 'Pengelasan', 'bahaya' => 'Percikan api',
                'OAK' => ['Bekerja dengan Panas / Pemotongan']],
        ]);
        $this->assertSame([101 => true], $hasil);
    }

    public function test_baris_tanpa_oak_cocok_tidak_ditandai_ikk(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            102 => ['aktivitas' => 'Mengangkat material', 'sub' => '', 'bahaya' => 'Tertimpa',
                'OAK' => ['Pengangkatan & Pengangkutan Unit/Material / Pengangkatan']],
        ]);
        $this->assertArrayNotHasKey(102, $hasil);
    }

    public function test_entri_cek_luar_workshop_tetap_ikk_bila_baris_tidak_sebut_workshop(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            103 => ['aktivitas' => 'Perbaikan unit di lapangan', 'sub' => '', 'bahaya' => 'Tertimpa alat berat',
                'OAK' => ['Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang / [CCV] Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang']],
        ]);
        $this->assertSame([103 => true], $hasil);
    }

    public function test_entri_cek_luar_workshop_dikecualikan_bila_baris_sebut_workshop(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            104 => ['aktivitas' => 'Perbaikan unit di workshop', 'sub' => '', 'bahaya' => 'Tertimpa alat berat',
                'OAK' => ['Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang / [CCV] Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang']],
        ]);
        $this->assertArrayNotHasKey(104, $hasil);
    }

    public function test_entri_cek_luar_workshop_dikecualikan_bila_baris_sebut_pit_stop(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            105 => ['aktivitas' => 'Perbaikan unit', 'sub' => '', 'bahaya' => 'Dikerjakan di Pit Stop BMO1',
                'OAK' => ['Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang / [CCV] Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang']],
        ]);
        $this->assertArrayNotHasKey(105, $hasil);
    }

    /** Sub aktivitas OAK boleh beda redaksional dari daftar asal masih MENGANDUNG kata yang sama (keputusan user 27 Nov 2026). */
    public function test_sub_aktivitas_oak_lebih_panjang_dari_daftar_tetap_ikk(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            108 => ['aktivitas' => 'Pengelasan pipa gas', 'sub' => '', 'bahaya' => 'Percikan api',
                'OAK' => ['Bekerja dengan Panas / Pemotongan plat besi di area tambang']],
        ]);
        $this->assertSame([108 => true], $hasil);
    }

    /** Sebaliknya: sub aktivitas OAK lebih pendek/ringkas, tapi masih terkandung dalam teks daftar. */
    public function test_sub_aktivitas_daftar_lebih_panjang_dari_oak_tetap_ikk(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            109 => ['aktivitas' => 'Perbaikan unit di lapangan', 'sub' => '', 'bahaya' => 'Tertimpa alat berat',
                'OAK' => ['Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang / CCV']],
        ]);
        $this->assertSame([109 => true], $hasil);
    }

    /** Aktivitas tetap harus cocok PERSIS — sub aktivitas yang kebetulan mengandung kata sama tidak cukup. */
    public function test_aktivitas_beda_tidak_ikk_meski_sub_mengandung_kata_sama(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            110 => ['aktivitas' => 'Aktivitas lain', 'sub' => '', 'bahaya' => 'Tidak relevan',
                'OAK' => ['Aktivitas Lain yang Tidak Terdaftar / Pemotongan rumput liar']],
        ]);
        $this->assertArrayNotHasKey(110, $hasil);
    }

    public function test_entri_ikk_nonaktif_tidak_dipakai(): void
    {
        $hasil = $this->unggah()->ikkPerBaris([
            106 => ['aktivitas' => 'Pit dewatering', 'sub' => '', 'bahaya' => 'Genangan air dalam',
                'OAK' => ['Bekerja di Dekat Air / Pit Dewatering']],
        ]);
        $this->assertArrayNotHasKey(106, $hasil);
    }

    public function test_tabel_ikk_belum_dimigrasi_mengembalikan_kosong(): void
    {
        DB::statement('DROP TABLE drm_master_ikk');
        $hasil = $this->unggah()->ikkPerBaris([
            107 => ['aktivitas' => 'Pengelasan pipa', 'sub' => 'Pengelasan', 'bahaya' => 'Percikan api',
                'OAK' => ['Bekerja dengan Panas / Pemotongan']],
        ]);
        $this->assertSame([], $hasil);
    }
}
