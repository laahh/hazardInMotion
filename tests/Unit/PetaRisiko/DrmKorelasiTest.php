<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmKorelasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Alur baseline (aktivitas → sub → bahaya → label SAP → grup → detail lokasi → PJA). SQLite memori di koneksi
 * tersendiri (bukan database .env) + snapshot palsu (Storage::fake).
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
        // Kolom perusahaan (ASLI, bukan nama_pja) dipakai simpulPja() sejak fitur filter Site/Perusahaan 26 Nov 2026.
        (require base_path('database/migrations/2026_11_24_100000_add_perusahaan_to_drm_master_lokasi_pja_table.php'))->up();
        // Kolom pja_anak dipakai simpulPja() sejak fitur kolom "PJA Child" 26 Nov 2026.
        (require base_path('database/migrations/2026_11_26_100000_add_pja_anak_to_drm_master_lokasi_pja_table.php'))->up();
        // drm_master_lokasi_pic dipakai simpulDl() sejak fitur "PIC Perusahaan" di detail panel, 26 Nov 2026.
        (require base_path('database/migrations/2026_11_26_110000_create_drm_master_lokasi_pic_table.php'))->up();
        // drm_master_pja_perusahaan dipakai simpulPja() utk menyaring PJA Child per perusahaan, 26 Nov 2026.
        (require base_path('database/migrations/2026_11_26_120000_create_drm_master_pja_perusahaan_table.php'))->up();
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

    public function test_alur_aktivitas_dari_sub_bahaya_label_sampai_pja(): void
    {
        $a = app(DrmKorelasi::class)->alur('aktivitas', '1');
        $node = collect($a['node'])->keyBy('key');
        $kolom = fn (string $k) => $node[$k]['kolom'];

        $this->assertSame('a:1', $a['pusat']);
        $this->assertSame(['aktivitas', 'sub', 'bahaya', 'label', 'grup', 'dl', 'pja', 'pja_anak'], $a['kolom']);
        // Dua sub aktivitas berbeda, dua bahaya, status SAP dari snapshot.
        $this->assertSame(2, $node->where('jenis', 'sub')->count());
        $this->assertSame('K', $node['b:1']['status']);
        $this->assertSame('G', $node['b:2']['status']);
        // Label Ketidaksesuaian yang sama (beda huruf/spasi) = satu simpul untuk kedua bahaya.
        $this->assertSame(2, $node->where('jenis', 'ket')->count());
        // Grup → detail lokasi → PJA → PJA Child.
        $this->assertSame([0, 1, 2, 4, 5, 6, 7], [$kolom('a:1'), $node->firstWhere('jenis', 'sub')['kolom'], $kolom('b:1'), $kolom('g:20'), $kolom('d:1'), $kolom('p:'.md5('PJA Workshop')), $kolom('pa:'.md5('PJA Mitra A'))]);
        // Semua relasi berarah kiri → kanan.
        foreach ($a['edge'] as $e) {
            $this->assertLessThan($node[$e['to']]['kolom'], $node[$e['from']]['kolom']);
        }
        $this->assertContains(['from' => 'g:20', 'to' => 'd:1'], $a['edge']);
        // PJA Workshop punya 2 PJA Child (Mitra A & B, lihat isi()).
        $this->assertSame(['PJA Mitra A', 'PJA Mitra B'], $node->where('jenis', 'pja_anak')->pluck('label')->sort()->values()->all());
        // PIC Perusahaan (drm_master_lokasi_pic) nempel di simpul Detail Lokasi BMO 1/Workshop.
        $this->assertSame(['PT Mitra Jaya'], $node['d:1']['pic']);
        $this->assertContains(['from' => 'p:'.md5('PJA Workshop'), 'to' => 'pa:'.md5('PJA Mitra A')], $a['edge']);
        $this->assertSame('2026-11-08', $a['minggu']);
    }

    public function test_alur_label_dan_pja_dibaca_dari_arah_berbeda(): void
    {
        $svc = app(DrmKorelasi::class);

        $label = $svc->alur('ket', 'BAHAYA MEKANIKAL / terjepit');
        $node = collect($label['node']);
        $this->assertSame(['b:1', 'b:2'], $node->where('jenis', 'bahaya')->pluck('key')->sort()->values()->all());
        $this->assertTrue($node->contains('key', 'a:1'));

        $pja = $svc->alur('pja', 'PJA Workshop');
        $this->assertSame('p:'.md5('PJA Workshop'), $pja['pusat']);
        $this->assertContains(['from' => 'd:1', 'to' => $pja['pusat']], $pja['edge']);
        $this->assertContains(['from' => 'a:1', 'to' => 'g:20'], $pja['edge']);
    }

    /**
     * Filter Site/Perusahaan toolbar korelasi (fitur 26 Nov 2026): alur baru dari sisi lokasi, BUKAN menyaring
     * alur yang sedang terbuka. BMO 1/Workshop cuma dipakai "PT Bukit Makmur Mandiri Utama"; LMO/ROM dipakai
     * "PT Berau Coal" & "PT Pama Persada" — supaya filter site vs perusahaan bisa dibedakan hasilnya.
     */
    public function test_alur_site_perusahaan_dari_filter_toolbar(): void
    {
        $svc = app(DrmKorelasi::class);

        $site = $svc->alur('site_perusahaan', 'BMO 1||');
        $node = collect($site['node']);
        $this->assertSame('a:1', $site['pusat']);
        $this->assertTrue($node->contains(fn ($n) => $n['jenis'] === 'pja' && $n['label'] === 'PJA Workshop'));
        $this->assertFalse($node->contains(fn ($n) => $n['jenis'] === 'pja' && $n['label'] === 'PJA ROM LMO'));
        // Tanpa filter Perusahaan: PJA Child tampil semua (Mitra A & B), tidak disaring.
        $this->assertSame(['PJA Mitra A', 'PJA Mitra B'], $node->where('jenis', 'pja_anak')->pluck('label')->sort()->values()->all());
        // Sub aktivitas, Bahaya-risiko & Label SAP sebelumnya hilang di alur ini (ditemukan user 26 Nov 2026 —
        // cuma Aktivitas langsung ke Grup); sekarang harus ikut tampil seperti alur fokus lain.
        $this->assertSame(2, $node->where('jenis', 'sub')->count());
        $this->assertTrue($node->contains('key', 'b:1'));
        $this->assertTrue($node->contains('key', 'b:2'));
        $this->assertSame(2, $node->where('jenis', 'ket')->count());
        $this->assertContains(['from' => 'a:1', 'to' => $node->firstWhere('jenis', 'sub')['key']], $site['edge']);
        $this->assertContains(['from' => 'b:1', 'to' => 'g:20'], $site['edge']);

        // Filter Site + Perusahaan sekaligus (koreksi user 26 Nov 2026): PJA Child disaring ke perusahaan
        // LANGSUNG-nya sendiri (drm_master_pja_perusahaan) — cuma Mitra A, bukan Mitra B meski keduanya anak
        // PJA Workshop yang sama.
        $sitePerusahaan = $svc->alur('site_perusahaan', 'BMO 1||PT Bukit Makmur Mandiri Utama');
        $node = collect($sitePerusahaan['node']);
        $this->assertTrue($node->contains(fn ($n) => $n['jenis'] === 'pja' && $n['label'] === 'PJA Workshop'));
        $this->assertSame(['PJA Mitra A'], $node->where('jenis', 'pja_anak')->pluck('label')->values()->all());

        $perusahaan = $svc->alur('site_perusahaan', '||PT Berau Coal');
        $node = collect($perusahaan['node']);
        $this->assertSame('a:2', $perusahaan['pusat']);
        $this->assertTrue($node->contains(fn ($n) => $n['jenis'] === 'pja' && $n['label'] === 'PJA ROM LMO'));
        $this->assertFalse($node->contains(fn ($n) => $n['jenis'] === 'pja' && $n['label'] === 'PJA Workshop'));
        $this->assertTrue($node->contains('key', 'b:3'));
        $this->assertContains(['from' => 'b:3', 'to' => 'g:3'], $perusahaan['edge']);

        // Irisan site BMO 1 × perusahaan yang cuma ada di LMO: kosong.
        $this->expectException(InvalidArgumentException::class);
        $svc->alur('site_perusahaan', 'BMO 1||PT Berau Coal');
    }

    /**
     * Bug ditemukan user 26 Nov 2026 lewat filter Site BMO 2 × Perusahaan: grup lokasi "Hauling Road" (aslinya)
     * kosong total di kolom Aktivitas HIRA/Sub Aktivitas/Bahaya-risiko/Label SAP — panel Rincian menunjukkan
     * "Berasal dari: —" — padahal grupnya sendiri tetap tampil lewat Detail Lokasi/PJA. Penyebabnya:
     * dariSitePerusahaan() dulu nge-cap bahaya-risiko dengan take(60) FLAT GLOBAL sebelum tahu baris itu milik
     * grup mana, jadi grup yang baris-nya kebetulan "telat" di urutan hasil query bisa kebagian nol. Fixture ini
     * mereproduksi persis: grup besar (60 baris, di-insert LEBIH DULU) vs grup kecil (1 baris, di-insert
     * TERAKHIR) dalam satu filter site+perusahaan yang sama — tanpa jatah PER GRUP, baris grup kecil itu akan
     * selalu tersisih oleh take(60) global (SQLite tanpa ORDER BY mengembalikan baris sesuai urutan insert).
     */
    public function test_alur_site_perusahaan_jatah_bahaya_per_grup_bukan_flat_global(): void
    {
        DB::table('drm_master_grup')->insert([
            ['gid' => 900, 'nama' => 'Grup Starvasi Besar', 'aktif' => true],
            ['gid' => 901, 'nama' => 'Grup Starvasi Kecil', 'aktif' => true],
        ]);
        DB::table('drm_master_aktivitas')->insert([
            ['aid' => 900, 'aktivitas' => 'Aktivitas Starvasi Besar', 'kelompok' => 'K10'],
            ['aid' => 901, 'aktivitas' => 'Aktivitas Starvasi Kecil', 'kelompok' => 'K10'],
        ]);
        foreach (range(2000, 2059) as $ri) {
            DB::table('drm_master_baris')->insert(['ri' => $ri, 'aid' => 900, 'aktivitas' => 'x', 'sub' => 'Sub Besar', 'bahaya' => 'Bahaya Besar '.$ri, 'kb' => 'Low', 'aktif' => true,
                'item_sumber_observasi' => null, 'item_sumber_oak' => null, 'item_sumber_ketidaksesuaian' => null]);
            DB::table('drm_master_grup_baris')->insert(['gid' => 900, 'ri' => $ri]);
        }
        DB::table('drm_master_baris')->insert(['ri' => 2999, 'aid' => 901, 'aktivitas' => 'x', 'sub' => 'Sub Kecil', 'bahaya' => 'Bahaya Kecil', 'kb' => 'Low', 'aktif' => true,
            'item_sumber_observasi' => null, 'item_sumber_oak' => null, 'item_sumber_ketidaksesuaian' => null]);
        DB::table('drm_master_grup_baris')->insert(['gid' => 901, 'ri' => 2999]);
        DB::table('drm_master_dl')->insert([
            ['id' => 900, 'site' => 'BMO 2', 'lokasi' => 'Area Besar', 'detail' => 'AB 1', 'gid' => 900, 'aktif' => true],
            ['id' => 901, 'site' => 'BMO 2', 'lokasi' => 'Area Kecil', 'detail' => 'AK 1', 'gid' => 901, 'aktif' => true],
        ]);
        DB::table('drm_master_lokasi_pja')->insert([
            ['site' => 'BMO 2', 'lokasi' => 'Area Besar', 'nama_pja' => 'PJA Starvasi Besar', 'perusahaan' => 'PT Starvasi Test', 'pja_anak' => null, 'aktif' => true],
            ['site' => 'BMO 2', 'lokasi' => 'Area Kecil', 'nama_pja' => 'PJA Starvasi Kecil', 'perusahaan' => 'PT Starvasi Test', 'pja_anak' => null, 'aktif' => true],
        ]);

        $r = app(DrmKorelasi::class)->alur('site_perusahaan', 'BMO 2||PT Starvasi Test');
        $node = collect($r['node']);
        $edge = collect($r['edge']);

        $this->assertTrue($node->contains(fn ($n) => $n['jenis'] === 'grup' && $n['label'] === 'Grup Starvasi Kecil'));
        $this->assertTrue($node->contains(fn ($n) => $n['jenis'] === 'pja' && $n['label'] === 'PJA Starvasi Kecil'));
        // Sebelum perbaikan: dua baris ini GAGAL (grup kecil nol bahaya-risiko sama sekali, persis laporan user).
        $this->assertTrue($node->contains(fn ($n) => $n['jenis'] === 'aktivitas' && $n['label'] === 'Aktivitas Starvasi Kecil'));
        $this->assertTrue($node->contains(fn ($n) => $n['jenis'] === 'bahaya' && $n['label'] === 'Bahaya Kecil'));
        $grupKecil = $node->first(fn ($n) => $n['jenis'] === 'grup' && $n['label'] === 'Grup Starvasi Kecil');
        $this->assertGreaterThan(0, $edge->where('to', $grupKecil['key'])->count(), 'Grup kecil harus punya garis masuk dari bahaya-risiko, bukan cuma dari DL/PJA.');

        // Grup besar tetap dibatasi jatahnya (bukan sampai 60 semua), tapi tidak sampai nol juga.
        $jumlahBahayaBesar = $node->where('jenis', 'bahaya')->filter(fn ($n) => str_starts_with($n['label'], 'Bahaya Besar'))->count();
        $this->assertGreaterThan(0, $jumlahBahayaBesar);
        $this->assertLessThan(60, $jumlahBahayaBesar);
    }

    public function test_cari_pusat_bawaan_dan_simpul_tidak_dikenal(): void
    {
        $svc = app(DrmKorelasi::class);
        $jenis = array_column($svc->cari('terjepit'), 'jenis');

        $this->assertContains('bahaya', $jenis);
        $this->assertContains('ket', $jenis);
        $this->assertSame([], $svc->cari('x'));
        // Aktivitas dengan baris terbanyak.
        $this->assertSame(['jenis' => 'aktivitas', 'id' => '1'], $svc->pusatBawaan());

        $this->expectException(InvalidArgumentException::class);
        $svc->alur('grup', '999');
    }

    private function isi(): void
    {
        DB::table('drm_master_kelompok')->insert([['kode' => 'K5', 'nama' => 'CPP', 'regex' => '', 'urutan' => 5], ['kode' => 'K10', 'nama' => 'Perawatan Unit', 'regex' => '', 'urutan' => 10]]);
        DB::table('drm_master_grup')->insert([['gid' => 3, 'nama' => 'CPP / ROM', 'aktif' => true], ['gid' => 20, 'nama' => 'Workshop/Pit Stop', 'aktif' => true]]);
        DB::table('drm_master_aktivitas')->insert([['aid' => 1, 'aktivitas' => 'Perawatan unit', 'kelompok' => 'K10'], ['aid' => 2, 'aktivitas' => 'Fixed plant', 'kelompok' => 'K5']]);
        $baris = [
            [1, 1, 'Servis roller', 'Terjepit roller', "Bahaya Mekanikal / Terjepit"],
            [2, 1, 'Ganti ban', 'Terjepit pintu', "bahaya  mekanikal / TERJEPIT\nKebakaran / Api"],
            [3, 2, 'Fixed plant', 'Debu batubara', null],
        ];
        foreach ($baris as [$ri, $aid, $sub, $bahaya, $ket]) {
            DB::table('drm_master_baris')->insert(['ri' => $ri, 'aid' => $aid, 'aktivitas' => 'x', 'sub' => $sub, 'bahaya' => $bahaya, 'kb' => 'High', 'aktif' => true,
                // Mode label per baris aktif bila kolom Observasi terisi (DrmMasterDb::labelDiBaris).
                'item_sumber_observasi' => $ri === 1 ? 'Inspeksi unit' : null, 'item_sumber_oak' => null, 'item_sumber_ketidaksesuaian' => $ket]);
        }
        DB::table('drm_master_grup_baris')->insert([['gid' => 20, 'ri' => 1], ['gid' => 20, 'ri' => 2], ['gid' => 3, 'ri' => 3]]);
        DB::table('drm_master_dl')->insert([
            ['id' => 1, 'site' => 'BMO 1', 'lokasi' => 'Workshop', 'detail' => 'WS 1', 'gid' => 20, 'aktif' => true],
            ['id' => 2, 'site' => 'LMO', 'lokasi' => 'ROM', 'detail' => 'ROM 1', 'gid' => 3, 'aktif' => true],
        ]);
        DB::table('drm_master_lokasi_pja')->insert([
            ['site' => 'BMO 1', 'lokasi' => 'Workshop', 'nama_pja' => 'PJA Workshop', 'perusahaan' => 'PT Bukit Makmur Mandiri Utama', 'pja_anak' => "PJA Mitra A\nPJA Mitra B", 'aktif' => true],
            ['site' => 'LMO', 'lokasi' => 'ROM', 'nama_pja' => 'PJA ROM LMO', 'perusahaan' => "PT Berau Coal\nPT Pama Persada", 'pja_anak' => null, 'aktif' => true],
        ]);
        DB::table('drm_master_lokasi_pic')->insert([
            ['site' => 'BMO 1', 'lokasi' => 'Workshop', 'pic_perusahaan' => 'PT Mitra Jaya', 'aktif' => true],
        ]);
        // PJA Mitra A = PT Bukit Makmur Mandiri Utama (BUMA); PJA Mitra B = perusahaan lain — supaya filter
        // Perusahaan=BUMA di simpulPja() cuma loloskan Mitra A, bukan keduanya (koreksi user 26 Nov 2026).
        DB::table('drm_master_pja_perusahaan')->insert([
            ['nama_pja' => 'PJA Mitra A', 'perusahaan' => 'PT Bukit Makmur Mandiri Utama'],
            ['nama_pja' => 'PJA Mitra B', 'perusahaan' => 'PT Lain Saja'],
        ]);
    }
}
