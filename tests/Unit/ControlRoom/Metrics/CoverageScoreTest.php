<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom\Metrics;

use App\Services\ControlRoom\Metrics\CoverageScore;
use App\Services\ControlRoom\Reference\LocationReader;
use App\Services\ControlRoom\Reference\LocationReaderContract;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use RuntimeException;
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

    public function test_location_reader_yang_belum_terverifikasi_melempar_exception_bukan_fabrikasi_skor(): void
    {
        $realReader = new LocationReader(new PembatasanLVOlapQuery());
        $metric = new CoverageScore($realReader);

        $this->expectException(RuntimeException::class);

        $metric->calculate([['lokasi' => 'A', 'detail_lokasi' => 'A1']]);
    }
}
