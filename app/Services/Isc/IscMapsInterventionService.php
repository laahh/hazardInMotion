<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Models\Isc\IscBoundaryEvent;
use App\Models\Isc\IscIntervention;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Tasklist intervensi dari event lokal (bukan scan Besigma di request UI).
 */
final class IscMapsInterventionService
{
    public const LIST_LIMIT = 80;

    /**
     * @var list<string>
     */
    public const TYPES = ['himbauan', 'evakuasi', 'penghentian_aktivitas', 'dampingan', 'lainnya'];

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'himbauan' => 'Himbauan',
        'evakuasi' => 'Evakuasi',
        'penghentian_aktivitas' => 'Penghentian aktivitas',
        'dampingan' => 'Dampingan',
        'lainnya' => 'Lainnya',
    ];

    public function __construct(
        private readonly IscPobDemoDataset $demo,
        private readonly IscHazardBoundaryClassifier $hazard,
        private readonly IscSiteNormalizer $sites,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(?User $user, bool $demo = false): array
    {
        $canCreate = $user?->can('create', IscIntervention::class) ?? false;
        if ($demo || ! IscSchema::eventsReady()) {
            return $this->fromRows($this->demoTasks(), 'demo', IscSchema::eventsReady(), $canCreate && IscSchema::eventsReady());
        }

        $columns = [
            'id', 'person_key', 'sid', 'name', 'company', 'job_title', 'lat', 'lng',
            'iupk_site', 'hazard_boundary_id', 'hazard_name', 'entered_at', 'exited_at',
            'duration_seconds', 'status', 'rule_code',
        ];
        $syncReady = IscSchema::violationSyncReady();
        if ($syncReady) {
            $columns = array_merge($columns, [
                'entity', 'besigma_violation_id', 'user_id', 'unit_id', 'hazard_kind', 'besigma_status',
            ]);
        }

        $events = IscBoundaryEvent::query()
            ->select($columns)
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('entered_at')
            ->limit(self::LIST_LIMIT)
            ->get();

        $rows = [];
        foreach ($events as $event) {
            $rows[] = $this->fromEvent($event, $syncReady);
        }

        return $this->fromRows($rows, 'local', true, $canCreate);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function fromRows(array $rows, string $source, bool $ready, bool $canCreate): array
    {
        $open = 0;
        $inProgress = 0;
        $kinds = [
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER => 0,
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE => 0,
            IscHazardBoundaryClassifier::KIND_UNIT_DANGER => 0,
        ];
        $siteCounts = [
            'BMO' => 0,
            'LMO' => 0,
            'GMO' => 0,
            'SMO' => 0,
            'PUNAN' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'in_progress') {
                $inProgress++;
            } else {
                $open++;
            }
            $kind = (string) ($row['hazard_kind'] ?? '');
            if (isset($kinds[$kind])) {
                $kinds[$kind]++;
            }
            $code = (string) ($row['site_code'] ?? '');
            if ($code !== '' && isset($siteCounts[$code])) {
                $siteCounts[$code]++;
            }
        }

        $sites = [];
        foreach ($siteCounts as $code => $total) {
            $sites[] = ['code' => $code, 'total' => $total];
        }

        return [
            'source' => $source,
            'ready' => $ready,
            'can_create' => $canCreate,
            'types' => self::TYPES,
            'type_labels' => self::TYPE_LABELS,
            'summary' => [
                'total' => count($rows),
                'open' => $open,
                'in_progress' => $inProgress,
                'kinds' => $kinds,
                'sites' => $sites,
            ],
            'tasks' => $rows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function demoTasks(): array
    {
        $rows = [];
        foreach ($this->demo->events() as $event) {
            if (! in_array((string) ($event['status'] ?? ''), ['open', 'in_progress'], true)) {
                continue;
            }
            $rows[] = $this->fromArray($event);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function fromEvent(IscBoundaryEvent $event, bool $syncReady): array
    {
        $entity = $syncReady ? (string) ($event->entity ?: 'person') : 'person';
        $kind = $syncReady ? (string) ($event->hazard_kind ?? '') : '';
        $lat = $event->lat;
        $lng = $event->lng;
        $hasPoint = $lat !== null && $lng !== null && (float) $lat != 0.0 && (float) $lng != 0.0;
        $site = $event->iupk_site;
        $siteCode = $this->sites->codeFrom($site);

        return [
            'id' => (int) $event->id,
            'entity' => $entity,
            'hazard_kind' => $kind !== '' ? $kind : null,
            'hazard_kind_label' => $this->hazard->label($kind !== '' ? $kind : null),
            'besigma_violation_id' => $syncReady ? ($event->besigma_violation_id ?: null) : null,
            'besigma_status' => $syncReady ? ($event->besigma_status ?: null) : null,
            'name' => (string) $event->name,
            'sid' => $event->sid,
            'company' => $event->company,
            'job_title' => $event->job_title,
            'site' => $site,
            'site_code' => $siteCode,
            'status' => (string) $event->status,
            'lat' => $hasPoint ? (float) $lat : null,
            'lng' => $hasPoint ? (float) $lng : null,
            'has_point' => $hasPoint,
            'duration_seconds' => $event->durationSecondsNow(),
            'entered_at' => $event->entered_at?->toIso8601String(),
            'hazard_name' => $event->hazard_name,
            'show_url' => route('isc.interventions.show', $event->id),
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function fromArray(array $event): array
    {
        $lat = isset($event['lat']) ? (float) $event['lat'] : null;
        $lng = isset($event['lng']) ? (float) $event['lng'] : null;
        $hasPoint = $lat !== null && $lng !== null && $lat != 0.0 && $lng != 0.0;
        $kind = (string) ($event['hazard_kind'] ?? '');
        $site = $event['iupk_site'] ?? null;
        $siteCode = $event['site_code'] ?? $this->sites->codeFrom($site);
        $id = (int) ($event['id'] ?? 0);
        $entered = $event['entered_at'] ?? null;
        $duration = isset($event['duration_seconds']) ? (int) $event['duration_seconds'] : 0;
        if ($duration === 0 && is_string($entered) && $entered !== '') {
            try {
                $duration = (int) Carbon::parse($entered)->diffInSeconds(now());
            } catch (\Throwable) {
                $duration = 0;
            }
        }

        return [
            'id' => $id,
            'entity' => (string) ($event['entity'] ?? 'person'),
            'hazard_kind' => $kind !== '' ? $kind : null,
            'hazard_kind_label' => $this->hazard->label($kind !== '' ? $kind : null),
            'besigma_violation_id' => $event['besigma_violation_id'] ?? null,
            'besigma_status' => $event['besigma_status'] ?? null,
            'name' => (string) ($event['name'] ?? ''),
            'sid' => $event['sid'] ?? null,
            'company' => $event['company'] ?? null,
            'job_title' => $event['job_title'] ?? null,
            'site' => $site,
            'site_code' => $siteCode,
            'status' => (string) ($event['status'] ?? 'open'),
            'lat' => $hasPoint ? $lat : null,
            'lng' => $hasPoint ? $lng : null,
            'has_point' => $hasPoint,
            'duration_seconds' => $duration,
            'entered_at' => is_string($entered) ? $entered : null,
            'hazard_name' => $event['hazard_name'] ?? null,
            'show_url' => $id > 0 ? route('isc.interventions.show', $id) : null,
        ];
    }
}
