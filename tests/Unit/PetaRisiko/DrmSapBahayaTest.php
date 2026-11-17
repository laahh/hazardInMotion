<?php

namespace Tests\Unit\PetaRisiko;

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

    private function sql(array $pola, string $kel, array $label = [123]): string
    {
        return $this->panggil('sql', [[11, 22], CarbonImmutable::parse('2026-11-08'), CarbonImmutable::parse('2026-11-14'),
            $label, $pola, $kel, ['DELETED'], ['tidak ada aktivitas']]);
    }

    public function test_obs_oak_hanya_kelompok_dengan_regex_pertama_dan_hazard_lewat_label(): void
    {
        $sql = $this->sql(['K10' => 'maintenance', 'K13' => 'jalan|road'], 'K13');

        // "hauling road maintenance" masuk K10 (cocok lebih dulu), bukan K13 — sama dengan okx di DrmSql.
        $this->assertStringContainsString("arrayFirst(r -> match(tx, r.2), [('K10', '(?i)maintenance'), ('K13', '(?i)jalan|road')]).1 = 'K13'", $sql);
        $this->assertStringContainsString("kc IN (123), 'hazard'", $sql);
        $this->assertStringContainsString('`id detail lokasi` IN (11,22)', $sql);
        $this->assertStringContainsString("toDate('2026-11-14') + 1", $sql);
        // OBS/OAK tidak lagi memetakan ke bahaya lewat teks aktivitas (M11/M12).
        $this->assertStringNotContainsString("'teks'", $sql);
    }

    public function test_kelompok_belum_terpetakan_hanya_bukti_label(): void
    {
        $sql = $this->sql(['K13' => 'jalan'], 'X', []);

        $this->assertStringNotContainsString('arrayFirst', $sql);
        $this->assertStringContainsString("kc IN (0), 'hazard'", $sql);
    }

    public function test_item_sumber_mengelompokkan_kunci_penangkap_bukan_laporan(): void
    {
        $arg = fn (string $sumber) => [[11], CarbonImmutable::parse('2026-11-08'), CarbonImmutable::parse('2026-11-14'), $sumber, [123],
            ['K10' => 'maintenance', 'K13' => 'jalan|road'], 'K13', ['DELETED'], ['tidak ada aktivitas']];

        $obs = $this->panggil('sqlItem', $arg('obs'));
        $this->assertStringContainsString("jenis_data = 'OBSERVASI'", $obs);
        $this->assertStringContainsString("any(extract(tx, '(?i)(jalan|road)')) AS kunci", $obs);
        $this->assertStringContainsString("arrayFirst(r -> match(tx, r.2), [('K10', '(?i)maintenance'), ('K13', '(?i)jalan|road')]).1 = 'K13'", $obs);
        $this->assertStringContainsString('GROUP BY tx', $obs);

        $this->assertStringContainsString("jenis_data = 'OBSERVASI AREA KRITIS'", $this->panggil('sqlItem', $arg('oak')));

        $ket = $this->panggil('sqlItem', $arg('ket'));
        $this->assertStringContainsString("jenis_data IN ('HAZARD', 'INSPEKSI')", $ket);
        $this->assertStringContainsString(')) IN (123)', $ket);
        $this->assertStringContainsString('GROUP BY isi', $ket);
    }

    public function test_semua_laporan_hanya_dari_ch_rfid_sap(): void
    {
        $sql = $this->sql(['K13' => 'jalan'], 'K13');

        $this->assertStringContainsString('FROM obds.ch_rfid_sap_cuti_all_site', $sql);
        $this->assertStringNotContainsString('oak_register', $sql);
        $this->assertStringNotContainsString('oak_detail_object', $sql);
    }
}
