<?php

declare(strict_types=1);

namespace App\Services\Isc;

use Illuminate\Http\Request;
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
            return response('Missing BBOX', 400);
        }

        return $this->fetch(
            $this->geoserverUrl('/geoserver/basemap/wms'),
            $query,
        );
    }

    public function wmts(int $z, int $x, int $y): Response
    {
        $z = max(0, min(22, $z));
        $size = 2 ** $z;
        if ($x < 0 || $x >= $size || $y < 0 || $y >= $size) {
            return response('Invalid tile', 400);
        }

        $rows = [$y, ($size - 1 - $y)];
        foreach (self::WMTS_LAYERS as $layer) {
            foreach (array_unique($rows) as $row) {
                $response = $this->fetch(
                    $this->geoserverUrl('/geoserver/gwc/service/wmts'),
                    [
                        'layer' => $layer,
                        'tilematrixset' => 'EPSG:900913',
                        'Service' => 'WMTS',
                        'Request' => 'GetTile',
                        'Version' => '1.0.0',
                        'Format' => 'image/png',
                        'TileMatrix' => 'EPSG:900913:'.$z,
                        'TileCol' => $x,
                        'TileRow' => $row,
                    ],
                );
                if ($response->getStatusCode() === 200 && str_starts_with((string) $response->headers->get('Content-Type'), 'image/')) {
                    return $response;
                }
            }
        }

        return response('Basemap tile not found', 502);
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

    /**
     * @param  array<string, mixed>  $query
     */
    private function fetch(string $url, array $query): Response
    {
        try {
            $upstream = Http::timeout(20)
                ->connectTimeout(5)
                ->withOptions(['allow_redirects' => false])
                ->accept('image/png, image/*, */*')
                ->get($url, $query);
        } catch (Throwable $e) {
            report($e);

            return response('Basemap unreachable', 502);
        }

        $contentType = (string) ($upstream->header('Content-Type') ?? 'application/octet-stream');
        $status = $upstream->status();
        if ($status < 200 || $status >= 300) {
            return response($upstream->body(), $status)
                ->header('Content-Type', $contentType);
        }

        return response($upstream->body(), 200)
            ->header('Content-Type', $contentType !== '' ? $contentType : 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function geoserverUrl(string $path): string
    {
        return 'http://'.self::GEOSERVER_HOST.':'.self::GEOSERVER_PORT.$path;
    }
}
