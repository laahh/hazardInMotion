<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\Master\DrmAlurEdit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Edit master dari halaman Alur baseline. SELALU SQLite memori di koneksi tersendiri, tidak pernah database .env.
 */
class DrmAlurEditTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        DB::purge('uji_drm');
        DB::setDefaultConnection($this->koneksiAsal);
        parent::tearDown();
    }

    public function test_aktivitas_sub_dan_bahaya(): void
    {
        $svc = app(DrmAlurEdit::class);

        $this->assertStringContainsString('nama diganti, kelompok diganti', $svc->ubahAktivitas(1, 'Perawatan unit berat', 'K5'));
        $this->assertSame(2, DB::table('drm_master_baris')->where('aktivitas', 'Perawatan unit berat')->count());
        $this->assertSame('K5', DB::table('drm_master_aktivitas')->where('aid', 1)->value('kelompok'));

        $this->assertSame('Sub aktivitas diganti untuk 1 bahaya.', $svc->ubahSub('1|Servis', 'Servis berkala'));
        $this->assertSame(1, $svc->simpul('sub', '1|Servis berkala')['baris']);

        $this->assertSame('Bahaya-risiko disimpan.', $svc->ubahBahaya(1, 'Terjepit roller conveyor', 'Significant', false));
        $b = DB::table('drm_master_baris')->where('ri', 1)->first();
        $this->assertSame(['Terjepit roller conveyor', 'Significant', 0], [$b->bahaya, $b->kb, (int) $b->aktif]);
        $this->assertSame('Tidak ada perubahan.', $svc->ubahBahaya(1, 'Terjepit roller conveyor', 'Significant', false));
        // Setiap perubahan tercatat.
        $this->assertGreaterThanOrEqual(4, DB::table('drm_master_log')->count());

        $this->expectException(InvalidArgumentException::class);
        $svc->ubahBahaya(1, 'x', 'Tinggi', true);
    }

    public function test_label_grup_dl_dan_pja(): void
    {
        $svc = app(DrmAlurEdit::class);

        // Label: dicocokkan tanpa beda huruf/spasi seperti sync.
        $this->assertSame('Label sudah ada di baris ini.', $svc->label(1, 'KET', 'BAHAYA mekanikal / terjepit', true));
        $this->assertSame('Label ditambahkan.', $svc->label(1, 'OAK', 'Maintenance / Servis', true));
        $this->assertSame(['Maintenance / Servis'], $svc->simpul('bahaya', '1')['label']['OAK']);
        $this->assertSame('Label dilepas.', $svc->label(1, 'KET', 'bahaya  mekanikal / Terjepit', false));
        $this->assertSame([], $svc->simpul('bahaya', '1')['label']['KET']);

        // Grup lokasi (M05).
        $this->assertSame('Bahaya ditambahkan ke grup lokasi.', $svc->grupBaris(1, 3, true));
        $this->assertSame(['CPP / ROM', 'Workshop'], array_column($svc->simpul('bahaya', '1')['grup'], 'nama'));
        $this->assertSame('Bahaya dikeluarkan dari grup lokasi.', $svc->grupBaris(1, 20, false));

        // Detail lokasi pindah grup.
        $this->assertSame('Detail lokasi disimpan.', $svc->ubahDl(1, 3, true));
        $this->assertSame(3, (int) DB::table('drm_master_dl')->where('id', 1)->value('gid'));

        // PJA per lokasi: hanya nama yang dikenal; lepas = nonaktif (tidak dihapus).
        $this->assertSame('PJA ditambahkan ke lokasi.', $svc->pjaLokasiUbah('BMO 1', 'Workshop', 'PJA CPP', true));
        $this->assertSame(['PJA CPP', 'PJA Workshop'], $svc->simpul('dl', '1')['pja']);
        $this->assertSame('PJA dilepas dari lokasi.', $svc->pjaLokasiUbah('BMO 1', 'Workshop', 'PJA Workshop', false));
        $this->assertSame(['PJA CPP'], $svc->simpul('dl', '1')['pja']);

        $this->expectException(InvalidArgumentException::class);
        $svc->pjaLokasiUbah('BMO 1', 'Workshop', 'PJA Salah Ketik', true);
    }

    public function test_label_ganti_dan_hapus_di_semua_bahaya(): void
    {
        $svc = app(DrmAlurEdit::class);
        // ri 2 memakai label yang sama dengan ejaan berbeda, ditambah label lain.
        DB::table('drm_master_baris')->where('ri', 2)->update(['item_sumber_ketidaksesuaian' => "bahaya  MEKANIKAL / terjepit\nKebakaran / Api"]);

        $l = $svc->simpul('ket', 'Bahaya Mekanikal / Terjepit');
        $this->assertSame([1, 2], array_column($l['baris'], 'ri'));

        $this->assertSame('Label diganti di 2 bahaya-risiko.', $svc->gantiLabel('KET', 'Bahaya Mekanikal / Terjepit', 'Bahaya Mekanikal / Terjepit Mesin'));
        $this->assertSame(['Bahaya Mekanikal / Terjepit Mesin', 'Kebakaran / Api'], $svc->simpul('bahaya', '2')['label']['KET']);
        $this->assertSame([], $svc->simpul('ket', 'Bahaya Mekanikal / Terjepit')['baris']);

        // Ganti ke label yang sudah ada di baris → tidak digandakan.
        $svc->gantiLabel('KET', 'Kebakaran / Api', 'Bahaya Mekanikal / Terjepit Mesin');
        $this->assertSame(['Bahaya Mekanikal / Terjepit Mesin'], $svc->simpul('bahaya', '2')['label']['KET']);

        $this->assertSame('Label dihapus dari 2 bahaya-risiko.', $svc->hapusLabelSemua('KET', 'bahaya mekanikal / terjepit mesin'));
        $this->assertSame([], $svc->simpul('bahaya', '1')['label']['KET']);

        // Label lama tanpa format "k / sk" tetap bisa dilepas, tapi tidak bisa ditambahkan.
        DB::table('drm_master_baris')->where('ri', 1)->update(['item_sumber_ketidaksesuaian' => 'Label lama tanpa garis miring']);
        $this->assertSame('Label dilepas.', $svc->label(1, 'KET', 'Label lama tanpa garis miring', false));
        $this->expectException(InvalidArgumentException::class);
        $svc->gantiLabel('KET', 'x / y', 'tanpa format');
    }

    private function isi(): void
    {
        DB::table('drm_master_kelompok')->insert([['kode' => 'K5', 'nama' => 'CPP', 'regex' => '', 'urutan' => 5], ['kode' => 'K10', 'nama' => 'Perawatan', 'regex' => '', 'urutan' => 10]]);
        DB::table('drm_master_grup')->insert([['gid' => 3, 'nama' => 'CPP / ROM', 'aktif' => true], ['gid' => 20, 'nama' => 'Workshop', 'aktif' => true]]);
        DB::table('drm_master_aktivitas')->insert([['aid' => 1, 'aktivitas' => 'Perawatan unit', 'kelompok' => 'K10']]);
        DB::table('drm_master_baris')->insert([
            ['ri' => 1, 'aid' => 1, 'aktivitas' => 'Perawatan unit', 'sub' => 'Servis', 'bahaya' => 'Terjepit', 'kb' => 'High', 'aktif' => true,
                'item_sumber_observasi' => 'Inspeksi unit', 'item_sumber_oak' => null, 'item_sumber_ketidaksesuaian' => 'Bahaya Mekanikal / Terjepit'],
            ['ri' => 2, 'aid' => 1, 'aktivitas' => 'Perawatan unit', 'sub' => 'Ganti ban', 'bahaya' => 'Ban meledak', 'kb' => 'High', 'aktif' => true,
                'item_sumber_observasi' => null, 'item_sumber_oak' => null, 'item_sumber_ketidaksesuaian' => null],
        ]);
        DB::table('drm_master_grup_baris')->insert([['gid' => 20, 'ri' => 1], ['gid' => 20, 'ri' => 2]]);
        DB::table('drm_master_dl')->insert([['id' => 1, 'site' => 'BMO 1', 'lokasi' => 'Workshop', 'detail' => 'WS 1', 'gid' => 20, 'aktif' => true]]);
        DB::table('drm_master_lokasi_pja')->insert([
            ['site' => 'BMO 1', 'lokasi' => 'Workshop', 'nama_pja' => 'PJA Workshop', 'aktif' => true],
            ['site' => 'BMO 1', 'lokasi' => 'CPP', 'nama_pja' => 'PJA CPP', 'aktif' => true],
        ]);
    }
}
