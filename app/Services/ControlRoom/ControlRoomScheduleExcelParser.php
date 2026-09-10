<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Membaca Excel jadwal mingguan menjadi assignment yang sama dengan
 * control_room_schedule_plans: satu baris = site + tanggal + shift + SID.
 */
final class ControlRoomScheduleExcelParser
{
    private const MAX_ROWS = 500;

    /**
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'tanggal' => 'date',
        'date' => 'date',
        'tgl' => 'date',
        'shift' => 'shift',
        'shift_code' => 'shift',
        'kode_shift' => 'shift',
        'sid' => 'sid',
        'personnel_source_key' => 'sid',
        'kode_sid' => 'sid',
        'source_key' => 'sid',
        'nama' => 'nama',
        'nama_personil' => 'nama',
        'personnel_name_snapshot' => 'nama',
        'name' => 'nama',
        'site' => 'site',
        'site_code' => 'site',
    ];

    public function parse(string $absolutePath, ControlRoomSiteCode $site, int $year, int $week): ControlRoomScheduleExcelParseResult
    {
        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (Throwable $e) {
            return new ControlRoomScheduleExcelParseResult([], ['File Excel tidak bisa dibaca. Unggah .xlsx atau .xls.']);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        if ($rows === []) {
            return new ControlRoomScheduleExcelParseResult([], ['Sheet Excel kosong.']);
        }

        $headerMap = $this->headerMap((array) array_shift($rows));
        if (! isset($headerMap['date'], $headerMap['shift'], $headerMap['sid'])) {
            return new ControlRoomScheduleExcelParseResult([], [
                'Header wajib: tanggal, shift, sid (nama opsional). Sesuai kolom jadwal di database.',
            ]);
        }

        $period = ControlRoomIsoWeekPeriod::of($year, $week);
        $weekStart = $period->start;
        $weekEnd = $period->end->startOfDay();
        $errors = [];
        $assignments = [];
        $seen = [];

        foreach ($rows as $offset => $row) {
            $excelRow = $offset + 2;
            if ($excelRow > self::MAX_ROWS + 1) {
                $errors[] = 'Maksimal '.self::MAX_ROWS.' baris data.';
                break;
            }

            $cells = is_array($row) ? $row : [];
            $dateRaw = $this->cell($cells, $headerMap['date']);
            $shiftRaw = $this->cell($cells, $headerMap['shift']);
            $sidRaw = $this->cell($cells, $headerMap['sid']);
            $namaRaw = isset($headerMap['nama']) ? $this->cell($cells, $headerMap['nama']) : '';
            $siteRaw = isset($headerMap['site']) ? $this->cell($cells, $headerMap['site']) : '';

            if ($this->isBlank($dateRaw) && $this->isBlank($shiftRaw) && $this->isBlank($sidRaw) && $this->isBlank($namaRaw)) {
                continue;
            }

            $sid = $this->extractSid($sidRaw, $namaRaw);
            if ($sid === '') {
                if (! $this->isBlank($namaRaw)) {
                    $errors[] = "Baris {$excelRow}: SID kosong. Isi kolom sid atau format Nama (SID).";
                }
                continue;
            }

            $date = $this->parseDate($dateRaw);
            if ($date === null) {
                $errors[] = "Baris {$excelRow}: tanggal tidak valid.";
                continue;
            }

            if ($date->lt($weekStart) || $date->gt($weekEnd)) {
                $errors[] = "Baris {$excelRow}: tanggal {$date->toDateString()} di luar minggu {$week}/{$year} ({$weekStart->toDateString()} – {$weekEnd->toDateString()}, Minggu–Sabtu).";
                continue;
            }

            $shifts = $this->parseShifts($shiftRaw);
            if ($shifts === []) {
                $errors[] = "Baris {$excelRow}: shift harus S1 atau S2.";
                continue;
            }

            if ($siteRaw !== '') {
                $rowSite = $this->parseSite($siteRaw);
                if ($rowSite === null) {
                    $errors[] = "Baris {$excelRow}: site \"{$siteRaw}\" tidak dikenali.";
                    continue;
                }
                if ($rowSite !== $site) {
                    $errors[] = "Baris {$excelRow}: site {$rowSite->value} tidak sama dengan site yang dipilih ({$site->value}).";
                    continue;
                }
            }

            foreach ($shifts as $shift) {
                $key = $date->toDateString().'|'.$shift.'|'.$sid;
                if (isset($seen[$key])) {
                    $errors[] = "Baris {$excelRow}: SID {$sid} dobel di {$date->toDateString()} {$shift}.";
                    continue;
                }
                $seen[$key] = true;
                $assignments[] = [
                    'date' => $date->toDateString(),
                    'shift_code' => $shift,
                    'personnel_source_key' => $sid,
                ];
            }
        }

        if ($assignments === [] && $errors === []) {
            $errors[] = 'Tidak ada baris jadwal yang bisa diimpor. Isi SID pada baris tanggal + shift.';
        }

        return new ControlRoomScheduleExcelParseResult($assignments, array_values(array_unique($errors)));
    }

    /**
     * @param  list<mixed>  $headerRow
     * @return array<string, int>
     */
    private function headerMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $index => $header) {
            $normalized = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', trim((string) $header))));
            $normalized = trim($normalized, '_');
            if ($normalized === '' || ! isset(self::HEADER_ALIASES[$normalized])) {
                continue;
            }
            $map[self::HEADER_ALIASES[$normalized]] = (int) $index;
        }

        return $map;
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function cell(array $cells, int $index): mixed
    {
        return $cells[$index] ?? null;
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return trim((string) $value) === '';
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::parse($value)->startOfDay();
        }

        if (is_numeric($value) && (float) $value > 20000) {
            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            } catch (Throwable) {
                return null;
            }
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            $parsed = CarbonImmutable::createFromFormat('!'.$format, $text);
            if ($parsed instanceof CarbonImmutable) {
                return $parsed->startOfDay();
            }
        }

        try {
            return CarbonImmutable::parse($text)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function parseShifts(mixed $value): array
    {
        $text = strtoupper(trim((string) $value));
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/[,\/|;]+/', $text) ?: [$text];
        $shifts = [];
        foreach ($parts as $part) {
            $token = preg_replace('/\s+/', '', trim($part)) ?? '';
            $code = match ($token) {
                'S1', 'SHIFT1', '1', 'PAGI' => ControlRoomShiftCode::S1->value,
                'S2', 'SHIFT2', '2', 'MALAM' => ControlRoomShiftCode::S2->value,
                default => null,
            };
            if ($code !== null) {
                $shifts[] = $code;
            }
        }

        return array_values(array_unique($shifts));
    }

    private function extractSid(mixed $sidRaw, mixed $namaRaw): string
    {
        $sid = $this->sidFromText((string) $sidRaw);
        if ($sid !== '') {
            return $sid;
        }

        return $this->sidFromText((string) $namaRaw);
    }

    private function sidFromText(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/\(([^)]+)\)\s*$/', $raw, $matches) === 1) {
            $raw = trim($matches[1]);
        }

        return strtoupper($raw);
    }

    private function parseSite(string $raw): ?ControlRoomSiteCode
    {
        $token = strtoupper((string) preg_replace('/[\s\-]+/', '', trim($raw)));
        if ($token === '') {
            return null;
        }

        foreach (ControlRoomSiteCode::cases() as $site) {
            $code = strtoupper((string) preg_replace('/[\s\-]+/', '', $site->value));
            $source = strtoupper((string) preg_replace('/[\s\-]+/', '', $site->sourceKey()));
            if ($token === $code || $token === $source) {
                return $site;
            }
        }

        return null;
    }
}
