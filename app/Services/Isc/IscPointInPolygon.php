<?php

declare(strict_types=1);

namespace App\Services\Isc;

/**
 * Ray-cast GeoJSON Polygon/MultiPolygon. Koordinat [lng, lat] (WGS84).
 */
final class IscPointInPolygon
{
    /**
     * @param  array<string, mixed>  $geometry
     */
    public function contains(float $lng, float $lat, array $geometry): bool
    {
        $type = (string) ($geometry['type'] ?? '');
        $coords = $geometry['coordinates'] ?? null;
        if (! is_array($coords)) {
            return false;
        }

        if ($type === 'Polygon') {
            return $this->inPolygon($lng, $lat, $coords);
        }
        if ($type === 'MultiPolygon') {
            foreach ($coords as $polygon) {
                if (is_array($polygon) && $this->inPolygon($lng, $lat, $polygon)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array{type?:string,features?:list<array<string,mixed>>}  $featureCollection
     * @return array<string, mixed>|null
     */
    public function firstContainingFeature(float $lng, float $lat, array $featureCollection): ?array
    {
        foreach ($featureCollection['features'] ?? [] as $feature) {
            if (! is_array($feature)) {
                continue;
            }
            $geometry = $feature['geometry'] ?? null;
            if (! is_array($geometry)) {
                continue;
            }
            if ($this->contains($lng, $lat, $geometry)) {
                return $feature;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $rings
     */
    private function inPolygon(float $lng, float $lat, array $rings): bool
    {
        if ($rings === [] || ! is_array($rings[0])) {
            return false;
        }
        if (! $this->inRing($lng, $lat, $rings[0])) {
            return false;
        }
        $holeCount = count($rings);
        for ($i = 1; $i < $holeCount; $i++) {
            if (is_array($rings[$i]) && $this->inRing($lng, $lat, $rings[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $ring
     */
    private function inRing(float $lng, float $lat, array $ring): bool
    {
        $count = count($ring);
        if ($count < 3) {
            return false;
        }

        $inside = false;
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) ($ring[$i][0] ?? 0);
            $yi = (float) ($ring[$i][1] ?? 0);
            $xj = (float) ($ring[$j][0] ?? 0);
            $yj = (float) ($ring[$j][1] ?? 0);
            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lng < (($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi));
            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
