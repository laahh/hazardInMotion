<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom\Metrics;

use App\Services\ControlRoom\Metrics\TbcValidity;
use Tests\TestCase;

final class TbcValidityTest extends TestCase
{
    public function test_kasus_normal_menghasilkan_persentase_bulat(): void
    {
        $metric = new TbcValidity();

        $this->assertSame(50.0, $metric->percentage(5, 10));
    }

    public function test_basis_nol_menghasilkan_null_bukan_nol(): void
    {
        $metric = new TbcValidity();

        $this->assertNull($metric->percentage(0, 0));
    }

    public function test_tidak_ada_yang_valid_menghasilkan_0_persen(): void
    {
        $metric = new TbcValidity();

        $this->assertSame(0.0, $metric->percentage(0, 10));
    }

    public function test_semua_valid_menghasilkan_100_persen(): void
    {
        $metric = new TbcValidity();

        $this->assertSame(100.0, $metric->percentage(10, 10));
    }
}
