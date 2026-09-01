<?php

declare(strict_types=1);

namespace App\Actions\Isc;

use App\Services\Isc\IscHazardBoundaryClassifier;
use App\Services\Isc\IscPointInPolygon;
use App\Services\Isc\IscSiteNormalizer;
use Illuminate\Support\Carbon;

final class IscPobClassifyAction
{
    public const PRESENCE_IN = 'in';

    public const PRESENCE_OUT = 'out';

    public const PRESENCE_UNKNOWN = 'unknown';

    public const SAFETY_SAFE = 'safe';

    public const SAFETY_UNSAFE = 'unsafe';

    /**
     * @var list<string>
     */
    private const KIND_PRIORITY = [
        IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE,
        IscHazardBoundaryClassifier::KIND_UNIT_DANGER,
        IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER,
    ];

    public function __construct(
        private readonly IscPointInPolygon $pip,
        private readonly IscHazardBoundaryClassifier $hazard,
        private readonly IscSiteNormalizer $sites = new IscSiteNormalizer(),
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
        $person['site_code'] = $this->sites->codeFrom($person['site'] ?? null);
        $person['hazard_boundary_id'] = null;
        $person['hazard_name'] = null;
        $person['hazard_kind'] = null;
        $person['hazard_kind_label'] = null;
        $person['marker'] = 'stale';

        if ($lat == 0.0 || $lng == 0.0) {
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
        $person['site_code'] = $this->sites->codeFrom(
            $person['iupk_site'],
            $iupkProps['Layer'] ?? null,
            $person['site'] ?? null
        );
        $person['safety'] = self::SAFETY_SAFE;
        $person['marker'] = 'safe';

        $hits = [];
        foreach ($hazardous as $feature) {
            $geometry = $feature['geometry'] ?? null;
            if (! is_array($geometry) || ! $this->pip->contains($lng, $lat, $geometry)) {
                continue;
            }
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $hits[] = $props;
        }
        $chosen = $this->pickHazard($hits);
        if ($chosen !== null) {
            $kind = $this->hazard->kind($chosen);
            $person['safety'] = self::SAFETY_UNSAFE;
            $person['marker'] = $kind ?? 'unsafe';
            $person['hazard_kind'] = $kind;
            $person['hazard_kind_label'] = $this->hazard->label($kind);
            $person['hazard_boundary_id'] = isset($chosen['id']) ? (string) $chosen['id'] : null;
            $person['hazard_name'] = $chosen['name'] ?? $chosen['nama'] ?? $chosen['title'] ?? $chosen['aktivitas'] ?? $chosen['activity'] ?? null;
            $person['hazard_activity'] = $chosen['aktivitas'] ?? $chosen['activity'] ?? null;
            $person['pit_name'] = $chosen['pit_name'] ?? $person['pit_name'] ?? null;
        }

        return $person;
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @return array<string, mixed>|null
     */
    private function pickHazard(array $hits): ?array
    {
        if ($hits === []) {
            return null;
        }
        foreach (self::KIND_PRIORITY as $kind) {
            foreach ($hits as $props) {
                if ($this->hazard->kind($props) === $kind) {
                    return $props;
                }
            }
        }

        return $hits[0];
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
