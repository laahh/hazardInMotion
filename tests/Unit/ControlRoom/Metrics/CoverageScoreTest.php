<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom\Metrics;

use App\Services\ControlRoom\Metrics\CoverageScore;
use App\Services\ControlRoom\Reference\LocationReader;
use App\Services\ControlRoom\Reference\LocationReaderContract;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Tests\TestCase;

final class CoverageScoreTest extends TestCase
{
    public function test_lokasi_non_kritis_dihitung_bobot_1(): void
    {
        $reader = $this->createMock(LocationReaderContract::class);
        $reader->method('isCritical')->willReturn(false);

        $metric = new CoverageScore($reader);

        $score = $metric->calculate([
            ['lokasi' => 'A', 'detail_lokasi' => 'A1'],
            ['lokasi' => 'B', 'detail_lokasi' => 'B1'],
        ]);

        $this->assertSame(2, $score);
    }

    public function test_lokasi_kritis_dihitung_bobot_2(): void
    {
        $reader = $this->createMock(LocationReaderContract::class);
        $reader->method('isCritical')->willReturn(true);

        $metric = new CoverageScore($reader);

        $score = $metric->calculate([
            ['lokasi' => 'A', 'detail_lokasi' => 'A1'],
        ]);

        $this->assertSame(2, $score);
    }

    public function test_campuran_lokasi_kritis_dan_non_kritis(): void
    {
        // NIA ANGGITA: 5 lokasi non-kritis + 10 lokasi kritis -> (5*1)+(10*2)=25
        // (regresi data existing, lihat plan-OCR.md T6.5 acceptance).
        $reader = $this->createMock(LocationReaderContract::class);
        $reader->method('isCritical')->willReturnOnConsecutiveCalls(
            ...array_fill(0, 5, false),
            ...array_fill(0, 10, true),
        );

        $metric = new CoverageScore($reader);

        $locations = array_map(
            fn (int $i): array => ['lokasi' => "L{$i}", 'detail_lokasi' => "D{$i}"],
            range(1, 15)
        );

        $this->assertSame(25, $metric->calculate($locations));
    }

    public function test_tanpa_lokasi_menghasilkan_skor_0(): void
    {
        $reader = $this->createMock(LocationReaderContract::class);
        $metric = new CoverageScore($reader);

        $this->assertSame(0, $metric->calculate([]));
    }

    public function test_dengan_location_reader_asli_area_kritis_terdeteksi_dari_nama_lokasi(): void
    {
        // LocationReader::isCritical() sekarang murni string-matching (pola
        // Tableau, lihat config('control-room.critical_area_keywords')) —
        // tidak butuh DB sama sekali untuk kasus ini.
        $realReader = new LocationReader(new PembatasanLVOlapQuery());
        $metric = new CoverageScore($realReader);

        $score = $metric->calculate([
            ['lokasi' => '(B7) Area Kritis Blok 7', 'detail_lokasi' => 'Front Double Pad Loading'],
            ['lokasi' => 'LATI', 'detail_lokasi' => 'Area Pengeboran'],
            ['lokasi' => 'Workshop', 'detail_lokasi' => 'Workshop MTN 059'],
        ]);

        // 2 lokasi kritis (x2) + 1 lokasi non-kritis (x1) = 5.
        $this->assertSame(5, $score);
    }
}
