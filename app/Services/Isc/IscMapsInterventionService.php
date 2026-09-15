<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Actions\Isc\IscSyncActiveViolationsAction;
use App\Models\Isc\IscBoundaryEvent;
use App\Models\Isc\IscIntervention;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Tasklist intervensi: sumber kebenaran = pelanggaran live Besigma (sama Beranda).
 * Tabel lokal hanya menyimpan state agar form intervensi punya event_id.
 */
final class IscMapsInterventionService
{
    public const LIST_LIMIT = 250;

    public const POB_LIVE_CACHE_KEY = 'isc.pob.snapshot.live.v6';

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
        private readonly IscBesigmaViolationReader $violations,
        private readonly IscSyncActiveViolationsAction $sync,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(?User $user, bool $demo = false): array
    {
        $canCreate = $user?->can('create', IscIntervention::class) ?? false;
        if ($demo) {
            return $this->fromRows($this->demoTasks(), 'demo', true, $canCreate);
        }

        if (! IscSchema::eventsReady()) {
            return $this->fromRows([], 'local', false, false);
        }

        $pack = $this->activeViolationPack();
        if ($pack !== null) {
            $this->syncPackQuietly($pack);

            return $this->fromRows(
                $this->loadOpenTaskRows(),
                $pack['source'],
                true,
                $canCreate,
            );
        }

        return $this->fromRows($this->loadOpenTaskRows(), 'local', true, $canCreate);
    }

    /**
     * Pack pelanggaran aktif: reader live dulu, fallback cache POB Beranda (TTL singkat).
     *
     * @return array{people:list<array<string,mixed>>,units:list<array<string,mixed>>,source:string}|null
     */
    private function activeViolationPack(): ?array
    {
        try {
            $this->violations->warmUp(true);
            if ($this->violations->isUp()) {
                $people = $this->violations->people();
                $units = $this->violations->units();
                if (! $this->violations->isUp()) {
                    return $this->packFromPobCache();
                }
                if ($people === [] && $units === []) {
                    return null;
                }

                return [
                    'people' => $people,
                    'units' => $units,
                    'source' => 'besigma',
                ];
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $this->packFromPobCache();
    }

    /**
     * @return array{people:list<array<string,mixed>>,units:list<array<string,mixed>>,source:string}|null
     */
    private function packFromPobCache(): ?array
    {
        $cached = Cache::get(self::POB_LIVE_CACHE_KEY);
        if (! is_array($cached) || ($cached['source'] ?? '') !== 'live') {
            return null;
        }

        $people = [];
        $units = [];
        $seenPeople = [];
        $seenUnits = [];
        foreach ($cached['people'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $vid = trim((string) ($row['besigma_violation_id'] ?? ''));
            $fromViolation = (bool) ($row['from_violation'] ?? false);
            if ($vid === '' && ! $fromViolation) {
                continue;
            }
            if (($row['entity'] ?? 'person') === 'unit') {
                $key = $vid !== '' ? $vid : ('unit:'.(string) ($row['unit_id'] ?? ''));
                if ($key === 'unit:' || isset($seenUnits[$key])) {
                    continue;
                }
                $seenUnits[$key] = true;
                $units[] = $this->personLikeToViolationRow($row, true);
                continue;
            }

            $key = $vid !== '' ? $vid : ('user:'.(string) ($row['user_id'] ?? $row['sid'] ?? ''));
            if ($key === 'user:' || isset($seenPeople[$key])) {
                continue;
            }
            $seenPeople[$key] = true;
            $people[] = $this->personLikeToViolationRow($row, false);
        }

        if ($people === [] && $units === []) {
            // Ringkasan kinds di cache: kalau Beranda bilang ada N, tapi people tidak
            // membawa violation id, jangan pakai cache kosong.
            return null;
        }

        return [
            'people' => $people,
            'units' => $units,
            'source' => 'pob_cache',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function personLikeToViolationRow(array $row, bool $unit): array
    {
        if ($unit) {
            return [
                'id' => (string) ($row['besigma_violation_id'] ?? ''),
                'unit_id' => (string) ($row['unit_id'] ?? ''),
                'sid' => (string) ($row['sid'] ?? ''),
                'name' => (string) ($row['name'] ?? 'Unit'),
                'company' => $row['company'] ?? null,
                'site' => $row['site'] ?? $row['site_label'] ?? null,
                'site_code' => $row['site_code'] ?? null,
                'boundary_id' => (string) ($row['hazard_boundary_id'] ?? ''),
                'hazard_name' => $row['hazard_name'] ?? null,
                'status' => (string) ($row['besigma_status'] ?? ''),
                'entered_at' => $row['entered_at'] ?? null,
                'lat' => $row['lat'] ?? null,
                'lng' => $row['lng'] ?? null,
            ];
        }

        return [
            'id' => (string) ($row['besigma_violation_id'] ?? ''),
            'user_id' => (string) ($row['user_id'] ?? ''),
            'sid' => (string) ($row['sid'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'company' => $row['company'] ?? null,
            'job_title' => $row['job_title'] ?? null,
            'site' => $row['site'] ?? $row['site_label'] ?? null,
            'site_code' => $row['site_code'] ?? null,
            'boundary_id' => (string) ($row['hazard_boundary_id'] ?? ''),
            'hazard_name' => $row['hazard_name'] ?? null,
            'hazard_kind' => $row['hazard_kind'] ?? IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER,
            'is_competency' => ($row['hazard_kind'] ?? '') === IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE ? 1 : 0,
            'status' => (string) ($row['besigma_status'] ?? ''),
            'entered_at' => $row['entered_at'] ?? null,
            'lat' => $row['lat'] ?? null,
            'lng' => $row['lng'] ?? null,
        ];
    }

    /**
     * @param  array{people:list<array<string,mixed>>,units:list<array<string,mixed>>,source:string}  $pack
     */
    private function syncPackQuietly(array $pack): void
    {
        if (! IscSchema::violationSyncReady()) {
            return;
        }

        try {
            $liveTotal = count($pack['people']) + count($pack['units']);
            $localOpen = IscBoundaryEvent::query()
                ->whereIn('status', ['open', 'in_progress'])
                ->count();

            if ($localOpen >= $liveTotal && $pack['source'] !== 'besigma') {
                return;
            }

            // Karyawan dulu (tanpa close) supaya HUD "Bahaya karyawan" cepat selaras Beranda.
            if ($pack['people'] !== []) {
                $this->sync->executeFromPack($pack['people'], [], closeOrphans: false);
            }

            // Full pack sekali. Close orphan HANYA dari live Besigma (cache POB bisa parsial).
            $this->sync->executeFromPack(
                $pack['people'],
                $pack['units'],
                closeOrphans: $pack['source'] === 'besigma',
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadOpenTaskRows(): array
    {
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

        return $rows;
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
            'user_id' => $syncReady ? ($event->user_id ?: null) : null,
            'unit_id' => $syncReady ? ($event->unit_id ?: null) : null,
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
            'has_trail' => $syncReady && (
                ($entity === 'unit' && trim((string) ($event->unit_id ?? '')) !== '')
                || ($entity !== 'unit' && trim((string) ($event->user_id ?? '')) !== '')
            ),
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
            'user_id' => $event['user_id'] ?? null,
            'unit_id' => $event['unit_id'] ?? null,
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
            'show_url' => ($id > 0 && $id < 9000) ? route('isc.interventions.show', $id) : null,
            'has_trail' => trim((string) ($event['user_id'] ?? '')) !== ''
                || trim((string) ($event['unit_id'] ?? '')) !== '',
        ];
    }
}
