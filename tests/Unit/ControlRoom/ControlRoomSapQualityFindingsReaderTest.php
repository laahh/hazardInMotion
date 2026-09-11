<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomSapDutyReader;
use App\Services\ControlRoom\ControlRoomSapQualityFindingsReader;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
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

    public function test_location_hits_memakai_cache_harian_tanpa_query_obds(): void
    {
        Cache::put(
            ControlRoomSapQualityFindingsReader::locationHitsDayCacheKey('2026-09-06'),
            [['lokasi' => 'Pit A', 'detil_lokasi' => 'Front', 'at' => '2026-09-06 08:00:00']],
            60,
        );
        Cache::put(
            ControlRoomSapQualityFindingsReader::locationHitsDayCacheKey('2026-09-07'),
            [['lokasi' => 'Workshop', 'detil_lokasi' => 'Office', 'at' => '2026-09-07 09:00:00']],
            60,
        );

        $result = $this->reader()->locationHits(
            CarbonImmutable::parse('2026-09-06'),
            CarbonImmutable::parse('2026-09-08'),
        );

        $this->assertTrue($result['loaded']);
        $this->assertCount(2, $result['findings']);
        $this->assertSame('Pit A', $result['findings'][0]['lokasi']);
        $this->assertSame('Workshop', $result['findings'][1]['lokasi']);
    }

    public function test_location_hits_range_memakai_cache_tanpa_query_obds(): void
    {
        $this->travelTo('2026-09-11 08:00:00');
        Cache::put(
            ControlRoomSapQualityFindingsReader::locationHitsRangeCacheKey('2026-09-06', '2026-09-12'),
            [['lokasi' => 'Pit A', 'detil_lokasi' => 'Front', 'at' => '2026-09-08 08:00:00']],
            60,
        );

        $result = $this->reader()->locationHitsRange(
            CarbonImmutable::parse('2026-09-06'),
            CarbonImmutable::parse('2026-09-12'),
        );
        $this->travelBack();

        $this->assertTrue($result['loaded']);
        $this->assertCount(1, $result['findings']);
        $this->assertSame('Pit A', $result['findings'][0]['lokasi']);
    }

    private function reader(): ControlRoomSapQualityFindingsReader
    {
        return new ControlRoomSapQualityFindingsReader(
            new PembatasanLVOlapQuery(),
            new ControlRoomSapDutyReader(new PembatasanLVOlapQuery()),
        );
    }
}
