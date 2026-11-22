<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmMaster;
use App\Services\PetaRisiko\Drm\DrmSapBahaya;
use Carbon\CarbonImmutable;
use ReflectionClass;
use Tests\TestCase;

class DrmSapBahayaTest extends TestCase
{
    private function panggil(string $metode, array $arg): string
    {
        $r = new ReflectionClass(DrmSapBahaya::class);

        return $r->getMethod($metode)->invoke($r->newInstanceWithoutConstructor(), ...$arg);
    }

    private function teks(array $obs, array $oak, string $mode): string
    {
        return $this->panggil('sqlTeks', [[11, 22], CarbonImmutable::parse('2026-11-08'), CarbonImmutable::parse('2026-11-14'),
            $obs, $oak, ['DELETED'], ['tidak ada aktivitas'], $mode]);
    }

    public function test_label_obs_oak_dinormalisasi_seperti_dokumentasi_risk_matrix(): void
    {
        // Huruf kecil, hanya huruf + angka, "Risk Intervention Center" = "Langsung".
        $this->assertSame('dumpinglangsungpenimbunandumping', DrmMaster::normTeks('Dumping / Risk Intervention Center - Penimbunan (Dumping)'));
        $this->assertSame(DrmMaster::crcTeks('Dumping / Langsung - Penimbunan (Dumping)'), DrmMaster::crcTeks('dumping/RISK INTERVENTION CENTER  -  penimbunan (dumping)'));
        $this->assertSame(DrmMaster::crcTeks('Pembuatan dan Perawatan Jalan'), DrmMaster::crcTeks('Pembuatan  dan perawatan jalan.'));
    }

    public function test_obs_oak_ditangkap_lewat_label_persis_m11_m12(): void
    {
        $item = $this->teks([101], [202], 'item');

        $this->assertStringContainsString('FROM obds.ch_rfid_sap_cuti_all_site', $item);
        $this->assertStringContainsString("(jenis = 'OBSERVASI' AND kc IN (101)) OR (jenis = 'OBSERVASI AREA KRITIS' AND (kc IN (202) OR ka IN (0)))", $item);
        $this->assertStringContainsString("'risk\\\\s*intervention\\\\s*center', 'langsung'), '[^0-9a-z]+', '')) AS kc", $item);
        $this->assertStringContainsString('`id detail lokasi` IN (11,22)', $item);
        // Satu laporan = satu task: ch_rfid memuat baris kembar per task.
        $this->assertStringContainsString('uniqExact(task) AS n', $item);
        $this->assertStringContainsString('uniqExactIf(task, stop = 1) AS nstop', $item);
        $this->assertStringContainsString('GROUP BY kc', $item);
        $this->assertStringNotContainsString('bcbeats', $item);

        // Tanpa label di master untuk satu jenis → jenis itu tidak bisa menangkap.
        $laporan = $this->teks([], [202], 'laporan');
        $this->assertStringContainsString("(jenis = 'OBSERVASI' AND kc IN (0))", $laporan);
        $this->assertStringContainsString('ORDER BY waktu DESC LIMIT 1 BY task LIMIT 5000', $laporan);
    }

    public function test_oak_juga_lewat_kurasi_aktivitas_oak_ke_aktivitas_hira(): void
    {
        $sql = $this->panggil('sqlTeks', [[11], CarbonImmutable::parse('2026-11-08'), CarbonImmutable::parse('2026-11-14'),
            [], [202], ['DELETED'], ['tidak ada aktivitas'], 'laporan', [303]]);
        $this->assertStringContainsString('(kc IN (202) OR ka IN (303))', $sql);

        $akt = $this->panggil('sqlTeks', [[11], CarbonImmutable::parse('2026-11-08'), CarbonImmutable::parse('2026-11-14'),
            [], [], ['DELETED'], ['tidak ada aktivitas'], 'item_akt', [303]]);
        $this->assertStringContainsString('GROUP BY ka', $akt);

        // Kurasi: OAK "Dumping" → semua baris beraktivitas HIRA Dumping material OB / Soil Dumping.
        $master = DrmMaster::dariArray(['baris' => [
            ['ri' => 1, 'aid' => 1, 'aktivitas' => 'Dumping material OB', 'sub' => 'a', 'bahaya' => 'x', 'kb' => 'High', 'umum' => false, 'idh' => []],
            ['ri' => 2, 'aid' => 2, 'aktivitas' => 'Soil Dumping', 'sub' => 'b', 'bahaya' => 'y', 'kb' => 'High', 'umum' => false, 'idh' => []],
            ['ri' => 3, 'aid' => 3, 'aktivitas' => 'Workshop', 'sub' => 'c', 'bahaya' => 'z', 'kb' => 'High', 'umum' => false, 'idh' => []],
        ]]);
        $hasil = $master->labelOakAktivitas(['oak_aktivitas_hira' => ['Dumping' => ['dumping  material ob', 'Soil Dumping', 'Tidak Ada']]]);
        $this->assertSame([['teks' => 'Dumping', 'crc' => DrmMaster::crcTeks('Dumping'), 'ri' => [1, 2]]], $hasil);
    }

