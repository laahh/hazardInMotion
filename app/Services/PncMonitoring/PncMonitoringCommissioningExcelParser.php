<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final class PncMonitoringCommissioningExcelParser
{
    public const MAX_ROWS = 5000;

    /**
     * @var list<string>
     */
    public const HEADERS = [
        'Site',
        'No Register SPIP',
        'Detail Jenis SPIP',
        'Keterangan SKO',
        'Nama Pengawas Teknis',
        'Permohonan Dokumen 1',
        'Week',
        'Tahun',
        'Pemilik SPIP',
        'Pengelola SPIP',
        'Temuan Komisioning',
        'Status Komisioning',
        'Keterangan',
        'Alasan Reject',
        'Status',
        'Performance SKO',
    ];

    /**
     * @var list<string>
     */
    public const FIELD_KEYS = [
        'site',
        'no_register_spip',
        'detail_jenis_spip',
        'keterangan_sko',
        'nama_pengawas_teknis',
        'permohonan_dokumen_1',
        'week',
        'tahun',
        'pemilik_spip',
        'pengelola_spip',
        'temuan_komisioning',
        'status_komisioning',
        'keterangan',
        'alasan_reject',
        'status',
        'performance_sko',
    ];

    public function parse(string $absolutePath): PncMonitoringExcelParseResult
    {
        if (! is_readable($absolutePath)) {
            return new PncMonitoringExcelParseResult([], ['File tidak dapat dibaca.']);
        }

        try {
            $spreadsheet = IOFactory::load($absolutePath);
            $sheet = $spreadsheet->getSheet(0);
            $rows = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable $e) {
            return new PncMonitoringExcelParseResult([], ['File Excel tidak terbaca: '.$e->getMessage()]);
        }

        if ($rows === []) {
            return new PncMonitoringExcelParseResult([], ['File kosong atau tidak terbaca.']);
        }

        $header = array_map(fn (mixed $cell): string => $this->normalizeHeader((string) ($cell ?? '')), (array) array_shift($rows));
        $expected = array_map(fn (string $label): string => $this->normalizeHeader($label), self::HEADERS);
        if ($header !== $expected) {
            return new PncMonitoringExcelParseResult([], [
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

            $register = trim((string) ($attrs['no_register_spip'] ?? ''));
            if ($register === '') {
                $errors[] = "Baris {$excelRow}: No Register SPIP wajib diisi.";
                continue;
            }

            if (($attrs['tahun'] ?? null) === null && is_string($attrs['permohonan_dokumen_1'] ?? null)) {
                $attrs['tahun'] = (int) substr((string) $attrs['permohonan_dokumen_1'], 0, 4);
            }

            $attrs['no_register_spip'] = $register;
            if (isset($seen[$register])) {
                $warnings[] = "Baris {$excelRow}: No Register SPIP {$register} dobel — baris terakhir dipakai.";
            }
            $seen[$register] = true;
            $parsed[$register] = $attrs;
        }

        return new PncMonitoringExcelParseResult(array_values($parsed), $errors, $warnings);
    }

    /**
     * @param  list<mixed>  $cells
     * @return array<string, mixed>
     */
    private function attributesFromRow(array $cells): array
    {
        $attrs = [];
        foreach (self::FIELD_KEYS as $index => $key) {
            $raw = $this->cell($cells, $index);
            $attrs[$key] = match ($key) {
                'permohonan_dokumen_1' => $this->parseDate($raw),
                'week', 'tahun', 'temuan_komisioning' => $this->parseInt($raw),
                'performance_sko' => $this->parseFloat($raw),
                default => $raw,
            };
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

    private function parseDate(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function parseInt(?string $raw): ?int
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (int) round((float) $raw);
    }

    private function parseFloat(?string $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $normalized = str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $raw) ?? $raw);
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * @param  array<string, mixed>  $attrs
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

    private function normalizeHeader(string $label): string
    {
        $label = preg_replace('/\s+/u', ' ', trim($label)) ?? trim($label);

        return mb_strtolower($label);
    }
}
