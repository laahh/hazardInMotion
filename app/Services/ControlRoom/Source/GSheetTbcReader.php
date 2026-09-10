<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Source;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Membaca Google Sheet validasi TBC via CSV / GViz — tanpa service account.
 * Sheet harus "Anyone with the link → Viewer".
 *
 * Dashboard tidak boleh mengunduh seluruh sheet (bisa ratusan ribu baris).
 * matchingRows() hanya men-query tasklist yang sama dengan report_id
 * Hazard/Inspeksi orang jaga minggu terpilih.
 */
final class GSheetTbcReader
{
    private const FAIL_CACHE_SECONDS = 90;

    private const HEADER_CACHE_SECONDS = 21600;

    private const QUERY_CHUNK = 40;

    /**
     * @var list<string>
     */
    private const TASKLIST_HEADERS = [
        'tasklist',
        'task number',
        'task list',
        'no tasklist',
        'nomor tasklist',
        'id tasklist',
    ];

    private readonly string $sheetId;

    private readonly string $gid;

    public function __construct(?string $sheetId = null, ?string $gid = null)
    {
        $this->sheetId = $sheetId ?? (string) config('control-room.gsheet_tbc.sheet_id');
        $this->gid = $gid ?? (string) config('control-room.gsheet_tbc.gid', '0');
    }

    public function isConfigured(): bool
    {
        return trim($this->sheetId) !== '';
    }

