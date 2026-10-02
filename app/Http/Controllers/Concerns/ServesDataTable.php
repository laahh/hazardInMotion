<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mesin bersama untuk tabel server-side (paging, sorting, search) plus
 * unduhan Excel/CSV. Dipakai oleh tab-tab halaman Peer Pressure.
 *
 * Sengaja berupa helper berparameter, bukan kelas abstrak dengan banyak
 * method wajib: tiap tab punya sumber yang berbeda (MySQL vs Postgres,
 * ada join vs tidak), jadi pemanggil tetap menyusun query-nya sendiri dan
 * trait ini hanya mengerjakan bagian yang benar-benar sama.
 */
trait ServesDataTable
{
    /**
     * Ambang-ambang ditulis sebagai method, bukan konstanta trait:
     * konstanta di dalam trait baru ada sejak PHP 8.2, dan tidak ada
     * alasan membuat file ini gagal di-parse oleh PHP yang lebih lama.
     */
    private function dtDefaultPageLength(): int
    {
        return 25;
    }

    private function dtMaxPageLength(): int
    {
        return 200;
    }

    protected function dtPageLength(Request $request): int
    {
        $length = (int) $request->input('length', $this->dtDefaultPageLength());

        if ($length < 1) {
            return $this->dtDefaultPageLength();
        }

        return min($length, $this->dtMaxPageLength());
    }

    protected function dtPage(Request $request): int
    {
        $start = max(0, (int) $request->input('start', 0));

        return (int) floor($start / $this->dtPageLength($request)) + 1;
    }

    protected function dtDirection(Request $request, string $default = 'asc'): string
    {
        $dir = strtolower((string) data_get($request->input('order'), '0.dir', $default));

        return $dir === 'desc' ? 'desc' : 'asc';
    }

    /**
     * Kolom sort dari whitelist. Index di luar daftar jatuh ke $fallback,
     * jadi nilai order dari pengguna tidak pernah masuk SQL apa adanya.
     *
     * @param  array<int, string>  $orderable
     */
    protected function dtOrderColumn(Request $request, array $orderable, string $fallback, int $defaultIndex = 0): string
    {
        $index = (int) data_get($request->input('order'), '0.column', $defaultIndex);

        return $orderable[$index] ?? $fallback;
    }

