<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Dms\Roster\DmsRosterOtomatisService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Buat/segarkan jadwal otomatis Master Roster (Senin–Jumat D1–D5, Sabtu/Minggu
 * OFF) untuk perusahaan di config dms_roster.master.otomatis, bulan berjalan
 * + bulan ke depan. Butuh cache karyawan aktif dari Tahap 8 dms:sync-roster-rfid.
 */
class DmsMasterRosterOtomatisCommand extends Command
{
    protected $signature = 'dms:master-roster-otomatis
        {--bulan= : Satu bulan saja (YYYY-MM); kosong = bulan ini + bulan ke depan dari config}
        {--pt= : Satu perusahaan saja (nama persis); kosong = semua di config}';

    protected $description = 'Generate jadwal otomatis Master Roster untuk karyawan yang belum punya roster';

    public function handle(DmsRosterOtomatisService $otomatis): int
    {
        try {
            $bulanList = $this->bulan();
        } catch (Throwable) {
            $this->error('Format --bulan harus YYYY-MM.');

            return self::FAILURE;
        }

        $ptList = $this->option('pt') ? [trim((string) $this->option('pt'))] : DmsRosterOtomatisService::perusahaan();
        if ($ptList === []) {
            $this->warn('Tidak ada perusahaan di config dms_roster.master.otomatis.perusahaan.');

            return self::SUCCESS;
        }

        $gagal = false;
        foreach ($ptList as $pt) {
            foreach ($bulanList as $bulan) {
                try {
                    $h = $otomatis->generate($pt, $bulan);
                } catch (Throwable $e) {
                    report($e);
                    $this->error("{$pt} {$bulan->format('Y-m')}: gagal — ".$e->getMessage());
                    $gagal = true;

                    continue;
                }

                if ($h['status'] === 'tanpa_cache') {
                    $this->warn("{$pt} {$h['bulan']}: daftar karyawan aktif (Tahap 8 dms:sync-roster-rfid) belum ada, dilewati.");
                    $gagal = true;

                    continue;
                }
                $this->info(sprintf(
                    '%s %s: %s karyawan, %s baris jadwal%s',
                    $pt,
                    $h['bulan'],
                    number_format($h['karyawan'], 0, ',', '.'),
                    number_format($h['baris'], 0, ',', '.'),
                    $h['upload_id'] ? " (upload #{$h['upload_id']})" : ' (semua sudah punya roster, upload otomatis dihapus)',
                ));
            }
        }

        return $gagal ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<CarbonImmutable> */
    private function bulan(): array
    {
        $tz = (string) config('app.timezone');
        if ($this->option('bulan')) {
            return [CarbonImmutable::createFromFormat('!Y-m', (string) $this->option('bulan'), $tz)->startOfMonth()];
        }

        $mulai = CarbonImmutable::now($tz)->startOfMonth();
        $ke = max(0, (int) config('dms_roster.master.otomatis.bulan_ke_depan', 1));
        $out = [];
        for ($i = 0; $i <= $ke; $i++) {
            $out[] = $mulai->addMonthsNoOverflow($i);
        }

        return $out;
    }
}
