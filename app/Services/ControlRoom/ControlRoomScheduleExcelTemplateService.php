<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Template Excel mingguan Control Room.
 * Sheet Jadwal = slot kalender (satu baris = control_room_schedule_plans).
 * Sheet Personil = daftar SID untuk disalin ke kolom sid.
 */
final class ControlRoomScheduleExcelTemplateService
{
    /** @var array<int, string> */
    private const HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];
    /**
     * @param  Collection<int, object>  $personnel
     */
    public function download(ControlRoomSiteCode $site, int $year, int $week, Collection $personnel = new Collection()): StreamedResponse
    {
        $spreadsheet = $this->spreadsheet($site, $year, $week, $personnel);
        $filename = sprintf('template-jadwal-control-room-%s-w%02d-%d.xlsx', $site->value, $week, $year);

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  Collection<int, object>  $personnel
     */
    public function spreadsheet(ControlRoomSiteCode $site, int $year, int $week, Collection $personnel = new Collection()): Spreadsheet
    {
        $weekStart = CarbonImmutable::now()->setISODate($year, $week, 1)->startOfDay();
        $weekEnd = $weekStart->addDays(6);
        $spreadsheet = new Spreadsheet();

        $this->writeJadwalSheet($spreadsheet, $site, $year, $week, $weekStart);
        $this->writePersonilSheet($spreadsheet, $personnel);
        $this->writePetunjukSheet($spreadsheet, $site, $year, $week, $weekStart, $weekEnd);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function writeJadwalSheet(
        Spreadsheet $spreadsheet,
        ControlRoomSiteCode $site,
        int $year,
        int $week,
        CarbonImmutable $weekStart,
    ): void {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jadwal');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F15');

        $headers = ['hari', 'tanggal', 'shift', 'sid', 'nama', 'site'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1E3A8A']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DBEAFE'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 2;
        for ($day = 0; $day < 7; $day++) {
            $date = $weekStart->addDays($day);
            $hari = self::HARI[(int) $date->dayOfWeekIso] ?? $date->format('l');
            foreach (['S1', 'S2'] as $shift) {
                $sheet->setCellValue('A'.$row, $hari);
                $sheet->setCellValue('B'.$row, $date->toDateString());
                $sheet->setCellValue('C'.$row, $shift);
                $sheet->setCellValue('D'.$row, '');
                $sheet->setCellValue('E'.$row, '');
                $sheet->setCellValue('F'.$row, $site->value);
                $fill = $shift === 'S1' ? 'EFF6FF' : 'FFF7ED';
                $sheet->getStyle('A'.$row.':F'.$row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($fill);
                $row++;
            }
        }

        $sheet->getStyle('D2:D15')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEF08A'],
            ],
        ]);
        $sheet->getStyle('A1:F15')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        $validation = $sheet->getCell('C2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setFormula1('"S1,S2"');
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Shift tidak valid');
        $validation->setError('Isi S1 atau S2.');
        $sheet->setDataValidation('C2:C15', $validation);

        $siteList = implode(',', array_map(
            static fn (ControlRoomSiteCode $item): string => $item->value,
            ControlRoomSiteCode::cases(),
        ));
        $siteValidation = $sheet->getCell('F2')->getDataValidation();
        $siteValidation->setType(DataValidation::TYPE_LIST);
        $siteValidation->setFormula1('"'.$siteList.'"');
        $siteValidation->setAllowBlank(false);
        $siteValidation->setShowDropDown(true);
        $siteValidation->setShowErrorMessage(true);
        $siteValidation->setErrorTitle('Site tidak valid');
        $siteValidation->setError('Pilih site dari daftar.');
        $sheet->setDataValidation('F2:F15', $siteValidation);

        $sheet->getComment('D2')->getText()->createTextRun('Wajib diisi. Salin SID dari sheet Personil. Baris SID kosong tidak diimpor.');
        $sheet->getComment('D2')->setWidth('240pt');
        $sheet->getComment('D2')->setHeight('60pt');

        $sheet->getProtection()->setSheet(false);
        $sheet->getStyle('A2:C15')->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        $sheet->getStyle('D2:F15')->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(32);
        $sheet->getColumnDimension('F')->setWidth(12);

        $sheet->setSelectedCell('D2');
        $sheet->getHeaderFooter()->setOddHeader('&CTemplate Jadwal Control Room — '.$site->value.' W'.$week.'/'.$year);
    }

    /**
     * @param  Collection<int, object>  $personnel
     */
    private function writePersonilSheet(Spreadsheet $spreadsheet, Collection $personnel): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Personil');
        $sheet->freezePane('A2');
        $sheet->fromArray(['sid', 'nama', 'site_dedicated'], null, 'A1');
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DCFCE7'],
            ],
        ]);

        $row = 2;
        foreach ($personnel as $person) {
            $sid = strtoupper(trim((string) ($person->sid ?? '')));
            if ($sid === '') {
                continue;
            }
            $sheet->setCellValue('A'.$row, $sid);
            $sheet->setCellValue('B'.$row, (string) ($person->emp_name ?? ''));
            $sheet->setCellValue('C'.$row, (string) ($person->site_dedicated ?? ''));
            $row++;
            if ($row > 3001) {
                break;
            }
        }

        if ($row === 2) {
            $sheet->setCellValue('A2', '—');
            $sheet->setCellValue('B2', 'Daftar personil aktif tidak termuat. Isi SID manual di sheet Jadwal.');
        }

        $last = max(2, $row - 1);
        $sheet->setAutoFilter('A1:C'.$last);
        foreach (range('A', 'C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function writePetunjukSheet(
        Spreadsheet $spreadsheet,
        ControlRoomSiteCode $site,
        int $year,
        int $week,
        CarbonImmutable $weekStart,
        CarbonImmutable $weekEnd,
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Petunjuk');
        $sheet->fromArray([
            ['Template upload jadwal Control Room'],
            ['Site', $site->value.' — '.$site->label()],
            ['Minggu ISO', 'W'.$week.'/'.$year],
            ['Rentang tanggal', $weekStart->toDateString().' s/d '.$weekEnd->toDateString().' (Senin–Minggu)'],
            [''],
            ['Cara isi'],
            ['1. Buka sheet Jadwal. Kolom kuning (sid) wajib diisi untuk baris yang dijadwalkan.'],
            ['2. Salin SID dari sheet Personil (boleh filter nama). Nama opsional — sistem mengisi dari master.'],
            ['3. Jangan ubah header: hari, tanggal, shift, sid, nama, site.'],
            ['4. Shift hanya S1 atau S2. Kolom site memakai dropdown semua site Control Room.'],
            ['5. Satu orang boleh S1 dan S2 di hari yang sama (akan ada peringatan).'],
            ['6. Simpan file, unggah di halaman Jadwal Rencana untuk site dan minggu yang sama.'],
            ['7. Baris SID kosong dilewati. Slot locked tidak ditimpa.'],
            [''],
            ['Pemetaan ke database control_room_schedule_plans'],
            ['Excel tanggal → date'],
            ['Excel shift → shift_code'],
            ['Excel sid → personnel_source_key'],
            ['Excel nama → personnel_name_snapshot (opsional)'],
            ['Excel site → site_code (dropdown; harus sama dengan site yang dipilih saat unggah)'],
            ['year & week_number dihitung otomatis dari tanggal (ISO).'],
        ], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A6')->getFont()->setBold(true);
        $sheet->getStyle('A15')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(100);
        $sheet->getColumnDimension('B')->setWidth(48);
    }
}