    /**
     * Search bebas ke sejumlah kolom.
     *
     * @param  array<int, string>  $columns
     * @param  string  $operator  'like' (MySQL) atau 'ilike' (Postgres)
     */
    protected function dtApplySearch(Builder $query, string $search, array $columns, string $operator = 'like'): void
    {
        $search = trim($search);

        if ($search === '' || $columns === []) {
            return;
        }

        // Escape wildcard supaya "%" / "_" dari pengguna jadi teks biasa.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);

        $query->where(function (Builder $inner) use ($escaped, $columns, $operator): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, $operator, '%' . $escaped . '%');
            }
        });
    }

    /**
     * Filter kesamaan persis untuk sejumlah kolom.
     *
     * @param  array<string, string>  $map  nama parameter => kolom SQL
     */
    protected function dtApplyEqualsFilters(Builder $query, Request $request, array $map): void
    {
        foreach ($map as $parameter => $column) {
            $value = trim((string) $request->input($parameter, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }
    }

    /** Rentang tanggal inklusif pada satu kolom. */
    protected function dtApplyDateRange(Builder $query, Request $request, string $column): void
    {
        $from = trim((string) $request->input('tanggal_dari', ''));
        $to = trim((string) $request->input('tanggal_sampai', ''));

        if ($from !== '') {
            $query->whereDate($column, '>=', $from);
        }

        if ($to !== '') {
            $query->whereDate($column, '<=', $to);
        }
    }

    /** Nilai unik sebuah kolom untuk mengisi dropdown filter. */
    protected function dtDistinctValues(Builder $query, string $column): array
    {
        return $query
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(static fn ($value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Unduhan hasil filter. Format 'csv' ditulis mengalir (memori datar,
     * sanggup tabel besar); 'xlsx' dibatasi dtExportRowLimit().
     *
     * @param  array<int, string>  $headers
     * @param  callable(object): array<int, mixed>  $rowMapper
     */
    protected function dtExport(
        Request $request,
        Builder $query,
        array $headers,
        callable $rowMapper,
        string $filenameBase
    ): StreamedResponse {
        $format = strtolower(trim((string) $request->input('format', 'xlsx'))) === 'csv' ? 'csv' : 'xlsx';
        $filename = $filenameBase . '-' . now()->format('Ymd-His') . '.' . $format;

        return $format === 'csv'
            ? $this->dtStreamCsv($query, $headers, $rowMapper, $filename)
            : $this->dtStreamXlsx($query, $headers, $rowMapper, $filename);
    }

    protected function dtExportRowLimit(): int
    {
        return 30000;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  callable(object): array<int, mixed>  $rowMapper
     */
    private function dtStreamCsv(Builder $query, array $headers, callable $rowMapper, string $filename): StreamedResponse
    {
        return response()->stream(
            function () use ($query, $headers, $rowMapper): void {
                set_time_limit(0);

                $out = fopen('php://output', 'wb');

                // BOM UTF-8: tanpa ini Excel di Windows merusak karakter non-ASCII.
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $headers, ';');

                // cursor(), bukan chunk(): chunk() memakai LIMIT/OFFSET sehingga
                // ORDER BY diulang di tiap potongan.
                $written = 0;
                foreach ($query->cursor() as $row) {
                    fputcsv($out, $rowMapper($row), ';');

                    if ((++$written % 5000) === 0) {
                        flush();
                    }
                }

                fclose($out);
            },
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store, no-cache',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }

    /**
     * @param  array<int, string>  $headers
     * @param  callable(object): array<int, mixed>  $rowMapper
     */
    private function dtStreamXlsx(Builder $query, array $headers, callable $rowMapper, string $filename): StreamedResponse
    {
        return response()->stream(
            function () use ($query, $headers, $rowMapper): void {
                set_time_limit(0);
                $this->dtRaiseMemoryLimitTo(768);

                $spreadsheet = $this->dtNewSpreadsheet($headers);
                $sheet = $spreadsheet->getActiveSheet();

                $rowNumber = 2;
                $buffer = [];

                foreach ($query->cursor() as $row) {
                    $buffer[] = $rowMapper($row);

                    if (count($buffer) === 2000) {
                        $sheet->fromArray($buffer, null, 'A' . $rowNumber);
                        $rowNumber += count($buffer);
                        $buffer = [];
                    }
                }

                if ($buffer !== []) {
                    $sheet->fromArray($buffer, null, 'A' . $rowNumber);
                }

                $writer = new Xlsx($spreadsheet);
                $writer->setPreCalculateFormulas(false);
                $writer->save('php://output');

                $spreadsheet->disconnectWorksheets();
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store, no-cache',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }

    /**
     * Sheet dengan header bergaya, TANPA auto-size kolom: auto-size memaksa
     * PhpSpreadsheet mengukur lebar tiap sel dan pada ekspor ribuan baris
     * biayanya sekitar 3x lipat.
     *
     * @param  array<int, string>  $headers
     */
    private function dtNewSpreadsheet(array $headers): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth(20);
        }

        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    /** Naikkan memory_limit bila saat ini lebih rendah; tidak pernah menurunkan. */
    private function dtRaiseMemoryLimitTo(int $megabytes): void
    {
        $current = trim((string) ini_get('memory_limit'));

        if ($current === '-1') {
            return;
        }

        $unit = strtolower(substr($current, -1));
        $value = (int) $current;
        $currentMb = match ($unit) {
            'g' => $value * 1024,
            'm' => $value,
            'k' => intdiv($value, 1024),
            default => intdiv($value, 1048576),
        };

        if ($currentMb < $megabytes) {
            ini_set('memory_limit', $megabytes . 'M');
        }
    }
}
