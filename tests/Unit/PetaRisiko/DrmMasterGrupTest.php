<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\Master\DrmMasterGrup;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Master grup lokasi & aktivitas (M05 per aktivitas + kelompok M02). SELALU SQLite memori di koneksi tersendiri,
 * tidak pernah database dari .env (database bersama).
 */
class DrmMasterGrupTest extends TestCase
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
        $this->isi();
    }

    protected function tearDown(): void
    {
        DB::purge('uji_drm');
        DB::setDefaultConnection($this->koneksiAsal);
        parent::tearDown();
    }

    public function test_daftar_grup_dan_aktivitas_per_grup(): void
    {
        $svc = app(DrmMasterGrup::class);

        $daftar = collect($svc->daftarGrup())->keyBy('gid');
        $this->assertSame(['baris' => 3, 'aktivitas' => 2, 'dl' => 2], array_intersect_key($daftar[20], array_flip(['baris', 'aktivitas', 'dl'])));
        $this->assertSame(0, $daftar[3]['baris']);

        $akt = collect($svc->aktivitasGrup(20))->keyBy('aid');
        // Fixed plant: 1 dari 2 barisnya ada di Workshop, sisanya di Jetty.
        $this->assertSame([754], array_column($akt[7]['baris'], 'ri'));
        $this->assertSame(2, $akt[7]['total']);
        $this->assertSame([['gid' => 8, 'nama' => 'Jetty', 'n' => 1]], $akt[7]['lain']);
        $this->assertSame('K10', $akt[1]['kelompok']);
        // Aktivitas yang barisnya belum semua di Workshop bisa ditambahkan.
        $this->assertSame([7], array_column($svc->aktivitasLuar(20), 'aid'));
    }

    public function test_pindah_baris_ke_grup_lain_tercatat_dan_tidak_menggandakan(): void
    {
        $svc = app(DrmMasterGrup::class);

        $this->assertSame(1, $svc->pindah(20, 3, [754]));
        $this->assertSame([3], DB::table('drm_master_grup_baris')->where('ri', 754)->pluck('gid')->map(fn ($v) => (int) $v)->all());
        // ri 755 sudah ada di Jetty: memindah dari Workshop (tidak ada di sana) tidak berbuat apa-apa.
        $this->assertSame(0, $svc->pindah(20, 8, [755]));
        // Pindah ke grup yang sudah memuat baris itu = cukup keluar dari asal.
        $svc->tambah(20, 7);
        $this->assertSame(1, $svc->pindah(20, 8, [755]));
        $this->assertSame(1, DB::table('drm_master_grup_baris')->where('ri', 755)->count());
        $this->assertSame(['ubah', 'tambah', 'tambah', 'hapus'], DB::table('drm_master_log')->orderBy('id')->pluck('aksi')->all());

        $this->expectException(InvalidArgumentException::class);
        $svc->pindah(20, 20, [1]);
    }

    public function test_semua_baris_flat_dengan_chip_grup(): void
    {
        $svc = app(DrmMasterGrup::class);

        $baris = collect($svc->semuaBaris())->keyBy('ri');
        // ri 1 & 2 hanya di Workshop; ri 754 di Workshop; ri 755 hanya di Jetty.
        $this->assertSame([20], array_column($baris[1]['grup'], 'gid'));
        $this->assertSame(['Jetty'], array_column($baris[755]['grup'], 'nama'));
        $this->assertSame('K10', $baris[754]['kelompok']);
        // Baris yang belum masuk grup mana pun tetap tampil (chip kosong), bukan disembunyikan.
        $this->assertCount(4, $baris);
    }

    public function test_semua_detail_lokasi_dikelompokkan_per_gid(): void
    {
        $svc = app(DrmMasterGrup::class);

        $dl = $svc->semuaDetailLokasi();
        $this->assertCount(2, $dl[20]);
        $this->assertArrayNotHasKey(8, $dl);
    }

    public function test_tambah_baris_lintas_aktivitas_tidak_menggandakan(): void
    {
        $svc = app(DrmMasterGrup::class);

        // ri 1 (aid 1) & ri 755 (aid 7, sudah di Jetty) sekaligus, lintas aktivitas — tambah() biasa tidak bisa begini.
        $this->assertSame(1, $svc->tambahBaris(8, [1, 755]));
        // ri 1 kini di kedua grup (Workshop semula + Jetty baru), diurutkan per gid.
        $this->assertSame(['Jetty', 'Workshop/Pit Stop'], array_column(collect($svc->semuaBaris())->keyBy('ri')[1]['grup'], 'nama'));
        $this->assertSame(0, $svc->tambahBaris(8, [1, 755]));

        $this->expectException(InvalidArgumentException::class);
        $svc->tambahBaris(999, [1]);
    }

    public function test_tambah_hapus_dan_ubah_kelompok(): void
    {
        $svc = app(DrmMasterGrup::class);

        $this->assertSame(1, $svc->tambah(20, 7)); // hanya ri 755 yang belum ada
        $this->assertSame(0, $svc->tambah(20, 7));
        $this->assertSame(2, $svc->hapus(20, [1, 2]));
        $this->assertSame(0, DB::table('drm_master_grup_baris')->where('gid', 20)->whereIn('ri', [1, 2])->count());

        $this->assertTrue($svc->ubahKelompok(7, 'K5'));
        $this->assertFalse($svc->ubahKelompok(7, 'K5'));
        $this->assertSame('K5', DB::table('drm_master_aktivitas')->where('aid', 7)->value('kelompok'));

        $this->expectException(InvalidArgumentException::class);
        $svc->ubahKelompok(7, 'K99');
    }

    private function isi(): void
    {
        $t = now();
        DB::table('drm_master_kelompok')->insert([
            ['kode' => 'K5', 'nama' => 'CPP, Fixed Plant & Stockpile', 'regex' => '', 'urutan' => 5, 'created_at' => $t, 'updated_at' => $t],
            ['kode' => 'K10', 'nama' => 'Perawatan Unit, Workshop & Tyre', 'regex' => '', 'urutan' => 10, 'created_at' => $t, 'updated_at' => $t],
        ]);
        DB::table('drm_master_grup')->insert([
            ['gid' => 3, 'nama' => 'CPP / ROM', 'aktif' => true], ['gid' => 8, 'nama' => 'Jetty', 'aktif' => true], ['gid' => 20, 'nama' => 'Workshop/Pit Stop', 'aktif' => true],
        ]);
        DB::table('drm_master_aktivitas')->insert([
            ['aid' => 1, 'aktivitas' => 'Perawatan unit', 'kelompok' => 'K10'], ['aid' => 7, 'aktivitas' => 'Pekerjaan Fixed Plant', 'kelompok' => 'K10'],
        ]);
        foreach ([[1, 1, 'Servis', 'Terjepit'], [2, 1, 'Servis', 'Terbakar'], [754, 7, 'Fixed plant', 'Benda berputar'], [755, 7, 'Fixed plant', 'Debu batubara']] as [$ri, $aid, $sub, $bahaya]) {
            DB::table('drm_master_baris')->insert(['ri' => $ri, 'aid' => $aid, 'aktivitas' => 'x', 'sub' => $sub, 'bahaya' => $bahaya, 'kb' => 'High', 'aktif' => true]);
        }
        DB::table('drm_master_grup_baris')->insert([['gid' => 20, 'ri' => 1], ['gid' => 20, 'ri' => 2], ['gid' => 20, 'ri' => 754], ['gid' => 8, 'ri' => 755]]);
        DB::table('drm_master_dl')->insert([
            ['site' => 'BMO 1', 'lokasi' => 'Workshop', 'detail' => 'WS 1', 'gid' => 20, 'aktif' => true],
            ['site' => 'BMO 1', 'lokasi' => 'Workshop', 'detail' => 'WS 2', 'gid' => 20, 'aktif' => true],
        ]);
    }
}
