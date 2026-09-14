<?php

declare(strict_types=1);

namespace Tests\Unit\PncMonitoring;

use App\Services\PncMonitoring\PncMonitoringCommissioningExcelParser;
use App\Services\PncMonitoring\PncMonitoringCommissioningExcelTemplateService;
use App\Services\PncMonitoring\PncMonitoringIkkExcelParser;
use App\Services\PncMonitoring\PncMonitoringIkkExcelTemplateService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class PncMonitoringExcelParserTest extends TestCase
{
    public function test_ikk_header_template_cocok(): void
    {
        $this->assertCount(20, PncMonitoringIkkExcelParser::HEADERS);
        $this->assertSame('Jenis', PncMonitoringIkkExcelParser::HEADERS[0]);
        $this->assertSame('Nomor', PncMonitoringIkkExcelParser::HEADERS[1]);
        $header = (new PncMonitoringIkkExcelTemplateService())->spreadsheet()->getSheet(0)->rangeToArray('A1:T1')[0];
        $this->assertSame(PncMonitoringIkkExcelParser::HEADERS, $header);
    }

    public function test_ikk_header_salah_ditolak(): void
    {
        $path = $this->writeSheet([
            ['Nomor', 'Site'],
            ['X', 'Y'],
        ]);
        $result = (new PncMonitoringIkkExcelParser())->parse($path);
        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Header Excel tidak sesuai template', $result->errors[0]);
        @unlink($path);
    }

    public function test_ikk_nomor_wajib(): void
    {
        $row = array_fill(0, 20, '');
        $row[0] = 'Jenis A';
        $path = $this->writeSheet([PncMonitoringIkkExcelParser::HEADERS, $row]);
        $result = (new PncMonitoringIkkExcelParser())->parse($path);
        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Nomor wajib', $result->errors[0]);
        @unlink($path);
    }

    public function test_ikk_happy_path(): void
    {
        $row = array_fill(0, 20, '');
        $row[0] = 'Hot Work';
        $row[1] = 'IKK-001';
        $row[4] = '12';
        $row[5] = '3';
        $row[6] = 'Binungan';
        $path = $this->writeSheet([PncMonitoringIkkExcelParser::HEADERS, $row]);
        $result = (new PncMonitoringIkkExcelParser())->parse($path);
        $this->assertFalse($result->hasErrors());
        $this->assertCount(1, $result->rows);
        $this->assertSame('IKK-001', $result->rows[0]['nomor']);
        $this->assertSame('Binungan', $result->rows[0]['site']);
        @unlink($path);
    }

    public function test_commissioning_header_dan_register_wajib(): void
    {
        $this->assertCount(16, PncMonitoringCommissioningExcelParser::HEADERS);
        $header = (new PncMonitoringCommissioningExcelTemplateService())->spreadsheet()->getSheet(0)->rangeToArray('A1:P1')[0];
        $this->assertSame(PncMonitoringCommissioningExcelParser::HEADERS, $header);

        $row = array_fill(0, 16, '');
        $row[0] = 'Site A';
        $path = $this->writeSheet([PncMonitoringCommissioningExcelParser::HEADERS, $row]);
        $result = (new PncMonitoringCommissioningExcelParser())->parse($path);
        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('No Register SPIP wajib', $result->errors[0]);
        @unlink($path);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function writeSheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pnc-mon-'.uniqid('', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
