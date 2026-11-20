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
        // PJA Inspektor: dikecualikan dari dashboard, tetapi di rapor publik tetap muncul.
        Storage::disk('local')->put('peta-risiko-drm/pja/88.json', json_encode(['info' => ['nama' => 'Inspektor Mining Uji', 'site' => 'BMO 1'], ...array_intersect_key($this->berkas(), ['personil' => 1])]));
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
        $this->assertSame(['PJA UJI', 'Inspektor Mining Uji'], array_column($hasil['pja'], 'nama'));
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
        // Status kartu KPI Beranda: hid 1 terkontrol (terdeteksi, tidak bermasalah), hid 3 ada gap, hid 2 open risk.
        $this->assertSame([1, 1, 1], [$u['idk'], $u['idg'], $u['tid']]);
        $this->assertSame(1, end($r['tren'])['idk']);

        // Coverage area: Front 1 dilaporkan 5 (3 oleh tim PJA), Front 2 tanpa laporan → tampil paling atas.
        $c = $r['coverage'];
        $this->assertSame([2, 1, 1, 5, 3], [$c['dl'], $c['dlAda'], $c['dlTim'], $c['laporan'], $c['laporanTim']]);
        $this->assertSame('Front 2', $c['daftar'][0]['detlok']);
        $this->assertSame([true, '2026-11-08'], [$c['perMinggu'], $c['minggu']]);

        // Pencapaian SAP personil: baris sendiri di atas, SID orang lain tidak dikirim (halaman publik).
        $pp = $r['personilPja'];
        $this->assertSame([2, 1, 1, 1, 5], [$pp['total'], $pp['bc'], $pp['aktif7'], $pp['tanpaLaporan'], $pp['laporan']]);
        $this->assertTrue($pp['daftar'][0]['saya']);
        $this->assertSame(80.0, $pp['daftar'][0]['bearc']['persen']);
        $this->assertSame(['coach' => 1, 'obs' => 4, 'ins' => 2, 'hzr' => 2], $pp['daftar'][0]['bearc']['target']);
        $this->assertSame('PJA MITRA UJI', $pp['daftar'][1]['pjaMitra']);
        $this->assertArrayNotHasKey('sid', $pp['daftar'][1]);
        $this->assertSame([80.0, 0, 1], [$pp['rataPersen'], $pp['capai'], $pp['adaBearc']]);
        // Kelompok: PJA utama dulu, lalu PJA child (Mitra); daftar terurut mengikuti kelompok.
        $this->assertSame([['PJA UJI', true, 1, 5], ['PJA MITRA UJI', false, 1, 0]],
            array_map(fn ($g) => [$g['nama'], $g['utama'], $g['n'], $g['laporan']], $pp['grup']));
        $this->assertSame(['', 'PJA MITRA UJI'], array_column($pp['daftar'], 'grup'));

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
                'perusahaan' => 'PT Berau Coal', 'site' => 'BMO 1', 'harian' => [0, 0, 1, 0, 0, 0, 0, 2, 0, 0, 0, 0, 2, 0], 'bc' => true],
                ['pja_id' => '78', 'pja' => 'PJA MITRA UJI', 'tipe' => 'Mitra', 'kategori' => 'Layer', 'sid' => 'X1Y2Z', 'nama' => 'ANDI',
                    'perusahaan' => 'PT Mitra', 'site' => 'BMO 1', 'harian' => array_fill(0, 14, 0), 'bc' => false]],
            'bearc' => [
                ['sid' => 'U8WAP', 'mulai' => '2026-11-01', 'persen' => 40, 'banned_sap' => 1, 'status_banned' => 'BANNED SAP'],
                ['sid' => 'U8WAP', 'mulai' => '2026-11-08', 'persen' => 80, 'banned_sap' => 0, 'status_banned' => '',
                    't_coach' => 1, 'n_coach' => 1, 't_obs' => 4, 'n_obs' => 3, 't_ins' => 2, 'n_ins' => 1, 't_hzr' => 2, 'n_hzr' => 2],
            ],
            // semua/dedicated = 28 hari; minggu_cov = per minggu Minggu–Sabtu. Rapor memakai minggu terpilih (8 Nov): 5 laporan, 3 oleh tim.
            'coverage' => [['pja' => '77', 'dl' => '10', 'semua' => 12, 'dedicated' => 7, 'minggu_cov' => '2026-11-01:7:4,2026-11-08:5:3']],
            'temuan' => [['dl' => '10', 'tanggal' => '2026-11-16', 'jenis' => 'HAZARD', 'ket' => 'Bahaya Eletrikal', 'subket' => 'Panel terbuka',
                'deskripsi' => 'Panel listrik tanpa barikade', 'status' => 'OPEN', 'pic' => 'X', 'pelapor' => ' Budi ', 'nilai' => '', 'janji' => '2026-11-30', 'aktivitas' => []]],
            'temuanTotal' => 1,
            'insiden' => [],
            'sorot' => [],
        ];
    }
}
