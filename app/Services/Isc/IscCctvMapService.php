<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Models\CctvData;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Titik CCTV ringan untuk /isc/maps. Tanpa stream, kredensial, atau polygon coverage.
 */
final class IscCctvMapService
{
    public const CACHE_KEY = 'isc.maps.cctv.v1';

    public const CACHE_SECONDS = 180;

    public function __construct(
        private readonly IscSiteNormalizer $sites,
    ) {}

    /**
     * @return array{source:string,count:int,fallback?:bool,cameras:list<array<string, mixed>>}
     */
    public function payload(bool $demo = false): array
    {
        if ($demo) {
            return $this->demoPayload(false);
        }

        try {
            /** @var array{source:string,count:int,cameras:list<array<string, mixed>>} */
            return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->livePayload());
        } catch (Throwable $e) {
            report($e);

            return $this->demoPayload(true);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function mapRow(object $row): ?array
    {
        $lat = $this->toFloat($row->latitude ?? null);
        $lng = $this->toFloat($row->longitude ?? null);
        if ($lat === null || $lng === null || $lat == 0.0 || $lng == 0.0) {
            return null;
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        $id = (string) ($row->id ?? '');
        $no = trim((string) ($row->no_cctv ?? ''));
        $name = trim((string) ($row->nama_cctv ?? ''));
        $kondisi = $this->nullableString($row->kondisi ?? $row->status ?? null);
        $link = $this->safeLink(isset($row->link_akses) ? (string) $row->link_akses : null);

        return [
            'id' => $id,
            'no_cctv' => $no !== '' ? $no : null,
            'name' => $name !== '' ? $name : ($no !== '' ? $no : 'CCTV '.$id),
            'lat' => $lat,
            'lng' => $lng,
            'site' => $this->nullableString($row->site ?? null),
            'site_code' => $this->sites->codeFrom($row->site ?? null, $row->lokasi_pemasangan ?? null),
            'company' => $this->nullableString($row->perusahaan ?? null),
            'location' => $this->nullableString($row->lokasi_pemasangan ?? null),
            'kondisi' => $kondisi,
            'ok' => $this->isOk($kondisi, $row->status ?? null, $row->connected ?? null),
            'has_link' => $link !== null,
            'link' => $link,
        ];
    }

    /**
     * @return array{source:string,count:int,cameras:list<array<string, mixed>>}
     */
    private function livePayload(): array
    {
        $rows = CctvData::query()
            ->select([
                'id',
                'no_cctv',
                'nama_cctv',
                'site',
                'perusahaan',
                'kondisi',
                'status',
                'connected',
                'lokasi_pemasangan',
                'latitude',
                'longitude',
                'link_akses',
            ])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $cameras = [];
        foreach ($rows as $row) {
            $point = $this->mapRow($row);
            if ($point === null) {
                continue;
            }
            $cameras[] = $point;
        }

        return [
            'source' => 'live',
            'count' => count($cameras),
            'cameras' => $cameras,
        ];
    }

    /**
     * @return array{source:string,count:int,fallback:bool,cameras:list<array<string, mixed>>}
     */
    private function demoPayload(bool $fallback): array
    {
        $cameras = [
            $this->demoCamera('d1', 'CCTV-BMO-01', 'Pit Office BMO', 'BMO', 'PT Berau Coal', 2.175, 117.48, true),
            $this->demoCamera('d2', 'CCTV-LMO-04', 'Gate Lati', 'LMO', 'PT Berau Coal', 2.24, 117.58, true),
            $this->demoCamera('d3', 'CCTV-GMO-02', 'Workshop Gurimbang', 'GMO', 'PT PAMA', 1.98, 117.52, false),
            $this->demoCamera('d4', 'CCTV-SMO-07', 'ROM Sambarata', 'SMO', 'PT BUMA', 2.05, 117.28, true),
            $this->demoCamera('d5', 'CCTV-PUN-01', 'Haul road Punan', 'PUNAN', 'PT Berau Coal', 1.88, 117.35, false),
        ];

        return [
            'source' => 'demo',
            'count' => count($cameras),
            'fallback' => $fallback,
            'cameras' => $cameras,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function demoCamera(
        string $id,
        string $no,
        string $name,
        string $site,
        string $company,
        float $lat,
        float $lng,
        bool $ok,
    ): array {
        return [
            'id' => $id,
            'no_cctv' => $no,
            'name' => $name,
            'lat' => $lat,
            'lng' => $lng,
            'site' => $site,
            'site_code' => $site,
            'company' => $company,
            'location' => $name,
            'kondisi' => $ok ? 'Baik' : 'Rusak',
            'ok' => $ok,
            'has_link' => false,
            'link' => null,
        ];
    }

    private function isOk(mixed $kondisi, mixed $status, mixed $connected): bool
    {
        $hay = mb_strtolower(trim(implode(' ', array_map(
            static fn (mixed $v): string => (string) $v,
            [$kondisi, $status, $connected]
        ))), 'UTF-8');

        if ($hay === '') {
            return false;
        }
        if (str_contains($hay, 'rusak') || str_contains($hay, 'breakdown') || str_contains($hay, 'offline')) {
            return false;
        }

        return str_contains($hay, 'baik')
            || str_contains($hay, 'live')
            || $hay === 'yes'
            || str_contains($hay, 'connected');
    }

    private function safeLink(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $url) !== 1) {
            return null;
        }

        return $url;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
