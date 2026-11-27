<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmBaselinePengecualian;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengecualian baseline Overview (drm_baseline_pengecualian) — keputusan user 27 Nov 2026: baris aktivitas ×
 * bahaya-risiko yang dinonaktifkan tetap tersimpan riwayatnya (tidak dihapus), "kembalikan aktif" cuma men-set
 * aktif=false. Kolom lv/kode/inc/tr adalah salinan field rincian saat di-flag, dipakai index.blade.php untuk
 * menambal kartu KPI secara langsung (lihat catatan migrasi). SELALU SQLite memori di koneksi tersendiri.
 */
class DrmBaselinePengecualianTest extends TestCase
{
    private string $koneksiAsal;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite tidak tersedia.');
        }
        $this->koneksiAsal = (string) config('database.default');
        config(['database.connections.uji_bp' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('uji_bp');
        (require base_path('database/migrations/2026_11_27_120000_create_drm_baseline_pengecualian_table.php'))->up();
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->koneksiAsal);
        parent::tearDown();
    }

    private function layanan(): DrmBaselinePengecualian
    {
        return new DrmBaselinePengecualian();
    }

    /** @return int */
    private function tandaiContoh(DrmBaselinePengecualian $l, string $kel = 'CPP', string $hid = 'h1', string $alasan = 'Alasan A')
    {
        return $l->tandai($kel, $hid, 'Coal crushing', '', 'Bahaya A', $alasan, 'Significant', 'TTGKT', true, false, 'budi@beraucoal.co.id');
    }

    public function test_siap_mendeteksi_tabel_ada(): void
    {
        $this->assertTrue($this->layanan()->siap());
        DB::statement('DROP TABLE drm_baseline_pengecualian');
        $this->assertFalse($this->layanan()->siap());
    }

    public function test_tandai_lalu_muncul_di_daftar_aktif(): void
    {
        $l = $this->layanan();
        $this->tandaiContoh($l);
        $this->assertSame([['kel' => 'CPP', 'hid' => 'h1', 'lv' => 'Significant', 'kode' => 'TTGKT', 'inc' => true, 'tr' => false]], $l->daftarAktif());
    }

    public function test_baris_nonaktif_tidak_ikut_daftar_aktif(): void
    {
        $l = $this->layanan();
        $id = $this->tandaiContoh($l);
        $l->kembalikan($id, 'ifa@beraucoal.co.id');
        $this->assertSame([], $l->daftarAktif());
    }

    public function test_kembalikan_mencatat_siapa_dan_kapan_tanpa_menghapus_baris(): void
    {
        $l = $this->layanan();
        $id = $this->tandaiContoh($l);
        $this->assertTrue($l->kembalikan($id, 'ifa@beraucoal.co.id'));
        $row = DB::table('drm_baseline_pengecualian')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->aktif);
        $this->assertSame('ifa@beraucoal.co.id', $row->dikembalikan_oleh);
        $this->assertNotNull($row->dikembalikan_pada);
    }

    public function test_kembalikan_baris_yang_sudah_nonaktif_gagal(): void
    {
        $l = $this->layanan();
        $id = $this->tandaiContoh($l);
        $this->assertTrue($l->kembalikan($id, 'ifa@beraucoal.co.id'));
        $this->assertFalse($l->kembalikan($id, 'ifa@beraucoal.co.id'));
    }

    public function test_tandai_ulang_kel_hid_sama_membuat_baris_riwayat_baru(): void
    {
        $l = $this->layanan();
        $id1 = $this->tandaiContoh($l, alasan: 'Alasan pertama');
        $l->kembalikan($id1, 'ifa@beraucoal.co.id');
        $id2 = $this->tandaiContoh($l, alasan: 'Alasan kedua');
        $this->assertNotSame($id1, $id2);
        $this->assertCount(1, $l->daftarAktif());
        $this->assertSame(2, DB::table('drm_baseline_pengecualian')->count());
    }

    public function test_riwayat_urut_terbaru_dulu(): void
    {
        $l = $this->layanan();
        $this->tandaiContoh($l, 'A', 'h1');
        $this->tandaiContoh($l, 'B', 'h2');
        $riwayat = $l->riwayat();
        $this->assertSame(['B', 'A'], $riwayat->pluck('kel')->all());
    }
}
