<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomSapDutyReader;
use App\Services\ControlRoom\ControlRoomSapQualityFindingsReader;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Tests\TestCase;

final class ControlRoomSapQualityFindingsReaderTest extends TestCase
{
    public function test_collapse_location_hits_menyimpan_timestamp_terakhir(): void
    {
        $rows = $this->reader()->collapseLocationHits([
            ['lokasi' => 'Pit A', 'detil_lokasi' => 'Front', 'at' => '2026-09-01 08:00:00'],
            ['lokasi' => 'Pit A', 'detil_lokasi' => 'Front', 'at' => '2026-09-02 10:00:00'],
            ['lokasi' => 'Pit B', 'detil_lokasi' => 'View', 'at' => '2026-09-01 09:00:00'],
            ['lokasi' => '', 'detil_lokasi' => '', 'at' => '2026-09-01 11:00:00'],
        ]);

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row['lokasi'].'|'.$row['detil_lokasi']] = $row['at'];
        }

        $this->assertCount(2, $rows);
        $this->assertSame('2026-09-02 10:00:00', $byKey['Pit A|Front']);
        $this->assertSame('2026-09-01 09:00:00', $byKey['Pit B|View']);
    }

    private function reader(): ControlRoomSapQualityFindingsReader
    {
        return new ControlRoomSapQualityFindingsReader(
            new PembatasanLVOlapQuery(),
            new ControlRoomSapDutyReader(new PembatasanLVOlapQuery()),
        );
    }
}
