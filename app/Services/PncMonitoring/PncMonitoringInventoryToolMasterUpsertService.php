<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryToolMaster;
use Illuminate\Support\Facades\DB;

final class PncMonitoringInventoryToolMasterUpsertService
{
    public function upsert(PncMonitoringInventoryToolMasterBulkParseResult $parsed): PncMonitoringInventoryToolMasterBulkUpsertResult
    {
        $created = 0;
        $updated = 0;
        $detailCounts = [];
        $warnings = [];

        DB::transaction(function () use ($parsed, &$created, &$updated, &$detailCounts, &$warnings): void {
            foreach ($parsed->coreRows as $row) {
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

            if ($parsed->detailRows === []) {
                return;
            }

            $nameToId = PncMonitoringInventoryToolMaster::query()
                ->pluck('tool_master_id', 'standard_name')
                ->mapWithKeys(fn ($id, $name) => [mb_strtolower((string) $name) => (int) $id]);

            foreach ($parsed->detailRows as $section => $rows) {
                $config = PncMonitoringInventoryToolMasterExcelParser::DETAIL_SECTIONS[$section] ?? null;
                if ($config === null || $rows === []) {
                    continue;
                }

                [$written, $sectionWarnings] = $this->replaceSection($config, $rows, $nameToId);
                $detailCounts[$section] = $written;
                $warnings = [...$warnings, ...$sectionWarnings];
            }
        });

        return new PncMonitoringInventoryToolMasterBulkUpsertResult($created, $updated, $detailCounts, $warnings);
    }

    /**
     * @param  array{sheetTitle:string, fields:list<string>, headers:list<string>, model:class-string, hasSequence:bool}  $config
     * @param  list<array<string, mixed>>  $rows
     * @param  \Illuminate\Support\Collection<string, int>  $nameToId
     * @return array{0: int, 1: list<string>}
     */
    private function replaceSection(array $config, array $rows, $nameToId): array
    {
        $warnings = [];
        $byToolMaster = [];

        foreach ($rows as $row) {
            $name = mb_strtolower((string) $row['standard_name']);
            $toolMasterId = $nameToId->get($name);
            if ($toolMasterId === null) {
                $warnings[] = "{$config['sheetTitle']}: Nama Alat '{$row['standard_name']}' tidak ditemukan di KatalogAlat maupun database, baris dilewati.";
                continue;
            }

            $values = $row;
            unset($values['standard_name']);
            if ($config['model'] === \App\Models\PncMonitoring\PncMonitoringInventoryToolUsageRule::class) {
                $ruleType = mb_strtolower((string) ($values['rule_type'] ?? ''));
                $values['rule_type'] = in_array($ruleType, ['dont', "don't"], true) ? 'dont' : 'do';
            }
            foreach ($config['fields'] as $field) {
                if (($values[$field] ?? '') === '' && $field !== $config['fields'][0]) {
                    $values[$field] = null;
                }
            }

            $byToolMaster[$toolMasterId][] = $values;
        }

        $modelClass = $config['model'];
        $written = 0;
        foreach ($byToolMaster as $toolMasterId => $entries) {
            $inserts = [];
            foreach ($entries as $index => $values) {
                $entry = ['tool_master_id' => $toolMasterId, ...$values];
                if ($config['hasSequence']) {
                    $entry['sequence'] = $index + 1;
                }
                $inserts[] = $entry;
            }
            $modelClass::query()->where('tool_master_id', $toolMasterId)->delete();
            $modelClass::query()->insert($inserts);
            $written += count($inserts);
        }

        return [$written, $warnings];
    }
}
