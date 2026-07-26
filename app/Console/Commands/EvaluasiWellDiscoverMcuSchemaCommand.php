<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SportEvaluation\McuSchemaDiscoveryService;
use Illuminate\Console\Command;

/**
 * Discovery read-only skema BeMCU untuk mengisi mapping config/bemcu.php.
 */
final class EvaluasiWellDiscoverMcuSchemaCommand extends Command
{
    protected $signature = 'evaluasi-well:discover-mcu';

    protected $description = 'Discover BeMCU Postgres schema (read-only) for metabolic column mapping';

    public function handle(McuSchemaDiscoveryService $discovery): int
    {
        $result = $discovery->discover();

        $this->line($result['message']);

        if (! $result['up']) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Suggested mapping:');
        $this->line(json_encode($result['suggestions'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info('Candidate tables (top):');
        foreach (array_slice($result['candidate_tables'], 0, 20) as $table) {
            $this->line(' - '.$table);
        }

        return self::SUCCESS;
    }
}
