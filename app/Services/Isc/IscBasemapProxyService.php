<?php

declare(strict_types=1);

namespace App\Services\Isc;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class IscBasemapProxyService
{
    public const GEOSERVER_HOST = '10.10.10.61';

    public const GEOSERVER_PORT = 8080;

    public const WMS_LAYER = 'basemap:basemap_allsite';

    public const WMTS_LAYERS = [
        'geonode:basemap_allsite',
        'basemap:basemap_allsite',
    ];

    private const MODE_CACHE_KEY = 'isc.basemap.mode';

    /**
     * @var list<string>
     */
    private const WMS_KEYS = [
        'SERVICE', 'REQUEST', 'VERSION', 'LAYERS', 'STYLES', 'FORMAT',
        'TRANSPARENT', 'WIDTH', 'HEIGHT', 'SRS', 'CRS', 'BBOX', 'TILED',
        'BGCOLOR', 'EXCEPTIONS',
    ];

    public function wms(Request $request): Response
    {
        $query = $this->wmsQuery($request);
        if ($query['BBOX'] === '' || $query['WIDTH'] < 1 || $query['HEIGHT'] < 1) {
            return $this->emptyTile();
        }

        $png = $this->fetchPng($this->geoserverUrl('/geoserver/basemap/wms'), $query)
            ?? $this->fetchPng($this->geoserverUrl('/geoserver/geonode/wms'), $query)
            ?? $this->fetchPng($this->geoserverUrl('/geoserver/wms'), $query);

        return $png ?? $this->emptyTile();
    }

    public function wmts(int $z, int $x, int $y): Response
    {
        $z = max(0, min(22, $z));
        $size = 2 ** $z;
        if ($x < 0 || $x >= $size || $y < 0 || $y >= $size) {
            return $this->emptyTile();
        }

        $mode = Cache::get(self::MODE_CACHE_KEY);
        if ($mode === 'wms') {
            return $this->fetchPng($this->geoserverUrl('/geoserver/basemap/wms'), $this->wmsTileQuery($z, $x, $y))
                ?? $this->emptyTile();
        }
        if ($mode === 'wmts') {
            return $this->fetchPng($this->wmtsUrl('geonode:basemap_allsite', $z, $x, $y))
                ?? $this->emptyTile();
        }

        $wmts = $this->fetchPng($this->wmtsUrl('geonode:basemap_allsite', $z, $x, $y));
        if ($wmts !== null) {
            Cache::put(self::MODE_CACHE_KEY, 'wmts', 3600);

            return $wmts;
        }

        $wms = $this->fetchPng($this->geoserverUrl('/geoserver/basemap/wms'), $this->wmsTileQuery($z, $x, $y));
        if ($wms !== null) {
            Cache::put(self::MODE_CACHE_KEY, 'wms', 3600);

            return $wms;
        }

        $fallback = $this->fetchPng($this->wmtsUrl('geonode:basemap_allsite', $z, $x, ($size - 1 - $y)))
            ?? $this->fetchPng($this->wmtsUrl('basemap:basemap_allsite', $z, $x, $y))
            ?? $this->fetchPng($this->geoserverUrl('/geoserver/geonode/wms'), $this->wmsTileQuery($z, $x, $y, 'geonode:basemap_allsite'));

        return $fallback ?? $this->emptyTile();
    }