    public function test_oak_hanya_lewat_label_m12_per_baris_tanpa_tema_butir(): void
    {
        $p = ['dari' => CarbonImmutable::parse('2026-11-08'), 'sampai' => CarbonImmutable::parse('2026-11-14'), 'cfg' => config('peta_risiko_drm'),
            'labelOak' => [202], 'labelOakAkt' => []];
        $item = $this->panggil('sqlOakLabel', [$p, [11], 'item']);

        $this->assertStringContainsString('FROM bcbeats.oak_register', $item);
        $this->assertStringContainsString('WHERE kl IN (202) OR ka IN (0)', $item);
        $this->assertStringContainsString('GROUP BY kl, ka', $item);
        $this->assertStringNotContainsString('oak_detail_object', $item);
        $this->assertStringNotContainsString('multiMatchAny', $item);
        $this->assertStringContainsString('toUInt8(ifNull(x.id_conclusion, 0) = 19502) AS stop', $this->panggil('sqlOakLabel', [$p, [11], 'laporan']));

        // Kurasi aktivitas OAK yang dihitung bawaannya kosong (jangan dipaksakan); draf hanya sebagai usulan.
        $this->assertSame([], config('peta_risiko_drm.oak_aktivitas_hira'));
        $this->assertNotEmpty(config('peta_risiko_drm.oak_aktivitas_hira_usulan'));
    }

    public function test_label_m09_dari_sumber_yang_dikecualikan_tidak_dihitung(): void
    {
        $master = DrmMaster::dariArray(['label' => [
            ['k' => 'A', 'sk' => 'a', 'ri' => [1], 'sumber' => 'HIRA-SAP v31 (Master A, terpilih)'],
            ['k' => 'B', 'sk' => 'b', 'ri' => [2], 'sumber' => 'Pemetaan label baru (label)'],
        ]]);

        $this->assertSame(['A'], array_column($master->labelHazard(['m09_sumber_dikecualikan' => ['Pemetaan label baru (label)']]), 'k'));
        $this->assertCount(2, $master->labelHazard(['m09_sumber_dikecualikan' => []]));
    }

    public function test_hazard_hanya_label_dari_ch_rfid(): void
    {
        $sql = $this->panggil('sqlHazard', [[11, 22], CarbonImmutable::parse('2026-11-08'), CarbonImmutable::parse('2026-11-14'), [123], ['DELETED']]);

        $this->assertStringContainsString('FROM obds.ch_rfid_sap_cuti_all_site', $sql);
        $this->assertStringContainsString("jenis_data IN ('HAZARD', 'INSPEKSI')", $sql);
        $this->assertStringContainsString('WHERE kc IN (123)', $sql);
        $this->assertStringContainsString("toDate('2026-11-14') + 1", $sql);

        $item = $this->panggil('sqlItemLabel', [[11], CarbonImmutable::parse('2026-11-08'), CarbonImmutable::parse('2026-11-14'), [123], ['DELETED']]);
        $this->assertStringContainsString('AND kc IN (123)', $item);
        $this->assertStringContainsString('GROUP BY kc', $item);
    }

    public function test_berulang_bila_ada_insiden_root_cause_sebelum_temuan_dalam_jendela(): void
    {
        $tgl = ['2025-12-01', '2026-06-10'];

        $this->assertTrue(DrmSapBahaya::adaInsidenSebelum($tgl, '2026-11-20', 365));
        $this->assertTrue(DrmSapBahaya::adaInsidenSebelum($tgl, '2026-06-10', 365));
        // Insiden sesudah temuan tidak dihitung; di luar jendela juga tidak.
        $this->assertFalse(DrmSapBahaya::adaInsidenSebelum($tgl, '2025-11-30', 365));
        $this->assertFalse(DrmSapBahaya::adaInsidenSebelum(['2025-01-01'], '2026-11-20', 365));
        $this->assertFalse(DrmSapBahaya::adaInsidenSebelum([], '2026-11-20', 365));
    }
}
