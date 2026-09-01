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
}
