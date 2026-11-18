<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmKorelasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Peta korelasi master DRM 2.1. SQLite memori di koneksi tersendiri (bukan database .env) + snapshot palsu (Storage::fake).
 */
class DrmKorelasiTest extends TestCase
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
        (require base_path('database/migrations/2026_11_18_120000_add_item_sumber_to_drm_master_baris_table.php'))->up();
        $this->isi();
        Storage::fake('local');
        Storage::disk('local')->put('peta-risiko-drm/meta.json', '{"dibuat":"x","sampai":"2026-11-17"}');
        // Minggu indeks 1 (8 Nov) = minggu penuh terakhir: ri 1 terkontrol, ri 2 ada gap, ri 3 tidak teridentifikasi.
        Storage::disk('local')->put('peta-risiko-drm/rincian.json', json_encode(['minggu' => ['2026-11-15', '2026-11-08'], 'lingkup' => ['ALL' => ['u' => [
            ['K10', '1', 'High', 0, 0, 'TK', '00', ''], ['K10', '2', 'High', 0, 0, 'TG', '01', ''], ['K5', '3', 'Significant', 0, 0, 'TT', '00', ''],
        ]]]]));
    }

    protected function tearDown(): void
    {
        DB::purge('uji_drm');
        DB::setDefaultConnection($this->koneksiAsal);
        parent::tearDown();
    }

    public function test_graf_grup_memuat_dl_pja_aktivitas_dan_kelompok(): void
    {
        $g = app(DrmKorelasi::class)->graf('grup', '20');
        $kunci = array_column($g['node'], 'jenis', 'key');

        $this->assertSame('g:20', $g['pusat']);
        $this->assertSame('dl', $kunci['d:1']);
        $this->assertSame('pja', $kunci['p:'.md5('PJA Workshop')]);
        $this->assertSame('aktivitas', $kunci['a:1']);
        $this->assertSame('kelompok', $kunci['k:K10']);
        $this->assertContains(['from' => 'a:1', 'to' => 'k:K10'], $g['edge']);
        $this->assertSame('2026-11-08', $g['minggu']);
    }

    public function test_graf_bahaya_menampilkan_label_dan_baris_lain_berlabel_sama(): void
    {
        $g = app(DrmKorelasi::class)->graf('bahaya', '1');
        $node = collect($g['node'])->keyBy('key');

        $this->assertSame('K', $node['b:1']['status']);
        $label = $node->firstWhere('jenis', 'ket');
        $this->assertSame('Bahaya Mekanikal / Terjepit', $label['label']);
        // ri 2 memakai label Ketidaksesuaian yang sama (beda huruf besar/spasi) → ikut tampil, status ada gap.
        $this->assertSame('G', $node['b:2']['status']);
        $this->assertContains(['from' => 'b:2', 'to' => $label['key']], array_map(fn ($e) => strcmp($e['from'], $e['to']) < 0 ? $e : ['from' => $e['to'], 'to' => $e['from']], $g['edge']));
        $this->assertTrue($node->contains('key', 'g:20'));
        $this->assertTrue($node->contains('key', 'g:3'));
    }

    public function test_cari_lintas_jenis_dan_simpul_tidak_dikenal(): void
    {
        $svc = app(DrmKorelasi::class);
        $jenis = array_column($svc->cari('terjepit'), 'jenis');

        $this->assertContains('bahaya', $jenis);
        $this->assertContains('ket', $jenis);
        $this->assertSame([], $svc->cari('x'));
        $this->assertSame(['jenis' => 'grup', 'id' => '20'], $svc->pusatBawaan());

        $this->expectException(InvalidArgumentException::class);
        $svc->graf('grup', '999');
    }

    private function isi(): void
    {
        DB::table('drm_master_kelompok')->insert([['kode' => 'K5', 'nama' => 'CPP', 'regex' => '', 'urutan' => 5], ['kode' => 'K10', 'nama' => 'Perawatan Unit', 'regex' => '', 'urutan' => 10]]);
        DB::table('drm_master_grup')->insert([['gid' => 3, 'nama' => 'CPP / ROM', 'aktif' => true], ['gid' => 20, 'nama' => 'Workshop/Pit Stop', 'aktif' => true]]);
        DB::table('drm_master_aktivitas')->insert([['aid' => 1, 'aktivitas' => 'Perawatan unit', 'kelompok' => 'K10'], ['aid' => 2, 'aktivitas' => 'Fixed plant', 'kelompok' => 'K5']]);
        $baris = [
            [1, 1, 'Terjepit roller', 'Bahaya Mekanikal / Terjepit'],
            [2, 1, 'Terjepit pintu', "bahaya  mekanikal / TERJEPIT\nKebakaran / Api"],
            [3, 2, 'Debu batubara', null],
        ];
        foreach ($baris as [$ri, $aid, $bahaya, $ket]) {
            DB::table('drm_master_baris')->insert(['ri' => $ri, 'aid' => $aid, 'aktivitas' => 'x', 'sub' => 'sub', 'bahaya' => $bahaya, 'kb' => 'High', 'aktif' => true,
                'item_sumber_observasi' => $ri === 1 ? 'Inspeksi unit' : null, 'item_sumber_oak' => null, 'item_sumber_ketidaksesuaian' => $ket]);
        }
        // Workshop (3 baris) lebih banyak dari CPP (2 baris) → pusat bawaan = Workshop.
        DB::table('drm_master_grup_baris')->insert([['gid' => 20, 'ri' => 1], ['gid' => 20, 'ri' => 2], ['gid' => 20, 'ri' => 3], ['gid' => 3, 'ri' => 1], ['gid' => 3, 'ri' => 3]]);
        DB::table('drm_master_dl')->insert([['id' => 1, 'site' => 'BMO 1', 'lokasi' => 'Workshop', 'detail' => 'WS 1', 'gid' => 20, 'aktif' => true]]);
        DB::table('drm_master_lokasi_pja')->insert([['site' => 'BMO 1', 'lokasi' => 'Workshop', 'nama_pja' => 'PJA Workshop', 'aktif' => true]]);
    }
}
