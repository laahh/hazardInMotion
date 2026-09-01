<?php

declare(strict_types=1);

namespace App\Services\Isc;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

final class IscIupkBoundaryLoader
{
    /**
     * @return array{type:string,features:list<array<string,mixed>>}
     */
    public function featureCollection(): array
    {
        return Cache::remember('isc.iupk_boundary.fc', 3600, function (): array {
            $path = public_path('isc-assets/BounderyBC.js');
            if (! File::exists($path)) {
                return ['type' => 'FeatureCollection', 'features' => []];
            }

            $raw = File::get($path);
            $start = strpos($raw, '{');
            if ($start === false) {
                return ['type' => 'FeatureCollection', 'features' => []];
            }

            $json = rtrim(substr($raw, $start), " \t\n\r;");
            $decoded = json_decode($json, true);
            if (! is_array($decoded) || ($decoded['type'] ?? '') !== 'FeatureCollection') {
                return ['type' => 'FeatureCollection', 'features' => []];
            }

            $features = [];
            foreach ($decoded['features'] ?? [] as $feature) {
                if (is_array($feature)) {
                    $features[] = $feature;
                }
            }

            return ['type' => 'FeatureCollection', 'features' => $features];
        });
    }
}
