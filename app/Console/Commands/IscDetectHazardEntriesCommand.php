<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Isc\IscDetectHazardEntryAction;
use Illuminate\Console\Command;

final class IscDetectHazardEntriesCommand extends Command
{
    protected $signature = 'isc:detect-hazard-entries {--demo : Gunakan snapshot dummy}';

    protected $description = 'Salin pelanggaran aktif Besigma ke task lokal ISC (read-only Besigma)';

    public function handle(IscDetectHazardEntryAction $action): int
    {
        $result = $action->execute((bool) $this->option('demo'));
        if ($result['skipped']) {
            $this->warn($result['message'] ?? 'Deteksi dilewati.');

            return self::SUCCESS;
        }
        $this->info(sprintf(
            'ISC detect: %d event baru, %d diperbarui, %d ditutup.',
            $result['created'],
            $result['updated'] ?? 0,
            $result['closed'],
        ));

        return self::SUCCESS;
    }
}