    /**
     * @return array<string, mixed>
     */
    public function wmsQuery(Request $request): array
    {
        $incoming = [];
        foreach ($request->query() as $key => $value) {
            $incoming[strtoupper((string) $key)] = is_array($value) ? (string) reset($value) : (string) $value;
        }

        $layers = $incoming['LAYERS'] ?? self::WMS_LAYER;
        if (! in_array($layers, self::WMTS_LAYERS, true)) {
            $layers = self::WMS_LAYER;
        }

        $query = [];
        foreach (self::WMS_KEYS as $key) {
            if (isset($incoming[$key]) && $incoming[$key] !== '') {
                $query[$key] = $incoming[$key];
            }
        }
        $query['SERVICE'] = 'WMS';
        $query['REQUEST'] = $query['REQUEST'] ?? 'GetMap';
        $query['VERSION'] = $query['VERSION'] ?? '1.1.1';
        $query['LAYERS'] = $layers;
        $query['FORMAT'] = 'image/png';
        $query['TRANSPARENT'] = $query['TRANSPARENT'] ?? 'true';
        $query['TILED'] = 'true';
        $query['WIDTH'] = max(1, min(1024, (int) ($query['WIDTH'] ?? 256)));
        $query['HEIGHT'] = max(1, min(1024, (int) ($query['HEIGHT'] ?? 256)));
        $query['SRS'] = $query['SRS'] ?? $query['CRS'] ?? 'EPSG:3857';
        $query['BBOX'] = $query['BBOX'] ?? '';
        unset($query['CRS']);

        return $query;
    }

    public function tileBbox(int $z, int $x, int $y): string
    {
        $origin = 20037508.342789244;
        $n = 2 ** max(0, $z);
        $tile = ($origin * 2) / $n;
        $minX = -$origin + ($x * $tile);
        $maxX = $minX + $tile;
        $maxY = $origin - ($y * $tile);
        $minY = $maxY - $tile;

        return $minX.','.$minY.','.$maxX.','.$maxY;
    }

    /**
     * @return array<string, mixed>
     */
    private function wmsTileQuery(int $z, int $x, int $y, string $layer = self::WMS_LAYER): array
    {
        return [
            'SERVICE' => 'WMS',
            'REQUEST' => 'GetMap',
            'VERSION' => '1.1.1',
            'LAYERS' => $layer,
            'STYLES' => '',
            'FORMAT' => 'image/png',
            'TRANSPARENT' => 'true',
            'TILED' => 'true',
            'WIDTH' => 256,
            'HEIGHT' => 256,
            'SRS' => 'EPSG:3857',
            'BBOX' => $this->tileBbox($z, $x, $y),
        ];
    }

    private function wmtsUrl(string $layer, int $z, int $x, int $y): string
    {
        return $this->geoserverUrl('/geoserver/gwc/service/wmts').'?'.http_build_query([
            'layer' => $layer,
            'tilematrixset' => 'EPSG:900913',
            'Service' => 'WMTS',
            'Request' => 'GetTile',
            'Version' => '1.0.0',
            'Format' => 'image/png',
            'TileMatrix' => 'EPSG:900913:'.$z,
            'TileCol' => $x,
            'TileRow' => $y,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array<string, mixed>|null  $query
     */
    private function fetchPng(string $url, ?array $query = null): ?Response
    {
        try {
            $request = Http::timeout(12)
                ->connectTimeout(3)
                ->withOptions(['allow_redirects' => false, 'http_errors' => false])
                ->accept('image/png, image/jpeg, image/*, */*');
            $upstream = $query === null ? $request->get($url) : $request->get($url, $query);
        } catch (Throwable) {
            return null;
        }

        $body = $upstream->body();
        if (! $this->isImage($body)) {
            return null;
        }

        $contentType = str_starts_with($body, "\xff\xd8\xff") ? 'image/jpeg' : 'image/png';

        return response($body, 200)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function isImage(string $body): bool
    {
        return str_starts_with($body, "\x89PNG") || str_starts_with($body, "\xff\xd8\xff");
    }

    private function emptyTile(): Response
    {
        return response($this->transparentPng(), 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=30');
    }

    private function transparentPng(): string
    {
        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(256, 256);
            imagesavealpha($image, true);
            imagealphablending($image, false);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefilledrectangle($image, 0, 0, 255, 255, $transparent);
            ob_start();
            imagepng($image);
            imagedestroy($image);

            return (string) ob_get_clean();
        }

        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==', true);
    }

    private function geoserverUrl(string $path): string
    {
        return 'http://'.self::GEOSERVER_HOST.':'.self::GEOSERVER_PORT.$path;
    }
}
