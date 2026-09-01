<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Services\Besigma\BesigmaConnectionService;
use App\Services\Besigma\BesigmaTunnelService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Jejak GPS post-event: orang/unit yang bergerak di satu hari, dari titik ke titik.
 */
final class IscPostEventTrackService
{
    public const CONNECTION = 'besigma_db';

    public const ROSTER_LIMIT = 200;

    public const TRAIL_RAW_LIMIT = 4000;

    public const TRAIL_MAX_POINTS = 400;

    public const MIN_MOVE_METERS = 18.0;

    public function __construct(
        private readonly BesigmaConnectionService $connection,
        private readonly BesigmaTunnelService $tunnel,
        private readonly IscPersonnelGpsReader $gps,
        private readonly IscBesigmaViolationReader $violations,
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
        $needle = mb_strtolower(trim($query));
        $people = $this->peopleWithGps($from, $to, $needle);
        $byId = [];
        foreach ($people as $row) {
            $byId[(string) $row['user_id']] = $row;
        }
        foreach ($this->peopleViolationsOnDay($from, $to) as $row) {
            $userId = (string) ($row['user_id'] ?? '');
            if ($userId === '') {
                continue;
            }
            if (! $this->matchesNeedle($needle, $row['name'] ?? '', $row['sid'] ?? '', $row['company'] ?? '')) {
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
        foreach ($this->unitsWithGps($from, $to, $needle) as $row) {
            $units[(string) $row['unit_id']] = $row;
        }
        foreach ($this->unitViolationsOnDay($from, $to) as $row) {
            $unitId = (string) ($row['unit_id'] ?? '');
            if ($unitId === '') {
                continue;
            }
            if (! $this->matchesNeedle($needle, $row['name'] ?? '', $row['sid'] ?? '', $row['company'] ?? '')) {
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
            $entered = ((int) ($b['entered'] ?? false)) <=> ((int) ($a['entered'] ?? false));
            if ($entered !== 0) {
                return $entered;
            }

            return strcmp((string) ($b['last_at'] ?? ''), (string) ($a['last_at'] ?? ''));
        });

        return [
            'source' => 'live',
            'date' => $date,
            'query' => $query,
            'count' => count($entries),
            'entries' => array_slice($entries, 0, self::ROSTER_LIMIT),
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
     * @return list<array<string, mixed>>
     */
    private function peopleWithGps(string $from, string $to, string $needle): array
    {
        try {
            if (! Schema::connection(self::CONNECTION)->hasTable('user_gps_logs')) {
                return [];
            }
            $bindings = [$from, $to];
            $searchSql = '';
            if ($needle !== '') {
                $like = '%'.$needle.'%';
                $searchSql = ' AND (LOWER(u.fullname) LIKE ? OR LOWER(u.sid_code) LIKE ? OR LOWER(IFNULL(u.npk, \'\')) LIKE ? OR LOWER(IFNULL(u.nik, \'\')) LIKE ?)';
                array_push($bindings, $like, $like, $like, $like);
            }
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT
                    g.user_id,
                    COUNT(*) AS point_count,
                    MIN(g.updated_at) AS first_at,
                    MAX(g.updated_at) AS last_at,
                    u.sid_code,
                    u.fullname,
                    u.npk,
                    u.nik,
                    u.dedicated_site,
                    u.site_assignment,
                    u.functional_position,
                    c.name AS company_name
                FROM user_gps_logs g
                INNER JOIN users u ON u.id = g.user_id AND u.is_deleted = 0
                LEFT JOIN companies c ON c.id = u.company_id
                WHERE g.updated_at >= ?
                  AND g.updated_at < ?
                  AND g.latitude IS NOT NULL
                  AND g.longitude IS NOT NULL
                  AND g.latitude != ''
                  AND g.longitude != ''
                  {$searchSql}
                GROUP BY g.user_id, u.sid_code, u.fullname, u.npk, u.nik, u.dedicated_site, u.site_assignment, u.functional_position, c.name
                ORDER BY last_at DESC
                LIMIT ".self::ROSTER_LIMIT."
            ", $bindings);
        } catch (Throwable $e) {
            report($e);
            $this->connection->rememberFailure($e);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $userId = (string) ($row->user_id ?? '');
            $sid = trim((string) ($row->sid_code ?? ''));
            $name = trim((string) ($row->fullname ?? ''));
            $out[] = [
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
                'point_count' => (int) ($row->point_count ?? 0),
                'first_at' => isset($row->first_at) ? (string) $row->first_at : null,
                'last_at' => isset($row->last_at) ? (string) $row->last_at : null,
                'entered' => false,
                'hazard_name' => null,
                'hazard_kind' => null,
                'has_trail' => true,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unitsWithGps(string $from, string $to, string $needle): array
    {
        try {
            if (! Schema::connection(self::CONNECTION)->hasTable('unit_gps_logs')) {
                return [];
            }
            $bindings = [$from, $to];
            $searchSql = '';
            if ($needle !== '') {
                $like = '%'.$needle.'%';
                $searchSql = ' AND (LOWER(IFNULL(u.vehicle_number, \'\')) LIKE ? OR LOWER(IFNULL(u.vehicle_name, \'\')) LIKE ? OR LOWER(IFNULL(u.vendor_name, \'\')) LIKE ?)';
                array_push($bindings, $like, $like, $like);
            }
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT
                    g.unit_id,
                    COUNT(*) AS point_count,
                    MIN(g.updated_at) AS first_at,
                    MAX(g.updated_at) AS last_at,
                    u.vehicle_number,
                    u.vehicle_name,
                    u.vendor_name
                FROM unit_gps_logs g
                INNER JOIN units u ON u.id = g.unit_id
                WHERE g.updated_at >= ?
                  AND g.updated_at < ?
                  AND g.latitude IS NOT NULL
                  AND g.longitude IS NOT NULL
                  {$searchSql}
                GROUP BY g.unit_id, u.vehicle_number, u.vehicle_name, u.vendor_name
                ORDER BY last_at DESC
                LIMIT ".self::ROSTER_LIMIT."
            ", $bindings);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $unitId = (string) ($row->unit_id ?? '');
            $plate = trim((string) ($row->vehicle_number ?? ''));
            $out[] = [
                'entity' => 'unit',
                'id' => $unitId,
                'unit_id' => $unitId,
                'key' => 'unit:'.$unitId,
                'sid' => $plate,
                'name' => $plate !== '' ? $plate : trim((string) ($row->vehicle_name ?? 'Unit')),
                'company' => $this->nullableString($row->vendor_name ?? null),
                'job_title' => $this->nullableString($row->vehicle_name ?? null),
                'site' => null,
                'site_code' => null,
                'point_count' => (int) ($row->point_count ?? 0),
                'first_at' => isset($row->first_at) ? (string) $row->first_at : null,
                'last_at' => isset($row->last_at) ? (string) $row->last_at : null,
                'entered' => false,
                'hazard_name' => null,
                'hazard_kind' => IscHazardBoundaryClassifier::KIND_UNIT_DANGER,
                'has_trail' => true,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{lat:float,lng:float,at:?string}>
     */
    private function personTrailPoints(string $userId, string $from, string $to): array
    {
        try {
            if (! Schema::connection(self::CONNECTION)->hasTable('user_gps_logs')) {
                return [];
            }
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
            if (! Schema::connection(self::CONNECTION)->hasTable('unit_gps_logs')) {
                return [];
            }
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
        } catch (Throwable $e) {
            report($e);

            return [];
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
    private function peopleViolationsOnDay(string $from, string $to): array
    {
        $out = [];
        foreach ($this->violations->people() as $row) {
            $at = (string) ($row['entered_at'] ?? '');
            if ($at !== '' && ($at < $from || $at >= $to)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unitViolationsOnDay(string $from, string $to): array
    {
        $out = [];
        foreach ($this->violations->units() as $row) {
            $at = (string) ($row['entered_at'] ?? '');
            if ($at !== '' && ($at < $from || $at >= $to)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
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
            'has_trail' => false,
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
        $unitName = 'DT-01';
        if ($this->matchesNeedle($needle, $unitName, 'DT-01', 'PT BUMA')) {
            $entries[] = [
                'entity' => 'unit',
                'id' => 'demo-unit-1',
                'unit_id' => 'demo-unit-1',
                'key' => 'unit:demo-unit-1',
                'sid' => 'DT-01',
                'name' => $unitName,
                'company' => 'PT BUMA',
                'job_title' => 'Dump truck',
                'site' => 'BMO',
                'site_code' => 'BMO',
                'point_count' => 12,
                'first_at' => $date.' 07:00:00',
                'last_at' => $date.' 15:20:00',
                'entered' => true,
                'hazard_name' => 'Zona Bahaya Unit Pit BMO',
                'hazard_kind' => IscHazardBoundaryClassifier::KIND_UNIT_DANGER,
                'has_trail' => true,
            ];
        }

        return [
            'source' => 'demo',
            'date' => $date,
            'query' => $query,
            'count' => count($entries),
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
