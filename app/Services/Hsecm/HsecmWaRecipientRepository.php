<?php

declare(strict_types=1);

namespace App\Services\Hsecm;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Penerima WA/Email HSECM: config bawaan + custom (storage JSON, tanpa migrate).
 * Override config disimpan di JSON terpisah agar email/nama bisa diedit tanpa ubah config.
 * Hapus kontak bawaan = soft-hide (JSON), tanpa ubah config/hsecm.php.
 */
class HsecmWaRecipientRepository
{
    private const STORAGE_RELATIVE = 'hsecm/wa_recipients.json';

    private const OVERRIDES_RELATIVE = 'hsecm/wa_recipient_overrides.json';

    private const HIDDEN_RELATIVE = 'hsecm/wa_recipient_hidden.json';

    public function path(): string
    {
        return storage_path('app/'.self::STORAGE_RELATIVE);
    }

    public function overridesPath(): string
    {
        return storage_path('app/'.self::OVERRIDES_RELATIVE);
    }

    public function hiddenPath(): string
    {
        return storage_path('app/'.self::HIDDEN_RELATIVE);
    }

    /**
     * Semua penerima (config + custom), urutan stabil untuk index kirim.
     * Kontak config yang di-hide tidak ikut.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $overrides = $this->overridesRaw();
        $hidden = $this->hiddenIds();

        $fromConfig = collect(config('hsecm.wa_recipients', []))
            ->values()
            ->map(function (array $row, int $i) use ($overrides): array {
                $id = 'config-'.$i;
                $merged = array_merge($row, $overrides[$id] ?? []);

                return $this->normalizeRecipient($merged, [
                    'id' => $id,
                    'source' => 'config',
                    'editable' => true,
                    'deletable' => true,
                ]);
            })
            ->reject(static fn (array $row): bool => in_array((string) $row['id'], $hidden, true))
            ->values()
            ->all();

        $fromCustom = collect($this->customRaw())
            ->values()
            ->map(fn (array $row): array => $this->normalizeRecipient($row, [
                'source' => 'custom',
                'editable' => true,
                'deletable' => true,
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
     * @return array<string, array<string, mixed>>
     */
    public function overridesRaw(): array
    {
        $path = $this->overridesPath();
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return [];
        }

        $map = [];
        foreach ($decoded as $id => $row) {
            if (! is_string($id) || ! is_array($row)) {
                continue;
            }
            $map[$id] = $row;
        }

        return $map;
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
            'deletable' => true,
            'created_at' => now()->toIso8601String(),
        ]);

        $rows[] = $recipient;
        $this->writeCustom($rows);

        return $recipient;
    }

    /**
     * Update custom recipient atau override kontak bawaan config.
     *
     * @param  array{nama: string, email: string, site?: ?string, perusahaan?: string, role?: string, no?: string}  $payload
     * @return array<string, mixed>|null
     */
    public function update(string $id, array $payload): ?array
    {
        if (str_starts_with($id, 'config-')) {
            return $this->updateConfigOverride($id, $payload);
        }

        return $this->updateCustom($id, $payload);
    }

    /**
     * @param  array{nama: string, email: string, site?: ?string, perusahaan?: string, role?: string, no?: string}  $payload
     * @return array<string, mixed>|null
     */
    private function updateCustom(string $id, array $payload): ?array
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
                'deletable' => true,
                'updated_at' => now()->toIso8601String(),
            ]);
            $rows[$i] = $this->normalizeRecipient($merged, [
                'id' => $id,
                'source' => 'custom',
                'editable' => true,
                'deletable' => true,
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

    /**
     * @param  array{nama: string, email: string, site?: ?string, perusahaan?: string, role?: string, no?: string}  $payload
     * @return array<string, mixed>|null
     */
    private function updateConfigOverride(string $id, array $payload): ?array
    {
        $index = (int) Str::after($id, 'config-');
        $configRows = array_values(config('hsecm.wa_recipients', []));
        if (! isset($configRows[$index]) || ! is_array($configRows[$index])) {
            return null;
        }

        $overrides = $this->overridesRaw();
        $overrides[$id] = [
            'nama' => $payload['nama'],
            'email' => $payload['email'],
            'site' => $payload['site'] ?? null,
            'perusahaan' => $payload['perusahaan'] ?? '',
            'role' => $payload['role'] ?? '',
            'no' => $payload['no'] ?? '',
            'updated_at' => now()->toIso8601String(),
        ];
        $this->writeOverrides($overrides);

        $merged = array_merge($configRows[$index], $overrides[$id]);

        return $this->normalizeRecipient($merged, [
            'id' => $id,
            'source' => 'config',
            'editable' => true,
            'deletable' => true,
        ]);
    }

    /**
     * Hapus custom (hard) atau hide kontak bawaan config (soft, tanpa ubah config).
     */
    public function delete(string $id): bool
    {
        if (str_starts_with($id, 'config-')) {
            return $this->hideConfigRecipient($id);
        }

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

    /**
     * @return list<string>
     */
    public function hiddenIds(): array
    {
        $path = $this->hiddenPath();
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(static fn ($id): bool => is_string($id) && $id !== '')
            ->map(static fn (string $id): string => $id)
            ->unique()
            ->values()
            ->all();
    }

    private function hideConfigRecipient(string $id): bool
    {
        $index = (int) Str::after($id, 'config-');
        $configRows = array_values(config('hsecm.wa_recipients', []));
        if (! isset($configRows[$index]) || ! is_array($configRows[$index])) {
            return false;
        }

        $hidden = $this->hiddenIds();
        if (in_array($id, $hidden, true)) {
            return true;
        }

        $hidden[] = $id;
        $this->writeHidden($hidden);

        // bersihkan override yang tidak relevan lagi
        $overrides = $this->overridesRaw();
        if (isset($overrides[$id])) {
            unset($overrides[$id]);
            $this->writeOverrides($overrides);
        }

        return true;
    }

    /**
     * @param  list<string>  $ids
     */
    private function writeHidden(array $ids): void
    {
        $path = $this->hiddenPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $payload = array_values(array_unique(array_filter(
            $ids,
            static fn ($id): bool => is_string($id) && $id !== ''
        )));

        File::put(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );
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
     * @param  array<string, array<string, mixed>>  $overrides
     */
    private function writeOverrides(array $overrides): void
    {
        $path = $this->overridesPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put(
            $path,
            json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
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

        $source = (string) ($meta['source'] ?? $row['source'] ?? 'custom');

        return [
            'id' => (string) ($meta['id'] ?? $row['id'] ?? Str::uuid()),
            'source' => $source,
            'editable' => (bool) ($meta['editable'] ?? $row['editable'] ?? true),
            'deletable' => (bool) ($meta['deletable'] ?? $row['deletable'] ?? true),
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
