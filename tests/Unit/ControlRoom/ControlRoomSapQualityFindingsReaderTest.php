<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomSapDutyReader;
use App\Services\ControlRoom\ControlRoomSapQualityFindingsReader;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Tests\TestCase;

final class ControlRoomSapQualityFindingsReaderTest extends TestCase
{
    public function test_collapse_location_hits_menyimpan_timestamp_terakhir_per_hari(): void
    {
        $rows = $this->reader()->collapseLocationHits([
            ['lokasi' => 'Pit A', 'detil_lokasi' => 'Front', 'at' => '2026-09-01 08:00:00'],
            ['lokasi' => 'Pit A', 'detil_lokasi' => 'Front', 'at' => '2026-09-01 11:00:00'],
            ['lokasi' => 'Pit A', 'detil_lokasi' => 'Front', 'at' => '2026-09-02 10:00:00'],
            ['lokasi' => 'Pit B', 'detil_lokasi' => 'View', 'at' => '2026-09-01 09:00:00'],
            ['lokasi' => '', 'detil_lokasi' => '', 'at' => '2026-09-01 11:00:00'],
        ]);

        $dates = [];
        foreach ($rows as $row) {
            $dates[$row['lokasi'].'|'.$row['detil_lokasi'].'|'.$row['at']] = true;
        }

        $this->assertCount(3, $rows);
        $this->assertArrayHasKey('Pit A|Front|2026-09-01 11:00:00', $dates);
        $this->assertArrayHasKey('Pit A|Front|2026-09-02 10:00:00', $dates);
        $this->assertArrayHasKey('Pit B|View|2026-09-01 09:00:00', $dates);
        $this->assertArrayNotHasKey('Pit A|Front|2026-09-01 08:00:00', $dates);
    }

    private function reader(): ControlRoomSapQualityFindingsReader
    {
        return new ControlRoomSapQualityFindingsReader(
            new PembatasanLVOlapQuery(),
            new ControlRoomSapDutyReader(new PembatasanLVOlapQuery()),
        );
    }
}
