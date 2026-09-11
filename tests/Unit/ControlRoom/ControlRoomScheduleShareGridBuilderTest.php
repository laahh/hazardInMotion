<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\ControlRoomIsoWeekPeriod;
use App\Services\ControlRoom\ControlRoomScheduleShareExcelService;
use App\Services\ControlRoom\ControlRoomScheduleShareGridBuilder;
use Tests\TestCase;

final class ControlRoomScheduleShareGridBuilderTest extends TestCase
{
    public function test_matriks_mengikuti_tanggal_plan_bukan_semua_hari_kosong(): void
    {
        $period = ControlRoomIsoWeekPeriod::of(2026, 37);
        $grid = $this->builder()->build($period, ControlRoomSiteCode::Bmo1, [
            [
                'site_code' => 'BMO1',
                'date' => '2026-09-11',
                'shift_code' => 'S1',
                'personnel_source_key' => 'AAA01',
                'personnel_name_snapshot' => 'Oscar Whimmy',
            ],
            [
                'site_code' => 'BMO1',
                'date' => '2026-09-12',
                'shift_code' => 'S2',
                'personnel_source_key' => 'BBB02',
                'personnel_name_snapshot' => 'Tegar',
            ],
        ]);

        $this->assertSame('2026-09-06', $grid['week_start']);
        $this->assertSame('2026-09-12', $grid['week_end']);
        $this->assertCount(7, $grid['days']);
        $this->assertSame('06/09/2026', $grid['days'][0]['header']);
        $this->assertSame(2, $grid['people_count']);
        $this->assertSame(2, $grid['slot_count']);

        $supervisor = $this->group($grid, 'Site Safety Supervisor');
        $this->assertSame('Oscar Whimmy', $supervisor['rows'][0]['name']);
        $this->assertSame('Shift 1', $supervisor['rows'][0]['cells'][5]['text']);
        $this->assertSame('', $supervisor['rows'][0]['cells'][0]['text']);

        $foreman = $this->group($grid, 'Site Safety Foreman');
        $this->assertSame('Tegar', $foreman['rows'][0]['name']);
        $this->assertSame('Shift 2', $foreman['rows'][0]['cells'][6]['text']);
    }

    public function test_jabatan_superintendent_mengelompokkan_baris(): void
    {
        $period = ControlRoomIsoWeekPeriod::of(2026, 37);
        $grid = $this->builder()->build(
            $period,
            ControlRoomSiteCode::HeadOffice,
            [
                [
                    'site_code' => 'HO',
                    'date' => '2026-09-11',
                    'shift_code' => 'S1',
                    'personnel_source_key' => 'SUP01',
                    'personnel_name_snapshot' => 'Wahyudi',
                ],
            ],
            ['SUP01' => 'Safety Superintendent'],
        );

        $this->assertNotNull($this->group($grid, 'Safety Superintendent'));
        $this->assertSame('Wahyudi', $this->group($grid, 'Safety Superintendent')['rows'][0]['name']);
        $this->assertNull($this->group($grid, 'Site Safety Supervisor'));
    }

    public function test_excel_share_memuat_judul_minggu(): void
    {
        $period = ControlRoomIsoWeekPeriod::of(2026, 37);
        $grid = $this->builder()->build($period, ControlRoomSiteCode::Lmo, [
            [
                'site_code' => 'LMO',
                'date' => '2026-09-08',
                'shift_code' => 'S1',
                'personnel_source_key' => 'LMO01',
                'personnel_name_snapshot' => 'Yadi Haryadi',
            ],
        ]);
        $sheet = (new ControlRoomScheduleShareExcelService())->spreadsheet($grid)->getActiveSheet();

        $this->assertStringContainsString('Tim Safety', (string) $sheet->getCell('A1')->getValue());
        $this->assertStringContainsString('Yadi Haryadi', (string) $sheet->getCell('B3')->getValue());
        $this->assertSame('Shift 1', (string) $sheet->getCell('E3')->getValue());
    }

    public function test_plan_di_luar_minggu_diabaikan(): void
    {
        $period = ControlRoomIsoWeekPeriod::of(2026, 37);
        $grid = $this->builder()->build($period, ControlRoomSiteCode::Gmo, [
            [
                'site_code' => 'GMO',
                'date' => '2026-09-13',
                'shift_code' => 'S1',
                'personnel_source_key' => 'ZZZ',
                'personnel_name_snapshot' => 'Luar Minggu',
            ],
        ]);

        $this->assertSame(0, $grid['people_count']);
        $this->assertSame([], $grid['groups']);
    }

    /**
     * @param  array<string, mixed>  $grid
     * @return array<string, mixed>|null
     */
    private function group(array $grid, string $title): ?array
    {
        foreach ($grid['groups'] as $group) {
            if ($group['title'] === $title) {
                return $group;
            }
        }

        return null;
    }

    private function builder(): ControlRoomScheduleShareGridBuilder
    {
        return new ControlRoomScheduleShareGridBuilder();
    }
}
