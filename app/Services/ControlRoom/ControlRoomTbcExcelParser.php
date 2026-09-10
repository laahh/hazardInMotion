<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Membaca Excel Validasi TBC. Header baris 1 harus sama persis urutan template.
 */
final class ControlRoomTbcExcelParser
{
    public const MAX_ROWS = 2000;

    /**
     * @var list<string>
     */
    public const HEADERS = [
        'No Alert',
        'Validator',
        'Tasklist',
        'TobeConcernedHazard',
        'GR',
        'Catatan',
        'Nomor GR valid',
        'Kategori GR valid KPI',
        'Blindspot terlapor BC',
        'Kronologi Singkat (summary dari Deskripsi)',
        'Rootcause Aktual (by dropdown)',
        'Detail Rootcause Aktual',
        'SID Pekerja Terlibat (pelaku/pelanggar)',
        'SID Pengawas Aktual/Pengawas Langsung (pelaku/pelanggar)',
        'Tindakan Perbaikan Aktual',
        'No Item PSPP',
        'Kategori GR',
    ];

    /**
     * @var list<string>
     */
    public const FIELD_KEYS = [
        'no_alert',
        'validator',
        'tasklist',
        'to_be_concerned_hazard',
        'gr',
        'catatan',
        'nomor_gr_valid',
        'kategori_gr_valid_kpi',
        'blindspot_terlapor_bc',
        'kronologi_singkat',
        'rootcause_aktual',
        'detail_rootcause_aktual',
        'sid_pekerja_terlibat',
        'sid_pengawas_aktual',
        'tindakan_perbaikan_aktual',
        'no_item_pspp',
        'kategori_gr',
    ];

    public function parse(string $absolutePath): ControlRoomTbcExcelParseResult
    {
        if (! is_readable($absolutePath)) {
            return new ControlRoomTbcExcelParseResult([], ['File tidak dapat dibaca.']);
        }

        try {
            $spreadsheet = IOFactory::load($absolutePath);
            $sheet = $spreadsheet->getSheet(0);
            $rows = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable $e) {
            return new ControlRoomTbcExcelParseResult([], ['File Excel tidak terbaca: '.$e->getMessage()]);
        }

        if ($rows === []) {
            return new ControlRoomTbcExcelParseResult([], ['File kosong atau tidak terbaca.']);
        }

        $header = array_map(fn (mixed $cell): string => $this->normalizeHeader((string) ($cell ?? '')), (array) array_shift($rows));
        $expected = array_map(fn (string $label): string => $this->normalizeHeader($label), self::HEADERS);
        if ($header !== $expected) {
            return new ControlRoomTbcExcelParseResult([], [
                'Header Excel tidak sesuai template. Urutan wajib: '.implode(', ', self::HEADERS).'.',
            ]);
        }

        $errors = [];
        $warnings = [];
        $parsed = [];
        $seen = [];

        foreach ($rows as $offset => $row) {
            $excelRow = $offset + 2;
            if ($excelRow > self::MAX_ROWS + 1) {
                $errors[] = 'Maksimal '.self::MAX_ROWS.' baris data.';
                break;
            }

            $cells = is_array($row) ? $row : [];
            $attrs = $this->attributesFromRow($cells);
            if ($this->isAttributesAllEmpty($attrs)) {
                continue;
            }

            $tasklist = (string) ($attrs['tasklist'] ?? '');
            if ($tasklist === '') {
                $errors[] = "Baris {$excelRow}: Tasklist wajib diisi.";
                continue;
            }

            if (isset($seen[$tasklist])) {
                $warnings[] = "Baris {$excelRow}: Tasklist {$tasklist} dobel di file — baris terakhir yang dipakai.";
            }
            $seen[$tasklist] = true;
            $parsed[$tasklist] = $attrs;
        }

        return new ControlRoomTbcExcelParseResult(array_values($parsed), $errors, $warnings);
    }

    /**
     * @param  list<mixed>  $cells
     * @return array<string, ?string>
     */
    private function attributesFromRow(array $cells): array
    {
        $attrs = [];
        foreach (self::FIELD_KEYS as $index => $key) {
            $attrs[$key] = $this->cell($cells, $index);
        }

        return $attrs;
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function cell(array $cells, int $index): ?string
    {
        $raw = $cells[$index] ?? null;
        if ($raw === null) {
            return null;
        }
        $value = trim((string) $raw);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, ?string>  $attrs
     */
    private function isAttributesAllEmpty(array $attrs): bool
    {
        foreach ($attrs as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    public function normalizeHeader(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($value));

        return $collapsed ?? trim($value);
    }
}
