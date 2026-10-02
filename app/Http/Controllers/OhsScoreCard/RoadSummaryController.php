<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Parameter "Jalan sesuai standar" — tabel app_mixer.road_summary.
 *
 * Isinya hasil evaluasi per segmen jalan (grade, lebar, superelevasi).
 * Tabelnya besar (±140 ribu baris), jadi DataTable-nya server-side:
 * paging, sorting, search, dan filter semuanya dikerjakan di SQL.
 */
final class RoadSummaryController extends Controller
{
    private const TABLE = 'road_summary';

    private const DEFAULT_PAGE_LENGTH = 25;
    private const MAX_PAGE_LENGTH = 200;

    /**
     * Batas baris untuk format .xlsx. Diturunkan dari pengukuran nyata di
     * mesin ini (memory_limit 512 MB): 10 rb baris ~130 MB, 50 rb ~480 MB.
     * 30 rb memberi ruang aman; di atas itu arahkan pengguna ke CSV.
     */
    private const MAX_XLSX_ROWS = 30000;

    /** Opsi dropdown filter di-cache, query DISTINCT-nya mahal di tabel sebesar ini. */
    private const FILTER_CACHE_TTL = 600;

    /** Kolom yang boleh difilter persis (exact match) dari query string. */
    private const FILTERABLE = [
        'site', 'pit', 'mitra', 'year', 'week', 'grade_stat', 'road_width', 'supereleva',
    ];

    /**
     * Ekspresi SQL "segmen memenuhi standar" — satu sumber kebenaran yang dipakai
     * bertiga: nilai kolom Kesimpulan, filter Kesimpulan, dan agregat ringkasan.
     *
     * Aturannya:
     *  - grade_stat, road_width, supereleva WAJIB 'ACCEPT' (selalu dinilai).
     *  - junction_1 / junction_s hanya ikut dinilai bila segmen tersebut memang
     *    titik pertemuan. Nilai '-' (atau kosong/NULL) berarti tidak berlaku,
     *    jadi tidak menggugurkan. Kalau terisi, nilainya harus 'ACCEPT'.
     *
     * Nilai junction selain '-' dan 'ACCEPT' otomatis dianggap tidak lolos,
     * jadi aman walau nanti muncul status baru yang belum dikenal.
     */
    private const STANDARD_SQL = "("
        . "grade_stat = 'ACCEPT' AND road_width = 'ACCEPT' AND supereleva = 'ACCEPT'"
        . " AND (junction_1 IS NULL OR junction_1 IN ('-', '', 'ACCEPT'))"
        . " AND (junction_s IS NULL OR junction_s IN ('-', '', 'ACCEPT'))"
        . ")";

    /** Nilai yang diterima filter Kesimpulan. */
    private const CONCLUSION_STANDARD = 'standar';
    private const CONCLUSION_NOT_STANDARD = 'tidak-standar';

    /**
     * Tabel ini tidak punya kolom bulan/tanggal — hanya `year` + `week`
     * (kolom tanggal di road_datasets adalah waktu ingest, bukan periode
     * yang diukur, jadi tidak dipakai). Bulan karena itu diturunkan dari
     * nomor minggu memakai aturan ISO 8601: satu minggu dimiliki oleh bulan
     * tempat hari KAMIS-nya jatuh.
     *
     * Dipakai aturan Kamis, bukan Senin, karena minggu bisa membelah dua
     * bulan — mis. 2026 minggu 1 mulai Senin 29 Des 2025; dengan aturan
     * Kamis (1 Jan 2026) minggu itu benar masuk Januari 2026, bukan
     * Desember 2025. Pemetaan ini sudah dicocokkan dengan MySQL
     * MONTH(STR_TO_DATE(... '%x%v %W') + 3 hari) untuk seluruh data.
     */
    /**
     * Konversi persentase segmen standar menjadi Nilai 1–4.
     *
     * Bentuk: [ambang bawah, nilai, label]. Dibaca dari atas; band pertama
     * yang ambangnya <= persentase dipakai.
     *
     * CATATAN: ambang yang diberikan berhenti di "98% <= X < 100%", sehingga
     * X = 100% tepat tidak tercakup. Di sini 100% ikut Nilai 4 karena itu
     * capaian terbaik. Kalau ternyata 100% seharusnya punya nilai sendiri
     * (mis. Nilai 5), cukup tambahkan band baru di paling atas.
     */
    private const SCORE_BANDS = [
        [98.0, 4, '98% – 100%'],
        [90.0, 3, '90% – <98%'],
        [80.0, 2, '80% – <90%'],
        [0.0,  1, '<80%'],
    ];

