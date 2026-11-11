<?php

namespace App\Services\PetaRisiko;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Snapshot Peta Risiko PJA di storage/app/peta-risiko-pja/:
 * - meta.json         : master proses/tema/SBS + daftar PJA beserta ringkasannya
 * - pja/{id}.json     : baris tema per periode, temuan SAP open, bahaya HIRA terkait
 * Ditulis atomik (file sementara lalu rename) agar halaman tidak membaca file setengah jadi.
 */
class PetaRisikoRepository
{
    private const DIR = 'peta-risiko-pja';

    /** @var array<string, mixed>|null */
    private ?array $meta = null;

    public function ada(): bool
    {
        return $this->disk()->exists(self::DIR.'/meta.json');
    }

    /** @return array<string, mixed>|null */
    public function meta(): ?array
    {
        if ($this->meta === null && $this->ada()) {
            $this->meta = $this->baca(self::DIR.'/meta.json');
        }

        return $this->meta;
    }

    /** @return array<string, mixed>|null */
    public function pja(string $id): ?array
    {
        if (! preg_match('/^[a-z0-9-]{1,190}$/', $id)) {
            return null;
        }
        $path = self::DIR."/pja/{$id}.json";

        return $this->disk()->exists($path) ? $this->baca($path) : null;
    }

    /** @param array{meta: array<string, mixed>, pja: array<string, array<string, mixed>>} $snapshot */
    public function simpan(array $snapshot): void
    {
        $disk = $this->disk();
        $sementara = self::DIR.'/.baru-'.uniqid();

        foreach ($snapshot['pja'] as $id => $data) {
            $disk->put("{$sementara}/pja/{$id}.json", json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }
        $disk->put("{$sementara}/meta.json", json_encode($snapshot['meta'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $lama = self::DIR.'/.lama-'.uniqid();
        $root = $disk->path('');
        if ($disk->exists(self::DIR.'/meta.json')) {
            $disk->makeDirectory($lama);
            rename($root.self::DIR.'/meta.json', $root.$lama.'/meta.json');
            if ($disk->exists(self::DIR.'/pja')) {
                rename($root.self::DIR.'/pja', $root.$lama.'/pja');
            }
        }
        rename($root.$sementara.'/pja', $root.self::DIR.'/pja');
        rename($root.$sementara.'/meta.json', $root.self::DIR.'/meta.json');

        $disk->deleteDirectory($sementara);
        $disk->deleteDirectory($lama);
        $this->meta = null;
    }

    /** @return array<string, mixed> */
    private function baca(string $path): array
    {
        return json_decode((string) $this->disk()->get($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
