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
        foreach (['2026_11_17_100000_create_drm_master_tables', '2026_11_24_100000_add_perusahaan_to_drm_master_lokasi_pja_table',
            '2026_11_26_100000_add_pja_anak_to_drm_master_lokasi_pja_table', '2026_11_26_110000_create_drm_master_lokasi_pic_table'] as $m) {
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
        // PJA Child (kolom pja_anak): identitas PJA anak itu sendiri, bukan perusahaan gabungan.
        $this->assertSame('PJA Mitra Pit A', $baris->pja_anak);

        // Master baru drm_master_lokasi_pic: PIC Perusahaan CUMA dari anak (PT Mitra Jaya) — "BC" (induk sendiri)
        // dan "PT Lain" (PJA tidak terkait) TIDAK ikut.
        $pic = DB::table('drm_master_lokasi_pic')->first();
        $this->assertSame('LMO', $pic->site);
        $this->assertSame('Pit A', $pic->lokasi);
        $this->assertSame('PT Mitra Jaya', $pic->pic_perusahaan);
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

    public function test_perusahaan_pja_payung_koordinator_pakai_personil_sendiri_tanpa_anak_murni_tanpa_db(): void
    {
        // Kasus nyata 26 Nov 2026: "SUPERIOR HO & TRANSPORTASI BC" punya personil sendiri (PT Berau Coal) DAN
        // puluhan anak yang sebenarnya PJA di site lain. Personil sendiri tetap masuk (benar ditugaskan ke ID
        // itu), tapi anaknya TIDAK digabung (beda dari PJA biasa yang anaknya ikut digabung).
        $pjaList = [
            ['id' => '1', 'nama' => 'SUPERIOR HO & TRANSPORTASI BC', 'parent' => '0'],
            ['id' => '2', 'nama' => 'PJA TRANSPORTASI BMC BMO', 'parent' => '1'],
            ['id' => '3', 'nama' => 'PJA Pit A BC', 'parent' => '0'],
            ['id' => '4', 'nama' => 'PJA Mitra Pit A', 'parent' => '3'],
        ];
        $perusahaanPerPja = [
            ['pja_id' => '1', 'perusahaan' => 'PT Berau Coal'],
            ['pja_id' => '2', 'perusahaan' => 'PT Transportasi Tersebar'],
            ['pja_id' => '3', 'perusahaan' => 'BC'],
            ['pja_id' => '4', 'perusahaan' => 'PT Mitra Jaya'],
        ];

        $hasil = DrmMasterDb::perusahaanPerNamaPja($pjaList, $perusahaanPerPja);

        // Payung: cuma personil sendiri, "PT Transportasi Tersebar" (dari anak) TIDAK ikut.
        $this->assertSame(['PT Berau Coal'], $hasil[DrmMasterDb::normNamaPja('SUPERIOR HO & TRANSPORTASI BC')]);
        // PJA biasa: tetap digabung induk+anak seperti sebelumnya.
        $this->assertSame(['BC', 'PT Mitra Jaya'], $hasil[DrmMasterDb::normNamaPja('PJA Pit A BC')]);
    }

    public function test_anak_per_nama_pja_satu_level_murni_tanpa_db(): void
    {
        $pjaList = [
            ['id' => '1', 'nama' => 'Induk A', 'parent' => '0'],
            ['id' => '2', 'nama' => 'Anak A1', 'parent' => '1'],
            ['id' => '3', 'nama' => 'Cucu A1a', 'parent' => '2'],
            ['id' => '4', 'nama' => 'Tanpa Anak', 'parent' => '0'],
        ];

        $hasil = DrmMasterDb::anakPerNamaPja($pjaList);

        $this->assertSame(['Anak A1'], $hasil[DrmMasterDb::normNamaPja('Induk A')]);
        // Satu level saja — cucu TIDAK ikut naik ke induk (beda dengan perusahaanPerNamaPja yang menggabung anak).
        $this->assertSame(['Cucu A1a'], $hasil[DrmMasterDb::normNamaPja('Anak A1')]);
        $this->assertArrayNotHasKey(DrmMasterDb::normNamaPja('Tanpa Anak'), $hasil);
    }

    public function test_pic_perusahaan_per_nama_pja_cuma_dari_anak_murni_tanpa_db(): void
    {
        $pjaList = [
            ['id' => '1', 'nama' => 'Induk A', 'parent' => '0'],
            ['id' => '2', 'nama' => 'Anak A1', 'parent' => '1'],
            ['id' => '3', 'nama' => 'Tanpa Anak', 'parent' => '0'],
        ];
        $perusahaanPerPja = [
            ['pja_id' => '1', 'perusahaan' => 'BC'], // perusahaan INDUK sendiri — tidak boleh ikut hasil PIC.
            ['pja_id' => '2', 'perusahaan' => 'PT Anak'],
            ['pja_id' => '3', 'perusahaan' => 'PT Tanpa Anak'],
        ];

        $hasil = DrmMasterDb::picPerusahaanPerNamaPja($pjaList, $perusahaanPerPja);

        // Beda dengan perusahaanPerNamaPja: "BC" (perusahaan Induk A sendiri) TIDAK ikut, cuma anaknya.
        $this->assertSame(['PT Anak'], $hasil[DrmMasterDb::normNamaPja('Induk A')]);
        // PJA tanpa anak: tidak ada PIC sama sekali, meski dia sendiri punya personil.
        $this->assertArrayNotHasKey(DrmMasterDb::normNamaPja('Tanpa Anak'), $hasil);
    }

    public function test_pic_perusahaan_mengecualikan_pja_payung_koordinator_murni_tanpa_db(): void
    {
        // Kasus nyata 26 Nov 2026: "SUPERIOR HO & TRANSPORTASI BC" terdaftar di HO/Dermaga tapi anaknya PJA
        // operasional di site lain (BMO/GMO/LMO) — PIC HO/Dermaga jadi 32 perusahaan tidak spesifik. PJA
        // bertipe payung (Superior/Inspektor/Koordinator) harus dikecualikan sebagai sumber PIC.
        $pjaList = [
            ['id' => '1', 'nama' => 'SUPERIOR HO & TRANSPORTASI BC', 'parent' => '0'],
            ['id' => '2', 'nama' => 'PJA TRANSPORTASI BMC BMO', 'parent' => '1'],
            ['id' => '3', 'nama' => 'Inspektor Mining BC PMO', 'parent' => '0'],
            ['id' => '4', 'nama' => 'Inspektor KDC', 'parent' => '3'],
            ['id' => '5', 'nama' => 'KOORDINATOR PJA EKSPLORASI', 'parent' => '0'],
            ['id' => '6', 'nama' => 'PJA AKTIFITAS LOGGING', 'parent' => '5'],
            ['id' => '7', 'nama' => 'PJA IT SUPPORT & OPERATION HO', 'parent' => '0'],
            ['id' => '8', 'nama' => 'PJA IT SUPPORT & OPERATION', 'parent' => '7'],
        ];
        $perusahaanPerPja = [
            ['pja_id' => '2', 'perusahaan' => 'PT Bumi Mining Coal'],
            ['pja_id' => '4', 'perusahaan' => 'PT Kaltim Diamond Coal'],
            ['pja_id' => '6', 'perusahaan' => 'PT Eksplorasi Jaya'],
            ['pja_id' => '8', 'perusahaan' => 'PT Unggul Jaya Berkah'],
        ];

        $hasil = DrmMasterDb::picPerusahaanPerNamaPja($pjaList, $perusahaanPerPja);

        $this->assertArrayNotHasKey(DrmMasterDb::normNamaPja('SUPERIOR HO & TRANSPORTASI BC'), $hasil);
        $this->assertArrayNotHasKey(DrmMasterDb::normNamaPja('Inspektor Mining BC PMO'), $hasil);
        $this->assertArrayNotHasKey(DrmMasterDb::normNamaPja('KOORDINATOR PJA EKSPLORASI'), $hasil);
        // PJA non-payung tetap diproses normal.
        $this->assertSame(['PT Unggul Jaya Berkah'], $hasil[DrmMasterDb::normNamaPja('PJA IT SUPPORT & OPERATION HO')]);
    }

    public function test_pic_perusahaan_per_lokasi_digabung_dari_semua_pja_pemilik_murni_tanpa_db(): void
    {
        // Satu lokasi (LMO, Area X) dimiliki 2 PJA berbeda (kasus nyata: 61% lokasi dimiliki >1 PJA) —
        // PIC-nya digabung semua, bukan dipisah per PJA (keputusan user 26 Nov 2026).
        $lokasiPja = [
            ['LMO', 'Area X', 'PJA Satu'],
            ['LMO', 'Area X', 'PJA Dua'],
            ['LMO', 'Area Lain', 'PJA Tiga'],
        ];
        $pjaList = [
            ['id' => '1', 'nama' => 'PJA Satu', 'parent' => '0'],
            ['id' => '2', 'nama' => 'Anak Satu', 'parent' => '1'],
            ['id' => '3', 'nama' => 'PJA Dua', 'parent' => '0'],
            ['id' => '4', 'nama' => 'Anak Dua', 'parent' => '3'],
            ['id' => '5', 'nama' => 'PJA Tiga', 'parent' => '0'],
        ];
        $perusahaanPerPja = [
            ['pja_id' => '2', 'perusahaan' => 'PT Anak Satu'],
            ['pja_id' => '4', 'perusahaan' => 'PT Anak Dua'],
        ];

        $hasil = DrmMasterDb::picPerusahaanPerLokasi($lokasiPja, $pjaList, $perusahaanPerPja);

        $this->assertSame(['PT Anak Dua', 'PT Anak Satu'], $hasil['LMO|Area X']);
        // PJA Tiga tidak punya anak sama sekali — Area Lain tidak muncul di hasil (bukan baris kosong).
        $this->assertArrayNotHasKey('LMO|Area Lain', $hasil);
    }
}
