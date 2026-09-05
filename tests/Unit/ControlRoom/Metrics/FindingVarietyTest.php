<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom\Metrics;

use App\Services\ControlRoom\Metrics\FindingVariety;
use Tests\TestCase;

final class FindingVarietyTest extends TestCase
{
    public function test_semua_kategori_berbeda_menghasilkan_skor_1(): void
    {
        $metric = new FindingVariety();

        $score = $metric->score(['A', 'B', 'C']);

        $this->assertSame(1.0, $score);
    }

    public function test_semua_kategori_sama_menghasilkan_skor_rendah(): void
    {
        $metric = new FindingVariety();

        $score = $metric->score(array_fill(0, 20, 'SAMA'));

        $this->assertSame(0.05, $score);
    }

    public function test_campuran_kategori_menghasilkan_skor_proporsional(): void
    {
        $metric = new FindingVariety();

        $score = $metric->score(['A', 'A', 'B', 'C']);

        $this->assertSame(0.75, $score);
    }

    public function test_tanpa_temuan_menghasilkan_null_bukan_nol(): void
    {
        $metric = new FindingVariety();

        $this->assertNull($metric->score([]));
    }
}
