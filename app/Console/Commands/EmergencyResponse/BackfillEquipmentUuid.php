<?php

declare(strict_types=1);

namespace App\Console\Commands\EmergencyResponse;

use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Support\EmergencyResponse\EquipmentUuidGenerator;
use Illuminate\Console\Command;

/**
 * Mengisi ulang UUID peralatan (kolom `code`) untuk data lama yang kodenya
 * masih diinput manual, memakai format SITE-Kategori-Nama-Urutan.
 *
 * Catatan: label QR yang sudah tercetak dengan kode lama perlu dicetak ulang.
 */
class BackfillEquipmentUuid extends Command
{
    protected $signature = 'emergency-response:backfill-equipment-uuid
                            {--all : Ikut menata ulang peralatan yang UUID-nya sudah digenerate}
                            {--dry-run : Tampilkan perubahan tanpa menyimpan}';

    protected $description = 'Generate ulang UUID peralatan emergency dari site-kategori-nama-urutan';

    public function handle(EquipmentUuidGenerator $generator): int
    {
        $query = EmergencyEquipment::query()->orderBy('created_at');

        if (! $this->option('all')) {
            $query->whereNull('sequence_number');
        }

        $equipment = $query->get();

        if ($equipment->isEmpty()) {
            $this->info('Tidak ada peralatan yang perlu di-backfill.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];

        foreach ($equipment as $item) {
            $before = $item->code;

            [$sequence, $uuid] = $generator->generate($item);

            $rows[] = [$before, $uuid];

            if ($dryRun) {
                continue;
            }

            $item->sequence_number = $sequence;
            $item->code = $uuid;
            $item->saveQuietly();
        }

        $this->table(['UUID lama', 'UUID baru'], $rows);
        $this->info(($dryRun ? 'Dry run: ' : 'Selesai: ').count($rows).' peralatan.');

        return self::SUCCESS;
    }
}
