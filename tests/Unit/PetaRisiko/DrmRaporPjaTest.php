<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmRaporPja;
use App\Services\PetaRisiko\Drm\DrmRepository;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Rapor PJA publik: indeks SID → PJA dan angka rapor dihitung dari berkas snapshot palsu
 * (Storage::fake, tidak menyentuh snapshot asli).
 */
class DrmRaporPjaTest extends TestCase
{
    private const SAMPAI = '2026-11-17'; // Selasa → minggu berjalan (15 Nov) belum penuh, minggu bawaan = 8 Nov

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::disk('local')->put('peta-risiko-drm/meta.json', '{"dibuat":"2026-11-17T08:00:00+08:00","sampai":"'.self::SAMPAI.'","pja":[]}');
        Storage::disk('local')->put('peta-risiko-drm/pja/77.json', json_encode($this->berkas()));
    }

    public function test_format_sid_dinormalkan_dan_input_aneh_ditolak(): void
    {
        $this->assertSame('U8WAP', DrmRaporPja::normSid(' u8wap '));
        $this->assertNull(DrmRaporPja::normSid('ab'));
        $this->assertNull(DrmRaporPja::normSid("U8WAP' OR 1=1"));
    }

    public function test_sid_dipetakan_ke_pja_dari_daftar_personil(): void
    {
        $rapor = new DrmRaporPja(new DrmRepository());

        $hasil = $rapor->cari('U8WAP');
        $this->assertSame('77', $hasil['pja'][0]['id']);
        $this->assertSame('PJA UJI', $hasil['pja'][0]['nama']);
        $this->assertSame('BUDI', $hasil['orang']['nama']);
        $this->assertNull($rapor->cari('ZZZZZ'));
        $this->assertSame(self::SAMPAI, $rapor->sampai());
    }

    public function test_rapor_menghitung_status_ketat_dan_daftar_belum_ditemukan(): void
    {
        $r = (new DrmRaporPja(new DrmRepository()))->rapor('77', 'U8WAP');

        $this->assertSame('2026-11-08', $r['periode']['minggu']);
        $this->assertFalse($r['periode']['berjalan']);
        // Significant + High: hid 1 sebagian (terkontrol di 1 dari 2 DL), hid 2 tanpa laporan, hid 3 bermasalah.
        $u = $r['ringkas']['utama'];
        $this->assertSame([3, 0, 1, 1, 1, 1], [$u['tot'], $u['full'], $u['part'], $u['gaponly'], $u['none'], $u['closed']]);
        $this->assertSame(1, $r['ringkas']['Medium']['tot']);

        $this->assertSame(1, $r['belum']['total']);
        $this->assertSame('Pengisian bahan bakar', $r['belum']['aktivitas'][0]['nama']);
        $this->assertSame('Kebakaran', $r['belum']['aktivitas'][0]['baris'][0]['bahaya']);
        $this->assertSame(1, $r['gap']['total']);
        $this->assertSame(['belum closed'], $r['gap']['aktivitas'][0]['baris'][0]['masalah']);

        $this->assertSame(5, $r['orang']['totalSap']);
        $this->assertSame(1, $r['temuan']['total']);
        $this->assertTrue($r['temuan']['daftar'][0]['milikSaya']);
        $this->assertSame('Pit A · Front 1', $r['temuan']['daftar'][0]['lokasi']);
    }

    public function test_endpoint_publik_tanpa_login(): void
    {
        $this->get('/form/rapor-pja?sid=u8wap')->assertOk()->assertSee('Rapor PJA')->assertSee('U8WAP');

        $this->getJson('/form/rapor-pja/cari?sid=a')->assertStatus(422)->assertJson(['found' => false]);
        $this->getJson('/form/rapor-pja/cari?sid=ZZZZZ')->assertOk()->assertJson(['found' => false]);
        $this->getJson('/form/rapor-pja/cari?sid=U8WAP&pja=999')->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('rapor.pja.id', '77') // PJA yang tidak memuat SID diabaikan
            ->assertJsonPath('rapor.ringkas.utama.tot', 3);
    }

    /** Dua detail lokasi, empat bahaya; bit minggu indeks 1 (8 Nov): 2 = spesifik, 4 = belum closed. */
    private function berkas(): array
    {
        $bahaya = fn ($hid, $an, $bn, $n1, $kel) => [$hid, 'a'.$kel, $an, $an, $bn, '', $n1, 'Mungkin', 'Major', $n1, 'Mungkin', 'Major', '', '', $kel];

        return [
            'info' => ['id' => 77, 'nama' => 'PJA UJI', 'tipe' => 'BC', 'site' => 'BMO 1', 'dl' => null, 'mitra' => []],
            'lokasi' => [
                ['dl' => '10', 'lokasi' => 'Pit A', 'detlok' => 'Front 1', 'grp' => 'PIT', 'site' => 'BMO 1'],
                ['dl' => '11', 'lokasi' => 'Pit A', 'detlok' => 'Front 2', 'grp' => 'PIT', 'site' => 'BMO 1'],
            ],
            'bahaya' => [
                $bahaya('1', 'Penggalian', 'Tertimbun longsoran', 'Significant', 'K1'),
                $bahaya('2', 'Pengisian bahan bakar', 'Kebakaran', 'High', 'K1'),
                $bahaya('3', 'Hauling', 'Tabrakan unit', 'High', 'K2'),
                $bahaya('4', 'Hauling', 'Debu', 'Medium', 'K2'),
            ],
            'pasangan' => ['10' => '1:0,2:0,3:0,4:0', '11' => '1:0,2:0,3:0,4:0'],
            'beranda' => [
                'kd' => (object) [],
                'sp' => ['10|1' => ['1:2', ''], '10|3' => ['1:6', '']],
                'dlast' => ['10' => '2026-11-12', '11' => ''],
                'umum' => (object) [],
                'rc' => [],
            ],
            'personil' => [['pja_id' => '77', 'pja' => 'PJA UJI', 'tipe' => 'BC', 'kategori' => 'Pengawas', 'sid' => 'U8WAP', 'nama' => 'BUDI',
                'perusahaan' => 'PT Berau Coal', 'site' => 'BMO 1', 'harian' => [0, 0, 1, 0, 0, 0, 0, 2, 0, 0, 0, 0, 2, 0], 'bc' => true]],
            'bearc' => [['sid' => 'U8WAP', 'mulai' => '2026-11-08', 'persen' => 80, 'banned_sap' => 0, 'status_banned' => '']],
            'temuan' => [['dl' => '10', 'tanggal' => '2026-11-16', 'jenis' => 'HAZARD', 'ket' => 'Bahaya Eletrikal', 'subket' => 'Panel terbuka',
                'deskripsi' => 'Panel listrik tanpa barikade', 'status' => 'OPEN', 'pic' => 'X', 'pelapor' => ' Budi ', 'nilai' => '', 'janji' => '2026-11-30', 'aktivitas' => []]],
            'temuanTotal' => 1,
            'insiden' => [],
            'sorot' => [],
        ];
    }
}
