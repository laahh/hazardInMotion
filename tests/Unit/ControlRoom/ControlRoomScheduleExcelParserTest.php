<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\ControlRoomScheduleExcelParser;
use App\Services\ControlRoom\ControlRoomScheduleExcelTemplateService;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class ControlRoomScheduleExcelParserTest extends TestCase
{
    public function test_membaca_baris_jadwal_mingguan_sesuai_kolom_database(): void
    {
        $date = CarbonImmutable::parse('2026-08-31');
        $path = $this->writeSheet([
            ['tanggal', 'shift', 'sid', 'nama', 'site'],
            [$date->toDateString(), 'S1', 'FJAVJ', 'Agung Nugroho', 'HO'],
            [$date->toDateString(), 'S2', 'Agung (ALI01)', '', 'HO'],
            [$date->toDateString(), 'S1', '', '', 'HO'],
        ]);

        $result = (new ControlRoomScheduleExcelParser())->parse(
            $path,
            ControlRoomSiteCode::HeadOffice,
            (int) $date->isoWeekYear(),
            (int) $date->isoWeek(),
        );

        $this->assertFalse($result->hasErrors());
        $this->assertSame(
            [
                ['date' => '2026-08-31', 'shift_code' => 'S1', 'personnel_source_key' => 'FJAVJ'],
                ['date' => '2026-08-31', 'shift_code' => 'S2', 'personnel_source_key' => 'ALI01'],
            ],
            $result->assignments,
        );

        @unlink($path);
    }

    public function test_menolak_tanggal_di_luar_minggu_iso(): void
    {
        $inWeek = CarbonImmutable::parse('2026-08-31');
        $outside = $inWeek->addWeek();
        $path = $this->writeSheet([
            ['tanggal', 'shift', 'sid'],
            [$outside->toDateString(), 'S1', 'FJAVJ'],
        ]);

        $result = (new ControlRoomScheduleExcelParser())->parse(
            $path,
            ControlRoomSiteCode::HeadOffice,
            (int) $inWeek->isoWeekYear(),
            (int) $inWeek->isoWeek(),
        );

        $this->assertTrue($result->hasErrors());
        $this->assertSame([], $result->assignments);
        $this->assertStringContainsString('di luar ISO week', $result->errors[0]);

        @unlink($path);
    }

    public function test_menolak_site_yang_berbeda_dari_filter(): void
    {
        $date = CarbonImmutable::parse('2026-08-31');
        $path = $this->writeSheet([
            ['tanggal', 'shift', 'sid', 'site'],
            [$date->toDateString(), 'S1', 'FJAVJ', 'BMO1'],
        ]);

        $result = (new ControlRoomScheduleExcelParser())->parse(
            $path,
            ControlRoomSiteCode::HeadOffice,
            (int) $date->isoWeekYear(),
            (int) $date->isoWeek(),
        );

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('tidak sama dengan site yang dipilih', $result->errors[0]);

        @unlink($path);
    }

    public function test_template_mingguan_bisa_diisi_sid_lalu_diimpor(): void
    {
        $date = CarbonImmutable::parse('2026-08-31');
        $year = (int) $date->isoWeekYear();
        $week = (int) $date->isoWeek();
        $spreadsheet = (new ControlRoomScheduleExcelTemplateService())->spreadsheet(
            ControlRoomSiteCode::HeadOffice,
            $year,
            $week,
            collect([(object) ['sid' => 'FJAVJ', 'emp_name' => 'Agung Nugroho', 'site_dedicated' => 'HO']]),
        );

        $this->assertSame(['Jadwal', 'Personil', 'Petunjuk'], $spreadsheet->getSheetNames());
        $jadwal = $spreadsheet->getSheetByName('Jadwal');
        $this->assertNotNull($jadwal);
        $this->assertSame('tanggal', $jadwal->getCell('B1')->getValue());
        $this->assertSame('sid', $jadwal->getCell('D1')->getValue());
        $jadwal->setCellValue('D2', 'FJAVJ');

        $siteValidation = $jadwal->getDataValidation('F2');
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST, $siteValidation->getType());
        foreach (ControlRoomSiteCode::cases() as $siteCode) {
            $this->assertStringContainsString($siteCode->value, $siteValidation->getFormula1());
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ocr-template-'.uniqid('', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $result = (new ControlRoomScheduleExcelParser())->parse($path, ControlRoomSiteCode::HeadOffice, $year, $week);

        $this->assertFalse($result->hasErrors(), implode(' | ', $result->errors));
        $this->assertCount(1, $result->assignments);
        $this->assertSame('FJAVJ', $result->assignments[0]['personnel_source_key']);
        $this->assertSame('S1', $result->assignments[0]['shift_code']);
        $this->assertSame($date->toDateString(), $result->assignments[0]['date']);

        @unlink($path);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function writeSheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ocr-jadwal-'.uniqid('', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
