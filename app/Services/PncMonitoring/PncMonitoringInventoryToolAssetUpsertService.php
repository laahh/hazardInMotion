<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryCompany;
use App\Models\PncMonitoring\PncMonitoringInventoryToolAsset;
use Illuminate\Support\Facades\DB;

final class PncMonitoringInventoryToolAssetUpsertService
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
                $companyName = $row['owner_company_name'] ?? null;
                unset($row['owner_company_name']);
                if ($companyName !== null) {
                    $company = PncMonitoringInventoryCompany::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($companyName)])->first();
                    if ($company === null) {
                        $company = PncMonitoringInventoryCompany::query()->create(['name' => $companyName]);
                    }
                    $row['owner_company_id'] = $company->company_id;
                }

                $existing = null;
                if (! empty($row['inventory_id'])) {
                    $existing = PncMonitoringInventoryToolAsset::query()->where('inventory_id', $row['inventory_id'])->first();
                }

                if ($existing === null) {
                    PncMonitoringInventoryToolAsset::query()->create($row);
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
