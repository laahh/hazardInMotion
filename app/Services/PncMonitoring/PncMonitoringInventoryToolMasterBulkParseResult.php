<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

final class PncMonitoringInventoryToolMasterBulkParseResult
{
    /**
     * @param  list<array<string, mixed>>  $coreRows
     * @param  array<string, list<array<string, mixed>>>  $detailRows  keyed by section (functions, checklist_items, ...)
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly array $coreRows,
        public readonly array $detailRows,
        public readonly array $errors,
        public readonly array $warnings,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
