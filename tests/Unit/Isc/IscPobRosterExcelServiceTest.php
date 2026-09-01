<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Services\Isc\IscPobRosterExcelService;
use App\Services\Isc\IscPobSnapshotService;
use Tests\TestCase;

final class IscPobRosterExcelServiceTest extends TestCase
{
    public function test_demo_rows_cover_in_out_and_rfid_gaps(): void
    {
        $pack = app(IscPobSnapshotService::class)->snapshot(true, true);
        $excel = app(IscPobRosterExcelService::class);

        $in = $excel->rows($pack, 'in');
        $out = $excel->rows($pack, 'out');
        $checkin = $excel->rows($pack, 'checkin');
        $both = $excel->rows($pack, 'both');
        $gapBr = $excel->rows($pack, 'gap_br');
        $gapRb = $excel->rows($pack, 'gap_rb');

        $inSids = array_column($in, 'sid');
        $this->assertNotEmpty($in);
        $this->assertNotEmpty($people = array_values(array_filter(
            $pack['people'] ?? [],
            static fn (array $row): bool => ($row['entity'] ?? 'person') === 'person' && ! ($row['roster_only'] ?? false)
        )));
        $this->assertSame(
            count($people),
            ($pack['summary']['in'] ?? 0) + ($pack['summary']['out'] ?? 0) + ($pack['summary']['unknown'] ?? 0)
        );
        $this->assertNotEmpty($inSids);
        $this->assertSame([], array_values(array_intersect($inSids, array_column($out, 'sid'))));
        $this->assertGreaterThan(0, ($pack['summary']['in'] ?? 0) + ($pack['summary']['out'] ?? 0));
        $this->assertCount(6, $checkin);
        $this->assertContains('BC001', array_column($both, 'sid'));
        $this->assertContains('BC007', array_column($gapBr, 'sid'));
        $this->assertContains('RFID09', array_column($gapRb, 'sid'));
        $this->assertArrayHasKey('name', $in[0]);
        $this->assertArrayHasKey('sid', $in[0]);
        $this->assertArrayHasKey('status', $in[0]);
    }

    public function test_kind_rows_include_roster_only_violations(): void
    {
        $excel = app(IscPobRosterExcelService::class);
        $pack = [
            'people' => [
                [
                    'entity' => 'person',
                    'roster_only' => false,
                    'presence' => 'in',
                    'safety' => 'safe',
                    'sid' => 'A',
                    'name' => 'Ali',
                    'hazard_kind' => null,
                ],
                [
                    'entity' => 'person',
                    'roster_only' => true,
                    'from_violation' => true,
                    'presence' => 'in',
                    'safety' => 'unsafe',
                    'sid' => 'B',
                    'name' => 'Budi',
                    'hazard_kind' => 'employee_danger',
                    'hazard_kind_label' => 'Pelanggaran Batas Bahaya Karyawan',
                    'site_code' => 'LMO',
                ],
            ],
            'checkins' => [],
            'reconcile' => [],
        ];

        $rows = $excel->rows($pack, 'kind', 'employee_danger');
        $this->assertSame(['B'], array_column($rows, 'sid'));
        $this->assertSame(['A'], array_column($excel->rows($pack, 'in'), 'sid'));
    }
}
