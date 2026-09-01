<?php

declare(strict_types=1);

namespace App\Actions\Isc;

use App\Services\Isc\IscHazardBoundaryClassifier;
use App\Services\Isc\IscPointInPolygon;
use Illuminate\Support\Carbon;

final class IscPobClassifyAction
{
    public const PRESENCE_IN = 'in';

    public const PRESENCE_OUT = 'out';

    public const PRESENCE_UNKNOWN = 'unknown';

    public const SAFETY_SAFE = 'safe';

    public const SAFETY_UNSAFE = 'unsafe';

    public function __construct(
        private readonly IscPointInPolygon $pip,
        private readonly IscHazardBoundaryClassifier $hazard,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $people
     * @param  array{type?:string,features?:list<array<string,mixed>>}  $iupk
     * @param  list<array<string, mixed>>  $hazardFeatures
     * @return list<array<string, mixed>>
     */
    public function execute(array $people, array $iupk, array $hazardFeatures, int $staleGpsSeconds = 900): array
    {
        $hazardous = [];
        foreach ($hazardFeatures as $feature) {
            if (! is_array($feature)) {
                continue;
            }
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            if (! $this->hazard->isHazardous($props)) {
                continue;
            }
            if (! is_array($feature['geometry'] ?? null)) {
                continue;
            }
            $hazardous[] = $feature;
        }

        $classified = [];
        foreach ($people as $person) {
            $classified[] = $this->classifyOne($person, $iupk, $hazardous, $staleGpsSeconds);
        }

        return $classified;
    }

    /**
     * @param  array<string, mixed>  $person
     * @param  array{type?:string,features?:list<array<string,mixed>>}  $iupk
     * @param  list<array<string, mixed>>  $hazardous
     * @return array<string, mixed>
     */
    public function classifyOne(array $person, array $iupk, array $hazardous, int $staleGpsSeconds = 900): array
    {
        $lat = isset($person['lat']) ? (float) $person['lat'] : 0.0;
        $lng = isset($person['lng']) ? (float) $person['lng'] : 0.0;
        $stale = $this->isStale($person['gps_updated_at'] ?? null, $staleGpsSeconds);

        $person['presence'] = self::PRESENCE_UNKNOWN;
        $person['safety'] = null;
        $person['stale'] = $stale;
        $person['iupk_site'] = null;
        $person['hazard_boundary_id'] = null;
        $person['hazard_name'] = null;
        $person['marker'] = 'stale';

        if ($lat == 0.0 || $lng == 0.0) {
            return $person;
        }
        if ($stale) {
            return $person;
        }

        $iupkFeature = $this->pip->firstContainingFeature($lng, $lat, $iupk);
        if ($iupkFeature === null) {
            $person['presence'] = self::PRESENCE_OUT;
            $person['marker'] = 'out';

            return $person;
        }

        $iupkProps = is_array($iupkFeature['properties'] ?? null) ? $iupkFeature['properties'] : [];
        $person['presence'] = self::PRESENCE_IN;
        $person['iupk_site'] = $iupkProps['Site'] ?? $iupkProps['Layer'] ?? $iupkProps['site'] ?? null;
        $person['safety'] = self::SAFETY_SAFE;
        $person['marker'] = 'safe';

        foreach ($hazardous as $feature) {
            $geometry = $feature['geometry'] ?? null;
            if (! is_array($geometry) || ! $this->pip->contains($lng, $lat, $geometry)) {
                continue;
            }
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $person['safety'] = self::SAFETY_UNSAFE;
            $person['marker'] = 'unsafe';
            $person['hazard_boundary_id'] = isset($props['id']) ? (string) $props['id'] : null;
            $person['hazard_name'] = $props['name'] ?? $props['nama'] ?? $props['title'] ?? $props['aktivitas'] ?? $props['activity'] ?? null;
            break;
        }

        return $person;
    }

    private function isStale(mixed $updatedAt, int $staleGpsSeconds): bool
    {
        if ($updatedAt === null || $updatedAt === '') {
            return true;
        }
        try {
            return Carbon::parse((string) $updatedAt)->lt(Carbon::now()->subSeconds($staleGpsSeconds));
        } catch (\Throwable) {
            return true;
        }
    }
}
