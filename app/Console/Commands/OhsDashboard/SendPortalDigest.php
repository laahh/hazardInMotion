<?php

declare(strict_types=1);

namespace App\Console\Commands\OhsDashboard;

use App\Services\OhsDashboard\EmailDigestService;
use Illuminate\Console\Command;
use Throwable;

final class SendPortalDigest extends Command
{
    protected $signature = 'ohs-dashboard:digest';

    protected $description = 'Kirim digest overview OHS Portal sesuai jadwal (window 75 menit).';

    public function handle(EmailDigestService $service): int
    {
        try {
            $result = $service->runScheduled();
            $this->info($result['message']);

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
