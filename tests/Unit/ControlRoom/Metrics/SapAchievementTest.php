<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom\Metrics;

use App\Services\ControlRoom\Metrics\SapAchievement;
use Tests\TestCase;

final class SapAchievementTest extends TestCase
{
    public function test_ketiga_komponen_lengkap_menghasilkan_100_persen(): void
    {
        $metric = new SapAchievement();

        $percentage = $metric->percentage(['hazard' => 1, 'inspeksi' => 1, 'observasi' => 1]);

        $this->assertSame(100.0, $percentage);
    }

    public function test_dua_dari_tiga_komponen_menghasilkan_66_67_persen(): void
    {
        $metric = new SapAchievement();

        $percentage = $metric->percentage(['hazard' => 1, 'inspeksi' => 0, 'observasi' => 2]);

        $this->assertSame(66.67, $percentage);
    }

    public function test_tanpa_laporan_sama_sekali_menghasilkan_0_persen(): void
    {
        $metric = new SapAchievement();

        $percentage = $metric->percentage([]);

        $this->assertSame(0.0, $percentage);
    }

    public function test_lebih_dari_satu_laporan_per_komponen_tetap_dihitung_sebagai_terpenuhi_satu_slot(): void
    {
        $metric = new SapAchievement();

        // 5 laporan hazard tetap 1 slot terpenuhi — bukan >100% (bonus di luar cakupan class ini).
        $percentage = $metric->percentage(['hazard' => 5, 'inspeksi' => 0, 'observasi' => 0]);

        $this->assertSame(33.33, $percentage);
    }

    public function test_oak_mengisi_slot_observasi(): void
    {
        $metric = new SapAchievement();

        $this->assertSame(33.33, $metric->percentage(['oak' => 1]));
        $this->assertSame(100.0, $metric->percentage(['hazard' => 1, 'inspeksi' => 1, 'oak' => 1]));
        $this->assertSame(100.0, $metric->percentage(['hazard' => 1, 'inspeksi' => 1, 'observasi' => 1, 'oak' => 4]));
    }
}