    /**
     * @return Collection<int, array<string, string|null>>
     */
    public function fetch(): Collection
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'GSheetTbcReader belum dikonfigurasi — CONTROL_ROOM_GSHEET_TBC_ID kosong di .env.'
            );
        }

        $url = "https://docs.google.com/spreadsheets/d/{$this->sheetId}/export?format=csv&gid={$this->gid}";
        $response = Http::timeout($this->timeout())->get($url);
        $this->assertCsvResponse($response);

        return $this->parseCsv($response->body());
    }

    /**
     * Ambil baris GSheet yang Tasklist-nya ada di $tasklists.
     * Tidak throw — dashboard tetap jalan kalau sheet privat / timeout.
     *
     * @param  list<string>  $tasklists
     * @return array{loaded: bool, rows: list<array<string, string|null>>}
     */
    public function matchingRows(array $tasklists): array
    {
        $wanted = [];
        foreach ($tasklists as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $wanted[$id] = $id;
            }
        }
        if ($wanted === [] || ! $this->isConfigured()) {
            return ['loaded' => false, 'rows' => []];
        }

        $failKey = $this->failCacheKey();
        if (Cache::get($failKey)) {
            return ['loaded' => false, 'rows' => []];
        }

        $ids = array_values($wanted);
        sort($ids);
        $cacheKey = $this->matchCacheKey($ids);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && array_key_exists('rows', $cached)) {
            return ['loaded' => true, 'rows' => $cached['rows']];
        }

        try {
            $letter = $this->tasklistColumnLetter();
            $rows = [];
            foreach (array_chunk($ids, self::QUERY_CHUNK) as $chunk) {
                foreach ($this->queryTasklists($letter, $chunk) as $row) {
                    $tasklist = $this->rowTasklist($row);
                    if ($tasklist === '' || ! isset($wanted[$tasklist])) {
                        continue;
                    }
                    $rows[$tasklist] = $row;
                }
            }
        } catch (Throwable $e) {
            Cache::put($failKey, 1, self::FAIL_CACHE_SECONDS);
            Log::warning('ControlRoom GSheet TBC gagal: '.$e->getMessage());

            return ['loaded' => false, 'rows' => []];
        }

        $payload = ['rows' => array_values($rows)];
        Cache::put($cacheKey, $payload, $this->cacheSeconds());

        return ['loaded' => true, 'rows' => $payload['rows']];
    }

    /**
     * @return Collection<int, array<string, string|null>>
     */
    private function parseCsv(string $csv): Collection
    {
        [$header, $dataLines] = $this->csvHeaderAndLines($csv);
        if ($header === []) {
            return collect();
        }

        return collect($dataLines)
            ->map(function (string $line) use ($header): array {
                $values = str_getcsv($line);
                $values = array_pad($values, count($header), null);

                return array_combine($header, $values) ?: [];
            })
            ->values();
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function csvHeaderAndLines(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        if ($lines === [] || $lines === ['']) {
            return [[], []];
        }

        $header = str_getcsv((string) array_shift($lines));
        $header = array_map(static fn (mixed $col): string => trim((string) $col), $header);
        $dataLines = array_values(array_filter(
            $lines,
            static fn (string $line): bool => trim($line) !== '',
        ));

        return [$header, $dataLines];
    }

    private function tasklistColumnLetter(): string
    {
        $cacheKey = sprintf('control-room:gsheet-tbc:header:v1:%s:%s', $this->sheetId, $this->gid);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::timeout($this->timeout())->get($this->gvizUrl('select * limit 1'));
        $this->assertCsvResponse($response);
        [$header] = $this->csvHeaderAndLines($response->body());
        $index = $this->tasklistHeaderIndex($header);
        if ($index === null) {
            throw new RuntimeException(
                'GSheetTbcReader: kolom Tasklist tidak ditemukan di header sheet TBC.'
            );
        }

        $letter = $this->columnLetter($index);
        Cache::put($cacheKey, $letter, self::HEADER_CACHE_SECONDS);

        return $letter;
    }

    /**
     * @param  list<string>  $header
     */
    public function tasklistHeaderIndex(array $header): ?int
    {
        foreach ($header as $index => $name) {
            $normalized = $this->normalizeHeader((string) $name);
            if (in_array($normalized, self::TASKLIST_HEADERS, true)) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tasklists
     * @return list<array<string, string|null>>
     */
    private function queryTasklists(string $letter, array $tasklists): array
    {
        $escaped = [];
        foreach ($tasklists as $id) {
            $escaped[] = preg_quote((string) $id, '/');
        }
        $query = sprintf(
            "select %s where %s matches '^(%s)$'",
            $letter,
            $letter,
            implode('|', $escaped),
        );
        $response = Http::timeout($this->timeout())->get($this->gvizUrl($query));
        $this->assertCsvResponse($response);

        return $this->parseCsv($response->body())->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowTasklist(array $row): string
    {
        foreach ($row as $key => $value) {
            if ($this->normalizeHeader((string) $key) === 'tasklist'
                || in_array($this->normalizeHeader((string) $key), self::TASKLIST_HEADERS, true)
            ) {
                return trim((string) $value);
            }
        }

        return trim((string) ($row['tasklist'] ?? $row['Tasklist'] ?? $row['Task_Number'] ?? ''));
    }

    private function gvizUrl(string $query): string
    {
        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv&gid=%s&tq=%s',
            $this->sheetId,
            $this->gid,
            rawurlencode($query),
        );
    }

    private function assertCsvResponse(Response $response): void
    {
        if (! $response->successful()) {
            throw new RuntimeException("GSheetTbcReader: gagal mengambil sheet, HTTP {$response->status()}.");
        }

        $body = ltrim($response->body());
        if ($body === '') {
            return;
        }
        if (str_starts_with($body, '<') || str_contains($body, 'google.visualization.Query.setResponse')) {
            throw new RuntimeException(
                'GSheetTbcReader: respons bukan CSV — sheet mungkin sudah tidak publik, atau Sheet ID/gid salah.'
            );
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $looksCsv = str_contains($contentType, 'csv')
            || str_contains($contentType, 'octet-stream')
            || str_contains($contentType, 'text/plain')
            || $contentType === '';
        if (! $looksCsv) {
            throw new RuntimeException(
                "GSheetTbcReader: respons bukan CSV (Content-Type: {$contentType}) — sheet mungkin sudah tidak publik, atau Sheet ID/gid salah."
            );
        }
    }

    private function normalizeHeader(string $name): string
    {
        $name = strtolower(trim($name));
        $name = str_replace(['_', '-', '.'], ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $n = $index + 1;
        while ($n > 0) {
            $n--;
            $letter = chr(65 + ($n % 26)).$letter;
            $n = intdiv($n, 26);
        }

        return $letter;
    }

    /**
     * @param  list<string>  $ids
     */
    private function matchCacheKey(array $ids): string
    {
        return sprintf(
            'control-room:gsheet-tbc:match:v1:%s:%s:%s',
            $this->sheetId,
            $this->gid,
            sha1(implode(',', $ids)),
        );
    }

    private function failCacheKey(): string
    {
        return sprintf('control-room:gsheet-tbc:fail:v1:%s:%s', $this->sheetId, $this->gid);
    }

    private function timeout(): int
    {
        return max(5, (int) config('control-room.gsheet_tbc.timeout', 12));
    }

    private function cacheSeconds(): int
    {
        return max(0, (int) config('control-room.gsheet_tbc.cache_seconds', 900));
    }
}
