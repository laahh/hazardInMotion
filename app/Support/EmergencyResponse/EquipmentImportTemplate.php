<?php

declare(strict_types=1);

namespace App\Support\EmergencyResponse;

use App\Jobs\EmergencyResponse\ImportEquipmentJob;
use App\Models\Company;
use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\MasterData\EquipmentCategory;
use App\Models\EmergencyResponse\MasterData\Site;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template import database equipment: sheet "Data" untuk diisi, sheet
 * "Referensi" berisi pilihan yang diambil dari database, dan sheet "Petunjuk".
 *
 * Kolom yang punya daftar nilai dipasangi dropdown Excel yang menunjuk ke
 * sheet Referensi — bukan daftar inline — karena daftar inline dibatasi 255
 * karakter oleh Excel dan tidak cukup untuk ratusan perusahaan.
 */
class EquipmentImportTemplate
{
    /** Baris data yang dipasangi dropdown. */
    private const LAST_DATA_ROW = 1001;

    private const DATA_SHEET = 'Data';

    private const REFERENCE_SHEET = 'Referensi';

    private const GUIDE_SHEET = 'Petunjuk';

    public function build(): Spreadsheet
    {
        $references = $this->references();

        $spreadsheet = new Spreadsheet();

        // Sheet diambil per indeks: createSheet() memindahkan active sheet,
        // sehingga getActiveSheet() akan menunjuk sheet yang salah.
        $data = $spreadsheet->getSheet(0);
        $data->setTitle(self::DATA_SHEET);

        $reference = $spreadsheet->createSheet();
        $reference->setTitle(self::REFERENCE_SHEET);

        $guide = $spreadsheet->createSheet();
        $guide->setTitle(self::GUIDE_SHEET);

        $this->writeReferenceSheet($reference, $references);
        $this->writeDataSheet($data, $references);
        $this->writeGuideSheet($guide);

        $spreadsheet->setActiveSheetIndexByName(self::DATA_SHEET);

        return $spreadsheet;
    }

    /**
     * Daftar pilihan per kolom Data. Kategori/SITE/Perusahaan diambil dari
     * tabelnya masing-masing, sisanya dari daftar status di model.
     *
     * @return array<string, array{title: string, options: array<int, string>, strict: bool}>
     */
    private function references(): array
    {
        return [
            'A' => [
                'title' => 'Kategori Peralatan',
                'options' => EquipmentCategory::query()->where('is_active', true)->orderBy('name')->pluck('name')->all(),
                'strict' => true,
            ],
            'E' => [
                'title' => 'Klasifikasi Alat',
                'options' => $this->usedClassifications(),
                // Klasifikasi belum punya master data, jadi nilai baru tetap boleh.
                'strict' => false,
            ],
            'F' => [
                'title' => 'Kondisi Peralatan',
                'options' => array_values(EmergencyEquipment::CONDITIONS),
                'strict' => true,
            ],
            'I' => [
                'title' => 'Status Posisi Barang',
                'options' => array_values(EmergencyEquipment::POSITION_STATUSES),
                'strict' => true,
            ],
            'J' => [
                'title' => 'SITE',
                'options' => Site::query()->where('is_active', true)->orderBy('name')->pluck('name')->unique()->values()->all(),
                'strict' => true,
            ],
            'K' => [
                'title' => 'Perusahaan',
                'options' => Company::query()->where('is_active', true)->orderBy('name')->pluck('name')->all(),
                'strict' => true,
            ],
            'L' => [
                'title' => 'Progress BA',
                'options' => array_values(EmergencyEquipment::BA_PROGRESSES),
                'strict' => true,
            ],
            'N' => [
                'title' => 'Status Barang',
                'options' => array_values(EmergencyEquipment::ITEM_STATUSES),
                'strict' => true,
            ],
        ];
    }

    /** @return array<int, string> */
    private function usedClassifications(): array
    {
        return EmergencyEquipment::query()
            ->whereNotNull('classification')
            ->where('classification', '!=', '')
            ->distinct()
            ->orderBy('classification')
            ->pluck('classification')
            ->all();
    }

