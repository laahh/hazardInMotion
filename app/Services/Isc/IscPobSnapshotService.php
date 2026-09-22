<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Actions\Isc\IscPobClassifyAction;
use App\Actions\Isc\IscRfidReconcileAction;
use App\Models\Isc\IscBoundaryEvent;
use App\Models\Isc\IscDetectionRule;
use Illuminate\Support\Carbon;
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
        private readonly IscSiteNormalizer $sites,
        private readonly IscBesigmaViolationReader $violations,
        private readonly IscBesigmaInstalledUsersService $installedUsers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(bool $fresh = false, bool $demo = true): array
    {
        $key = $demo ? 'isc.pob.snapshot.demo.v6' : 'isc.pob.snapshot.live.v6';
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 10, function () use ($demo): array {
            if ($demo) {
                return $this->buildDemo();
            }

            // Samakan bootstrap dengan /besigma/connection-test sebelum baca live.
            $this->boundaries->prepareLikeConnectionTest();

            return $this->buildLive();
        });
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
        $classified = $this->decoratePeople($classified, $hazards);

        $ever = $this->demo->everIdentifiers();
        // "GPS aktif" (current_count) disamakan dengan "Dalam konsesi"
        // (summary.in): sama-sama orang, bukan roster-only, presence "in" —
        // lihat catatan yang sama di buildLive().
        $current = array_values(array_filter(
            $classified,
            static fn (array $p): bool => trim((string) ($p['sid'] ?? '')) !== ''
                && ($p['entity'] ?? 'person') === 'person'
                && ! ($p['roster_only'] ?? false)
                && ($p['presence'] ?? null) === IscPobClassifyAction::PRESENCE_IN,
        ));
        $rfid = $this->demo->rfidOnsite();
        $checkins = $this->normalizeCheckins($rfid);
        $reconcile = $this->reconcile->execute($ever, $current, $rfid);
        unset($reconcile['ever'], $reconcile['current'], $reconcile['rfid']);
        $reconcile = $this->applyGapRfidTanpaGps($reconcile, $checkins, $classified);
        $reconcile['installed_total'] = $this->installedUsers->total();

        return [
            'source' => 'demo',
            'generated_at' => now()->toIso8601String(),
            'stale_gps_seconds' => $stale,
            'besigma_connected' => false,
            'besigma_error' => 'Mode dummy: polygon bahaya preview, bukan live Besigma.',
            'rfid_available' => true,
            'summary' => $this->summarize($classified, $checkins),
            'sites' => $this->sites->catalog(),
            'checkins' => $checkins,
            'reconcile' => array_merge($reconcile, ['rfid_available' => true]),
            'people' => $classified,
            'hazard_features' => $hazards,
        ];
    }

    /**
     * "RFID tanpa GPS" = check-in RFID hari itu yang SID-nya BUKAN sedang
     * berada di dalam boundary IUPK (bukan lagi diturunkan dari "pernah
     * terlihat Besigma di mana saja", yang sebelumnya salah selalu 0 karena
     * SID check-in RFID ikut dimasukkan ke set "ever" itu sendiri).
     *
     * @param  array<string, mixed>  $reconcile
     * @param  list<array<string, mixed>>  $checkins
     * @param  list<array<string, mixed>>  $classified
     * @return array<string, mixed>
     */
    private function applyGapRfidTanpaGps(array $reconcile, array $checkins, array $classified): array
    {
        // Angka headline = pengurangan langsung: total check-in RFID hari itu
        // dikurangi "GPS aktif" (current_count, sudah disamakan dengan
        // "Dalam konsesi") — sengaja arithmetic sederhana (bukan irisan SID
        // presisi) supaya konsisten kelihatan di mata dengan dua angka lain
        // yang ditampilkan berdampingan di kartu yang sama.
        $reconcile['gap_rfid_minus_besigma_count'] = max(0, count($checkins) - (int) ($reconcile['current_count'] ?? 0));

        // Daftar drill-down tetap dari irisan SID asli (checkin yang SID-nya
        // BUKAN sedang di dalam boundary) — panjangnya bisa sedikit beda dari
        // angka headline di atas (selisih maksimal sebesar GPS aktif), tapi
        // tetap daftar nama yang paling masuk akal untuk "Lihat daftar".
        $inSids = [];
        foreach ($classified as $person) {
            $isHudPerson = ($person['entity'] ?? 'person') === 'person' && ! ($person['roster_only'] ?? false);
            if (! $isHudPerson || ($person['presence'] ?? null) !== IscPobClassifyAction::PRESENCE_IN) {
                continue;
            }
            $sid = trim((string) ($person['sid'] ?? ''));
            if ($sid !== '') {
                $inSids[mb_strtoupper($sid)] = true;
            }
        }

        $withoutGps = [];
        foreach ($checkins as $row) {
            $sid = trim((string) ($row['sid'] ?? ''));
            if ($sid === '' || isset($inSids[mb_strtoupper($sid)])) {
                continue;
            }
            $withoutGps[] = [
                'sid' => $sid,
                'name' => $row['name'] ?? $sid,
                'company' => $row['company'] ?? null,
                'site_code' => $row['site_code'] ?? null,
                'checked_in_at' => $row['checked_in_at'] ?? null,
            ];
        }

        $reconcile['gap_rfid_minus_besigma'] = array_slice($withoutGps, 0, IscRfidReconcileAction::LIST_LIMIT);

        return $reconcile;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLive(): array
    {
        if (! $this->boundaries->isUp()) {
            $demo = $this->buildDemo();
            $demo['besigma_error'] = $demo['besigma_error'] ?? 'besigma_db tidak terhubung';

            return $demo;
        }

        $stale = $this->staleGpsSeconds();
        $people = $this->gps->latest();
        $iupk = $this->iupk->featureCollection();
        $hazardPack = $this->liveHazards();
        $classified = $this->classify->execute($people, $iupk, $hazardPack['features'], $stale);

        $peopleViolations = $this->violations->people();
        $unitViolations = $this->violations->units();
        $classified = $this->applyViolations($classified, $peopleViolations, $unitViolations);
        $classified = $this->attachOpenEvents($classified);
        $classified = $this->decoratePeople($classified, $hazardPack['features']);

        $rfidPack = $this->rfid->onsiteTodayAll();
        $checkins = $this->normalizeCheckins($rfidPack['people']);
        // "ever" = SID yang genuinely terlihat Besigma hari ini (GPS +
        // pelanggaran — $classified sudah menggabungkan keduanya lewat
        // applyViolations() di atas). Diambil LANGSUNG dari $classified,
        // BUKAN lagi re-lookup ke besigma_db.users.sid_code lewat
        // identifiersBySids(): re-lookup itu mencocokkan HANYA kolom
        // sid_code, padahal $person['sid'] bisa fallback ke NIK/NPK kalau
        // sid_code kosong (lihat IscPersonnelGpsReader::sidFromUser()) —
        // jadi orang yang SID-nya dari NIK/NPK diam-diam hilang dari "ever",
        // dan "Keduanya cocok" (both_count) jadi under-count walau orang itu
        // benar-benar terlacak Besigma hari itu. Juga TANPA ikut SID
        // check-in RFID sendiri (lihat catatan lama: kalau ikut disatukan,
        // both_count selalu = total check-in RFID, matched 100% palsu).
        $ever = array_values(array_filter(
            $classified,
            static fn (array $p): bool => trim((string) ($p['sid'] ?? '')) !== '' && ($p['entity'] ?? 'person') === 'person',
        ));
        // "GPS aktif" (current_count) disamakan dengan "Dalam konsesi"
        // (summary.in): orang, bukan roster-only, dan presence "in" — bukan
        // lagi soal stale/tidaknya GPS (itu konsep terpisah dari "di dalam
        // boundary sekarang").
        $current = array_values(array_filter(
            $classified,
            static fn (array $p): bool => trim((string) ($p['sid'] ?? '')) !== ''
                && ($p['entity'] ?? 'person') === 'person'
                && ! ($p['roster_only'] ?? false)
                && ($p['presence'] ?? null) === IscPobClassifyAction::PRESENCE_IN,
        ));
        $reconcile = $this->reconcile->execute($ever, $current, $rfidPack['people']);
        unset($reconcile['ever'], $reconcile['current'], $reconcile['rfid']);
        $reconcile = $this->applyGapRfidTanpaGps($reconcile, $checkins, $classified);
        $reconcile['installed_total'] = $this->installedUsers->total();

        $kindCounts = [
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER => 0,
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE => 0,
            IscHazardBoundaryClassifier::KIND_UNIT_DANGER => count($unitViolations),
        ];
        foreach ($peopleViolations as $row) {
            $kind = (string) ($row['hazard_kind'] ?? IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER);
            if (! isset($kindCounts[$kind])) {
                $kind = IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER;
            }
            $kindCounts[$kind]++;
        }

        return [
            'source' => 'live',
            'generated_at' => now()->toIso8601String(),
            'stale_gps_seconds' => $stale,
            'besigma_connected' => true,
            'besigma_error' => $hazardPack['error'],
            'rfid_available' => $rfidPack['available'],
            'summary' => $this->summarize($classified, $checkins, $kindCounts),
            'sites' => $this->sites->catalog(),
            'checkins' => $checkins,
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
                $kind = $this->hazard->kind($props);
                $props['hazard_kind'] = $kind;
                $props['hazard_kind_label'] = $this->hazard->label($kind);
                $feature['properties'] = $props;
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
     * @param  list<array<string, mixed>>  $classified
     * @param  list<array<string, mixed>>  $peopleViolations
     * @param  list<array<string, mixed>>  $unitViolations
     * @return list<array<string, mixed>>
     */
    private function applyViolations(array $classified, array $peopleViolations, array $unitViolations): array
    {
        $byUser = [];
        $bySid = [];
        foreach ($peopleViolations as $row) {
            $userId = trim((string) ($row['user_id'] ?? ''));
            if ($userId !== '') {
                $byUser[$userId] = $row;
            }
            $sid = mb_strtoupper(trim((string) ($row['sid'] ?? '')));
            if ($sid !== '') {
                $bySid[$sid] = $row;
            }
        }

        $seenUsers = [];
        $seenSids = [];
        foreach ($classified as $i => $person) {
            $userId = trim((string) ($person['user_id'] ?? ''));
            $sid = mb_strtoupper(trim((string) ($person['sid'] ?? '')));
            $classified[$i]['entity'] = 'person';
            $classified[$i]['roster_only'] = false;
            $classified[$i]['from_violation'] = false;
            $violation = null;
            if ($userId !== '' && isset($byUser[$userId])) {
                $violation = $byUser[$userId];
            } elseif ($sid !== '' && isset($bySid[$sid])) {
                $violation = $bySid[$sid];
            }
            if ($violation === null) {
                continue;
            }
            $vUser = trim((string) ($violation['user_id'] ?? $userId));
            if ($vUser !== '') {
                $seenUsers[$vUser] = true;
            }
            foreach ([$sid, mb_strtoupper(trim((string) ($violation['sid'] ?? '')))] as $markSid) {
                if ($markSid !== '') {
                    $seenSids[$markSid] = true;
                }
            }
            $classified[$i] = $this->overlayViolationOnPerson($person, $violation);
        }

        foreach ($peopleViolations as $row) {
            $userId = trim((string) ($row['user_id'] ?? ''));
            $sid = mb_strtoupper(trim((string) ($row['sid'] ?? '')));
            if (($userId !== '' && isset($seenUsers[$userId])) || ($sid !== '' && isset($seenSids[$sid]))) {
                continue;
            }
            $classified[] = $this->personFromViolation($row);
        }

        foreach ($unitViolations as $row) {
            $classified[] = $this->unitFromViolation($row);
        }

        return $classified;
    }

    /**
     * @param  array<string, mixed>  $person
     * @param  array<string, mixed>  $violation
     * @return array<string, mixed>
     */
    private function overlayViolationOnPerson(array $person, array $violation): array
    {
        $kind = (string) ($violation['hazard_kind'] ?? IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER);
        $wasIn = ($person['presence'] ?? '') === IscPobClassifyAction::PRESENCE_IN;
        $person['safety'] = IscPobClassifyAction::SAFETY_UNSAFE;
        $person['from_violation'] = true;
        $person['entity'] = 'person';
        $person['roster_only'] = ! $wasIn;
        $person['hazard_kind'] = $kind;
        $person['hazard_kind_label'] = $this->hazard->label($kind);
        $person['hazard_boundary_id'] = $violation['boundary_id'] ?? $person['hazard_boundary_id'] ?? null;
        $person['hazard_name'] = $violation['hazard_name'] ?? $person['hazard_name'] ?? null;
        $person['entered_at'] = $violation['entered_at'] ?? $person['entered_at'] ?? null;
        $person['besigma_status'] = $violation['status'] ?? $person['besigma_status'] ?? null;
        $person['besigma_violation_id'] = $violation['id'] ?? $person['besigma_violation_id'] ?? null;
        $person['hazard_code'] = $violation['hazard_code'] ?? $person['hazard_code'] ?? null;
        $person['hazard_type'] = $violation['hazard_type'] ?? $person['hazard_type'] ?? null;
        $person['pit_name'] = $violation['pit_name'] ?? $person['pit_name'] ?? null;
        $person['site_label'] = $violation['site_label'] ?? $person['site_label'] ?? null;
        if (($person['site'] ?? null) === null && isset($violation['site'])) {
            $person['site'] = $violation['site'];
        }
        if (! $wasIn) {
            $person['presence'] = IscPobClassifyAction::PRESENCE_IN;
            $person['marker'] = $kind;
        } else {
            $person['marker'] = $kind;
        }
        if (($person['site_code'] ?? null) === null && isset($violation['site_code'])) {
            $person['site_code'] = $violation['site_code'];
        }

        return $person;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function personFromViolation(array $row): array
    {
        $sid = (string) ($row['sid'] ?? '');
        $userId = (string) ($row['user_id'] ?? '');
        $kind = (string) ($row['hazard_kind'] ?? IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER);

        return [
            'key' => $this->gps->personKey($sid, $userId),
            'user_id' => $userId,
            'sid' => $sid,
            'nik' => $row['nik'] ?? null,
            'npk' => $row['npk'] ?? null,
            'name' => (string) ($row['name'] ?? $sid),
            'company' => $row['company'] ?? null,
            'job_title' => $row['job_title'] ?? null,
            'division' => $row['division'] ?? null,
            'site' => $row['site'] ?? null,
            'site_code' => $row['site_code'] ?? null,
            'lat' => 0.0,
            'lng' => 0.0,
            'gps_updated_at' => null,
            'presence' => IscPobClassifyAction::PRESENCE_IN,
            'safety' => IscPobClassifyAction::SAFETY_UNSAFE,
            'stale' => true,
            'marker' => 'stale',
            'entity' => 'person',
            'roster_only' => true,
            'from_violation' => true,
            'hazard_kind' => $kind,
            'hazard_kind_label' => $this->hazard->label($kind),
            'hazard_boundary_id' => $row['boundary_id'] ?? null,
            'hazard_name' => $row['hazard_name'] ?? null,
            'entered_at' => $row['entered_at'] ?? null,
            'besigma_status' => $row['status'] ?? null,
            'besigma_violation_id' => $row['id'] ?? null,
            'hazard_code' => $row['hazard_code'] ?? null,
            'hazard_type' => $row['hazard_type'] ?? null,
            'pit_name' => $row['pit_name'] ?? null,
            'site_label' => $row['site_label'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function unitFromViolation(array $row): array
    {
        $unitId = (string) ($row['unit_id'] ?? '');
        $kind = IscHazardBoundaryClassifier::KIND_UNIT_DANGER;

        return [
            'key' => 'unit:'.$unitId,
            'user_id' => null,
            'unit_id' => $unitId,
            'sid' => (string) ($row['sid'] ?? ''),
            'name' => (string) ($row['name'] ?? 'Unit'),
            'company' => $row['company'] ?? null,
            'site_code' => $row['site_code'] ?? null,
            'lat' => 0.0,
            'lng' => 0.0,
            'gps_updated_at' => null,
            'presence' => IscPobClassifyAction::PRESENCE_IN,
            'safety' => IscPobClassifyAction::SAFETY_UNSAFE,
            'stale' => true,
            'marker' => 'stale',
            'entity' => 'unit',
            'roster_only' => true,
            'from_violation' => true,
            'hazard_kind' => $kind,
            'hazard_kind_label' => $this->hazard->label($kind),
            'hazard_boundary_id' => $row['boundary_id'] ?? null,
            'hazard_name' => $row['hazard_name'] ?? null,
            'entered_at' => $row['entered_at'] ?? null,
            'besigma_status' => $row['status'] ?? null,
            'besigma_violation_id' => $row['id'] ?? null,
            'hazard_code' => $row['hazard_code'] ?? null,
            'hazard_type' => $row['hazard_type'] ?? null,
            'pit_name' => $row['pit_name'] ?? null,
            'site_label' => $row['site_label'] ?? null,
        ];
    }

    /**
     * Lengkapi narasi apa/di mana/kapan dari pelanggaran + polygon bahaya.
     *
     * @param  list<array<string, mixed>>  $people
     * @param  list<array<string, mixed>>  $features
     * @return list<array<string, mixed>>
     */
    private function decoratePeople(array $people, array $features): array
    {
        $byId = [];
        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $id = trim((string) ($props['id'] ?? ''));
            if ($id !== '') {
                $byId[$id] = $props;
            }
        }

        foreach ($people as $i => $person) {
            $bid = trim((string) ($person['hazard_boundary_id'] ?? ''));
            if ($bid !== '' && isset($byId[$bid])) {
                $props = $byId[$bid];
                if (($person['hazard_activity'] ?? null) === null) {
                    $people[$i]['hazard_activity'] = $props['aktivitas'] ?? $props['activity'] ?? null;
                }
                if (($people[$i]['pit_name'] ?? null) === null) {
                    $people[$i]['pit_name'] = $props['pit_name'] ?? null;
                }
                if (($people[$i]['hazard_type'] ?? null) === null) {
                    $people[$i]['hazard_type'] = $props['type'] ?? null;
                }
                if (($people[$i]['site_label'] ?? null) === null) {
                    $people[$i]['site_label'] = $props['site_label'] ?? null;
                }
            }
            if (($people[$i]['duration_seconds'] ?? null) === null) {
                $people[$i]['duration_seconds'] = $this->secondsSince($people[$i]['entered_at'] ?? null);
            }
            $people[$i]['violation_action'] = $this->violationAction($people[$i]);
        }

        return $people;
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private function violationAction(array $person): ?string
    {
        $unsafe = ($person['safety'] ?? null) === IscPobClassifyAction::SAFETY_UNSAFE || ($person['from_violation'] ?? false);
        if (! $unsafe) {
            return null;
        }
        $kind = (string) ($person['hazard_kind_label'] ?? 'zona berbahaya');
        $zone = (string) ($person['hazard_name'] ?? '');
        $who = ($person['entity'] ?? 'person') === 'unit' ? 'Unit berada di' : 'Masuk';
        $parts = [$who.' '.$kind];
        if ($zone !== '') {
            $parts[] = 'zona '.$zone;
        }
        $activity = trim((string) ($person['hazard_activity'] ?? ''));
        if ($activity !== '') {
            $parts[] = 'aktivitas '.$activity;
        }
        $job = trim((string) ($person['job_title'] ?? ''));
        if ($job !== '' && ($person['entity'] ?? 'person') !== 'unit') {
            $parts[] = 'sebagai '.$job;
        }

        return implode(' · ', $parts);
    }

    private function secondsSince(mixed $at): ?int
    {
        if ($at === null || $at === '') {
            return null;
        }
        try {
            return max(0, Carbon::parse((string) $at)->diffInSeconds(now()));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @param  list<array<string, mixed>>  $checkins
     * @param  array<string, int>|null  $kindCounts
     * @return array<string, mixed>
     */
    private function summarize(array $people, array $checkins, ?array $kindCounts = null): array
    {
        $in = $out = $unknown = $safe = $unsafe = $traced = 0;
        $unsafeByKind = [
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER => 0,
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE => 0,
            IscHazardBoundaryClassifier::KIND_UNIT_DANGER => 0,
        ];
        $inBySite = $this->emptySiteCounts();
        $checkinBySite = $this->emptySiteCounts();

        foreach ($people as $person) {
            $isHudPerson = ($person['entity'] ?? 'person') === 'person' && ! ($person['roster_only'] ?? false);
            $presence = (string) ($person['presence'] ?? 'unknown');
            $code = (string) ($person['site_code'] ?? '');
            $hasGps = (float) ($person['lat'] ?? 0) != 0.0 && (float) ($person['lng'] ?? 0) != 0.0;
            if ($isHudPerson && $hasGps) {
                $traced++;
            }
            if ($isHudPerson && $presence === IscPobClassifyAction::PRESENCE_IN) {
                $in++;
                if ($code !== '' && isset($inBySite[$code])) {
                    $inBySite[$code]++;
                }
                if (($person['safety'] ?? null) === IscPobClassifyAction::SAFETY_UNSAFE) {
                    $unsafe++;
                } else {
                    $safe++;
                }
            } elseif ($isHudPerson && $presence === IscPobClassifyAction::PRESENCE_OUT) {
                $out++;
            } elseif ($isHudPerson) {
                $unknown++;
            }

            if ($kindCounts === null && ($person['safety'] ?? null) === IscPobClassifyAction::SAFETY_UNSAFE) {
                $kind = (string) ($person['hazard_kind'] ?? IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER);
                if (! isset($unsafeByKind[$kind])) {
                    $kind = IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER;
                }
                $unsafeByKind[$kind]++;
            }
        }

        if ($kindCounts !== null) {
            foreach ($unsafeByKind as $kind => $_) {
                $unsafeByKind[$kind] = (int) ($kindCounts[$kind] ?? 0);
            }
        }

        foreach ($checkins as $row) {
            $code = (string) ($row['site_code'] ?? '');
            if ($code !== '' && isset($checkinBySite[$code])) {
                $checkinBySite[$code]++;
            }
        }

        return [
            'total' => count($people),
            'in' => $in,
            'out' => $out,
            'unknown' => $unknown,
            'traced' => $traced,
            'safe' => $safe,
            'unsafe' => $unsafe,
            'unsafe_by_kind' => $unsafeByKind,
            'checkin_total' => count($checkins),
            'checkin_by_site' => $this->siteRows($checkinBySite),
            'in_by_site' => $this->siteRows($inBySite),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeCheckins(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $code = $this->sites->codeFrom($row['gate'] ?? null, $row['site'] ?? null, $row['site_code'] ?? null);
            $sid = trim((string) ($row['sid'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $out[] = [
                'sid' => $sid,
                'name' => $name !== '' ? $name : ($sid !== '' ? $sid : 'Personel'),
                'company' => $row['company'] ?? null,
                'gate' => $row['gate'] ?? null,
                'site_code' => $code,
                'site_label' => $code !== null ? $this->sites->label($code) : null,
                'checked_in_at' => $row['checked_in_at'] ?? null,
                'key' => $sid !== '' ? 'sid:'.mb_strtoupper($sid) : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function emptySiteCounts(): array
    {
        $counts = [];
        foreach (IscSiteNormalizer::SITES as $code => $_label) {
            $counts[$code] = 0;
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{code:string,label:string,count:int}>
     */
    private function siteRows(array $counts): array
    {
        $rows = [];
        foreach (IscSiteNormalizer::SITES as $code => $label) {
            $rows[] = [
                'code' => $code,
                'label' => $label,
                'count' => $counts[$code] ?? 0,
            ];
        }

        return $rows;
    }
}
