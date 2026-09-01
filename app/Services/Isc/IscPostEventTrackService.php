<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Services\Besigma\BesigmaConnectionService;
use App\Services\Besigma\BesigmaTunnelService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Jejak GPS post-event: orang/unit yang bergerak di satu hari, dari titik ke titik.
 */
final class IscPostEventTrackService
{
    public const CONNECTION = 'besigma_db';

    public const ROSTER_LIMIT = 200;

    public const PEOPLE_LIMIT = 120;

    public const UNIT_LIMIT = 80;

    public const TRAIL_RAW_LIMIT = 4000;

    public const TRAIL_MAX_POINTS = 400;

    public const MIN_MOVE_METERS = 18.0;

    public function __construct(
        private readonly BesigmaConnectionService $connection,
        private readonly BesigmaTunnelService $tunnel,
        private readonly IscPersonnelGpsReader $gps,
        private readonly IscPobDemoDataset $demo,
        private readonly IscSiteNormalizer $sites,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function roster(string $date, string $query = '', bool $demo = false): array
    {
        if ($demo || ! $this->isUp()) {
            return $this->demoRoster($date, $query, $demo || ! $this->isUp());
        }

        $from = IscPersonnelGpsReader::dayStart($date);
        $to = IscPersonnelGpsReader::dayEndExclusive($date);
        $search = trim($query);
        $people = $this->peopleRoster($from, $to, $search);
        $byId = [];
        foreach ($people as $row) {
            $byId[(string) $row['user_id']] = $row;
        }
        foreach ($this->peopleViolationsOnDay($from, $to, $search) as $row) {
            $userId = (string) ($row['user_id'] ?? '');
            if ($userId === '') {
                continue;
            }
            if (isset($byId[$userId])) {
                $byId[$userId]['entered'] = true;
                $byId[$userId]['hazard_name'] = $row['hazard_name'] ?? $byId[$userId]['hazard_name'];
                $byId[$userId]['hazard_kind'] = $row['hazard_kind'] ?? $byId[$userId]['hazard_kind'];
                continue;
            }
            $byId[$userId] = $this->personCardFromViolation($row);
        }

        $units = [];
        foreach ($this->unitsRoster($from, $to, $search) as $row) {
            $units[(string) $row['unit_id']] = $row;
        }
        foreach ($this->unitViolationsOnDay($from, $to, $search) as $row) {
            $unitId = (string) ($row['unit_id'] ?? '');
            if ($unitId === '') {
                continue;
            }
            if (isset($units[$unitId])) {
                $units[$unitId]['entered'] = true;
                $units[$unitId]['hazard_name'] = $row['hazard_name'] ?? $units[$unitId]['hazard_name'];
                continue;
            }
            $units[$unitId] = $this->unitCardFromViolation($row);
        }

        $entries = array_values(array_merge($byId, $units));
        usort($entries, static function (array $a, array $b): int {
            $kind = ((string) ($b['entity'] ?? '') === 'unit' ? 1 : 0) <=> ((string) ($a['entity'] ?? '') === 'unit' ? 1 : 0);
            if ($kind !== 0) {
                return $kind;
            }
            $entered = ((int) ($b['entered'] ?? false)) <=> ((int) ($a['entered'] ?? false));
            if ($entered !== 0) {
                return $entered;
            }

            return strcmp((string) ($b['last_at'] ?? ''), (string) ($a['last_at'] ?? ''));
        });
        $entries = array_slice($entries, 0, self::ROSTER_LIMIT);
        $peopleCount = count(array_filter($entries, static fn (array $row): bool => ($row['entity'] ?? '') !== 'unit'));
        $unitCount = count($entries) - $peopleCount;

        return [
            'source' => 'live',
            'date' => $date,
            'query' => $query,
            'count' => count($entries),
            'people_count' => $peopleCount,
            'unit_count' => $unitCount,
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trail(string $entity, string $id, string $date, bool $demo = false): array
    {
        if ($demo || ! $this->isUp()) {
            return $this->demoTrail($entity, $id, $date);
        }

        $from = IscPersonnelGpsReader::dayStart($date);
        $to = IscPersonnelGpsReader::dayEndExclusive($date);
        $points = $entity === 'unit'
            ? $this->unitTrailPoints($id, $from, $to)
            : $this->personTrailPoints($id, $from, $to);
        $thinned = self::downsample($points, self::TRAIL_MAX_POINTS, self::MIN_MOVE_METERS);

        return [
            'source' => 'live',
            'entity' => $entity === 'unit' ? 'unit' : 'person',
            'id' => $id,
            'date' => $date,
            'point_count' => count($thinned),
            'raw_point_count' => count($points),
            'points' => $thinned,
        ];
    }

    /**
     * @param  list<array{lat:float,lng:float,at:?string}>  $points
     * @return list<array{lat:float,lng:float,at:?string}>
     */
    public static function downsample(array $points, int $maxPoints = self::TRAIL_MAX_POINTS, float $minMeters = self::MIN_MOVE_METERS): array
    {
        if ($points === []) {
            return [];
        }
        $kept = [$points[0]];
        $last = $points[0];
        $end = count($points) - 1;
        for ($i = 1; $i < $end; $i++) {
            $row = $points[$i];
            if (self::haversineMeters($last['lat'], $last['lng'], $row['lat'], $row['lng']) < $minMeters) {
                continue;
            }
            $kept[] = $row;
            $last = $row;
        }
        $final = $points[$end];
        if ($kept[count($kept) - 1] !== $final) {
            $kept[] = $final;
        }
        $n = count($kept);
        if ($n <= $maxPoints) {
            return $kept;
        }
        $step = (int) ceil($n / $maxPoints);
        $out = [];
        for ($i = 0; $i < $n; $i += $step) {
            $out[] = $kept[$i];
        }
        if ($out[count($out) - 1] !== $kept[$n - 1]) {
            $out[] = $kept[$n - 1];
        }

        return $out;
    }

    private function isUp(): bool
    {
        $this->tunnel->applyRuntimeConfig();

        return $this->connection->isUp();
    }

    /**
     * Roster orang: cari user dulu, jangan scan seluruh user_gps_logs harian (itu yang 504).
     *
     * @return list<array<string, mixed>>
     */
    private function peopleRoster(string $from, string $to, string $search): array
    {
        $candidates = $search !== ''
            ? $this->findUsers($search)
            : $this->peopleFromLatests($from, $to);
        if ($candidates === []) {
            return [];
        }

        return $candidates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unitsRoster(string $from, string $to, string $search): array
    {
        $candidates = $search !== ''
            ? $this->findUnits($search)
            : $this->unitsFromLatests($from, $to);
        if ($candidates === []) {
            return [];
        }

        return $candidates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findUsers(string $search): array
    {
        try {
            $like = '%'.$this->likeNeedle($search).'%';
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT
                    u.id AS user_id,
                    u.sid_code,
                    u.fullname,
                    u.npk,
                    u.dedicated_site,
                    u.site_assignment,
                    u.functional_position,
                    c.name AS company_name
                FROM users u
                LEFT JOIN companies c ON c.id = u.company_id
                WHERE u.is_deleted = 0
                  AND (
                    u.fullname LIKE ?
                    OR u.sid_code LIKE ?
                    OR u.npk LIKE ?
                    OR u.nik LIKE ?
                  )
                ORDER BY u.fullname ASC
                LIMIT 40
            ", [$like, $like, $like, $like]);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return array_map(function (object $row): array {
            $card = $this->personCardFromUserRow($row);
            $card['has_trail'] = true;

            return $card;
        }, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function peopleFromLatests(string $from, string $to): array
    {
        try {
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT
                    g.user_id,
                    g.updated_at AS last_at,
                    u.sid_code,
                    u.fullname,
                    u.npk,
                    u.dedicated_site,
                    u.site_assignment,
                    u.functional_position,
                    c.name AS company_name
                FROM user_gps_latests g
                INNER JOIN users u ON u.id = g.user_id AND u.is_deleted = 0
                LEFT JOIN companies c ON c.id = u.company_id
                WHERE g.updated_at >= ?
                  AND g.updated_at < ?
                  AND g.latitude IS NOT NULL
                  AND g.longitude IS NOT NULL
                ORDER BY g.updated_at DESC
                LIMIT ".self::PEOPLE_LIMIT."
            ", [$from, $to]);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $card = $this->personCardFromUserRow($row);
            $card['last_at'] = isset($row->last_at) ? (string) $row->last_at : null;
            $card['first_at'] = $card['last_at'];
            $card['has_trail'] = true;
            $out[] = $card;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findUnits(string $search): array
    {
        $byId = [];
        foreach ($this->findUnitsFromMaster($search) as $row) {
            $unitId = (string) ($row['unit_id'] ?? '');
            if ($unitId === '') {
                continue;
            }
            $byId[$unitId] = $row;
        }
        foreach ($this->findUnitsFromLatests($search) as $row) {
            $unitId = (string) ($row['unit_id'] ?? '');
            if ($unitId === '') {
                continue;
            }
            if (isset($byId[$unitId])) {
                $byId[$unitId]['has_trail'] = true;
                $byId[$unitId]['last_at'] = $row['last_at'] ?? $byId[$unitId]['last_at'];
                $byId[$unitId]['first_at'] = $row['first_at'] ?? $byId[$unitId]['first_at'];
                continue;
            }
            $byId[$unitId] = $row;
        }

        return array_values($byId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findUnitsFromMaster(string $search): array
    {
        try {
            $like = '%'.$this->likeNeedle($search).'%';
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT id AS unit_id, vehicle_number, vehicle_name, vendor_name
                FROM units
                WHERE vehicle_number LIKE ?
                   OR vehicle_name LIKE ?
                   OR vendor_name LIKE ?
                ORDER BY vehicle_number ASC
                LIMIT 40
            ", [$like, $like, $like]);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return array_map(fn (object $row): array => $this->unitCardFromRow($row, false), $rows);
    }

    /**
     * Unit yang GPS terakhirnya masih di tanggal jejak. Satu baris per unit.
     *
     * @return list<array<string, mixed>>
     */
    private function unitsFromLatests(string $from, string $to): array
    {
        $rows = $this->selectUnitLatests(
            "
                SELECT
                    {$this->unitLatestIdSql()} AS unit_id,
                    g.updated_at AS last_at,
                    g.vehicle_number,
                    g.vehicle_name,
                    g.vendor_name,
                    g.vehicle_type
                FROM unit_gps_latests g
                WHERE g.updated_at >= ?
                  AND g.updated_at < ?
                  AND g.latitude IS NOT NULL
                  AND g.longitude IS NOT NULL
                ORDER BY g.updated_at DESC
                LIMIT ".self::UNIT_LIMIT."
            ",
            [$from, $to],
        );

        return $this->unitCardsFromGpsRows($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findUnitsFromLatests(string $search): array
    {
        $like = '%'.$this->likeNeedle($search).'%';
        $rows = $this->selectUnitLatests(
            "
                SELECT
                    {$this->unitLatestIdSql()} AS unit_id,
                    g.updated_at AS last_at,
                    g.vehicle_number,
                    g.vehicle_name,
                    g.vendor_name,
                    g.vehicle_type
                FROM unit_gps_latests g
                WHERE g.latitude IS NOT NULL
                  AND g.longitude IS NOT NULL
                  AND (
                    g.vehicle_number LIKE ?
                    OR g.vehicle_name LIKE ?
                    OR g.vendor_name LIKE ?
                    OR CAST(g.unit_id AS CHAR) LIKE ?
                  )
                ORDER BY g.updated_at DESC
                LIMIT 40
            ",
            [$like, $like, $like, $like],
        );

        return $this->unitCardsFromGpsRows($rows);
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<object>
     */
    private function selectUnitLatests(string $sql, array $bindings): array
    {
        try {
            return DB::connection(self::CONNECTION)->select($sql, $bindings);
        } catch (Throwable $e) {
            $fallbackSql = str_replace($this->unitLatestIdSql(), 'g.unit_id', $sql);
            $fallbackSql = str_replace(', g.vehicle_type', '', $fallbackSql);
            try {
                return DB::connection(self::CONNECTION)->select($fallbackSql, $bindings);
            } catch (Throwable $inner) {
                report($inner);

                return [];
            }
        }
    }

    private function unitLatestIdSql(): string
    {
        return "COALESCE(NULLIF(CAST(g.unit_id AS CHAR), ''), CAST(g.integration_id AS CHAR))";
    }

    /**
     * @param  list<object>  $rows
     * @return list<array<string, mixed>>
     */
    private function unitCardsFromGpsRows(array $rows): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $card = $this->unitCardFromRow($row, true);
            $unitId = (string) ($card['unit_id'] ?? '');
            if ($unitId === '' || isset($seen[$unitId])) {
                continue;
            }
            $seen[$unitId] = true;
            $card['last_at'] = isset($row->last_at) ? (string) $row->last_at : null;
            $card['first_at'] = $card['last_at'];
            $out[] = $card;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function unitCardFromRow(object $row, bool $hasTrail): array
    {
        $unitId = (string) ($row->unit_id ?? '');
        $plate = trim((string) ($row->vehicle_number ?? ''));
        $name = $plate !== '' ? $plate : trim((string) ($row->vehicle_name ?? 'Unit'));

        return [
            'entity' => 'unit',
            'id' => $unitId,
            'unit_id' => $unitId,
            'key' => 'unit:'.$unitId,
            'sid' => $plate,
            'name' => $name !== '' ? $name : ('Unit '.substr($unitId, 0, 8)),
            'company' => $this->nullableString($row->vendor_name ?? null),
            'job_title' => $this->nullableString($row->vehicle_type ?? $row->vehicle_name ?? null),
            'site' => null,
            'site_code' => null,
            'point_count' => 0,
            'first_at' => null,
            'last_at' => null,
            'entered' => false,
            'hazard_name' => null,
            'hazard_kind' => IscHazardBoundaryClassifier::KIND_UNIT_DANGER,
            'has_trail' => $hasTrail,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personCardFromUserRow(object $row): array
    {
        $userId = (string) ($row->user_id ?? '');
        $sid = trim((string) ($row->sid_code ?? ''));
        $name = trim((string) ($row->fullname ?? ''));

        return [
            'entity' => 'person',
            'id' => $userId,
            'user_id' => $userId,
            'key' => $this->gps->personKey($sid, $userId),
            'sid' => $sid,
            'name' => $name !== '' ? $name : ($sid !== '' ? $sid : 'User '.substr($userId, 0, 8)),
            'company' => $this->nullableString($row->company_name ?? null),
            'job_title' => $this->nullableString($row->functional_position ?? null),
            'site' => $this->nullableString($row->dedicated_site ?? $row->site_assignment ?? null),
            'site_code' => $this->sites->codeFrom(null, $row->dedicated_site ?? null, $row->site_assignment ?? null),
            'point_count' => 0,
            'first_at' => null,
            'last_at' => null,
            'entered' => false,
            'hazard_name' => null,
            'hazard_kind' => null,
            'has_trail' => false,
        ];
    }

    /**
     * @return list<array{lat:float,lng:float,at:?string}>
     */
    private function personTrailPoints(string $userId, string $from, string $to): array
    {
        try {
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT latitude, longitude, updated_at
                FROM user_gps_logs
                WHERE user_id = ?
                  AND updated_at >= ?
                  AND updated_at < ?
                  AND latitude IS NOT NULL
                  AND longitude IS NOT NULL
                ORDER BY updated_at ASC
                LIMIT ".self::TRAIL_RAW_LIMIT."
            ", [$userId, $from, $to]);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return $this->rowsToPoints($rows);
    }

    /**
     * @return list<array{lat:float,lng:float,at:?string}>
     */
    private function unitTrailPoints(string $unitId, string $from, string $to): array
    {
        try {
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT latitude, longitude, updated_at
                FROM unit_gps_logs
                WHERE (unit_id = ? OR integration_id = ?)
                  AND updated_at >= ?
                  AND updated_at < ?
                  AND latitude IS NOT NULL
                  AND longitude IS NOT NULL
                ORDER BY updated_at ASC
                LIMIT ".self::TRAIL_RAW_LIMIT."
            ", [$unitId, $unitId, $from, $to]);
        } catch (Throwable $e) {
            try {
                $rows = DB::connection(self::CONNECTION)->select("
                    SELECT latitude, longitude, updated_at
                    FROM unit_gps_logs
                    WHERE unit_id = ?
                      AND updated_at >= ?
                      AND updated_at < ?
                      AND latitude IS NOT NULL
                      AND longitude IS NOT NULL
                    ORDER BY updated_at ASC
                    LIMIT ".self::TRAIL_RAW_LIMIT."
                ", [$unitId, $from, $to]);
            } catch (Throwable $inner) {
                report($inner);

                return [];
            }
        }

        return $this->rowsToPoints($rows);
    }

    /**
     * @param  list<object>  $rows
     * @return list<array{lat:float,lng:float,at:?string}>
     */
    private function rowsToPoints(array $rows): array
    {
        $points = [];
        foreach ($rows as $row) {
            $lat = $this->toFloat($row->latitude ?? null);
            $lng = $this->toFloat($row->longitude ?? null);
            if ($lat === null || $lng === null || $lat == 0.0 || $lng == 0.0) {
                continue;
            }
            $points[] = [
                'lat' => $lat,
                'lng' => $lng,
                'at' => isset($row->updated_at) ? (string) $row->updated_at : null,
            ];
        }

        return $points;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function peopleViolationsOnDay(string $from, string $to, string $search = ''): array
    {
        if ($search === '') {
            return [];
        }
        try {
            $like = '%'.$this->likeNeedle($search).'%';
            $bindings = [$from, $to, $like, $like, $like];
            $searchSql = ' AND (u.fullname LIKE ? OR u.sid_code LIKE ? OR u.npk LIKE ?)';
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT
                    v.user_id,
                    v.created_at,
                    v.is_competency,
                    u.sid_code,
                    u.fullname,
                    u.functional_position,
                    u.dedicated_site,
                    c.name AS company_name,
                    b.name AS boundary_name,
                    s.code AS site_code_raw,
                    s.name AS site_name
                FROM boundary_violations v
                INNER JOIN users u ON u.id = v.user_id AND u.is_deleted = 0
                LEFT JOIN companies c ON c.id = u.company_id
                LEFT JOIN boundaries b ON b.id = v.boundary_id
                LEFT JOIN sites s ON s.id = v.site_id
                WHERE v.is_deleted = 0
                  AND v.deleted_at IS NULL
                  AND v.created_at >= ?
                  AND v.created_at < ?
                  {$searchSql}
                ORDER BY v.created_at DESC
                LIMIT ".self::ROSTER_LIMIT."
            ", $bindings);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $byUser = [];
        foreach ($rows as $row) {
            $userId = (string) ($row->user_id ?? '');
            if ($userId === '' || isset($byUser[$userId])) {
                continue;
            }
            $kind = ((int) ($row->is_competency ?? 0)) === 1
                ? IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE
                : IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER;
            $sid = trim((string) ($row->sid_code ?? ''));
            $byUser[$userId] = [
                'user_id' => $userId,
                'sid' => $sid,
                'name' => trim((string) ($row->fullname ?? '')) ?: $sid,
                'company' => $this->nullableString($row->company_name ?? null),
                'job_title' => $this->nullableString($row->functional_position ?? null),
                'site' => $this->nullableString($row->dedicated_site ?? $row->site_name ?? null),
                'site_code' => $this->sites->codeFrom($row->site_code_raw ?? null, $row->site_name ?? null, $row->dedicated_site ?? null),
                'hazard_name' => $this->nullableString($row->boundary_name ?? null),
                'hazard_kind' => $kind,
                'entered_at' => isset($row->created_at) ? (string) $row->created_at : null,
            ];
        }

        return array_values($byUser);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unitViolationsOnDay(string $from, string $to, string $search = ''): array
    {
        if ($search === '') {
            return [];
        }
        try {
            $like = '%'.$this->likeNeedle($search).'%';
            $bindings = [$from, $to, $like, $like, $like];
            $searchSql = ' AND (u.vehicle_number LIKE ? OR u.vehicle_name LIKE ? OR u.vendor_name LIKE ?)';
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT
                    v.unit_id,
                    v.created_at,
                    u.vehicle_number,
                    u.vehicle_name,
                    u.vendor_name,
                    b.name AS boundary_name,
                    s.code AS site_code_raw,
                    s.name AS site_name
                FROM boundary_violation_units v
                INNER JOIN units u ON u.id = v.unit_id
                LEFT JOIN boundaries b ON b.id = v.boundary_id
                LEFT JOIN sites s ON s.id = v.site_id
                WHERE v.is_deleted = 0
                  AND v.deleted_at IS NULL
                  AND v.created_at >= ?
                  AND v.created_at < ?
                  {$searchSql}
                ORDER BY v.created_at DESC
                LIMIT ".self::ROSTER_LIMIT."
            ", $bindings);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $byUnit = [];
        foreach ($rows as $row) {
            $unitId = (string) ($row->unit_id ?? '');
            if ($unitId === '' || isset($byUnit[$unitId])) {
                continue;
            }
            $plate = trim((string) ($row->vehicle_number ?? ''));
            $byUnit[$unitId] = [
                'unit_id' => $unitId,
                'sid' => $plate,
                'name' => $plate !== '' ? $plate : trim((string) ($row->vehicle_name ?? 'Unit')),
                'company' => $this->nullableString($row->vendor_name ?? null),
                'site_code' => $this->sites->codeFrom($row->site_code_raw ?? null, $row->site_name ?? null),
                'hazard_name' => $this->nullableString($row->boundary_name ?? null),
                'entered_at' => isset($row->created_at) ? (string) $row->created_at : null,
            ];
        }

        return array_values($byUnit);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function personCardFromViolation(array $row): array
    {
        $userId = (string) ($row['user_id'] ?? '');
        $sid = (string) ($row['sid'] ?? '');

        return [
            'entity' => 'person',
            'id' => $userId,
            'user_id' => $userId,
            'key' => $this->gps->personKey($sid, $userId),
            'sid' => $sid,
            'name' => (string) ($row['name'] ?? $sid),
            'company' => $row['company'] ?? null,
            'job_title' => $row['job_title'] ?? null,
            'site' => $row['site'] ?? null,
            'site_code' => $row['site_code'] ?? null,
            'point_count' => 0,
            'first_at' => $row['entered_at'] ?? null,
            'last_at' => $row['entered_at'] ?? null,
            'entered' => true,
            'hazard_name' => $row['hazard_name'] ?? null,
            'hazard_kind' => $row['hazard_kind'] ?? null,
            'has_trail' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function unitCardFromViolation(array $row): array
    {
        $unitId = (string) ($row['unit_id'] ?? '');

        return [
            'entity' => 'unit',
            'id' => $unitId,
            'unit_id' => $unitId,
            'key' => 'unit:'.$unitId,
            'sid' => (string) ($row['sid'] ?? ''),
            'name' => (string) ($row['name'] ?? 'Unit'),
            'company' => $row['company'] ?? null,
            'job_title' => null,
            'site' => null,
            'site_code' => $row['site_code'] ?? null,
            'point_count' => 0,
            'first_at' => $row['entered_at'] ?? null,
            'last_at' => $row['entered_at'] ?? null,
            'entered' => true,
            'hazard_name' => $row['hazard_name'] ?? null,
            'hazard_kind' => $row['hazard_kind'] ?? IscHazardBoundaryClassifier::KIND_UNIT_DANGER,
            'has_trail' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function demoRoster(string $date, string $query, bool $fallback): array
    {
        $needle = mb_strtolower(trim($query));
        $entries = [];
        foreach ($this->demo->people() as $person) {
            if (! $this->matchesNeedle($needle, $person['name'] ?? '', $person['sid'] ?? '', $person['company'] ?? '')) {
                continue;
            }
            $entries[] = [
                'entity' => 'person',
                'id' => (string) ($person['user_id'] ?? $person['key']),
                'user_id' => (string) ($person['user_id'] ?? ''),
                'key' => $person['key'],
                'sid' => $person['sid'],
                'name' => $person['name'],
                'company' => $person['company'] ?? null,
                'job_title' => $person['job_title'] ?? null,
                'site' => $person['site'] ?? null,
                'site_code' => $person['site_code'] ?? $person['site'] ?? null,
                'point_count' => 18,
                'first_at' => $date.' 06:10:00',
                'last_at' => $date.' 16:40:00',
                'entered' => in_array($person['sid'] ?? '', ['BC002', 'BC006', 'BC005'], true),
                'hazard_name' => in_array($person['sid'] ?? '', ['BC002', 'BC006'], true) ? 'Zona Peledakan Pit BMO' : null,
                'hazard_kind' => in_array($person['sid'] ?? '', ['BC002', 'BC006'], true)
                    ? IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER
                    : null,
                'has_trail' => true,
            ];
        }
        $demoUnits = [
            ['id' => 'demo-unit-1', 'sid' => 'DT-01', 'name' => 'DT-01', 'company' => 'PT BUMA', 'job_title' => 'Dump truck', 'site' => 'BMO', 'entered' => true, 'hazard_name' => 'Zona Bahaya Unit Pit BMO'],
            ['id' => 'demo-unit-2', 'sid' => 'HD-12', 'name' => 'HD-12', 'company' => 'PT BUMA', 'job_title' => 'Haul dump', 'site' => 'LMO', 'entered' => false, 'hazard_name' => null],
            ['id' => 'demo-unit-3', 'sid' => 'LV-07', 'name' => 'LV-07', 'company' => 'PT Pamapersada', 'job_title' => 'Light vehicle', 'site' => 'GMO', 'entered' => true, 'hazard_name' => 'Zona Bahaya Unit Pit GMO'],
        ];
        foreach ($demoUnits as $unit) {
            if (! $this->matchesNeedle($needle, $unit['name'], $unit['sid'], $unit['company'])) {
                continue;
            }
            $entries[] = [
                'entity' => 'unit',
                'id' => $unit['id'],
                'unit_id' => $unit['id'],
                'key' => 'unit:'.$unit['id'],
                'sid' => $unit['sid'],
                'name' => $unit['name'],
                'company' => $unit['company'],
                'job_title' => $unit['job_title'],
                'site' => $unit['site'],
                'site_code' => $unit['site'],
                'point_count' => 12,
                'first_at' => $date.' 07:00:00',
                'last_at' => $date.' 15:20:00',
                'entered' => $unit['entered'],
                'hazard_name' => $unit['hazard_name'],
                'hazard_kind' => IscHazardBoundaryClassifier::KIND_UNIT_DANGER,
                'has_trail' => true,
            ];
        }

        $peopleCount = count(array_filter($entries, static fn (array $row): bool => ($row['entity'] ?? '') !== 'unit'));

        return [
            'source' => 'demo',
            'date' => $date,
            'query' => $query,
            'count' => count($entries),
            'people_count' => $peopleCount,
            'unit_count' => count($entries) - $peopleCount,
            'fallback' => $fallback,
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function demoTrail(string $entity, string $id, string $date): array
    {
        $origin = [1.95, 117.30];
        foreach ($this->demo->people() as $person) {
            if ((string) ($person['user_id'] ?? '') === $id || (string) ($person['key'] ?? '') === $id || (string) ($person['sid'] ?? '') === $id) {
                $origin = [(float) $person['lat'], (float) $person['lng']];
                break;
            }
        }
        if ($entity === 'unit') {
            $origin = [1.930, 117.250];
        }
        $points = [];
        $steps = 16;
        for ($i = 0; $i < $steps; $i++) {
            $t = $i / max(1, $steps - 1);
            $hour = 6 + (int) floor($t * 10);
            $minute = (int) round(($t * 10 - floor($t * 10)) * 60);
            $points[] = [
                'lat' => $origin[0] + 0.012 * sin($t * 3.1) + 0.004 * $t,
                'lng' => $origin[1] + 0.018 * $t + 0.006 * cos($t * 2.4),
                'at' => sprintf('%s %02d:%02d:00', $date, $hour, min(59, $minute)),
            ];
        }

        return [
            'source' => 'demo',
            'entity' => $entity === 'unit' ? 'unit' : 'person',
            'id' => $id,
            'date' => $date,
            'point_count' => count($points),
            'raw_point_count' => count($points),
            'points' => $points,
        ];
    }

    private function likeNeedle(string $search): string
    {
        return addcslashes($search, '%_\\');
    }

    private function matchesNeedle(string $needle, string $name, string $sid, string $company): bool
    {
        if ($needle === '') {
            return true;
        }
        $hay = mb_strtolower($name.' '.$sid.' '.$company);

        return str_contains($hay, $needle);
    }

    public static function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1.0, sqrt($a)));
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $normalized = str_replace(',', '.', (string) $value);

        return is_numeric($normalized) ? (float) $normalized : null;
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
