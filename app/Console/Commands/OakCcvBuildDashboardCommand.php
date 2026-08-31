<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Rebuild resources/data/oak_ccv_dashboard.json from the source OAK Excel-export JSON.
 */
final class OakCcvBuildDashboardCommand extends Command
{
    protected $signature = 'oak-ccv:build-dashboard';

    protected $description = 'Rebuild OAK CCV dashboard aggregate JSON (OBSERVASI AREA KRITIS only)';

    public function handle(): int
    {
        $script = base_path('scripts/oak_ccv_build_dashboard.py');
        if (! is_file($script)) {
            $this->error('Script tidak ditemukan: '.$script);

            return self::FAILURE;
        }

        $python = $this->resolvePython();
        if ($python === null) {
            $this->error('Python tidak ditemukan di PATH.');

            return self::FAILURE;
        }

        $cmd = escapeshellarg($python).' '.escapeshellarg($script);
        $this->info('Menjalankan: '.$cmd);
        passthru($cmd, $code);

        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resolvePython(): ?string
    {
        foreach (['python', 'py', 'python3'] as $bin) {
            $out = [];
            $code = 0;
            exec(escapeshellarg($bin).' --version 2>&1', $out, $code);
            if ($code === 0) {
                return $bin;
            }
        }

        return null;
    }
}
