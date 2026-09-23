<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

final class PncMonitoringInventoryToolMasterBulkUpsertResult
{
    /**
     * @param  array<string, int>  $detailCounts  keyed by section, jumlah baris ditulis
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly int $coreCreated,
        public readonly int $coreUpdated,
        public readonly array $detailCounts,
        public readonly array $warnings,
    ) {}
}
