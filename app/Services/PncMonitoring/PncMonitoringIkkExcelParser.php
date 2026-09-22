<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringIkkRecord;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Membaca Excel Main Data IKK. Header baris 1 harus sama urutan template.
 */
final class PncMonitoringIkkExcelParser
{
    public const MAX_ROWS = 5000;

    /**
     * @var list<string>
     */
    public const HEADERS = [
        'Jenis',
        'Nomor',
        'Pekerjaan',
        'Tanggal',
        'Minggu',
        'Bulan',
        'Site',
        'Mine Contractor',
        'Perusahaan',
        'Finding IA',
        'Finding Verlap',
        'IA',
        'IPK',
        'PLAN OKK',
        'OKK 1',
        'OKK 2',
        'OKK 3',
        'OKK Performance',
        'OKK Layer 2',
        'OKK Layer 3',
        'OKK Layer 4',
        'Performance Layer 2 Up',
    ];

    /**
     * Kolom "OKK Performance" & "Performance Layer 2 Up" bersifat informasional saja
     * (dihitung otomatis dari OKK 1-3 / OKK Layer 2-4 terhadap PLAN OKK) — ditandai null
     * agar dilewati saat parsing, isinya diabaikan meski diisi user.
     *
     * @var list<string|null>
     */
    public const FIELD_KEYS = [
        'jenis',
        'nomor',
        'pekerjaan',
        'tanggal',
        'minggu',
        'bulan',
        'site',
        'mine_contractor',
        'perusahaan',
        'finding_ia',
        'finding_verlap',
        'ia',
        'ipk',
        'plan_okk',
        'okk_1',
        'okk_2',
        'okk_3',
        null,
        'okk_layer_2',
        'okk_layer_3',
        'okk_layer_4',
        null,
    ];

    /**
     * Batas wajar untuk kolom hitungan (bukan tanggal/flag) — mencegah nilai korup
     * (mis. angka jutaan akibat kolom Excel salah map) tersimpan ke database.
     *
     * @var array<string, int>
     */
    private const COUNT_FIELD_MAX = [
        'finding_ia' => 999,
        'finding_verlap' => 999,
        'plan_okk' => 999,
        'okk_1' => 999,
        'okk_2' => 999,
        'okk_3' => 999,
        'okk_layer_2' => 999,
        'okk_layer_3' => 999,
        'okk_layer_4' => 999,
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

            foreach (self::COUNT_FIELD_MAX as $field => $max) {
                $value = $attrs[$field] ?? null;
                if (is_int($value) && $value > $max) {
                    $warnings[] = "Baris {$excelRow}: nilai {$field} ({$value}) tidak wajar (maks {$max}) — diset ke 0, mohon periksa ulang sumber datanya.";
                    $attrs[$field] = 0;
                }
            }

            $nomor = trim((string) ($attrs['nomor'] ?? ''));
            if ($nomor === '') {
                $errors[] = "Baris {$excelRow}: Nomor wajib diisi.";
                continue;
            }

            $tanggalYmd = $attrs['tanggal'] ?? null;
            $minggu = $attrs['minggu'] ?? null;
            $tahun = $attrs['tahun'] ?? null;
            if ($tahun === null && is_string($tanggalYmd) && $tanggalYmd !== '') {
                $tahun = (int) substr($tanggalYmd, 0, 4);
                $attrs['tahun'] = $tahun;
            }
            if (($attrs['bulan'] ?? null) === null && is_string($tanggalYmd) && $tanggalYmd !== '') {
                $attrs['bulan'] = (int) substr($tanggalYmd, 5, 2);
            }

            $upsertKey = PncMonitoringIkkRecord::buildUpsertKey(
                $nomor,
                is_string($tanggalYmd) ? $tanggalYmd : null,
                is_int($minggu) ? $minggu : null,
                is_int($tahun) ? $tahun : null,
            );
            $attrs['upsert_key'] = $upsertKey;
            $attrs['nomor'] = $nomor;

            if (isset($seen[$upsertKey])) {
                $warnings[] = "Baris {$excelRow}: kunci {$upsertKey} dobel di file — baris terakhir yang dipakai.";
            }
            $seen[$upsertKey] = true;
            $parsed[$upsertKey] = $attrs;
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
            if ($key === null) {
                continue;
            }
            $raw = $this->cell($cells, $index);
            $attrs[$key] = match ($key) {
                'tanggal' => $this->parseDate($raw),
                'minggu', 'bulan', 'finding_ia', 'finding_verlap', 'ia', 'ipk',
                'plan_okk', 'okk_1', 'okk_2', 'okk_3', 'okk_layer_2', 'okk_layer_3', 'okk_layer_4' => $this->parseInt($raw),
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
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw);

                return $date->format('Y-m-d');
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
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }

        return (int) round((float) $raw);
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