    private const MONTH_LABELS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** Index kolom DataTable -> kolom SQL. Whitelist, supaya order tidak bisa diinjeksi. */
    private const ORDERABLE = [
        0 => 'site',
        1 => 'pit',
        2 => 'mitra',
        3 => 'year',
        4 => 'week',
        // Kolom Bulan diturunkan dari week, jadi urut minggu = urut bulan.
        5 => 'week',
        6 => 'nama_jalan',
        7 => 'segment',
        8 => 'grade_stat',
        9 => 'road_width',
        10 => 'supereleva',
        11 => 'junction_1',
        12 => 'junction_s',
        // 13 = kolom Kesimpulan, ditangani khusus karena hasil hitungan (lihat applyOrder()).
    ];

    /** Index kolom DataTable untuk kolom Kesimpulan. */
    private const CONCLUSION_COLUMN_INDEX = 13;

    /** Kolom yang ikut kena kotak search bebas. */
    private const SEARCHABLE = [
        'site', 'pit', 'mitra', 'nama_jalan', 'grade_stat', 'road_width', 'supereleva',
    ];

    public function index(): View
    {
        return view('ohs-score-card.jalan-sesuai-standar.index', [
            'filterOptions' => $this->filterOptions(),
            'monthOptions' => $this->monthOptions(),
            'totalSegments' => $this->totalCount(),
            'maxXlsxRows' => self::MAX_XLSX_ROWS,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);
        $search = (string) $request->input('search.value', '');

        $recordsTotal = $this->totalCount();

        $filtered = $this->buildFilteredQuery($request);

        // Tanpa filter & search, hasilnya pasti sama dengan seluruh tabel —
        // hindari dua full scan (count + agregat ringkasan) di tiap request.
        $isUnfiltered = $this->activeFilterCount($request) === 0 && trim($search) === '';

        if ($isUnfiltered) {
            $recordsFiltered = $recordsTotal;
            $summary = $this->totalSummary();
        } else {
            // count() meniadakan order by, jadi hitung dulu sebelum paging dipasang.
            $recordsFiltered = (clone $filtered)->count();
            $summary = $this->summarise(clone $filtered);
        }

        $filtered
            ->select([
                'site', 'pit', 'mitra', 'year', 'week', 'nama_jalan',
                'segment', 'grade_stat', 'road_width', 'supereleva',
                'junction_1', 'junction_s',
            ])
            ->selectRaw(self::STANDARD_SQL . ' AS is_standar');

        $this->applyOrder($filtered, $request);

        $rows = $filtered
            ->orderBy('id') // tie-breaker: paging stabil saat nilai kolom sort kembar
            ->forPage($this->page($request), $this->pageLength($request))
            ->get()
            ->map(function (object $row): object {
                // Cast eksplisit: MySQL mengembalikan 1/0, pastikan JSON-nya boolean.
                $row->is_standar = (bool) $row->is_standar;

                // Bulan dihitung di PHP, bukan SQL, supaya query tetap sargable.
                $month = $this->monthOfIsoWeek((int) $row->year, (int) $row->week);
                $row->bulan = self::MONTH_LABELS[$month] ?? '–';

                return $row;
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
            'summary' => $summary,
        ]);
    }

    /**
     * Unduh hasil filter saat ini.
     *
     * Dua format, karena keduanya punya batasan berbeda:
     *  - xlsx : rapi & langsung jadi file Excel, tapi PhpSpreadsheet menahan
     *           seluruh sheet di memori. Diukur di mesin ini: 10 rb baris
     *           ~13 dtk / 130 MB, 50 rb baris ~115 dtk / 480 MB — padahal
     *           memory_limit 512 MB. Karena itu dibatasi MAX_XLSX_ROWS.
     *  - csv  : ditulis mengalir per potongan, memori nyaris tetap, sanggup
     *           seluruh tabel. Dibuka langsung oleh Excel (pakai BOM UTF-8).
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $format = strtolower(trim((string) $request->input('format', 'xlsx')));
        $format = $format === 'csv' ? 'csv' : 'xlsx';

        $query = $this->buildFilteredQuery($request);
        $total = (clone $query)->count();

        if ($format === 'xlsx' && $total > self::MAX_XLSX_ROWS) {
            return response()->json([
                'message' => sprintf(
                    'Hasil filter %s baris, melebihi batas %s baris untuk format Excel (.xlsx). '
                    . 'Persempit filter — misalnya pilih satu bulan atau satu site — atau unduh sebagai CSV.',
                    number_format($total, 0, ',', '.'),
                    number_format(self::MAX_XLSX_ROWS, 0, ',', '.')
                ),
                'total' => $total,
                'max' => self::MAX_XLSX_ROWS,
            ], 422);
        }

        $query
            ->select([
                'site', 'pit', 'mitra', 'year', 'week', 'nama_jalan',
                'segment', 'grade_stat', 'road_width', 'supereleva',
                'junction_1', 'junction_s',
            ])
            ->selectRaw(self::STANDARD_SQL . ' AS is_standar')
            ->orderBy('site')
            ->orderBy('year')
            ->orderBy('week')
            ->orderBy('nama_jalan')
            ->orderBy('segment')
            ->orderBy('id');

        $filename = 'jalan-sesuai-standar-' . now()->format('Ymd-His') . '.' . $format;

        return $format === 'csv'
            ? $this->streamCsv($query, $filename)
            : $this->streamXlsx($query, $filename);
    }

    /** @return array<int, string> */
    private function exportHeaders(): array
    {
        return [
            'Site', 'Pit', 'Mitra', 'Tahun', 'Minggu', 'Bulan', 'Nama Jalan', 'Segmen',
            'Grade', 'Lebar Jalan', 'Superelevasi', 'Junction 1', 'Junction S', 'Kesimpulan',
        ];
    }

    /**
     * Satu baris database -> satu baris file.
     *
     * @return array<int, string|int>
     */
    private function exportRow(object $row): array
    {
        $month = $this->monthOfIsoWeek((int) $row->year, (int) $row->week);

        return [
            (string) $row->site,
            (string) $row->pit,
            (string) $row->mitra,
            (int) $row->year,
            (int) $row->week,
            self::MONTH_LABELS[$month] ?? '-',
            (string) $row->nama_jalan,
            (int) $row->segment,
            (string) $row->grade_stat,
            (string) $row->road_width,
            (string) $row->supereleva,
            (string) $row->junction_1,
            (string) $row->junction_s,
            $row->is_standar ? 'STANDAR' : 'TIDAK STANDAR',
        ];
    }

    /**
     * Sheet kosong berisi header bergaya.
     *
     * Sengaja TIDAK memakai SpreadsheetExporter::createSheetWithHeaders(),
     * karena helper itu menyalakan setAutoSize(true) untuk tiap kolom.
     * Auto-size memaksa PhpSpreadsheet mengukur lebar teks tiap sel, dan pada
     * ekspor sebesar ini biayanya sekitar 3x lipat (12 rb baris: 41 dtk dengan
     * auto-size vs ~13 dtk tanpa). Lebar kolom di sini dipatok manual.
     */
    /**
     * Naikkan memory_limit ke $megabytes bila saat ini lebih rendah.
     * Tidak pernah menurunkan, dan membiarkan konfigurasi tak terbatas (-1).
     */
    private function raiseMemoryLimitTo(int $megabytes): void
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

    private function newExportSpreadsheet(): Spreadsheet
    {
        $widths = [10, 14, 10, 8, 9, 12, 26, 9, 13, 13, 14, 12, 12, 16];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jalan Sesuai Standar');
        $sheet->fromArray($this->exportHeaders(), null, 'A1');

        $lastColumn = Coordinate::stringFromColumnIndex(count($this->exportHeaders()));

        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }

        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    private function streamCsv(Builder $query, string $filename): StreamedResponse
    {
        return response()->stream(
            function () use ($query): void {
                // Seluruh tabel (±140 rb baris) butuh lebih dari 30 dtk default,
                // walau memorinya datar karena ditulis per potongan.
                set_time_limit(0);

                $out = fopen('php://output', 'wb');

                // BOM UTF-8: tanpa ini Excel di Windows merusak karakter non-ASCII.
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $this->exportHeaders(), ';');

                // cursor(), BUKAN chunk(). chunk() memakai LIMIT/OFFSET sehingga
                // MySQL mengulang ORDER BY atas seluruh hasil di setiap potongan
                // — pada 140 rb baris itu puluhan kali filesort dan praktis
                // menggantung. cursor() menjalankan satu query lalu menarik baris
                // satu per satu.
                $written = 0;
                foreach ($query->cursor() as $row) {
                    fputcsv($out, $this->exportRow($row), ';');

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

    private function streamXlsx(Builder $query, string $filename): StreamedResponse
    {
        return response()->stream(
            function () use ($query): void {
                // Membangun .xlsx itu mahal: diukur di data ini, ~24 dtk/176 MB
                // untuk 12 rb baris dan ~58 dtk/258 MB untuk 20 rb baris
                // (satu bulan penuh). Header respons sudah terkirim duluan,
                // jadi yang perlu dilonggarkan tinggal batas waktu & memori
                // proses — bukan menurunkan batas baris sampai sebulan penuh
                // tidak bisa diunduh.
                set_time_limit(0);
                $this->raiseMemoryLimitTo(768);

                $spreadsheet = $this->newExportSpreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                // cursor() dengan alasan yang sama seperti di streamCsv():
                // chunk() akan memaksa MySQL mengulang ORDER BY tiap potongan.
                // Penulisan tetap dikumpulkan per 2000 baris, karena fromArray()
                // sekali-banyak jauh lebih murah daripada setCellValue() per sel.
                $rowNumber = 2;
                $buffer = [];

                foreach ($query->cursor() as $row) {
                    $buffer[] = $this->exportRow($row);

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

    private function baseQuery(): Builder
    {
        return DB::table(self::TABLE);
    }

    /** Jumlah seluruh baris; berubah hanya saat ada ingest baru, jadi aman di-cache. */
    private function totalCount(): int
    {
        return (int) Cache::remember(
            'ohs-score-card.road-summary.total',
            self::FILTER_CACHE_TTL,
            fn (): int => $this->baseQuery()->count()
        );
    }

    /**
     * Ringkasan untuk kondisi tanpa filter — sama untuk semua pengguna, jadi di-cache.
     *
     * @return array<string, int|float>
     */
    private function totalSummary(): array
    {
        return Cache::remember(
            'ohs-score-card.road-summary.summary',
            self::FILTER_CACHE_TTL,
            fn (): array => $this->summarise($this->baseQuery())
        );
    }

    /**
     * Berapa dropdown filter yang sedang terisi — termasuk Kesimpulan, yang
     * bukan kolom fisik. Kalau Kesimpulan tidak ikut dihitung, jalur cepat
     * "tanpa filter" akan keliru menyajikan total & ringkasan seluruh tabel.
     */
    private function activeFilterCount(Request $request): int
    {
        $count = 0;

        foreach (self::FILTERABLE as $column) {
            if (trim((string) $request->input($column, '')) !== '') {
                $count++;
            }
        }

        if (in_array(
            trim((string) $request->input('kesimpulan', '')),
            [self::CONCLUSION_STANDARD, self::CONCLUSION_NOT_STANDARD],
            true
        )) {
            $count++;
        }

        $month = (int) $request->input('month', 0);

        if ($month >= 1 && $month <= 12) {
            $count++;
        }

        return $count;
    }

    /**
     * Query dengan seluruh filter terpasang. Dipakai bersama oleh data() dan
     * export(), supaya isi file unduhan dijamin sama persis dengan yang
     * sedang tampil di tabel.
     */
    private function buildFilteredQuery(Request $request): Builder
    {
        $query = $this->applyFilters($this->baseQuery(), $request);
        $this->applyMonthFilter($query, $request);
        $this->applyConclusionFilter($query, $request);
        $this->applySearch($query, (string) $request->input('search.value', $request->input('search', '')));

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        foreach (self::FILTERABLE as $column) {
            $value = trim((string) $request->input($column, ''));

            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        return $query;
    }

    /**
     * Band Nilai untuk sebuah persentase.
     *
     * @return array{0: float, 1: int, 2: string} [ambang, nilai, label]
     */
    private function scoreBandFor(float $percent): array
    {
        foreach (self::SCORE_BANDS as $band) {
            if ($percent >= $band[0]) {
                return $band;
            }
        }

        // Tidak tercapai: band terakhir berambang 0. Disediakan agar aman
        // kalau daftar band diubah dan ambang terbawah tidak lagi 0.
        return [0.0, 1, '<80%'];
    }

    /**
     * Bulan pemilik sebuah minggu ISO: bulan tempat hari Kamis-nya jatuh.
     * Mengembalikan 0 bila nomor minggu tidak masuk akal.
     */
    private function monthOfIsoWeek(int $year, int $week): int
    {
        if ($week < 1 || $week > 53 || $year < 1970) {
            return 0;
        }

        // Hari ke-4 pada minggu ISO = Kamis.
        return (int) (new \DateTimeImmutable())->setISODate($year, $week, 4)->format('n');
    }

    /**
     * Pasangan (year, week) yang benar-benar ada di data. Dipakai untuk
     * menyusun opsi dropdown Bulan sekaligus menerjemahkan filter bulan
     * menjadi daftar minggu.
     *
     * @return array<int, array{year: int, week: int}>
     */
    private function yearWeekPairs(): array
    {
        return Cache::remember(
            'ohs-score-card.road-summary.year-weeks',
            self::FILTER_CACHE_TTL,
            fn (): array => $this->baseQuery()
                ->select('year', 'week')
                ->whereNotNull('year')
                ->whereNotNull('week')
                ->distinct()
                ->orderBy('year')
                ->orderBy('week')
                ->get()
                ->map(static fn (object $row): array => [
                    'year' => (int) $row->year,
                    'week' => (int) $row->week,
                ])
                ->all()
        );
    }

    /**
     * Bulan yang ada datanya, untuk mengisi dropdown.
     *
     * @return array<int, string> [nomor bulan => label]
     */
    private function monthOptions(): array
    {
        $months = [];

        foreach ($this->yearWeekPairs() as $pair) {
            $month = $this->monthOfIsoWeek($pair['year'], $pair['week']);

            if ($month > 0) {
                $months[$month] = self::MONTH_LABELS[$month];
            }
        }

        ksort($months);

        return $months;
    }

    /**
     * Filter bulan. Diterjemahkan jadi daftar minggu per tahun, bukan fungsi
     * tanggal di WHERE — supaya MySQL tetap bisa memakai index pada year/week
     * dan tidak memaksa full scan di 140 ribu baris.
     */
    private function applyMonthFilter(Builder $query, Request $request): void
    {
        $month = (int) $request->input('month', 0);

        if ($month < 1 || $month > 12) {
            return;
        }

        $weeksByYear = [];

        foreach ($this->yearWeekPairs() as $pair) {
            if ($this->monthOfIsoWeek($pair['year'], $pair['week']) === $month) {
                $weeksByYear[$pair['year']][] = $pair['week'];
            }
        }

        if ($weeksByYear === []) {
            $query->whereRaw('1 = 0'); // bulan dipilih tapi tak ada datanya

            return;
        }

        $query->where(function (Builder $outer) use ($weeksByYear): void {
            foreach ($weeksByYear as $year => $weeks) {
                $outer->orWhere(function (Builder $inner) use ($year, $weeks): void {
                    $inner->where('year', $year)->whereIn('week', $weeks);
                });
            }
        });
    }

    /**
     * Filter kolom Kesimpulan. Bukan kolom fisik, jadi dipakaikan ekspresi
     * STANDARD_SQL yang sama dengan yang menghasilkan nilainya.
     */
    private function applyConclusionFilter(Builder $query, Request $request): void
    {
        $value = trim((string) $request->input('kesimpulan', ''));

        if ($value === self::CONCLUSION_STANDARD) {
            $query->whereRaw(self::STANDARD_SQL . ' = 1');

            return;
        }

        if ($value === self::CONCLUSION_NOT_STANDARD) {
            $query->whereRaw(self::STANDARD_SQL . ' = 0');
        }
    }

    private function applyOrder(Builder $query, Request $request): void
    {
        $index = (int) data_get($request->input('order'), '0.column', 0);
        $direction = $this->orderDirection($request);

        if ($index === self::CONCLUSION_COLUMN_INDEX) {
            $query->orderByRaw(self::STANDARD_SQL . ' ' . $direction);

            return;
        }

        $query->orderBy(self::ORDERABLE[$index] ?? 'site', $direction);
    }

    private function applySearch(Builder $query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        // Escape wildcard LIKE supaya "%" / "_" dari user diperlakukan sebagai teks biasa.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);

        $query->where(function (Builder $inner) use ($escaped): void {
            foreach (self::SEARCHABLE as $column) {
                $inner->orWhere($column, 'like', '%' . $escaped . '%');
            }
        });
    }

    /**
     * Ringkasan kepatuhan atas hasil filter saat ini (bukan cuma halaman aktif).
     *
     * @return array<string, int|float>
     */
    private function summarise(Builder $query): array
    {
        $row = $query->selectRaw(
            'COUNT(*) AS total,'
            . " SUM(grade_stat = 'ACCEPT') AS grade_ok,"
            . " SUM(road_width = 'ACCEPT') AS width_ok,"
            . " SUM(supereleva = 'ACCEPT') AS super_ok,"
            . ' SUM(' . self::STANDARD_SQL . ') AS standar_ok'
        )->first();

        $total = (int) ($row->total ?? 0);
        $percent = static fn (int $ok): float => $total > 0 ? round($ok / $total * 100, 2) : 0.0;

        $gradeOk = (int) ($row->grade_ok ?? 0);
        $widthOk = (int) ($row->width_ok ?? 0);
        $superOk = (int) ($row->super_ok ?? 0);
        $standarOk = (int) ($row->standar_ok ?? 0);
        $standarPct = $percent($standarOk);

        // Tanpa baris sama sekali, persentasenya 0 — tapi itu "tidak ada data",
        // bukan capaian 0%. Jangan dilaporkan sebagai Nilai 1.
        if ($total === 0) {
            $nilai = 0;
            $nilaiBand = 'tidak ada data';
        } else {
            [, $nilai, $nilaiBand] = $this->scoreBandFor($standarPct);
        }

        return [
            'total' => $total,
            'grade_ok' => $gradeOk,
            'width_ok' => $widthOk,
            'super_ok' => $superOk,
            'standar_ok' => $standarOk,
            'grade_pct' => $percent($gradeOk),
            'width_pct' => $percent($widthOk),
            'super_pct' => $percent($superOk),
            'standar_pct' => $standarPct,
            'nilai' => $nilai,
            'nilai_band' => $nilaiBand,
        ];
    }

    /**
     * Nilai unik tiap kolom filter, untuk mengisi dropdown.
     *
     * @return array<string, array<int, string>>
     */
    private function filterOptions(): array
    {
        return Cache::remember('ohs-score-card.road-summary.filters', self::FILTER_CACHE_TTL, function (): array {
            $options = [];

            foreach (self::FILTERABLE as $column) {
                $options[$column] = $this->baseQuery()
                    ->select($column)
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->distinct()
                    ->orderBy($column)
                    ->pluck($column)
                    ->map(static fn ($value): string => (string) $value)
                    ->all();
            }

            return $options;
        });
    }

    private function orderDirection(Request $request): string
    {
        $dir = strtolower((string) data_get($request->input('order'), '0.dir', 'asc'));

        return $dir === 'desc' ? 'desc' : 'asc';
    }

    private function pageLength(Request $request): int
    {
        $length = (int) $request->input('length', self::DEFAULT_PAGE_LENGTH);

        if ($length < 1) {
            return self::DEFAULT_PAGE_LENGTH;
        }

        return min($length, self::MAX_PAGE_LENGTH);
    }

    private function page(Request $request): int
    {
        $start = max(0, (int) $request->input('start', 0));

        return (int) floor($start / $this->pageLength($request)) + 1;
    }
}
