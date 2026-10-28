<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterHariSingkat;
use PHPUnit\Framework\TestCase;

/**
 * Pengecualian hari ke-14 singkat (<= 2 jam di site) pada aturan > 13 hari
 * berturut-turut. Run mulai offset 10 → hari ke-14 = offset 23.
 */
class DmsRosterHariSingkatTest extends TestCase
{
    private const RUN_14 = ['mulai' => 10, 'selesai' => 23, 'hari' => 14];

    public function test_hari_ke_14_dua_jam_tidak_masuk_potensial_banned(): void
    {
        $s = DmsRosterHariSingkat::sesuaikan(self::RUN_14, 13, [23 => 120], 120);

        $this->assertFalse($s['melanggar']);
        $this->assertSame([23], $s['dilewati']);
        $this->assertSame(13, $s['hari_efektif']);
    }

    public function test_hari_ke_14_lebih_dari_dua_jam_tetap_melanggar(): void
    {
        $s = DmsRosterHariSingkat::sesuaikan(self::RUN_14, 13, [23 => 121], 120);

        $this->assertTrue($s['melanggar']);
        $this->assertSame(23, $s['hari_langgar']);
        $this->assertSame([], $s['dilewati']);
    }

    public function test_tanpa_checkout_durasi_tidak_diketahui_tetap_melanggar(): void
    {
        $s = DmsRosterHariSingkat::sesuaikan(self::RUN_14, 13, [], 120);

        $this->assertTrue($s['melanggar']);
        $this->assertSame(23, $s['hari_langgar']);
    }

    public function test_hari_singkat_dilewati_lalu_hari_berikutnya_jadi_hari_ke_14(): void
    {
        // Offset 23 hanya 1 jam, offset 24 kerja penuh → melanggar di offset 24.
        $s = DmsRosterHariSingkat::sesuaikan(['mulai' => 10, 'selesai' => 24, 'hari' => 15], 13, [23 => 60, 24 => 720], 120);

        $this->assertTrue($s['melanggar']);
        $this->assertSame(24, $s['hari_langgar']);
        $this->assertSame([23], $s['dilewati']);
        $this->assertSame(14, $s['hari_efektif']);
    }

    public function test_hari_singkat_di_tengah_run_tetap_dihitung_kerja(): void
    {
        $s = DmsRosterHariSingkat::sesuaikan(self::RUN_14, 13, [15 => 30, 23 => 600], 120);

        $this->assertTrue($s['melanggar']);
        $this->assertSame([], $s['dilewati']);
    }

    public function test_run_13_hari_tidak_pernah_melanggar(): void
    {
        $s = DmsRosterHariSingkat::sesuaikan(['mulai' => 10, 'selesai' => 22, 'hari' => 13], 13, [], 120);

        $this->assertFalse($s['melanggar']);
        $this->assertNull($s['hari_langgar']);
    }
}
