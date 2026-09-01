<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Isc\IscDetectHazardEntryAction;
use Illuminate\Console\Command;

final class IscDetectHazardEntriesCommand extends Command
{
    protected $signature = 'isc:detect-hazard-entries {--demo : Gunakan snapshot dummy}';

    protected $description = 'Deteksi personel In+Unsafe vs event terbuka ISC dan kirim notifikasi';

    public function handle(IscDetectHazardEntryAction $action): int
    {
        $result = $action->execute((bool) $this->option('demo'));
        if ($result['skipped']) {
            $this->warn($result['message'] ?? 'Deteksi dilewati.');

            return self::SUCCESS;
        }
        $this->info(sprintf('ISC detect: %d event baru, %d event ditutup.', $result['created'], $result['closed']));

        return self::SUCCESS;
    }
}
