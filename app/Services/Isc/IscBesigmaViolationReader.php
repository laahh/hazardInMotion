<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Services\Besigma\BesigmaConnectionService;
use App\Services\Besigma\BesigmaTunnelService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pembaca read-only pelanggaran aktif Besigma untuk HUD /isc/maps.
 */
final class IscBesigmaViolationReader
{
    public const CONNECTION = 'besigma_db';

    /**
     * @var list<string>
     */
    public const ACTIVE_STATUSES = ['WARNING', 'STANDBY', 'DANGER'];

    public const LIST_LIMIT = 2000;

    public function __construct(
        private readonly BesigmaConnectionService $connection,
        private readonly BesigmaTunnelService $tunnel,
        private readonly IscHazardBoundaryClassifier $hazard,
        private readonly IscSiteNormalizer $sites,
    ) {}

    public function isUp(): bool
    {
        $this->tunnel->applyRuntimeConfig();

        return $this->connection->isUp();
    }

    /**
     * @return array<string, int>
     */
    public function kindCounts(): array
    {
        $counts = [
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER => 0,
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE => 0,
            IscHazardBoundaryClassifier::KIND_UNIT_DANGER => 0,
        ];
        foreach ($this->people() as $row) {
            $kind = $this->kindFromCompetency($row['is_competency']);
            $counts[$kind]++;
        }
        $counts[IscHazardBoundaryClassifier::KIND_UNIT_DANGER] = count($this->units());

        return $counts;
    }

