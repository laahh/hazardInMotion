<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\Master\DrmMasterBarisExcel;
use App\Services\PetaRisiko\Drm\Master\DrmMasterBarisUnggah;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function test_satu_sheet_satu_label_per_baris_dengan_dropdown(): void
    {
        [, $x, $path] = $this->unduh();
        $b = IOFactory::load($path);
        $this->assertSame([DrmMasterBarisExcel::SHEET], array_values(array_filter(array_map(
            fn ($ws) => $ws->getSheetState() === 'visible' ? $ws->getTitle() : null, $b->getAllSheets()))));

        // Baris 1: 1 Observasi + 2 Ketidaksesuaian = 3 baris; baris 2: 1 OAK. Aktivitas/bahaya diulang per baris label.
        $this->assertSame([
            ['1', 'Tergelincir di tongkang', 'Observasi', 'Cast Off Tongkang'],
            ['1', 'Tergelincir di tongkang', 'Ketidaksesuaian', 'Bahaya Biologi / Berlubang'],
            ['1', 'Tergelincir di tongkang', 'Ketidaksesuaian', 'Rambu / Tidak ada rambu'],
            ['2', 'Tali putus', 'OAK', 'Barging / Langsung'],
        ], array_map(fn ($r) => [$r['ri'], $r['bahaya'], $r['jenis'], $r['label']], $this->baris($path)));

        $s = $b->getSheetByName(DrmMasterBarisExcel::SHEET);
        $jenis = $s->getDataValidation($x->kol('jenis').'2');
        $this->assertSame(['list', 'stop', '"Observasi,OAK,Ketidaksesuaian"'], [$jenis->getType(), $jenis->getErrorStyle(), $jenis->getFormula1()]);
        $label = $s->getDataValidation($x->kol('label').'2');
        $this->assertSame(['list', 'information'], [$label->getType(), $label->getErrorStyle()]);
        $this->assertStringContainsString('INDIRECT(', $label->getFormula1());
        $this->assertTrue($s->dataValidationExists($x->kol('label').'500'));
        $this->assertNotNull($b->getNamedRange('L_KET'));
    }

    public function test_menambah_label_lewat_baris_baru_tidak_menimpa_label_lama(): void
    {
        [$u, $x, $path] = $this->unduh();
        $r = $this->baris($path);
        // Tambah 2 label OAK untuk ID 1 (baris baru), hapus label "Rambu", ubah sub aktivitas di SATU baris saja.
        $r[0]['sub'] = 'Pemuatan batubara';
        unset($r[2]);
        $r[] = [...$r[1], 'jenis' => 'OAK', 'label' => 'Barging / Langsung - Pelepasan Tongkang', 'versi' => ''];
        $r[] = ['ri' => '1', 'jenis' => 'OAK', 'label' => 'Barging / CCV Barging'];
        $this->tulisUlang($path, $r);

        $p = $u->pratinjau($x->baca($path));
        $this->assertSame([2, 1, 1, 0, 0], [$p['total'], count($p['ubah']), $p['tetap'], count($p['galat']), count($p['konflik'])]);
        $ubah = $p['ubah'][0]['ubah'];
        $this->assertSame(['sub', 'OAK', 'KET'], array_keys($ubah));
        $this->assertSame(['tambah' => ['Barging / Langsung - Pelepasan Tongkang', 'Barging / CCV Barging'], 'hapus' => []], $ubah['OAK']);
        $this->assertSame(['tambah' => [], 'hapus' => ['Rambu / Tidak ada rambu']], $ubah['KET']);

        $this->assertSame(['diubah' => 1, 'dilewati' => []], $u->terapkan($p, 'label-baru.xlsx'));
        $b = DB::table('drm_master_baris')->where('ri', 1)->first();
        $this->assertSame(['Pemuatan batubara', 'High', 'Cast Off Tongkang'], [$b->sub, $b->kb, $b->item_sumber_observasi]);
        $this->assertSame("Barging / Langsung - Pelepasan Tongkang\nBarging / CCV Barging", $b->item_sumber_oak);
        $this->assertSame('Bahaya Biologi / Berlubang', $b->item_sumber_ketidaksesuaian);

        // Riwayat: satu catatan per baris (siapa, sebelum → sesudah, berkas) + satu ringkasan unggahan.
        $log = DB::table('drm_master_log')->where('aksi', 'unggah')->orderBy('id')->get();
        $this->assertCount(2, $log);
        $this->assertSame(['1', 'Budi Editor', 7], [$log[0]->kunci, $log[0]->user_nama, (int) $log[0]->user_id]);
        $this->assertSame('', json_decode($log[0]->sebelum, true)['OAK']);
        $this->assertSame('label-baru.xlsx', json_decode($log[0]->sesudah, true)['berkas']);
    }

    public function test_id_tidak_ada_di_berkas_tidak_diubah_dan_baris_kosong_menghapus_semua(): void
    {
        [$u, $x, $path] = $this->unduh();
        // ID 1 dihapus dari berkas → labelnya tetap; ID 2 tinggal satu baris tanpa Jenis & Label → semua labelnya dihapus.
        $this->tulisUlang($path, [[...$this->baris($path)[3], 'jenis' => '', 'label' => '']]);

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
        $r = $this->baris($path);
        $r[] = ['ri' => '1', 'jenis' => 'OAK', 'label' => 'Barging / Baru'];
        $this->tulisUlang($path, $r);
        DB::table('drm_master_baris')->where('ri', 1)->update(['item_sumber_oak' => 'Barging / CCV']); // diubah orang lain

        $p = $u->pratinjau($x->baca($path));
        $this->assertSame([], $p['ubah']);
        $this->assertSame(1, $p['konflik'][0]['ri']);
        $this->assertSame('Barging / CCV', DB::table('drm_master_baris')->where('ri', 1)->value('item_sumber_oak'));
    }

    public function test_galat_divalidasi_dan_baris_dilewati(): void
    {
        [$u, $x, $path] = $this->unduh();
        $r = $this->baris($path);
        $r[0]['sub'] = 'Sub A';
        $r[1]['sub'] = 'Sub B'; // dua nilai baru berbeda untuk ID yang sama
        $r[3]['jenis'] = 'Lain-lain';
        $r[] = ['ri' => 'abc', 'jenis' => 'OAK', 'label' => 'x'];
        $r[] = ['ri' => '999', 'jenis' => 'OAK', 'label' => 'y'];
        $this->tulisUlang($path, $r);

        $p = $u->pratinjau($x->baca($path));
        $this->assertSame([], $p['ubah']);
        $pesan = implode(' | ', array_column($p['galat'], 'pesan'));
        $this->assertStringContainsString('Sub diisi berbeda di beberapa baris', $pesan);
        $this->assertStringContainsString('Jenis "Lain-lain" tidak dikenal', $pesan);
        $this->assertStringContainsString('ID baris kosong / bukan angka', $pesan);
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

    public function test_site_per_baris_dari_grup_lokasi_dan_departemen_marine(): void
    {
        DB::table('drm_master_dl')->insert([
            ['site' => 'LMO', 'lokasi' => 'Pit A', 'detail' => 'Front', 'gid' => 2, 'aktif' => true],
            ['site' => 'BMO 1', 'lokasi' => 'Pit B', 'detail' => 'Front', 'gid' => 2, 'aktif' => true],
            ['site' => 'GMO', 'lokasi' => 'Pit C', 'detail' => 'Lama', 'gid' => 2, 'aktif' => false],
        ]);
        DB::table('drm_master_grup_baris')->insert(['gid' => 2, 'ri' => 2]);
        $u = app(DrmMasterBarisUnggah::class);

        // Grup lokasi → detail lokasi aktif → site.
        $this->assertSame([1 => ['BMO 1', 'LMO'], 2 => ['BMO 1', 'LMO']], $u->sitePerBaris([]));
        // Departemen HIRA (dokumen yang cocok) Port and Barging Operation → hanya MARINE.
        $hira = [1 => [['dept' => 'Port and Barging Operation Dept.', 'cocok' => true]], 2 => [['dept' => 'Shipping Dept.', 'cocok' => false]]];
        $this->assertSame([1 => ['MARINE'], 2 => ['BMO 1', 'LMO']], $u->sitePerBaris($hira));

        $this->assertSame([1], array_column($u->ekspor(['site' => 'MARINE'], $hira), 'ri'));
        $this->assertSame([2], array_column($u->ekspor(['site' => 'LMO'], $hira), 'ri'));
        $this->assertSame("BMO 1\nLMO", $u->ekspor(['site' => 'LMO'], $hira)[0]['site']);
    }

    /** @return array{0: DrmMasterBarisUnggah, 1: DrmMasterBarisExcel, 2: string} */
    private function unduh(): array
    {
        $u = app(DrmMasterBarisUnggah::class);
        $x = new DrmMasterBarisExcel();

        return [$u, $x, $this->berkas[] = $x->tulis($u->ekspor([], []), $u->daftarLabel())];
    }

    /** Baris data sheet sebagai array berkunci KOLOM (seperti dilihat user). @return list<array<string, string>> */
    private function baris(string $path): array
    {
        $data = IOFactory::load($path)->getSheetByName(DrmMasterBarisExcel::SHEET)->toArray(null, false, false, false);
        $kunci = array_keys(DrmMasterBarisExcel::KOLOM);

        return array_values(array_map(fn ($r) => array_combine($kunci, array_map('strval', array_slice($r, 0, count($kunci)))),
            array_filter(array_slice($data, 1), fn ($r) => trim(implode('', array_map('strval', $r))) !== '')));
    }

    /** Tulis ulang seluruh baris data (seperti user menambah / menghapus / mengubah baris). */
    private function tulisUlang(string $path, array $rows): void
    {
        $x = IOFactory::load($path);
        $s = $x->getSheetByName(DrmMasterBarisExcel::SHEET);
        $s->removeRow(2, max(1, $s->getHighestRow() - 1));
        $kunci = array_keys(DrmMasterBarisExcel::KOLOM);
        foreach (array_values($rows) as $i => $r) {
            $s->fromArray([array_map(fn ($k) => (string) ($r[$k] ?? ''), $kunci)], null, 'A'.($i + 2), true);
        }
        (new Xlsx($x))->save($path);
    }
}
