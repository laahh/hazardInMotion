<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmMaster;
use PHPUnit\Framework\TestCase;

class DrmPjaDikecualikanTest extends TestCase
{
    private const CFG = ['pja_dikecualikan' => ['inspektor', 'inspector']];

    public function test_pja_inspektor_dan_inspector_dibuang_tanpa_beda_huruf(): void
    {
        $this->assertTrue(DrmMaster::pjaDikecualikan('Inspektor Mining BC PMO', self::CFG));
        $this->assertTrue(DrmMaster::pjaDikecualikan('SAFETY INSPECTOR EKSPLORASI BC', self::CFG));
        $this->assertTrue(DrmMaster::pjaDikecualikan('Inspector  Blok 7 & 8', self::CFG));
    }

    public function test_pja_lain_tetap_ikut(): void
    {
        $this->assertFalse(DrmMaster::pjaDikecualikan('PJA Maintenance BC GMO', self::CFG));
        $this->assertFalse(DrmMaster::pjaDikecualikan('Inspeksi Jalan Hauling', self::CFG));
        $this->assertFalse(DrmMaster::pjaDikecualikan('Inspektor Mining BC PMO', []));
    }
}
