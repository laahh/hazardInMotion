<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

/**
 * Hasil baca Excel sebelum upsert.
 */
final class PncMonitoringExcelParseResult
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $errors,
        public readonly array $warnings = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
