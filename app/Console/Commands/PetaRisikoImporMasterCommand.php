<?php

namespace App\Console\Commands;

use App\Services\PetaRisiko\Drm\DrmMaster;
use Illuminate\Console\Command;
use Throwable;

/**
 * Impor workbook master mapping DRM 2.1 (M01–M16) sebagai baseline /peta-risiko-pja.
 * Setelah impor, jalankan `php artisan peta-risiko:sync` agar dashboard memakai master baru.
 */
class PetaRisikoImporMasterCommand extends Command
{
    protected $signature = 'peta-risiko:impor-master
        {file? : Path workbook DRM21_Mapping_Master_Overall.xlsx (bawaan: salinan terakhir yang sudah diimpor)}';

    protected $description = 'Impor master mapping DRM 2.1 (baris HIRA, grup lokasi, detail lokasi, label SAP, teks OBS/OAK) untuk Peta Risiko PJA';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');
        // Bawaan: workbook yang ikut repo, lalu salinan terakhir yang sudah diimpor.
        $repo = base_path('database/data/peta-risiko/DRM21_Mapping_Master_Overall.xlsx');
        $file = (string) ($this->argument('file') ?: (is_file($repo) ? $repo : DrmMaster::pathXlsx()));

        try {
            $master = DrmMaster::dariXlsx($file);
            $master->simpan($file);
        } catch (Throwable $e) {
            $this->error('Gagal impor: '.$e->getMessage());

            return self::FAILURE;
        }

        $d = $master->data;
        $cfg = (array) config('peta_risiko_drm');
        $this->info('Master DRM 2.1 diimpor dari '.$d['sumber']);
        $this->table(['Bagian', 'Jumlah'], [
            ['M01 baris HIRA', count($d['baris'])],
            ['M04 grup lokasi', count($d['grup'])],
            ['M05 pasangan grup → baris', array_sum(array_map('count', $d['grupBaris']))],
            ['M06 detail lokasi', count($d['dl'])],
            ['M09 label SAP (unik)', count($d['label'])],
            ['M11 teks OBS', count($d['obs'])],
            ['M12 teks OAK', count($d['oak'])],
            ['M16 pasangan tema → baris (dihitung ulang)', array_sum(array_map('count', $master->temaBaris($cfg['tema_checklist'])))],
        ]);

        // Kelompok aktivitas dihitung ulang dari regex M02; laporkan selisihnya dengan kolom G M01.
        $nama = array_map(fn ($k) => $k[0], $cfg['proses_bisnis']);
        $kel = $master->kelompokBaris($cfg['proses_bisnis']);
        $beda = array_filter($d['baris'], fn ($b) => $nama[$kel[$b['ri']]] !== $b['kelompokM01']);
        $this->line(sprintf('Kelompok aktivitas regex M02 vs kolom G M01: %d dari %d baris berbeda.', count($beda), count($d['baris'])));
        foreach (array_slice($beda, 0, 5) as $b) {
            $this->line(sprintf('  ri %d "%s / %s": M01 = %s, regex = %s', $b['ri'], $b['aktivitas'], $b['sub'], $b['kelompokM01'], $nama[$kel[$b['ri']]]));
        }

        $lit = $master->literal($cfg);
        $this->line(sprintf('Ukuran literal query: %s KB (batas ClickHouse 256 KB per query, termasuk SQL lainnya).', number_format(array_sum(array_map('strlen', $lit)) / 1024, 1, ',', '.')));
        $this->info('Lanjutkan dengan: php artisan peta-risiko:sync');

        return self::SUCCESS;
    }
}
