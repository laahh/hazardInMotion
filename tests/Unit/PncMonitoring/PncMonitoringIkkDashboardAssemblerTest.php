<?php

declare(strict_types=1);

namespace Tests\Unit\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringIkkRecord;
use App\Services\PncMonitoring\PncMonitoringIkkDashboardAssembler;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class PncMonitoringIkkDashboardAssemblerTest extends TestCase
{
    public function test_cancel_dan_ipk_denominator(): void
    {
        $rows = new Collection([
            new PncMonitoringIkkRecord(['nomor' => 'A', 'ipk' => 1, 'ia' => 1, 'finding_verlap' => 0, 'finding_ia' => 0, 'plan_okk' => 1, 'okk_1' => 1, 'okk_2' => 0, 'okk_3' => 0, 'okk_layer_2' => 0, 'okk_layer_3' => 0, 'okk_layer_4' => 0]),
            new PncMonitoringIkkRecord(['nomor' => 'B', 'ipk' => 0, 'ia' => 1, 'finding_verlap' => 2, 'finding_ia' => 1, 'plan_okk' => 1, 'okk_1' => 0, 'okk_2' => 0, 'okk_3' => 0, 'okk_layer_2' => 0, 'okk_layer_3' => 0, 'okk_layer_4' => 0]),
            new PncMonitoringIkkRecord(['nomor' => 'C', 'ipk' => null, 'ia' => 0, 'finding_verlap' => 0, 'finding_ia' => 0, 'plan_okk' => 0, 'okk_1' => 0, 'okk_2' => 0, 'okk_3' => 0, 'okk_layer_2' => 0, 'okk_layer_3' => 0, 'okk_layer_4' => 0]),
        ]);

        $kpis = (new PncMonitoringIkkDashboardAssembler())->kpis($rows);

        $this->assertSame(3, $kpis['ikkCount']);
        $this->assertSame(2, $kpis['cancelCount']); // ipk 0 + blank
        $this->assertSame(1, $kpis['ipkActual']);
        $this->assertSame(2, $kpis['ipkDenominator']); // only 0 and 1
        $this->assertEqualsWithDelta(0.5, $kpis['ipkPerformance'], 0.0001);
        $this->assertSame(2, $kpis['iaActual']);
        $this->assertSame(1, $kpis['iaPenaltyCases']);
        $this->assertSame(1, $kpis['iaEffective']); // 2 - 1
    }
}
