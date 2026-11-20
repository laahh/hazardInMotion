<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\Master\DrmMasterBarisExcel;
use App\Services\PetaRisiko\Drm\Master\DrmMasterBarisUnggah;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Master per baris lewat Excel: unduh → edit → unggah → pratinjau → terapkan + riwayat. Yang bisa diedit: aktivitas,
 * sub aktivitas, bahaya, dan tiga Item Sumber. SELALU SQLite memori di koneksi tersendiri (bukan database bersama).
 */
class DrmMasterBarisUnggahTest extends TestCase
{
    private string $koneksiAsal;

    private array $berkas = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite tidak tersedia.');
        }
        $this->koneksiAsal = (string) config('database.default');
        config(['database.connections.uji_drm' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('uji_drm');
        foreach (['2026_11_17_100000_create_drm_master_tables', '2026_11_18_120000_add_item_sumber_to_drm_master_baris_table'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        $kini = now();
        DB::table('drm_master_grup')->insert([['gid' => 1, 'nama' => 'Jetty/Port', 'aktif' => true], ['gid' => 2, 'nama' => 'Loading Point', 'aktif' => true]]);
        DB::table('drm_master_kelompok')->insert(['kode' => 'K7', 'nama' => 'Jetty, Barging & Marine', 'regex' => 'jetty', 'urutan' => 7]);
        DB::table('drm_master_aktivitas')->insert(['aid' => 1, 'aktivitas' => 'Barging Batubara', 'kelompok' => 'K7']);
        DB::table('drm_master_baris')->insert([
            ['ri' => 1, 'aid' => 1, 'aktivitas' => 'Barging Batubara', 'sub' => 'Pemuatan', 'bahaya' => 'Tergelincir di tongkang', 'kb' => 'High',
                'umum' => false, 'idh' => '8332', 'aktif' => true, 'item_sumber_observasi' => 'Cast Off Tongkang', 'item_sumber_oak' => '',
                'item_sumber_ketidaksesuaian' => "Bahaya Biologi / Berlubang\nRambu / Tidak ada rambu", 'created_at' => $kini, 'updated_at' => $kini],
            ['ri' => 2, 'aid' => 1, 'aktivitas' => 'Barging Batubara', 'sub' => 'Penambatan', 'bahaya' => 'Tali putus', 'kb' => 'Significant',
                'umum' => false, 'idh' => '', 'aktif' => true, 'item_sumber_observasi' => '', 'item_sumber_oak' => '', 'item_sumber_ketidaksesuaian' => '',
                'created_at' => $kini, 'updated_at' => $kini],
        ]);
        DB::table('drm_master_grup_baris')->insert(['gid' => 2, 'ri' => 1]);
        Auth::setUser(new GenericUser(['id' => 7, 'name' => 'Budi Editor']));
    }

    protected function tearDown(): void
    {
        foreach ($this->berkas as $f) {
            @unlink($f);
        }
        DB::purge('uji_drm');
        DB::setDefaultConnection($this->koneksiAsal);
        parent::tearDown();
    }

    public function test_unduh_edit_unggah_terapkan_dan_tercatat_di_riwayat(): void
    {
        [$u, $x, $path] = $this->unduh();
        // Edit seperti user: sub aktivitas & bahaya dikoreksi, label Observasi ditambah, satu label Ketidaksesuaian dihapus.
        // Kolom info (risiko awal, grup lokasi) yang diubah di Excel diabaikan. Baris 2 tidak diubah.
        $this->ubahSel($path, 1, ['sub' => 'Pemuatan batubara', 'bahaya' => 'Tergelincir di dek tongkang', 'kb' => 'Low', 'grup' => 'Jetty/Port',
            'OBS' => "Cast Off Tongkang\nPenambatan tongkang", 'KET' => 'Bahaya Biologi / Berlubang']);

        $p = $u->pratinjau($x->baca($path));
        $this->assertSame([2, 1, 1, 0, 0], [$p['total'], count($p['ubah']), $p['tetap'], count($p['galat']), count($p['konflik'])]);
        $ubah = $p['ubah'][0]['ubah'];
        $this->assertSame(['sub', 'bahaya', 'OBS', 'KET'], array_keys($ubah));
        $this->assertSame(['dari' => 'Pemuatan', 'ke' => 'Pemuatan batubara'], $ubah['sub']);
        $this->assertSame(['tambah' => ['Penambatan tongkang'], 'hapus' => []], $ubah['OBS']);
        $this->assertSame(['tambah' => [], 'hapus' => ['Rambu / Tidak ada rambu']], $ubah['KET']);
        $this->assertStringContainsString('Teks bahaya diubah', implode(' ', $p['ubah'][0]['peringatan']));

        $this->assertSame(['diubah' => 1, 'dilewati' => []], $u->terapkan($p, 'mapping-marine.xlsx'));
        $b = DB::table('drm_master_baris')->where('ri', 1)->first();
        $this->assertSame(['Pemuatan batubara', 'Tergelincir di dek tongkang', 'High'], [$b->sub, $b->bahaya, $b->kb]);
        $this->assertSame("Cast Off Tongkang\nPenambatan tongkang", $b->item_sumber_observasi);
        $this->assertSame('Bahaya Biologi / Berlubang', $b->item_sumber_ketidaksesuaian);
        $this->assertSame([2], DB::table('drm_master_grup_baris')->where('ri', 1)->pluck('gid')->map(fn ($v) => (int) $v)->all());

        // Riwayat: satu catatan per baris (siapa, sebelum → sesudah, berkas) + satu ringkasan unggahan.
        $log = DB::table('drm_master_log')->where('aksi', 'unggah')->orderBy('id')->get();
        $this->assertCount(2, $log);
        $this->assertSame(['1', 'Budi Editor', 7], [$log[0]->kunci, $log[0]->user_nama, (int) $log[0]->user_id]);
        $this->assertSame('Tergelincir di tongkang', json_decode($log[0]->sebelum, true)['bahaya']);
        $this->assertSame('mapping-marine.xlsx', json_decode($log[0]->sesudah, true)['berkas']);
        $this->assertSame(1, json_decode($log[1]->sesudah, true)['baris diubah']);
    }

    public function test_konflik_bila_baris_diubah_orang_lain_setelah_diunduh(): void
    {
        [$u, $x, $path] = $this->unduh();
        $this->ubahSel($path, 1, ['OAK' => 'Barging / Langsung']);
        DB::table('drm_master_baris')->where('ri', 1)->update(['item_sumber_oak' => 'Barging / CCV']); // diubah orang lain

        $p = $u->pratinjau($x->baca($path));
        $this->assertSame([], $p['ubah']);
        $this->assertSame(1, $p['konflik'][0]['ri']);
        $this->assertSame('Barging / CCV', DB::table('drm_master_baris')->where('ri', 1)->value('item_sumber_oak'));
    }

    public function test_galat_divalidasi_dan_baris_dilewati(): void
    {
        [$u, $x, $path] = $this->unduh();
        $this->ubahSel($path, 1, ['bahaya' => '', 'KET' => 'Tanpa garis miring']);
        $this->ubahSel($path, 2, ['ri' => '999']);

        $p = $u->pratinjau($x->baca($path));
        $this->assertSame([], $p['ubah']);
        $pesan = implode(' | ', array_column($p['galat'], 'pesan'));
        $this->assertStringContainsString('Bahaya tidak boleh kosong', $pesan);
        $this->assertStringContainsString('ketidaksesuaian / sub ketidaksesuaian', $pesan);
        $this->assertStringContainsString('ID baris tidak dikenal', $pesan);
    }

    public function test_unduhan_mengikuti_filter_halaman(): void
    {
        $u = app(DrmMasterBarisUnggah::class);
        $hira = [1 => [['dept' => 'Port and Barging Operation Dept.']]];

        $this->assertSame([1], array_column($u->ekspor(['dep' => 'Port and Barging Operation Dept.'], $hira), 'ri'));
        $this->assertSame([2], array_column($u->ekspor(['kosong' => 'SEMUA'], $hira), 'ri'));
        $this->assertSame([2], array_column($u->ekspor(['kb' => 'Significant'], $hira), 'ri'));
        $this->assertSame([1], array_column($u->ekspor(['q' => 'tongkang biologi'], $hira), 'ri'));
        $this->assertSame('Port and Barging Operation Dept.', $u->ekspor(['dep' => 'Port and Barging Operation Dept.'], $hira)[0]['dept_hira']);
    }

    /** @return array{0: DrmMasterBarisUnggah, 1: DrmMasterBarisExcel, 2: string} */
    private function unduh(): array
    {
        $u = app(DrmMasterBarisUnggah::class);
        $x = new DrmMasterBarisExcel();

        return [$u, $x, $this->berkas[] = $x->tulis($u->ekspor([], []), array_values($u->grupAktif()))];
    }

    /** Ubah sel baris data ke-$n (1 = baris data pertama) berdasarkan kunci kolom, seperti diedit di Excel. */
    private function ubahSel(string $path, int $n, array $nilai): void
    {
        $x = IOFactory::load($path);
        $s = $x->getSheetByName(DrmMasterBarisExcel::SHEET);
        $kunci = array_keys(DrmMasterBarisExcel::KOLOM);
        foreach ($nilai as $k => $v) {
            $s->setCellValue(Coordinate::stringFromColumnIndex(array_search($k, $kunci, true) + 1).($n + 1), $v);
        }
        (new Xlsx($x))->save($path);
    }
}
