<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Hsecm\HsecmShiftEmailDispatchService;
use Illuminate\Console\Command;
use Throwable;

class HsecmEscalateOpenTasklistsCommand extends Command
{
    protected $signature = 'hsecm:escalate-open-tasklists
                            {--dry-run : Simulasi tanpa kirim / update escalate}
                            {--email= : Batasi hanya ke alamat email ini}';

    protected $description = 'Escalate email untuk tasklist yang belum closed (H+1 08:00, lalu tiap 6 jam)';

    public function handle(HsecmShiftEmailDispatchService $dispatchService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyEmail = $this->optionString('email');
        $this->info('HSECM escalate tasklists'.($dryRun ? ' [DRY-RUN]' : '').($onlyEmail ? " [email={$onlyEmail}]" : ''));

        try {
            $result = $dispatchService->dispatchEscalate(
                dryRun: $dryRun,
                onlyEmail: $onlyEmail,
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($result['message']);
        foreach ($result['details'] as $detail) {
            $mark = ($detail['success'] ?? false) ? 'OK' : 'FAIL';
            $this->line("  [{$mark}] ".($detail['nama'] ?? $detail['scope'] ?? '-').' <'.($detail['email'] ?? '-').'> — '.($detail['message'] ?? ''));
        }

        return ($result['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function optionString(string $key): ?string
    {
        $value = trim((string) ($this->option($key) ?? ''));

        return $value !== '' ? $value : null;
    }
}
