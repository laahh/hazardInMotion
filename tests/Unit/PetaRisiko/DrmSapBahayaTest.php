<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmSapBahaya;
use Carbon\CarbonImmutable;
use ReflectionClass;
use Tests\TestCase;

class DrmSapBahayaTest extends TestCase
{
    private function sql(array $pola, string $kel, array $label = [123], array $teks = []): string
    {
        $r = new ReflectionClass(DrmSapBahaya::class);

        return $r->getMethod('sql')->invoke($r->newInstanceWithoutConstructor(), [11, 22],
            CarbonImmutable::parse('2026-11-08'), CarbonImmutable::parse('2026-11-14'), $label, $teks, $pola, $kel, ['DELETED'], ['tidak ada aktivitas']);
    }

    public function test_kelompok_memakai_regex_pertama_yang_cocok_dan_gerbang_detail_lokasi(): void
    {
        $sql = $this->sql(['K10' => 'maintenance', 'K13' => 'jalan|road'], 'K13');

        // "hauling road maintenance" masuk K10 (cocok lebih dulu), bukan K13 — sama dengan okx di DrmSql.
        $this->assertStringContainsString("arrayFirst(r -> match(tx, r.2), [('K10', '(?i)maintenance'), ('K13', '(?i)jalan|road')]).1 = 'K13'", $sql);
        $this->assertStringContainsString('`id detail lokasi` IN (11,22)', $sql);
        $this->assertStringContainsString(') IN (123), \'label\'', $sql);
        $this->assertStringContainsString("toDate('2026-11-14') + 1", $sql);
        $this->assertStringContainsString("NOT IN ('DELETED')", $sql);
    }

    public function test_kelompok_belum_terpetakan_hanya_bukti_spesifik(): void
    {
        $sql = $this->sql(['K13' => 'jalan'], 'X', [], []);

        $this->assertStringNotContainsString('arrayFirst', $sql);
        $this->assertStringContainsString("AND 0, 'kelompok'", $sql);
        $this->assertStringContainsString(') IN (0), \'label\'', $sql);
    }
}
