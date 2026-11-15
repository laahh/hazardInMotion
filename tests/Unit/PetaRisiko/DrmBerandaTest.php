<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmBeranda;
use App\Services\PetaRisiko\Drm\DrmSql;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DrmBerandaTest extends TestCase
{
    private const SAMPAI = '2026-11-14'; // Sabtu → minggu berjalan mulai Minggu 8 Nov

    public function test_status_tanpa_laporan_adalah_gap_dan_masalah_mengalahkan_deteksi(): void
    {
        $this->assertSame(['none', 0], DrmBeranda::status(0, 0));
        $this->assertSame(['ok', 0], DrmBeranda::status(DrmBeranda::DETEKSI, 0));
        $this->assertSame(['ok', 0], DrmBeranda::status(0, DrmBeranda::SPESIFIK));
        // OAK stop di kelompok membuat semua baris kelompok bermasalah walau ada deteksi.
        $this->assertSame(['bm', DrmBeranda::OAK_STOP], DrmBeranda::status(DrmBeranda::DETEKSI | DrmBeranda::OAK_STOP, 0));
        $this->assertSame(['bm', DrmBeranda::BELUM_CLOSED | DrmBeranda::BERULANG], DrmBeranda::status(DrmBeranda::DETEKSI, 2 | 4 | 8));
        $this->assertSame(['bm', DrmBeranda::TIDAK_SESUAI], DrmBeranda::status(0, 2 | 32));
        // Bit insiden (harian) bukan masalah dan bukan deteksi.
        $this->assertSame(['none', 0], DrmBeranda::status(0, DrmBeranda::INSIDEN));
    }

    public function test_urai_bit_jarang_dan_minggu_kalender_minggu_sabtu(): void
    {
        $this->assertSame([0 => 3, 2 => 17], DrmBeranda::urai('0:1,2:17,0:2'));
        $this->assertSame([], DrmBeranda::urai(''));

        $b = new DrmBeranda(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI));
        $minggu = $b->minggu();
        $this->assertSame('2026-11-08', $minggu[0]);
        $this->assertSame('2026-11-01', $minggu[1]);
        $this->assertCount((int) config('peta_risiko_drm.beranda.minggu'), $minggu);
        $this->assertSame('2026-10-18', $b->hari0());
    }

    public function test_bahaya_umum_teks_sama_di_empat_kelompok_dalam_satu_grup(): void
    {
        $hira = [];
        foreach (['DNB', 'MNT', 'CG', 'CH'] as $i => $kel) {
            $hira["u$i"] = ['bn' => 'Tidak memakai APD', 'proses' => $kel, 'n1' => 'High'];
        }
        $hira['s1'] = ['bn' => 'Lereng longsor', 'proses' => 'OB', 'n1' => 'Significant'];
        $hidPerDl = ['1' => ['u0', 'u1', 's1'], '2' => ['u2', 'u3'], '3' => ['u0']];
        $dl = ['1' => ['grp' => 'Pit'], '2' => ['grp' => 'Pit'], '3' => ['grp' => 'Disposal']];

        $umum = (new DrmBeranda(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI)))->bahayaUmum(['1', '2', '3'], $hidPerDl, $hira, $dl);

        $this->assertEqualsCanonicalizing(['u0', 'u1', 'u2', 'u3'], $umum['Pit']);
        $this->assertArrayNotHasKey('Disposal', $umum); // hanya 1 kelompok di grup itu
    }

    public function test_berkas_dan_ringkasan_mgmt_per_minggu_per_level(): void
    {
        $b = (new DrmBeranda(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI)))->denganGrup(['1' => ['grp' => 'Pit'], '9' => ['grp' => 'Pit']]);
        $hira = [
            'h1' => ['bn' => 'Jalan licin', 'proses' => 'CH', 'n1' => 'Significant'],
            'h2' => ['bn' => 'Debu', 'proses' => 'CH', 'n1' => 'High'],
            'h3' => ['bn' => 'Retakan', 'proses' => 'OB', 'n1' => 'High'],
        ];
        $idx = $b->indeks(
            [
                ['dl' => '1', 'kel' => 'CH', 'w' => '0:1,1:17', 'h' => '27:1', 'last' => '2026-11-14'],
                ['dl' => '1', 'kel' => '', 'w' => '', 'h' => '', 'last' => '2026-11-14'],
                ['dl' => '9', 'kel' => 'CH', 'w' => '0:1', 'h' => '', 'last' => '2026-11-10'], // bukan detail lokasi PJA
            ],
            [['dl' => '1', 'hid' => 'h3', 'w' => '0:6', 'h' => '27:70']],
        );
        $berkas = $b->berkas(['1'], ['1' => ['h1', 'h2', 'h3']], $hira, ['1' => ['grp' => 'Pit']], $idx, [
            '1' => [['no' => '7', 'dl' => '1', 'tanggal' => '2026-03-01', 'kategori' => 'Near Miss', 'detlok' => 'Front', 'lokasi' => 'Pit',
                'label' => 'Jalan › Licin', 'sebelum' => '0', 'bulan' => '1', 'sesudah' => '2', 'k_sebelum' => '3', 'k_sesudah' => '1', 'terakhir' => '1970-01-01', 'belum_closed' => '1']],
        ]);

        $this->assertSame(['1|CH'], array_keys((array) $berkas['kd']));
        $this->assertSame('2026-11-14', ((array) $berkas['dlast'])['1']);
        $this->assertSame('', $berkas['rc'][0]['terakhir']);
        $this->assertSame(0, $berkas['rc'][0]['sebelum']);

        $m = $b->ringkasMgmt(['1'], ['1' => ['h1', 'h2', 'h3']], $hira, $berkas, [['tanggal' => '2026-11-01'], ['tanggal' => '2026-01-01']]);
        // Satuan ketat [tot, penuh, sebagian, hanya bermasalah, tanpa laporan, closed, ulang, stop, tidak sesuai].
        // Minggu 0: CH terdeteksi → h1 (Significant) & h2 (High) terkontrol penuh; h3 belum closed → hanya bermasalah.
        $this->assertSame([1, 1, 0, 0, 0, 0, 0, 0, 0], $m['w'][0]['Significant']);
        $this->assertSame([2, 1, 0, 1, 0, 1, 0, 0, 0], $m['w'][0]['High']);
        // Minggu 1: OAK stop di CH → h1 & h2 hanya bermasalah; h3 tanpa laporan.
        $this->assertSame([1, 0, 0, 1, 0, 0, 0, 1, 0], $m['w'][1]['Significant']);
        $this->assertSame([2, 0, 0, 1, 1, 0, 0, 1, 0], $m['w'][1]['High']);
        $this->assertSame(['CH' => 1, 'OB' => 1], $m['kelTot']['High']);
        $this->assertSame(['OB' => 1], $m['kelGap'][0]['High']);
        $this->assertSame([0 => 1, 1 => 1], $m['kgTot']['High']);
        $this->assertSame([1 => 1], $m['u4'][0]['High']); // h3 (OB|Pit) tak terkontrol minggu 0-3
        $this->assertSame([0 => 1], $m['u4'][1]['Significant']); // h1 terkontrol di minggu 0, tidak di 1-4
        $this->assertSame(0, $m['lama']);
        $this->assertSame(1, $m['insiden']);
    }

    public function test_sql_beranda_memakai_minggu_kalender_dan_root_cause(): void
    {
        $sql = (new DrmSql(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI)))->untukSite('LMO');

        $kel = $sql->berandaKelompok();
        $this->assertStringContainsString('toStartOfWeek(t_end, 0) AS wk0', $kel);
        $this->assertStringContainsString("AND t.site = 'LMO'", $kel);
        $this->assertStringContainsString('HAVING uniqExact(h.proses) <= 2', $kel);

        $spe = $sql->berandaSpesifik();
        $this->assertStringContainsString("r.Status_Causal = 'ROOT CAUSE'", $spe);
        $this->assertStringContainsString('8 * (nw >= 3)', $spe);
        $this->assertStringContainsString('parseDateTimeBestEffortUSOrNull', $spe);

        $inc = $sql->berandaInsiden();
        $this->assertStringContainsString("toDate('2025-10-01')", $inc);
        $this->assertStringContainsString("NOT IN ('CLOSED', 'DONE')", $inc);
    }
}
