<?php

declare(strict_types=1);

namespace App\Console\Commands\Dms;

use App\Services\Dms\Roster\DmsRosterJamKerjaLebihCache;
use Illuminate\Console\Command;
use Throwable;

/**
 * Hitung ulang jumlah hari jam kerja roster > ambang per SID
 * (DmsRosterJamKerjaLebihCache) — dasar kartu "Total Karyawan" baru di
 * Ringkasan Roster. Terjadwal tiap menit; putaran tanpa perubahan data
 * Master Roster hanya membaca versi (murah).
 *
 *   php artisan dms:master-roster-jam-kerja-ringkas           # hanya bila versi berubah
 *   php artisan dms:master-roster-jam-kerja-ringkas --paksa   # selalu hitung ulang
 */
final class MasterRosterJamKerjaRingkasCommand extends Command
{
    protected $signature = 'dms:master-roster-jam-kerja-ringkas {--paksa : Hitung ulang walau data tidak berubah}';

    protected $description = 'Ringkasan hari jam kerja roster > ambang per SID untuk kartu Total Karyawan (latar)';

    public function handle(): int
    {
        try {
            $hasil = DmsRosterJamKerjaLebihCache::segarkan((bool) $this->option('paksa'));
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
