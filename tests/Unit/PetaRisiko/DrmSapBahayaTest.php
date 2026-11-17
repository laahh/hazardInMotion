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
        $this->assertStringContainsString("(jenis = 'OBSERVASI' AND kc IN (101)) OR (jenis = 'OBSERVASI AREA KRITIS' AND kc IN (202))", $item);
        $this->assertStringContainsString("'risk\\\\s*intervention\\\\s*center', 'langsung'), '[^0-9a-z]+', '')) AS kc", $item);
        $this->assertStringContainsString('`id detail lokasi` IN (11,22)', $item);
        $this->assertStringContainsString('countIf(stop = 1) AS nstop', $item);
        $this->assertStringContainsString('GROUP BY kc', $item);
        $this->assertStringNotContainsString('bcbeats', $item);

        // Tanpa label di master untuk satu jenis → jenis itu tidak bisa menangkap.
        $this->assertStringContainsString("(jenis = 'OBSERVASI' AND kc IN (0))", $this->teks([], [202], 'laporan'));
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
}
