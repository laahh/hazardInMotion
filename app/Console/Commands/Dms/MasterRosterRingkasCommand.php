<?php

declare(strict_types=1);

namespace App\Console\Commands\Dms;

use App\Services\Dms\Roster\DmsRosterMasterCache;
use Illuminate\Console\Command;
use Throwable;

/**
 * Hitung ulang ringkasan dms_roster_master_jadwal untuk halaman Master Roster
 * (DmsRosterMasterCache) bila datanya berubah. Terjadwal tiap menit; putaran
 * tanpa perubahan hanya membaca versi (murah).
 *
 *   php artisan dms:master-roster-ringkas           # hanya bila versi berubah
 *   php artisan dms:master-roster-ringkas --paksa   # selalu hitung ulang
 */
final class MasterRosterRingkasCommand extends Command
{
    protected $signature = 'dms:master-roster-ringkas {--paksa : Hitung ulang walau data tidak berubah}';

    protected $description = 'Ringkasan jadwal Master Roster untuk Overview/Approval/Edit (latar)';

    public function handle(): int
    {
        try {
            $hasil = DmsRosterMasterCache::segarkan((bool) $this->option('paksa'));
        } catch (Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line($hasil['dihitung']
            ? "Ringkasan dihitung ulang ({$hasil['detik']} dtk)."
            : 'Data tidak berubah — ringkasan tetap.');

        return self::SUCCESS;
    }
}
