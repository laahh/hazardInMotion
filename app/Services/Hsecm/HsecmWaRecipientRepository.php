<?php

declare(strict_types=1);

namespace App\Services\Hsecm;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Penerima WA/Email HSECM: config bawaan + custom (storage JSON, tanpa migrate).
 */
class HsecmWaRecipientRepository
{
    private const STORAGE_RELATIVE = 'hsecm/wa_recipients.json';

    public function path(): string
    {
        return storage_path('app/'.self::STORAGE_RELATIVE);
    }

    /**
     * Semua penerima (config + custom), urutan stabil untuk index kirim.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $fromConfig = collect(config('hsecm.wa_recipients', []))
            ->values()
            ->map(function (array $row, int $i): array {
                return $this->normalizeRecipient($row, [
                    'id' => 'config-'.$i,
                    'source' => 'config',
                    'editable' => false,
                ]);
            })
            ->all();

        $fromCustom = collect($this->customRaw())
            ->values()
            ->map(fn (array $row): array => $this->normalizeRecipient($row, [
                'source' => 'custom',
                'editable' => true,
            ]))
            ->all();

        return array_values(array_merge($fromConfig, $fromCustom));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customRaw(): array
    {
        $path = $this->path();
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array{nama: string, email: string, site?: ?string, perusahaan?: string, role?: string, no?: string}  $payload
     * @return array<string, mixed>
     */
    public function add(array $payload): array
    {
        $rows = $this->customRaw();
        $recipient = $this->normalizeRecipient($payload, [
            'id' => (string) Str::uuid(),
            'source' => 'custom',
            'editable' => true,
            'created_at' => now()->toIso8601String(),
        ]);

        $rows[] = $recipient;
        $this->writeCustom($rows);

        return $recipient;
    }

    /**
     * @param  array{nama?: string, email?: string, site?: ?string, perusahaan?: string, role?: string, no?: string}  $payload
     * @return array<string, mixed>|null
     */
    public function update(string $id, array $payload): ?array
    {
        $rows = $this->customRaw();
        $found = null;

        foreach ($rows as $i => $row) {
            if ((string) ($row['id'] ?? '') !== $id) {
                continue;
            }

            $merged = array_merge($row, $payload, [
                'id' => $id,
                'source' => 'custom',
                'editable' => true,
                'updated_at' => now()->toIso8601String(),
            ]);
            $rows[$i] = $this->normalizeRecipient($merged, [
                'id' => $id,
                'source' => 'custom',
                'editable' => true,
            ]);
            $found = $rows[$i];
            break;
        }

        if ($found === null) {
            return null;
        }

        $this->writeCustom($rows);

        return $found;
    }

    public function delete(string $id): bool
    {
        $rows = $this->customRaw();
        $filtered = array_values(array_filter(
            $rows,
            static fn (array $row): bool => (string) ($row['id'] ?? '') !== $id
        ));

        if (count($filtered) === count($rows)) {
            return false;
        }

        $this->writeCustom($filtered);

        return true;
    }

    public function findById(string $id): ?array
    {
        foreach ($this->all() as $row) {
            if ((string) ($row['id'] ?? '') === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeCustom(array $rows): void
    {
        $path = $this->path();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $payload = array_map(static function (array $row): array {
            return [
                'id' => (string) ($row['id'] ?? ''),
                'site' => $row['site'] ?? null,
                'perusahaan' => (string) ($row['perusahaan'] ?? ''),
                'role' => (string) ($row['role'] ?? ''),
                'nama' => (string) ($row['nama'] ?? ''),
                'no' => (string) ($row['no'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $rows);

        File::put(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function normalizeRecipient(array $row, array $meta = []): array
    {
        $site = $row['site'] ?? null;
        if (is_string($site)) {
            $site = trim($site);
            $site = $site === '' ? null : $site;
        } else {
            $site = null;
        }

        return [
            'id' => (string) ($meta['id'] ?? $row['id'] ?? Str::uuid()),
            'source' => (string) ($meta['source'] ?? $row['source'] ?? 'custom'),
            'editable' => (bool) ($meta['editable'] ?? $row['editable'] ?? true),
            'site' => $site,
            'perusahaan' => trim((string) ($row['perusahaan'] ?? '')),
            'role' => trim((string) ($row['role'] ?? '')),
            'nama' => trim((string) ($row['nama'] ?? '')),
            'no' => trim((string) ($row['no'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'created_at' => $row['created_at'] ?? ($meta['created_at'] ?? null),
            'updated_at' => $row['updated_at'] ?? ($meta['updated_at'] ?? null),
        ];
    }
}
