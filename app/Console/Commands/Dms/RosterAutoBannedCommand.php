<?php

declare(strict_types=1);

namespace App\Console\Commands\Dms;

use App\Services\Dms\Roster\DmsRosterAutoBannedService;
use App\Services\Dms\Roster\DmsRosterRegLiveService;
use Illuminate\Console\Command;

/**
 * Masukkan "Pelanggaran periode kerja > 70 hari" ke Master Roster Banned
 * (sid_roster_banned_master) secara manual.
 *
 *   php artisan dms:roster-auto-banned            # pratinjau saja (bawaan)
 *   php artisan dms:roster-auto-banned --uji      # sama: pratinjau, tidak menulis
 *   php artisan dms:roster-auto-banned --jalankan # benar-benar menyimpan
 *
 * Versi otomatisnya berjalan di Tahap 6 dms:sync-roster-rfid bila
 * DMS_AUTO_BANNED=true.
 */
final class RosterAutoBannedCommand extends Command
{
    protected $signature = 'dms:roster-auto-banned
        {--uji : Pratinjau saja, tidak menulis ke database (bawaan)}
        {--jalankan : Simpan ke sid_roster_banned_master}';

    protected $description = 'Masukkan pelanggaran periode kerja > 70 hari (RFID) ke Master Roster Banned';

    public function handle(DmsRosterRegLiveService $live, DmsRosterAutoBannedService $banned): int
    {
        $this->info('Menghitung pelanggaran periode kerja dari RFID ...');
        $hasil = $live->segarkan();
        if ($hasil === null) {
            $this->error('Gagal membaca RFID: '.($live->galatTerakhir() ?? 'tidak diketahui'));

            return self::FAILURE;
        }

        ['calon' => $calon, 'dilewati' => $dilewati] = $banned->calon($hasil['daftar']['onsite']);

        $this->line(count($hasil['daftar']['onsite']).' karyawan periode kerja > 70 hari; '
            .count($calon).' akan dimasukkan, '.count($dilewati).' dilewati.');

        if ($calon !== []) {
            $this->table(
                ['SID', 'NIK', 'Nama', 'PT', 'Site', 'Periode kerja', 'Tgl pelanggaran'],
                array_map(static fn (array $c): array => [
                    $c['sid'], $c['nik'], $c['nama'], $c['perusahaan'], $c['site_dedicated'], $c['_periode'], $c['tanggal_pelanggaran'],
                ], $calon),
            );
        }
        foreach ($dilewati as $d) {
            $this->warn("  dilewati: {$d['sid']} {$d['nama']} — {$d['alasan']}");
        }

        if (! $this->option('jalankan')) {
            $this->comment('Pratinjau saja — belum ada yang disimpan. Jalankan dengan --jalankan untuk menyimpan.');

            return self::SUCCESS;
        }

        $ditambah = $banned->masukkan($calon);
        $this->info("{$ditambah} baris ditambahkan ke sid_roster_banned_master.");

        // Master berubah → kartu aturan dihitung ulang supaya orang yang baru
        // di-banned langsung hilang dari daftar "harus di-banned".
        if ($ditambah > 0) {
            $live->segarkan();
        }

        return self::SUCCESS;
    }
}
