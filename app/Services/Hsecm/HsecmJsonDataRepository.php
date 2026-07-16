<?php

declare(strict_types=1);

namespace App\Services\Hsecm;

use Illuminate\Support\Facades\Cache;

/**
 * Sumber data HSECM dari resources/views/BaseRule/database.json.
 */
class HsecmJsonDataRepository
{
    private const CACHE_KEY = 'hsecm.json_database.v1';

    private const CACHE_TTL_SECONDS = 300;

    public function path(): string
    {
        return resource_path('views/BaseRule/database.json');
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $path = $this->path();
            if (! is_file($path)) {
                return [];
            }

            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                return [];
            }

            $result = [];
            foreach ($decoded as $sheet => $rows) {
                if (! is_array($rows)) {
                    continue;
                }
                $normalized = [];
                foreach ($rows as $index => $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $row['_row_id'] = $index + 1;
                    $normalized[] = $row;
                }
                $result[(string) $sheet] = $normalized;
            }

            return $result;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sheet(string $sheetName): array
    {
        return $this->all()[$sheetName] ?? [];
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('hsecm.filter_options.json.v1');
    }
}
