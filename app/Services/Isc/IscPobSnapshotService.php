<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Actions\Isc\IscPobClassifyAction;
use App\Actions\Isc\IscRfidReconcileAction;
use App\Models\Isc\IscBoundaryEvent;
use App\Models\Isc\IscDetectionRule;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class IscPobSnapshotService
{
    public const DEFAULT_STALE_SECONDS = 900;

    public function __construct(
        private readonly IscPersonnelGpsReader $gps,
        private readonly IscIupkBoundaryLoader $iupk,
        private readonly IscBoundaryMapService $boundaries,
        private readonly IscPobClassifyAction $classify,
        private readonly IscRfidOnsiteReader $rfid,
        private readonly IscRfidReconcileAction $reconcile,
        private readonly IscHazardBoundaryClassifier $hazard,
        private readonly IscPobDemoDataset $demo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(bool $fresh = false, bool $demo = true): array
    {
        $key = $demo ? 'isc.pob.snapshot.demo.v1' : 'isc.pob.snapshot.live.v1';
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 20, fn (): array => $demo ? $this->buildDemo() : $this->buildLive());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function personByKey(string $key, bool $demo = true): ?array
    {
        foreach ($this->snapshot(false, $demo)['people'] ?? [] as $person) {
            if ((string) ($person['key'] ?? '') === $key) {
                return $person;
            }
        }

        return null;
    }

    public function staleGpsSeconds(): int
    {
        if (! IscSchema::rulesReady()) {
            return self::DEFAULT_STALE_SECONDS;
        }
        try {
            $rule = IscDetectionRule::query()->where('is_active', true)->orderBy('id')->first();
            $seconds = (int) ($rule?->stale_gps_seconds ?? self::DEFAULT_STALE_SECONDS);

            return $seconds > 0 ? $seconds : self::DEFAULT_STALE_SECONDS;
        } catch (Throwable) {
            return self::DEFAULT_STALE_SECONDS;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDemo(): array
    {
        $stale = $this->staleGpsSeconds();
        $iupk = $this->iupk->featureCollection();
        $hazards = $this->demo->hazardFeatures();
        $classified = $this->classify->execute($this->demo->people(), $iupk, $hazards, $stale);
        $events = [];
        foreach ($this->demo->events() as $event) {
            $events[$event['person_key']] = $event;
        }
        foreach ($classified as $i => $person) {
            $event = $events[$person['key']] ?? null;
            $classified[$i]['open_event_id'] = $event['id'] ?? null;
            $classified[$i]['intervention_status'] = $event['status'] ?? null;
            $classified[$i]['entered_at'] = $event['entered_at'] ?? null;
            $classified[$i]['duration_seconds'] = $event['duration_seconds'] ?? null;
        }

        $ever = $this->demo->everIdentifiers();
        $current = array_values(array_filter(
            $classified,
            static fn (array $p): bool => ! ($p['stale'] ?? true) && trim((string) ($p['sid'] ?? '')) !== '',
        ));
        $rfid = $this->demo->rfidOnsite();
        $reconcile = $this->reconcile->execute($ever, $current, $rfid);
        unset($reconcile['ever'], $reconcile['current'], $reconcile['rfid']);

        return [
            'source' => 'demo',
            'generated_at' => now()->toIso8601String(),
            'stale_gps_seconds' => $stale,
            'besigma_connected' => false,
            'besigma_error' => 'Mode dummy: polygon bahaya preview, bukan live Besigma.',
            'rfid_available' => true,
            'summary' => $this->summarize($classified),
            'reconcile' => array_merge($reconcile, ['rfid_available' => true]),
            'people' => $classified,
            'hazard_features' => $hazards,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLive(): array
    {
        $stale = $this->staleGpsSeconds();
        $people = $this->gps->latest();
        if ($people === []) {
            return $this->buildDemo();
        }

        $iupk = $this->iupk->featureCollection();
        $hazardPack = $this->liveHazards();
        $classified = $this->classify->execute($people, $iupk, $hazardPack['features'], $stale);
        $classified = $this->attachOpenEvents($classified);

        $ever = $this->gps->everIdentifiers();
        $current = array_values(array_filter(
            $classified,
            static fn (array $p): bool => ! ($p['stale'] ?? true) && trim((string) ($p['sid'] ?? '')) !== '',
        ));
        $sids = $this->collectSids($ever, $classified);
        $rfidPack = $this->rfid->onsiteToday($sids);
        $reconcile = $this->reconcile->execute($ever, $current, $rfidPack['people']);
        unset($reconcile['ever'], $reconcile['current'], $reconcile['rfid']);

        return [
            'source' => 'live',
            'generated_at' => now()->toIso8601String(),
            'stale_gps_seconds' => $stale,
            'besigma_connected' => $hazardPack['connected'],
            'besigma_error' => $hazardPack['error'],
            'rfid_available' => $rfidPack['available'],
            'summary' => $this->summarize($classified),
            'reconcile' => array_merge($reconcile, ['rfid_available' => $rfidPack['available']]),
            'people' => $classified,
            'hazard_features' => $hazardPack['features'],
        ];
    }

    /**
     * @return array{connected:bool,error:?string,features:list<array<string,mixed>>}
     */
    private function liveHazards(): array
    {
        try {
            $geo = $this->boundaries->boundariesGeoJson();
        } catch (Throwable $e) {
            report($e);

            return ['connected' => false, 'error' => $e->getMessage(), 'features' => []];
        }

        $features = [];
        foreach ($geo['features'] ?? [] as $feature) {
            if (! is_array($feature)) {
                continue;
            }
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            if ($this->hazard->isHazardous($props)) {
                $features[] = $feature;
            }
        }

        return [
            'connected' => (bool) ($geo['connected'] ?? false),
            'error' => isset($geo['error']) ? (string) $geo['error'] : null,
            'features' => $features,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @return list<array<string, mixed>>
     */
    private function attachOpenEvents(array $people): array
    {
        if (! IscSchema::eventsReady()) {
            return $people;
        }
        try {
            $open = IscBoundaryEvent::query()->whereIn('status', ['open', 'in_progress'])->get()->keyBy('person_key');
        } catch (Throwable) {
            return $people;
        }
        foreach ($people as $i => $person) {
            $event = $open->get((string) ($person['key'] ?? ''));
            $people[$i]['open_event_id'] = $event?->id;
            $people[$i]['intervention_status'] = $event?->status;
            $people[$i]['entered_at'] = $event?->entered_at?->toIso8601String();
            $people[$i]['duration_seconds'] = $event?->durationSecondsNow();
        }

        return $people;
    }

    /**
     * @param  list<array<string, mixed>>  $ever
     * @param  list<array<string, mixed>>  $classified
     * @return list<string>
     */
    private function collectSids(array $ever, array $classified): array
    {
        $sids = [];
        foreach (array_merge($ever, $classified) as $person) {
            $sid = trim((string) ($person['sid'] ?? ''));
            if ($sid !== '') {
                $sids[mb_strtoupper($sid)] = $sid;
            }
        }

        return array_values($sids);
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @return array<string, int>
     */
    private function summarize(array $people): array
    {
        $in = $out = $unknown = $safe = $unsafe = 0;
        foreach ($people as $person) {
            $presence = (string) ($person['presence'] ?? 'unknown');
            if ($presence === IscPobClassifyAction::PRESENCE_IN) {
                $in++;
                if (($person['safety'] ?? null) === IscPobClassifyAction::SAFETY_UNSAFE) {
                    $unsafe++;
                } else {
                    $safe++;
                }
            } elseif ($presence === IscPobClassifyAction::PRESENCE_OUT) {
                $out++;
            } else {
                $unknown++;
            }
        }

        return [
            'total' => count($people),
            'in' => $in,
            'out' => $out,
            'unknown' => $unknown,
            'safe' => $safe,
            'unsafe' => $unsafe,
        ];
    }
}