    /**
     * Distinct user aktif di boundary_violations.
     *
     * @return list<array<string, mixed>>
     */
    public function people(): array
    {
        if (! $this->isUp()) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count(self::ACTIVE_STATUSES), '?'));
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT
                    v.id,
                    v.user_id,
                    v.boundary_id,
                    v.site_id,
                    v.status,
                    v.is_competency,
                    v.created_at,
                    u.sid_code,
                    u.fullname,
                    u.npk,
                    u.nik,
                    u.company_id,
                    u.site_assignment,
                    u.dedicated_site,
                    u.functional_position,
                    u.structural_position,
                    u.division_name,
                    u.department_name,
                    c.name AS company_name,
                    b.name AS boundary_name,
                    b.code AS boundary_code,
                    b.type AS boundary_type,
                    s.code AS site_code_raw,
                    s.name AS site_name,
                    p.name AS pit_name
                FROM boundary_violations v
                INNER JOIN users u ON u.id = v.user_id AND u.is_deleted = 0
                LEFT JOIN companies c ON c.id = u.company_id
                LEFT JOIN boundaries b ON b.id = v.boundary_id
                LEFT JOIN sites s ON s.id = v.site_id
                LEFT JOIN pits p ON p.id = b.pit_id
                WHERE v.is_deleted = 0
                  AND v.deleted_at IS NULL
                  AND v.status IN ({$placeholders})
                ORDER BY v.created_at DESC
                LIMIT ".self::LIST_LIMIT."
            ", self::ACTIVE_STATUSES);
        } catch (Throwable $e) {
            report($e);
            $this->connection->rememberFailure($e);

            return [];
        }

        $byUser = [];
        foreach ($rows as $row) {
            $userId = (string) ($row->user_id ?? '');
            if ($userId === '') {
                continue;
            }
            $kind = $this->kindFromCompetency($row->is_competency ?? 0);
            $existing = $byUser[$userId] ?? null;
            if ($existing !== null && $this->kindRank($existing['hazard_kind']) <= $this->kindRank($kind)) {
                continue;
            }
            $sid = trim((string) ($row->sid_code ?? ''));
            $siteCode = $this->sites->codeFrom($row->site_code_raw ?? null, $row->site_name ?? null, $row->dedicated_site ?? null);
            $byUser[$userId] = [
                'id' => (string) ($row->id ?? ''),
                'user_id' => $userId,
                'sid' => $sid,
                'name' => $this->displayName($row->fullname ?? null, $sid, $userId),
                'npk' => $this->nullableString($row->npk ?? null),
                'nik' => $this->nullableString($row->nik ?? null),
                'company' => $this->nullableString($row->company_name ?? null),
                'job_title' => $this->nullableString($row->functional_position ?? $row->structural_position ?? null),
                'division' => $this->nullableString($row->division_name ?? $row->department_name ?? null),
                'site' => $this->nullableString($row->dedicated_site ?? $row->site_name ?? null),
                'site_code' => $siteCode,
                'boundary_id' => (string) ($row->boundary_id ?? ''),
                'hazard_name' => $this->nullableString($row->boundary_name ?? null),
                'hazard_code' => $this->nullableString($row->boundary_code ?? null),
                'hazard_type' => $this->nullableString($row->boundary_type ?? null),
                'pit_name' => $this->nullableString($row->pit_name ?? null),
                'site_label' => $siteCode !== null ? $this->sites->label($siteCode) : $this->nullableString($row->site_name ?? null),
                'hazard_kind' => $kind,
                'hazard_kind_label' => $this->hazard->label($kind),
                'is_competency' => (int) ($row->is_competency ?? 0),
                'status' => (string) ($row->status ?? ''),
                'entered_at' => isset($row->created_at) ? (string) $row->created_at : null,
            ];
        }

        return array_values($byUser);
    }

    /**
     * Distinct unit aktif di boundary_violation_units. Tanpa koordinat GPS.
     *
     * @return list<array<string, mixed>>
     */
    public function units(): array
    {
        if (! $this->isUp()) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count(self::ACTIVE_STATUSES), '?'));
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT
                    v.id,
                    v.unit_id,
                    v.boundary_id,
                    v.site_id,
                    v.status,
                    v.is_competency,
                    v.created_at,
                    u.vehicle_number,
                    u.vehicle_name,
                    u.vendor_name,
                    b.name AS boundary_name,
                    b.code AS boundary_code,
                    b.type AS boundary_type,
                    s.code AS site_code_raw,
                    s.name AS site_name,
                    p.name AS pit_name
                FROM boundary_violation_units v
                INNER JOIN units u ON u.id = v.unit_id
                LEFT JOIN boundaries b ON b.id = v.boundary_id
                LEFT JOIN sites s ON s.id = v.site_id
                LEFT JOIN pits p ON p.id = b.pit_id
                WHERE v.is_deleted = 0
                  AND v.deleted_at IS NULL
                  AND v.status IN ({$placeholders})
                ORDER BY v.created_at DESC
                LIMIT ".self::LIST_LIMIT."
            ", self::ACTIVE_STATUSES);
        } catch (Throwable $e) {
            report($e);
            $this->connection->rememberFailure($e);

            return [];
        }

        $byUnit = [];
        foreach ($rows as $row) {
            $unitId = (string) ($row->unit_id ?? '');
            if ($unitId === '' || isset($byUnit[$unitId])) {
                continue;
            }
            $kind = IscHazardBoundaryClassifier::KIND_UNIT_DANGER;
            $plate = trim((string) ($row->vehicle_number ?? ''));
            $siteCode = $this->sites->codeFrom($row->site_code_raw ?? null, $row->site_name ?? null);
            $byUnit[$unitId] = [
                'id' => (string) ($row->id ?? ''),
                'unit_id' => $unitId,
                'sid' => $plate,
                'name' => $plate !== '' ? $plate : ('Unit '.substr($unitId, 0, 8)),
                'company' => $this->nullableString($row->vendor_name ?? null),
                'site_code' => $siteCode,
                'boundary_id' => (string) ($row->boundary_id ?? ''),
                'hazard_name' => $this->nullableString($row->boundary_name ?? null),
                'hazard_code' => $this->nullableString($row->boundary_code ?? null),
                'hazard_type' => $this->nullableString($row->boundary_type ?? null),
                'pit_name' => $this->nullableString($row->pit_name ?? null),
                'site_label' => $siteCode !== null ? $this->sites->label($siteCode) : $this->nullableString($row->site_name ?? null),
                'hazard_kind' => $kind,
                'hazard_kind_label' => $this->hazard->label($kind),
                'status' => (string) ($row->status ?? ''),
                'entered_at' => isset($row->created_at) ? (string) $row->created_at : null,
            ];
        }

        return array_values($byUnit);
    }

    private function kindFromCompetency(mixed $flag): string
    {
        return ((int) $flag) === 1
            ? IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE
            : IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER;
    }

    private function kindRank(string $kind): int
    {
        return match ($kind) {
            IscHazardBoundaryClassifier::KIND_EMPLOYEE_COMPETENCE => 0,
            IscHazardBoundaryClassifier::KIND_UNIT_DANGER => 1,
            default => 2,
        };
    }

    private function displayName(mixed $fullname, string $sid, string $userId): string
    {
        $name = trim((string) $fullname);
        if ($name !== '') {
            return $name;
        }
        if ($sid !== '') {
            return $sid;
        }

        return 'User '.substr($userId, 0, 8);
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