    /**
     * @param  array<string, array{title: string, options: array<int, string>, strict: bool}>  $references
     */
    private function writeDataSheet(Worksheet $sheet, array $references): void
    {
        $headers = ImportEquipmentJob::TEMPLATE_HEADERS;
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeaderRow($sheet, count($headers));

        $referenceColumn = 'A';

        foreach ($references as $column => $reference) {
            $this->applyDropdown($sheet, $column, count($reference['options']), $referenceColumn, $reference['strict']);
            $this->markDropdownHeader($sheet, $column);
            $referenceColumn++;
        }

        $sheet->getStyle('O2:O'.self::LAST_DATA_ROW)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->freezePane('A2');
    }

    private function applyDropdown(Worksheet $sheet, string $column, int $optionCount, string $referenceColumn, bool $strict): void
    {
        if ($optionCount === 0) {
            return;
        }

        $range = sprintf('%s!$%s$2:$%s$%d', self::REFERENCE_SHEET, $referenceColumn, $referenceColumn, $optionCount + 1);

        $validation = $sheet->getCell($column.'2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle($strict ? DataValidation::STYLE_STOP : DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Nilai tidak dikenal');
        $validation->setError('Pilih salah satu nilai dari dropdown (daftar lengkap ada di sheet Referensi).');
        $validation->setFormula1($range);

        $sheet->setDataValidation($column.'2:'.$column.self::LAST_DATA_ROW, $validation);
    }

    /** Menandai kolom ber-dropdown supaya petunjuk "kolom berwarna" benar. */
    private function markDropdownHeader(Worksheet $sheet, string $column): void
    {
        $sheet->getStyle($column.'1')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F6F43']],
        ]);
    }

    /**
     * @param  array<string, array{title: string, options: array<int, string>, strict: bool}>  $references
     */
    private function writeReferenceSheet(Worksheet $sheet, array $references): void
    {
        $column = 'A';
        foreach ($references as $reference) {
            $sheet->setCellValue($column.'1', $reference['title']);

            $row = 2;
            foreach ($reference['options'] as $option) {
                $sheet->setCellValue($column.$row, $option);
                $row++;
            }

            $sheet->getColumnDimension($column)->setAutoSize(true);
            $column++;
        }

        $this->styleHeaderRow($sheet, count($references));
    }

    private function writeGuideSheet(Worksheet $sheet): void
    {
        $sheet->fromArray(array_map(fn (string $line): array => [$line], [
            'Template Import Database Equipment',
            '',
            'Cara pakai:',
            '1. Isi sheet "Data" mulai baris 2. Hapus baris contoh sebelum upload.',
            '2. Kolom berwarna punya dropdown: klik selnya lalu pilih nilai dari daftar.',
            '3. Daftar lengkap pilihan ada di sheet "Referensi" (diambil dari database).',
            '4. Simpan sebagai .xlsx, lalu upload lewat tombol Import.',
            '',
            'Catatan:',
            '- Kolom UUID tidak ada di template. UUID dibuat otomatis dari SITE-Kategori-Nama-Urutan.',
            '- "Nama Peralatan" wajib diisi. Baris tanpa nama akan dilewati.',
            '- "No Registrasi" dipakai untuk mencegah data ganda: baris dengan No Registrasi',
            '  yang sudah ada akan memperbarui data lama, bukan menambah baris baru.',
            '  Baris tanpa No Registrasi selalu dibuat sebagai data baru.',
            '- "Tanggal Close BA" format YYYY-MM-DD (mis. 2026-09-15) atau tanggal Excel.',
            '- Nilai di luar dropdown akan dikosongkan dan dicatat di log sistem.',
            '- Kalau Kategori/SITE/Perusahaan yang dibutuhkan belum ada di dropdown,',
            '  tambahkan dulu lewat menu Master Data / daftar perusahaan, lalu unduh template lagi.',
        ]), null, 'A1');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getColumnDimension('A')->setWidth(95);
    }

    private function styleHeaderRow(Worksheet $sheet, int $columnCount): void
    {
        if ($columnCount === 0) {
            return;
        }

        $lastColumn = chr(ord('A') + $columnCount - 1);

        $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
