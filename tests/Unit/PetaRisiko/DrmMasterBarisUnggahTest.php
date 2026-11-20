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
 * Master per baris lewat Excel: unduh → edit → unggah → pratinjau → terapkan + riwayat. Sheet "Master baris" untuk
 * aktivitas / sub / bahaya, sheet "Label" satu label per baris (menambah label = menambah baris, tidak menimpa).
 * SELALU SQLite memori di koneksi tersendiri (bukan database bersama).
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
                'umum' => false, 'idh' => '', 'aktif' => true, 'item_sumber_observasi' => '', 'item_sumber_oak' => 'Barging / Langsung', 'item_sumber_ketidaksesuaian' => '',
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

    public function test_sheet_label_satu_label_per_baris_dengan_dropdown(): void
    {
        [, , $path] = $this->unduh();
        $x = IOFactory::load($path);
        $s = $x->getSheetByName(DrmMasterBarisExcel::SHEET_ITEM);

        // Baris 1: 1 Observasi + 2 Ketidaksesuaian = 3 baris; baris 2: 1 OAK.
        $this->assertSame([
            ['ID baris', 'Aktivitas · Bahaya (info)', 'Jenis', 'Label'],
            ['1', 'Barging Batubara · Tergelincir di tongkang', 'Observasi', 'Cast Off Tongkang'],
            ['1', 'Barging Batubara · Tergelincir di tongkang', 'Ketidaksesuaian', 'Bahaya Biologi / Berlubang'],
            ['1', 'Barging Batubara · Tergelincir di tongkang', 'Ketidaksesuaian', 'Rambu / Tidak ada rambu'],
            ['2', 'Barging Batubara · Tali putus', 'OAK', 'Barging / Langsung'],
        ], $s->rangeToArray('A1:D5'));
        // Dropdown Jenis (ketat) dan Label (mengikuti Jenis, label baru boleh), juga di baris kosong untuk menambah.
        $jenis = $s->getDataValidation('C2');
        $this->assertSame(['list', 'stop', '"Observasi,OAK,Ketidaksesuaian"'], [$jenis->getType(), $jenis->getErrorStyle(), $jenis->getFormula1()]);
        $label = $s->getDataValidation('D2');
        $this->assertSame(['list', 'information'], [$label->getType(), $label->getErrorStyle()]);
        $this->assertStringContainsString('INDIRECT(', $label->getFormula1());
        $this->assertTrue($s->dataValidationExists('D500'));
        $this->assertNotNull($x->getNamedRange('L_KET'));
        $this->assertSame('Rambu / Tidak ada rambu', $x->getSheetByName(DrmMasterBarisExcel::SHEET_LABEL)->getCell('C3')->getValue());
    }

    public function test_menambah_label_lewat_baris_baru_tidak_menimpa_label_lama(): void
    {
        [$u, $x, $path] = $this->unduh();
        $this->ubahSel($path, 1, ['sub' => 'Pemuatan batubara', 'kb' => 'Low']); // kb = info, diabaikan
        // Tambah 2 label OAK untuk baris 1 (baris baru), hapus satu Ketidaksesuaian. Baris 2 tidak disentuh.
        $this->aturLabel($path, [
            ['1', 'Observasi', 'Cast Off Tongkang'],
            ['1', 'Ketidaksesuaian', 'Bahaya Biologi / Berlubang'],
            ['1', 'OAK', 'Barging / Langsung - Pelepasan Tongkang'],
            ['1', 'OAK', 'Barging / CCV Barging'],
            ['2', 'OAK', 'Barging / Langsung'],
        ]);

        $p = $u->pratinjau($x->baca($path));
        $this->assertSame([2, 1, 1, 0, 0], [$p['total'], count($p['ubah']), $p['tetap'], count($p['galat']), count($p['konflik'])]);
        $ubah = $p['ubah'][0]['ubah'];
        $this->assertSame(['sub', 'OAK', 'KET'], array_keys($ubah));
        $this->assertSame(['tambah' => ['Barging / Langsung - Pelepasan Tongkang', 'Barging / CCV Barging'], 'hapus' => []], $ubah['OAK']);
        $this->assertSame(['tambah' => [], 'hapus' => ['Rambu / Tidak ada rambu']], $ubah['KET']);

        $this->assertSame(['diubah' => 1, 'dilewati' => []], $u->terapkan($p, 'label-baru.xlsx'));
        $b = DB::table('drm_master_baris')->where('ri', 1)->first();
        $this->assertSame(['Pemuatan batubara', 'High'], [$b->sub, $b->kb]);
        $this->assertSame('Cast Off Tongkang', $b->item_sumber_observasi);
        $this->assertSame("Barging / Langsung - Pelepasan Tongkang\nBarging / CCV Barging", $b->item_sumber_oak);
        $this->assertSame('Bahaya Biologi / Berlubang', $b->item_sumber_ketidaksesuaian);

        // Riwayat: satu catatan per baris (siapa, sebelum → sesudah, berkas) + satu ringkasan unggahan.
        $log = DB::table('drm_master_log')->where('aksi', 'unggah')->orderBy('id')->get();
        $this->assertCount(2, $log);
        $this->assertSame(['1', 'Budi Editor', 7], [$log[0]->kunci, $log[0]->user_nama, (int) $log[0]->user_id]);
        $this->assertSame('', json_decode($log[0]->sebelum, true)['OAK']);
        $this->assertSame('label-baru.xlsx', json_decode($log[0]->sesudah, true)['berkas']);
    }

    public function test_id_tidak_ada_di_sheet_label_tidak_diubah_dan_penanda_kosong_menghapus_semua(): void
    {
        [$u, $x, $path] = $this->unduh();
        // Baris 1 tidak muncul di sheet Label → labelnya tetap; baris 2 hanya penanda kosong → semua labelnya dihapus.
        $this->aturLabel($path, [['2', '', '']]);

        $p = $u->pratinjau($x->baca($path));
        $this->assertSame([2], array_column($p['ubah'], 'ri'));
        $this->assertSame(['tambah' => [], 'hapus' => ['Barging / Langsung']], $p['ubah'][0]['ubah']['OAK']);
        $u->terapkan($p, 'x.xlsx');
        $this->assertSame('Cast Off Tongkang', DB::table('drm_master_baris')->where('ri', 1)->value('item_sumber_observasi'));
        $this->assertSame('', DB::table('drm_master_baris')->where('ri', 2)->value('item_sumber_oak'));
    }

    public function test_konflik_bila_baris_diubah_orang_lain_setelah_diunduh(): void
    {
        [$u, $x, $path] = $this->unduh();
        $this->aturLabel($path, [['1', 'OAK', 'Barging / Baru'], ['2', 'OAK', 'Barging / Langsung']]);
        DB::table('drm_master_baris')->where('ri', 1)->update(['item_sumber_oak' => 'Barging / CCV']); // diubah orang lain

        $p = $u->pratinjau($x->baca($path));
        $this->assertSame([], $p['ubah']);
        $this->assertSame(1, $p['konflik'][0]['ri']);
        $this->assertSame('Barging / CCV', DB::table('drm_master_baris')->where('ri', 1)->value('item_sumber_oak'));
    }

    public function test_galat_divalidasi_dan_baris_dilewati(): void
    {
        [$u, $x, $path] = $this->unduh();
        $this->ubahSel($path, 2, ['bahaya' => '']);
        $this->aturLabel($path, [
            ['1', 'Ketidaksesuaian', 'Tanpa garis miring'],
            ['1', 'Lain-lain', 'Apa saja'],
            ['abc', 'OAK', 'x'],
            ['999', 'OAK', 'y'],
        ]);

        $p = $u->pratinjau($x->baca($path));
        $this->assertSame([], $p['ubah']);
        $pesan = implode(' | ', array_column($p['galat'], 'pesan'));
        $this->assertStringContainsString('Jenis "Lain-lain" tidak dikenal', $pesan);
        $this->assertStringContainsString('ID baris kosong / bukan angka', $pesan);
        $this->assertStringContainsString('Bahaya tidak boleh kosong', $pesan);
        $this->assertStringContainsString('ID baris tidak dikenal', $pesan);
    }

    public function test_unduhan_mengikuti_filter_halaman(): void
    {
        $u = app(DrmMasterBarisUnggah::class);
        $hira = [1 => [['dept' => 'Port and Barging Operation Dept.']]];

        $this->assertSame([1], array_column($u->ekspor(['dep' => 'Port and Barging Operation Dept.'], $hira), 'ri'));
        $this->assertSame([2], array_column($u->ekspor(['kosong' => 'OBS'], $hira), 'ri'));
        $this->assertSame([2], array_column($u->ekspor(['kb' => 'Significant'], $hira), 'ri'));
        $this->assertSame([1], array_column($u->ekspor(['q' => 'tongkang biologi'], $hira), 'ri'));
        $this->assertSame(['OBS' => ['Cast Off Tongkang'], 'OAK' => ['Barging / Langsung'], 'KET' => ['Bahaya Biologi / Berlubang', 'Rambu / Tidak ada rambu']],
            $u->daftarLabel());
    }

    /** @return array{0: DrmMasterBarisUnggah, 1: DrmMasterBarisExcel, 2: string} */
    private function unduh(): array
    {
        $u = app(DrmMasterBarisUnggah::class);
        $x = new DrmMasterBarisExcel();

        return [$u, $x, $this->berkas[] = $x->tulis($u->ekspor([], []), array_values($u->grupAktif()), $u->daftarLabel())];
    }

    /** Ubah sel sheet Master baris, baris data ke-$n (1 = pertama), seperti diedit di Excel. */
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

    /** Ganti seluruh isi sheet Label dengan baris [ID, Jenis, Label], seperti user menambah / menghapus baris. */
    private function aturLabel(string $path, array $baris): void
    {
        $x = IOFactory::load($path);
        $s = $x->getSheetByName(DrmMasterBarisExcel::SHEET_ITEM);
        $s->removeRow(2, max(1, $s->getHighestRow() - 1));
        foreach ($baris as $i => [$ri, $jenis, $label]) {
            $s->fromArray([[$ri, '', $jenis, $label]], null, 'A'.($i + 2), true);
        }
        (new Xlsx($x))->save($path);
    }
}
