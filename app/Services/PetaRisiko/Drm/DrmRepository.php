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
        $this->tulisGzipMeta();
        $this->tulisRincian($snapshot['rincian'] ?? null);
        $this->bukaIzinBaca(Storage::disk('local')->path(static::DIR));
    }

    /** Path rincian.json: baris per status untuk modal kartu KPI (dimuat saat modal dibuka). */
    public function pathRincian(): string
    {
        return Storage::disk('local')->path(static::DIR.'/rincian.json');
    }

    /** @param array<string, mixed>|null $rincian */
    private function tulisRincian(?array $rincian): void
    {
        $path = $this->pathRincian();
        if ($rincian === null) {
            @unlink($path);
            @unlink($path.'.gz');

            return;
        }
        $isi = json_encode($rincian, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($path.'.tmp', $isi);
        rename($path.'.tmp', $path);
        @file_put_contents($path.'.gz', gzencode($isi, 6));
    }

    /** Path meta.json.gz (dikirim ke browser yang menerima gzip; Apache server ini tanpa mod_deflate). */
    public function pathMetaGz(): string
    {
        return $this->pathMeta().'.gz';
    }

    private function tulisGzipMeta(): void
    {
        $isi = @file_get_contents($this->pathMeta());
        if ($isi !== false) {
            @file_put_contents($this->pathMetaGz(), gzencode($isi, 6));
        }
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
