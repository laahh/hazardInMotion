<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hazard/Inspeksi: /report/photoCar/{id} adalah halaman HTML; foto ada di /beats2/file/{id}.
 * OAK/Observasi: /beats2/file/document/{id} adalah file gambar (PNG/JPEG), dipakai langsung.
 */
final class ControlRoomSapPhotoResolver
{
    public const HOST = 'https://hseautomation.beraucoal.co.id';

    public const KIND_PHOTOCAR = 'photocar';

    public const KIND_DOCUMENT = 'document';

    private const CACHE_SECONDS = 3600;

    /**
     * @return array{foto_temuan: ?string, foto_penyelesaian: ?string}
     */
    public function resolve(int $id, string $kind = self::KIND_PHOTOCAR): array
    {
        return $kind === self::KIND_DOCUMENT
            ? $this->fromDocumentId($id)
            : $this->fromPhotoCarId($id);
    }

    /**
     * @return array{foto_temuan: ?string, foto_penyelesaian: ?string}
     */
    public function fromPhotoCarId(int $id): array
    {
        if ($id <= 0) {
            return ['foto_temuan' => null, 'foto_penyelesaian' => null];
        }

        /** @var array{foto_temuan: ?string, foto_penyelesaian: ?string} */
        return Cache::remember('control-room:sap-photos:v1:'.$id, self::CACHE_SECONDS, function () use ($id): array {
            return $this->fetchPage(self::HOST.'/report/photoCar/'.$id, $id, 'photoCar');
        });
    }

    /**
     * @return array{foto_temuan: ?string, foto_penyelesaian: ?string}
     */
    public function fromDocumentId(int $id): array
    {
        if ($id <= 0) {
            return ['foto_temuan' => null, 'foto_penyelesaian' => null];
        }

        return [
            'foto_temuan' => self::HOST.'/beats2/file/document/'.$id,
            'foto_penyelesaian' => null,
        ];
    }

    /**
     * @return array{foto_temuan: ?string, foto_penyelesaian: ?string}
     */
    public function parseHtml(string $html): array
    {
        $parts = preg_split('/Foto\s+Penyelesaian/i', $html, 2) ?: [$html];
        $temuanHtml = $parts[0] ?? '';
        $selesaiHtml = $parts[1] ?? '';

        $temuan = $this->firstFileUrl($temuanHtml);
        $selesai = $selesaiHtml !== '' ? $this->firstFileUrl($selesaiHtml) : null;

        if ($temuan === null && $selesai === null) {
            $all = $this->allFileUrls($html);
            $temuan = $all[0] ?? null;
            $selesai = $all[1] ?? null;
        }

        return [
            'foto_temuan' => $temuan,
            'foto_penyelesaian' => $selesai,
        ];
    }

    /**
     * @return array{foto_temuan: ?string, foto_penyelesaian: ?string}
     */
    private function fetchPage(string $url, int $id, string $source): array
    {
        try {
            $response = Http::timeout(10)->get($url);
            if (! $response->successful()) {
                return ['foto_temuan' => null, 'foto_penyelesaian' => null];
            }

            return $this->parseHtml($response->body());
        } catch (Throwable $e) {
            Log::warning('ControlRoom SAP '.$source.' gagal: '.$e->getMessage(), ['id' => $id]);

            return ['foto_temuan' => null, 'foto_penyelesaian' => null];
        }
    }

    private function firstFileUrl(string $html): ?string
    {
        $urls = $this->allFileUrls($html);

        return $urls[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function allFileUrls(string $html): array
    {
        $raw = [];
        if (preg_match_all('#(?:href|src|data-src)=["\']([^"\']*beats2/file/\d+)#i', $html, $matches) > 0) {
            $raw = $matches[1];
        } elseif (preg_match_all('#https?://[^"\'\s]*beats2/file/\d+#i', $html, $loose) > 0) {
            $raw = $loose[0];
        }
        $urls = [];
        foreach ($raw as $item) {
            $absolute = $this->absolute((string) $item);
            if ($absolute !== null) {
                $urls[$absolute] = $absolute;
            }
        }

        return array_values($urls);
    }

    private function absolute(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5);
        if ($url === '') {
            return null;
        }
        if (! str_starts_with($url, 'http')) {
            $url = self::HOST.'/'.ltrim($url, '/');
        }
        if (preg_match('#^https?://hseautomation\.beraucoal\.co\.id/beats2/file/\d+$#i', $url) !== 1) {
            return null;
        }

        return $url;
    }
}
