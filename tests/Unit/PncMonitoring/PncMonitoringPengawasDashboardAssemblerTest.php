<?php

declare(strict_types=1);

namespace Tests\Unit\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringCommissioning;
use App\Services\PncMonitoring\PncMonitoringPengawasDashboardAssembler;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class PncMonitoringPengawasDashboardAssemblerTest extends TestCase
{
    public function test_performance_tanpa_temuan(): void
    {
        $rows = new Collection([
            new PncMonitoringCommissioning(['nama_pengawas_teknis' => 'A', 'temuan_komisioning' => 0, 'status' => 'Release SKO', 'site' => 'S1', 'pemilik_spip' => 'P1']),
            new PncMonitoringCommissioning(['nama_pengawas_teknis' => 'A', 'temuan_komisioning' => 2, 'status' => 'Reject SKO', 'site' => 'S1', 'pemilik_spip' => 'P1']),
            new PncMonitoringCommissioning(['nama_pengawas_teknis' => 'B', 'temuan_komisioning' => 0, 'status' => 'Release SKO', 'site' => 'S2', 'pemilik_spip' => 'P2']),
        ]);

        $assembler = new PncMonitoringPengawasDashboardAssembler();
        $stats = $assembler->supervisorStats($rows);
        $kpis = $assembler->kpis($rows, $stats);

        $this->assertSame(3, $kpis['totalUnits']);
        $this->assertSame(2, $kpis['releaseCount']);
        $this->assertSame(1, $kpis['rejectCount']);
        $this->assertSame(2, $kpis['status1']);
        $this->assertSame(1, $kpis['status0']);
        $this->assertEqualsWithDelta(2 / 3, $kpis['performance'], 0.0001);
        $this->assertSame(2, $kpis['totalSupervisors']);

        $a = $stats->firstWhere('name', 'A');
        $this->assertNotNull($a);
        $this->assertSame(2, $a['units']);
        $this->assertSame(1, $a['unitsWithFindings']);
        $this->assertEqualsWithDelta(0.5, $a['performance'], 0.0001);
    }
}
