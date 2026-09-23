<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryToolMaster;
use Illuminate\Support\Facades\DB;

final class PncMonitoringInventoryToolMasterUpsertService
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsert(array $rows): PncMonitoringExcelUpsertResult
    {
        $created = 0;
        $updated = 0;
        $warnings = [];

        DB::transaction(function () use ($rows, &$created, &$updated, &$warnings): void {
            foreach ($rows as $row) {
                $existing = PncMonitoringInventoryToolMaster::query()
                    ->where('category_id', $row['category_id'])
                    ->whereRaw('LOWER(standard_name) = ?', [mb_strtolower($row['standard_name'])])
                    ->first();

                if ($existing === null) {
                    PncMonitoringInventoryToolMaster::query()->create($row);
                    $created++;
                    continue;
                }

                $existing->fill($row);
                $existing->save();
                $updated++;
            }
        });

        return new PncMonitoringExcelUpsertResult($created, $updated, [], $warnings);
    }
}
