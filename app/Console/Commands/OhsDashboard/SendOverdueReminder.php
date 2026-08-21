<?php

declare(strict_types=1);

namespace App\Console\Commands\OhsDashboard;

use App\Services\OhsDashboard\OverdueReminderService;
use Illuminate\Console\Command;
use Throwable;

final class SendOverdueReminder extends Command
{
    protected $signature = 'ohs-dashboard:overdue-reminder';

    protected $description = 'Kirim reminder due date tracker OHS Portal (H-3 s/d H-0, 08:00 WIB).';

    public function handle(OverdueReminderService $service): int
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
