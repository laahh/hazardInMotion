<?php

declare(strict_types=1);

namespace App\Console\Commands\PraOperasi;

use App\Services\PraOperasi\PraOperasiOperatorRosterReader;
use Illuminate\Console\Command;

/**
 * Pre-warm cache roster operator (bcsid.m_karyawan x m_jabatan) supaya scan
 * berat (tabel m_karyawan 6GB tanpa index kode_sid/id_jabatan) tidak pernah
 * terjadi secara sinkron di dalam siklus request HTTP dashboard Pra Operasi
 * (penyebab 504 Gateway Timeout sebelumnya).
 *
 * Jadwalkan tiap beberapa jam (lihat app/Console/Kernel.php) — jabatan
 * karyawan tidak berubah menit-ke-menit, jadi cache lama (6 jam) aman.
 */
final class SyncOperatorRosterCommand extends Command
{
    protected $signature = 'pra-operasi:sync-operator-roster';

    protected $description = 'Pre-warm cache roster operator Pra Operasi dari hse_automation (bcsid.m_karyawan x m_jabatan)';

    public function handle(PraOperasiOperatorRosterReader $reader): int
    {
        if (! $reader->isUp()) {
            $this->warn('Koneksi hse_automation (pgsql_ssh/pgsql_direct) tidak tersedia — cache roster tidak diperbarui.');

            return self::FAILURE;
        }

        $this->info('Mengambil roster operator dari bcsid.m_karyawan x m_jabatan ...');
        $started = microtime(true);
        $roster = $reader->refresh();
        $elapsed = round(microtime(true) - $started, 2);

        $this->info("Selesai: {$this->pluralCount(count($roster))} dalam {$elapsed} detik.");

        return self::SUCCESS;
    }

    private function pluralCount(int $count): string
    {
        return number_format($count).' operator';
    }
}
