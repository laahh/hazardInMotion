<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomSapDutyReader;
use App\Services\ControlRoom\ControlRoomSapWeekCountsReader;
use App\Services\ControlRoom\Metrics\SapAchievement;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class ControlRoomSapWeekCountsReaderTest extends TestCase
{
    public function test_laporan_hari_h_dan_h_plus_satu_masuk_slot_jaga(): void
    {
        $counts = $this->reader()->countForDuties(
            [
                ['sid' => 'FJAVJ', 'at' => CarbonImmutable::parse('2026-08-31 09:23:00'), 'component' => 'hazard'],
                ['sid' => 'FJAVJ', 'at' => CarbonImmutable::parse('2026-09-01 10:00:00'), 'component' => 'inspeksi'],
                ['sid' => 'FJAVJ', 'at' => CarbonImmutable::parse('2026-09-01 19:10:00'), 'component' => 'observasi'],
            ],
            [['sid' => 'FJAVJ', 'date' => '2026-08-31']],
        );

        $this->assertSame(
            ['hazard' => 1, 'inspeksi' => 1, 'observasi' => 1],
            $counts['FJAVJ|2026-08-31'],
        );
    }

    public function test_laporan_hari_h_plus_dua_tidak_masuk(): void
    {
        $counts = $this->reader()->countForDuties(
            [
                ['sid' => 'FJAVJ', 'at' => CarbonImmutable::parse('2026-09-02 00:00:00'), 'component' => 'hazard'],
            ],
            [['sid' => 'FJAVJ', 'date' => '2026-08-31']],
        );

        $this->assertSame(
            ['hazard' => 0, 'inspeksi' => 0, 'observasi' => 0],
            $counts['FJAVJ|2026-08-31'],
        );
    }

    public function test_observasi_dan_oak_mengisi_slot_yang_sama(): void
    {
        $counts = $this->reader()->countForDuties(
            [
                ['sid' => 'FJAVJ', 'at' => CarbonImmutable::parse('2026-08-31 08:00:00'), 'component' => 'observasi'],
                ['sid' => 'FJAVJ', 'at' => CarbonImmutable::parse('2026-08-31 09:00:00'), 'component' => 'oak'],
            ],
            [['sid' => 'FJAVJ', 'date' => '2026-08-31']],
        );

        $this->assertSame(2, $counts['FJAVJ|2026-08-31']['observasi']);
        $this->assertSame(0, $counts['FJAVJ|2026-08-31']['hazard']);
        $this->assertSame(0, $counts['FJAVJ|2026-08-31']['inspeksi']);
    }

    public function test_oak_saja_mengisi_slot_observasi_untuk_persen_sap(): void
    {
        $counts = $this->reader()->countForDuties(
            [
                ['sid' => 'FJAVJ', 'at' => CarbonImmutable::parse('2026-08-31 08:00:00'), 'component' => 'hazard'],
                ['sid' => 'FJAVJ', 'at' => CarbonImmutable::parse('2026-08-31 09:00:00'), 'component' => 'inspeksi'],
                ['sid' => 'FJAVJ', 'at' => CarbonImmutable::parse('2026-08-31 10:00:00'), 'component' => 'oak'],
            ],
            [['sid' => 'FJAVJ', 'date' => '2026-08-31']],
        );

        $this->assertSame(
            ['hazard' => 1, 'inspeksi' => 1, 'observasi' => 1],
            $counts['FJAVJ|2026-08-31'],
        );
        $this->assertSame(100.0, (new SapAchievement())->percentage($counts['FJAVJ|2026-08-31']));
    }

    public function test_sid_lain_tidak_terhitung(): void
    {
        $counts = $this->reader()->countForDuties(
            [
                ['sid' => 'XXXXX', 'at' => CarbonImmutable::parse('2026-08-31 09:00:00'), 'component' => 'hazard'],
            ],
            [['sid' => 'FJAVJ', 'date' => '2026-08-31']],
        );

        $this->assertSame(0, $counts['FJAVJ|2026-08-31']['hazard']);
    }

    public function test_temuan_di_luar_jendela_jaga_tidak_masuk_kualitas(): void
    {
        $findings = $this->reader()->findingsOnDuty(
            [
                ['sid' => 'FJAVJ', 'at' => '2026-08-31 09:00:00', 'category' => 'Tidak menggunakan APD'],
                ['sid' => 'FJAVJ', 'at' => '2026-09-02 00:00:00', 'category' => 'Di luar jaga'],
                ['sid' => 'XXXXX', 'at' => '2026-08-31 09:00:00', 'category' => 'Bukan jaga'],
            ],
            [['sid' => 'FJAVJ', 'date' => '2026-08-31']],
        );

        $this->assertCount(1, $findings);
        $this->assertSame('Tidak menggunakan APD', $findings[0]['category']);
    }

    private function reader(): ControlRoomSapWeekCountsReader
    {
        return new ControlRoomSapWeekCountsReader(
            new PembatasanLVOlapQuery(),
            new ControlRoomSapDutyReader(new PembatasanLVOlapQuery()),
        );
    }
}
