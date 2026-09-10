<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomTbcExcelParser;
use App\Services\ControlRoom\ControlRoomTbcExcelTemplateService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class ControlRoomTbcExcelParserTest extends TestCase
{
    public function test_header_template_ada_tujuh_belas_kolom_sesuai_urutan(): void
    {
        $this->assertCount(17, ControlRoomTbcExcelParser::HEADERS);
        $this->assertSame('No Alert', ControlRoomTbcExcelParser::HEADERS[0]);
        $this->assertSame('Tasklist', ControlRoomTbcExcelParser::HEADERS[2]);
        $this->assertSame('Rootcause Aktual (by dropdown)', ControlRoomTbcExcelParser::HEADERS[10]);
        $this->assertSame('Kategori GR', ControlRoomTbcExcelParser::HEADERS[16]);
        $header = (new ControlRoomTbcExcelTemplateService())->spreadsheet()->getSheet(0)->rangeToArray('A1:Q1')[0];
        $this->assertSame(ControlRoomTbcExcelParser::HEADERS, $header);
    }

    public function test_header_salah_ditolak(): void
    {
        $path = $this->writeSheet([
            ['Validator', 'Tasklist'],
            ['A', '1'],
        ]);

        $result = (new ControlRoomTbcExcelParser())->parse($path);

        $this->assertTrue($result->hasErrors());
        $this->assertSame([], $result->rows);
        $this->assertStringContainsString('Header Excel tidak sesuai template', $result->errors[0]);

        @unlink($path);
    }

    public function test_tasklist_kosong_error(): void
    {
        $row = array_fill(0, 17, '');
        $row[1] = 'Validator A';
        $path = $this->writeSheet([
            ControlRoomTbcExcelParser::HEADERS,
            $row,
        ]);

        $result = (new ControlRoomTbcExcelParser())->parse($path);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Tasklist wajib', $result->errors[0]);

        @unlink($path);
    }

    public function test_tasklist_dobel_di_file_baris_terakhir_yang_dipakai(): void
    {
        $first = array_fill(0, 17, '');
        $first[2] = '9374205';
        $first[3] = 'Lama';
        $second = array_fill(0, 17, '');
        $second[2] = '9374205';
        $second[3] = 'Baru';
        $path = $this->writeSheet([
            ControlRoomTbcExcelParser::HEADERS,
            $first,
            $second,
        ]);

        $result = (new ControlRoomTbcExcelParser())->parse($path);

        $this->assertFalse($result->hasErrors());
        $this->assertCount(1, $result->rows);
        $this->assertSame('9374205', $result->rows[0]['tasklist']);
        $this->assertSame('Baru', $result->rows[0]['to_be_concerned_hazard']);
        $this->assertNotEmpty($result->warnings);

        @unlink($path);
    }

    public function test_header_dengan_baris_baru_tetap_diterima(): void
    {
        $headers = ControlRoomTbcExcelParser::HEADERS;
        $headers[10] = "Rootcause Aktual\n  (by dropdown)";
        $row = array_fill(0, 17, '');
        $row[2] = 'TL-1';
        $row[0] = 'No Alert';
        $path = $this->writeSheet([$headers, $row]);

        $result = (new ControlRoomTbcExcelParser())->parse($path);

        $this->assertFalse($result->hasErrors(), implode(' | ', $result->errors));
        $this->assertSame('TL-1', $result->rows[0]['tasklist']);
        $this->assertSame('No Alert', $result->rows[0]['no_alert']);

        @unlink($path);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function writeSheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ocr-tbc-'.uniqid('', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
