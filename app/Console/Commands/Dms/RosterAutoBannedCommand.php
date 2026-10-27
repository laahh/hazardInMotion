<?php

declare(strict_types=1);

namespace App\Console\Commands\Dms;

use App\Services\Dms\Roster\DmsRosterAutoBannedService;
use App\Services\Dms\Roster\DmsRosterRegLiveService;
use Illuminate\Console\Command;

/**
 * Masukkan "Pelanggaran periode kerja > 70 hari" (dan, dengan --beruntun,
 * "Bekerja > 13 hari berturut-turut") ke Master Roster Banned
 * (sid_roster_banned_master) secara manual.
 *
 *   php artisan dms:roster-auto-banned                       # pratinjau saja (bawaan)
 *   php artisan dms:roster-auto-banned --uji                 # sama: pratinjau, tidak menulis
 *   php artisan dms:roster-auto-banned --beruntun            # pratinjau + kerja > 13 hari
 *   php artisan dms:roster-auto-banned --beruntun --jalankan # benar-benar menyimpan keduanya
 *
 * Versi otomatisnya berjalan di Tahap 6 dms:sync-roster-rfid bila
 * DMS_AUTO_BANNED=true (dan DMS_AUTO_BANNED_BERUNTUN=true untuk > 13 hari).
 */
final class RosterAutoBannedCommand extends Command
{
    protected $signature = 'dms:roster-auto-banned
        {--uji : Pratinjau saja, tidak menulis ke database (bawaan)}
        {--jalankan : Simpan ke sid_roster_banned_master}
        {--beruntun : Ikutkan pelanggaran kerja > 13 hari berturut-turut}';

    protected $description = 'Masukkan pelanggaran periode kerja > 70 hari / kerja > 13 hari berturut-turut (RFID) ke Master Roster Banned';

    public function handle(DmsRosterRegLiveService $live, DmsRosterAutoBannedService $banned): int
    {
        $this->info('Menghitung pelanggaran periode kerja dari RFID ...');
        $hasil = $live->segarkan();
        if ($hasil === null) {
            $this->error('Gagal membaca RFID: '.($live->galatTerakhir() ?? 'tidak diketahui'));

            return self::FAILURE;
        }

        $beruntun = $this->option('beruntun') ? $hasil['daftar']['beruntun'] : [];
        ['calon' => $calon, 'dilewati' => $dilewati] = $banned->calon($hasil['daftar']['onsite'], $beruntun);

        $this->line(count($hasil['daftar']['onsite']).' karyawan periode kerja > 70 hari'
            .($this->option('beruntun') ? ', '.count($beruntun).' kerja > 13 hari berturut-turut' : '').'; '
            .count($calon).' akan dimasukkan, '.count($dilewati).' dilewati.');

        if ($calon !== []) {
            $this->table(
                ['SID', 'NIK', 'Nama', 'PT', 'Site', 'Aturan', 'Nilai', 'Tgl pelanggaran'],
                array_map(static fn (array $c): array => [
                    $c['sid'], $c['nik'], $c['nama'], $c['perusahaan'], $c['site_dedicated'],
                    $c['_aturan'] === 'beruntun' ? '> 13 hari beruntun' : '> 70 hari', $c['_periode'], $c['tanggal_pelanggaran'],
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
