<?php

namespace App\Services\PetaRisiko\Drm;

use App\Services\PetaRisiko\PetaRisikoRepository;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Snapshot Peta Risiko PJA versi DRM 2.1 (dari ClickHouse OBDS) di storage/app/peta-risiko-drm/:
 * - meta.json     : cakupan Site / Group Lokasi / PJA (KPI, matriks 5x5, tren mingguan) + daftar PJA
 * - pja/{id}.json : detail satu PJA (lokasi, aktivitas × detail lokasi + strip, bahaya, temuan, insiden, personil)
 * Penulisan atomik diwarisi dari PetaRisikoRepository.
 */
class DrmRepository extends PetaRisikoRepository
{
    protected const DIR = 'peta-risiko-drm';

    /**
     * Sync biasanya dijalankan user shell/cron (mis. `ubuntu`), sedangkan web memakai user lain
     * (mis. `www-data`). Laravel membuat folder baru berizin 0700, sehingga web tidak bisa
     * membacanya dan halaman menampilkan "Snapshot belum dibuat". Setelah simpan, buka izin baca.
     */
    public function simpan(array $snapshot): void
    {
        parent::simpan($snapshot);
        $this->bukaIzinBaca(Storage::disk('local')->path(static::DIR));
    }

    /** Path absolut file Excel mapping (ditulis setelah simpan, karena simpan mengganti folder). */
    public function pathEkspor(): string
    {
        return Storage::disk('local')->path(static::DIR.'/'.DrmMappingEkspor::NAMA_FILE);
    }

    public function adaEkspor(): bool
    {
        return is_file($this->pathEkspor());
    }

    private function bukaIzinBaca(string $root): void
    {
        if (! is_dir($root)) {
            return;
        }
        @chmod($root, 0755);
        $isi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($isi as $f) {
            @chmod($f->getPathname(), $f->isDir() ? 0755 : 0644);
        }
    }
}
