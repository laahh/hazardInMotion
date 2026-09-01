<?php

declare(strict_types=1);

namespace App\Actions\Isc;

use App\Events\Isc\IscHazardEntered;
use App\Models\Isc\IscBoundaryEvent;
use App\Models\Isc\IscDetectionRule;
use App\Services\Isc\IscBesigmaViolationReader;
use App\Services\Isc\IscHazardBoundaryClassifier;
use App\Services\Isc\IscPobDemoDataset;
use App\Services\Isc\IscSchema;
use App\Services\Isc\IscSiteNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Salin pelanggaran aktif Besigma (orang + unit) ke MySQL lokal.
 * Tidak menulis ke connection Besigma; hanya IscBesigmaViolationReader yang membaca.
 */
final class IscSyncActiveViolationsAction
{
    public const RULE_CODE = 'hazard-entry';

    public function __construct(
        private readonly IscBesigmaViolationReader $violations,
        private readonly IscPobDemoDataset $demo,
        private readonly IscSiteNormalizer $sites = new IscSiteNormalizer(),
        private readonly IscHazardBoundaryClassifier $hazard = new IscHazardBoundaryClassifier(),
    ) {}

    /**
     * @return array{created:int,updated:int,closed:int,skipped:bool,message:?string}
     */
    public function execute(bool $demo = false): array
    {
        if (! IscSchema::violationSyncReady()) {
            return [
                'created' => 0,
                'updated' => 0,
                'closed' => 0,
                'skipped' => true,
                'message' => 'Kolom sync pelanggaran belum ada. Jalankan migration isc_boundary_events setelah konfirmasi.',
            ];
        }

        $rows = $this->activeRows($demo);
        $open = IscBoundaryEvent::query()->whereIn('status', ['open', 'in_progress'])->get();
        $index = $this->indexOpen($open);

        $seenIds = [];
        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            $existing = $this->findExisting($index, $row);
            if ($existing !== null) {
                $this->refreshEvent($existing, $row);
                $seenIds[(int) $existing->id] = true;
                $updated++;
                continue;
            }

            $event = IscBoundaryEvent::query()->create($this->createPayload($row));
            $this->remember($index, $event);
            $seenIds[(int) $event->id] = true;
            $created++;
            try {
                IscHazardEntered::dispatch($event);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $closed = 0;
        foreach ($open as $event) {
            if (isset($seenIds[(int) $event->id])) {
                continue;
            }
            $exited = now();
            $event->exited_at = $exited;
            $event->duration_seconds = (int) Carbon::parse($event->entered_at)->diffInSeconds($exited);
            if ($event->status === 'open') {
                $event->status = 'closed';
                $closed++;
            }
            $event->save();
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'closed' => $closed,
            'skipped' => false,
            'message' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activeRows(bool $demo): array
    {
        if ($demo) {
            $pack = $this->demo->activeViolations();
            $people = is_array($pack['people'] ?? null) ? $pack['people'] : [];
            $units = is_array($pack['units'] ?? null) ? $pack['units'] : [];
        } else {
            $people = $this->violations->people();
            $units = $this->violations->units();
        }

        $rows = [];
        foreach ($people as $person) {
            $mapped = $this->mapPerson($person);
            if ($mapped !== null) {
                $rows[] = $mapped;
            }
        }
        foreach ($units as $unit) {
            $mapped = $this->mapUnit($unit);
            if ($mapped !== null) {
                $rows[] = $mapped;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>|null
     */
    private function mapPerson(array $person): ?array
    {
        $userId = trim((string) ($person['user_id'] ?? ''));
        $sid = trim((string) ($person['sid'] ?? ''));
        $personKey = $sid !== '' ? 'sid:'.mb_strtoupper($sid) : ($userId !== '' ? 'user:'.$userId : '');
        if ($personKey === '') {
            return null;
        }
        $boundaryId = trim((string) ($person['boundary_id'] ?? $person['hazard_boundary_id'] ?? ''));
        $kind = (string) ($person['hazard_kind'] ?? IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER);
        if (! isset(IscHazardBoundaryClassifier::KIND_LABELS[$kind])) {
            $kind = IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER;
        }

        return $this->normalizedRow(
            entity: 'person',
            personKey: $personKey,
            violationId: trim((string) ($person['id'] ?? '')),
            userId: $userId !== '' ? $userId : null,
            unitId: null,
            sid: $sid !== '' ? $sid : null,
            name: (string) ($person['name'] ?? $personKey),
            company: $this->nullableString($person['company'] ?? null),
            jobTitle: $this->nullableString($person['job_title'] ?? null),
            site: $this->nullableString($person['site'] ?? $person['site_code'] ?? null),
            siteCode: $this->nullableString($person['site_code'] ?? null),
            boundaryId: $boundaryId !== '' ? $boundaryId : null,
            hazardName: $this->nullableString($person['hazard_name'] ?? null),
            kind: $kind,
            status: strtoupper(trim((string) ($person['status'] ?? ''))),
            enteredAt: $person['entered_at'] ?? null,
            lat: $person['lat'] ?? null,
            lng: $person['lng'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $unit
     * @return array<string, mixed>|null
     */
    private function mapUnit(array $unit): ?array
    {
        $unitId = trim((string) ($unit['unit_id'] ?? ''));
        if ($unitId === '') {
            return null;
        }
        $sid = trim((string) ($unit['sid'] ?? ''));
        $boundaryId = trim((string) ($unit['boundary_id'] ?? $unit['hazard_boundary_id'] ?? ''));

        return $this->normalizedRow(
            entity: 'unit',
            personKey: 'unit:'.$unitId,
            violationId: trim((string) ($unit['id'] ?? '')),
            userId: null,
            unitId: $unitId,
            sid: $sid !== '' ? $sid : null,
            name: (string) ($unit['name'] ?? ($sid !== '' ? $sid : 'Unit '.substr($unitId, 0, 8))),
            company: $this->nullableString($unit['company'] ?? null),
            jobTitle: null,
            site: $this->nullableString($unit['site'] ?? $unit['site_code'] ?? null),
            siteCode: $this->nullableString($unit['site_code'] ?? null),
            boundaryId: $boundaryId !== '' ? $boundaryId : null,
            hazardName: $this->nullableString($unit['hazard_name'] ?? null),
            kind: IscHazardBoundaryClassifier::KIND_UNIT_DANGER,
            status: strtoupper(trim((string) ($unit['status'] ?? ''))),
            enteredAt: $unit['entered_at'] ?? null,
            lat: $unit['lat'] ?? null,
            lng: $unit['lng'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedRow(
        string $entity,
        string $personKey,
        string $violationId,
        ?string $userId,
        ?string $unitId,
        ?string $sid,
        string $name,
        ?string $company,
        ?string $jobTitle,
        ?string $site,
        ?string $siteCode,
        ?string $boundaryId,
        ?string $hazardName,
        string $kind,
        string $status,
        mixed $enteredAt,
        mixed $lat,
        mixed $lng,
    ): array {
        $subject = $entity === 'unit' ? (string) $unitId : (string) $userId;
        if ($subject === '') {
            $subject = $personKey;
        }

        return [
            'entity' => $entity,
            'person_key' => $personKey,
            'besigma_violation_id' => $violationId !== '' ? $violationId : null,
            'user_id' => $userId,
            'unit_id' => $unitId,
            'sid' => $sid,
            'name' => $name,
            'company' => $company,
            'job_title' => $jobTitle,
            'iupk_site' => $site ?? $siteCode,
            'site_code' => $siteCode ?? $this->sites->codeFrom($site, $siteCode),
            'hazard_boundary_id' => $boundaryId,
            'hazard_name' => $hazardName,
            'hazard_kind' => $kind,
            'hazard_kind_label' => $this->hazard->label($kind),
            'besigma_status' => $status !== '' ? $status : null,
            'entered_at' => $this->parseTime($enteredAt),
            'lat' => $this->coord($lat),
            'lng' => $this->coord($lng),
            'dedup_key' => $entity.'|'.$subject.'|'.(string) $boundaryId,
        ];
    }

    /**
     * @param  Collection<int, IscBoundaryEvent>  $open
     * @return array<string, IscBoundaryEvent>
     */
    private function indexOpen(Collection $open): array
    {
        $index = [];
        foreach ($open as $event) {
            $this->remember($index, $event);
        }

        return $index;
    }

    /**
     * @param  array<string, IscBoundaryEvent>  $index
     */
    private function remember(array &$index, IscBoundaryEvent $event): void
    {
        $vid = trim((string) ($event->besigma_violation_id ?? ''));
        if ($vid !== '') {
            $index['vid:'.$vid] = $event;
        }
        $index['fb:'.$this->eventFallbackKey($event)] = $event;
        $index['pair:'.$event->person_key.'|'.(string) $event->hazard_boundary_id] = $event;
    }

    /**
     * @param  array<string, IscBoundaryEvent>  $index
     * @param  array<string, mixed>  $row
     */
    private function findExisting(array $index, array $row): ?IscBoundaryEvent
    {
        $vid = (string) ($row['besigma_violation_id'] ?? '');
        if ($vid !== '' && isset($index['vid:'.$vid])) {
            return $index['vid:'.$vid];
        }
        $fallback = (string) ($row['dedup_key'] ?? '');
        if ($fallback !== '' && isset($index['fb:'.$fallback])) {
            return $index['fb:'.$fallback];
        }
        $pair = (string) ($row['person_key'] ?? '').'|'.(string) ($row['hazard_boundary_id'] ?? '');

        return $index['pair:'.$pair] ?? null;
    }

    private function eventFallbackKey(IscBoundaryEvent $event): string
    {
        $entity = (string) ($event->entity ?: 'person');
        $subject = $entity === 'unit'
            ? (string) ($event->unit_id ?: $event->person_key)
            : (string) ($event->user_id ?: $event->person_key);

        return $entity.'|'.$subject.'|'.(string) $event->hazard_boundary_id;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function refreshEvent(IscBoundaryEvent $event, array $row): void
    {
        $event->name = (string) $row['name'];
        $event->sid = $row['sid'];
        $event->company = $row['company'];
        $event->job_title = $row['job_title'];
        $event->iupk_site = $row['iupk_site'];
        $event->hazard_name = $row['hazard_name'];
        $event->hazard_kind = $row['hazard_kind'];
        $event->besigma_status = $row['besigma_status'];
        $event->entity = $row['entity'];
        if ($row['besigma_violation_id'] !== null) {
            $event->besigma_violation_id = $row['besigma_violation_id'];
        }
        if ($row['user_id'] !== null) {
            $event->user_id = $row['user_id'];
        }
        if ($row['unit_id'] !== null) {
            $event->unit_id = $row['unit_id'];
        }
        if ($row['lat'] !== null) {
            $event->lat = $row['lat'];
        }
        if ($row['lng'] !== null) {
            $event->lng = $row['lng'];
        }
        $event->exited_at = null;
        $event->save();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function createPayload(array $row): array
    {
        return [
            'person_key' => $row['person_key'],
            'entity' => $row['entity'],
            'sid' => $row['sid'],
            'name' => $row['name'],
            'company' => $row['company'],
            'job_title' => $row['job_title'],
            'lat' => $row['lat'],
            'lng' => $row['lng'],
            'iupk_site' => $row['iupk_site'],
            'hazard_boundary_id' => $row['hazard_boundary_id'],
            'hazard_name' => $row['hazard_name'],
            'entered_at' => $row['entered_at'] ?? now(),
            'status' => 'open',
            'rule_code' => $this->activeRuleCode(),
            'besigma_violation_id' => $row['besigma_violation_id'],
            'user_id' => $row['user_id'],
            'unit_id' => $row['unit_id'],
            'hazard_kind' => $row['hazard_kind'],
            'besigma_status' => $row['besigma_status'],
        ];
    }

    private function activeRuleCode(): string
    {
        if (! IscSchema::rulesReady()) {
            return self::RULE_CODE;
        }
        $rule = IscDetectionRule::query()->where('is_active', true)->orderBy('id')->first();

        return $rule?->code ?: self::RULE_CODE;
    }

    private function parseTime(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return now();
        }
        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return now();
        }
    }

    private function coord(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $number = (float) $value;
        if ($number == 0.0) {
            return null;
        }

        return $number;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
