<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Services\Besigma\BesigmaConnectionService;
use App\Services\Besigma\BesigmaTunnelService;
use Illuminate\Support\Facades\DB;
use Throwable;

final class IscPersonnelGpsReader
{
    public const CONNECTION = 'besigma_db';

    private const GPS_LIMIT = 4000;

    private const SID_CHUNK = 500;

    public function __construct(
        private readonly BesigmaConnectionService $connection,
        private readonly BesigmaTunnelService $tunnel,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function latest(): array
    {
        $this->tunnel->applyRuntimeConfig();
        if (! $this->connection->isUp()) {
            return [];
        }

        try {
            $gpsLogs = DB::connection(self::CONNECTION)
                ->table('user_gps_latests as g')
                ->join('users as u', 'u.id', '=', 'g.user_id')
                ->leftJoin('companies as c', 'c.id', '=', 'u.company_id')
                ->where('u.is_deleted', 0)
                ->whereNotNull('g.latitude')
                ->whereNotNull('g.longitude')
                ->where('g.latitude', '!=', '')
                ->where('g.longitude', '!=', '')
                ->orderByDesc('g.updated_at')
                ->limit(self::GPS_LIMIT)
                ->select([
                    'g.user_id',
                    'g.latitude',
                    'g.longitude',
                    'g.updated_at',
                    'u.sid_code',
                    'u.fullname',
                    'u.npk',
                    'u.nik',
                    'u.company_id',
                    'u.site_assignment',
                    'u.dedicated_site',
                    'u.functional_position',
                    'u.structural_position',
                    'u.division_name',
                    'u.department_name',
                    'c.name as company_name',
                ])
                ->get();
        } catch (Throwable $e) {
            report($e);
            $this->connection->rememberFailure($e);

            return [];
        }

        $people = [];
        $seen = [];
        foreach ($gpsLogs as $row) {
            $userId = (string) ($row->user_id ?? '');
            if ($userId === '' || isset($seen[$userId])) {
                continue;
            }
            $lat = $this->toFloat($row->latitude ?? null);
            $lng = $this->toFloat($row->longitude ?? null);
            if ($lat === null || $lng === null || $lat == 0.0 || $lng == 0.0) {
                continue;
            }
            $seen[$userId] = true;
            $sid = $this->sidFromUser($row);
            $name = trim((string) ($row->fullname ?? ''));
            $people[] = [
                'key' => $this->personKey($sid, $userId),
                'user_id' => $userId,
                'sid' => $sid,
                'nik' => $this->nullableString($row->nik ?? null),
                'npk' => $this->nullableString($row->npk ?? null),
                'name' => $name !== '' ? $name : ($sid !== '' ? $sid : 'User '.substr($userId, 0, 8)),
                'company' => $this->nullableString($row->company_name ?? null),
                'job_title' => $this->nullableString($row->functional_position ?? $row->structural_position ?? null),
                'division' => $this->nullableString($row->division_name ?? $row->department_name ?? null),
                'site' => $this->nullableString($row->dedicated_site ?? $row->site_assignment ?? null),
                'lat' => $lat,
                'lng' => $lng,
                'gps_updated_at' => isset($row->updated_at) ? (string) $row->updated_at : null,
            ];
        }

        return $people;
    }

    /**
     * Lookup user Besigma berdasarkan SID, tanpa scan seluruh tabel users.
     *
     * @param  list<string>  $sids
     * @return list<array<string, mixed>>
     */
    public function identifiersBySids(array $sids): array
    {
        $this->tunnel->applyRuntimeConfig();
        $normalized = [];
        foreach ($sids as $sid) {
            $trimmed = trim((string) $sid);
            if ($trimmed === '') {
                continue;
            }
            $normalized[mb_strtoupper($trimmed)] = $trimmed;
        }
        if ($normalized === [] || ! $this->connection->isUp()) {
            return [];
        }

        $people = [];
        try {
            foreach (array_chunk(array_keys($normalized), self::SID_CHUNK) as $chunk) {
                $rows = DB::connection(self::CONNECTION)
                    ->table('users as u')
                    ->leftJoin('companies as c', 'c.id', '=', 'u.company_id')
                    ->where('u.is_deleted', 0)
                    ->whereRaw('UPPER(TRIM(u.sid_code)) IN ('.implode(',', array_fill(0, count($chunk), '?')).')', $chunk)
                    ->select([
                        'u.id',
                        'u.sid_code',
                        'u.nik',
                        'u.npk',
                        'u.fullname',
                        'u.functional_position',
                        'u.structural_position',
                        'c.name as company_name',
                    ])
                    ->get();
                foreach ($rows as $row) {
                    $sid = $this->sidFromUser($row);
                    if ($sid === '') {
                        continue;
                    }
                    $name = trim((string) ($row->fullname ?? ''));
                    $people[] = [
                        'key' => $this->personKey($sid, (string) $row->id),
                        'user_id' => (string) $row->id,
                        'sid' => $sid,
                        'nik' => $this->nullableString($row->nik ?? null),
                        'name' => $name !== '' ? $name : $sid,
                        'company' => $this->nullableString($row->company_name ?? null),
                        'job_title' => $this->nullableString($row->functional_position ?? $row->structural_position ?? null),
                    ];
                }
            }
        } catch (Throwable $e) {
            report($e);
            $this->connection->rememberFailure($e);
        }

        return $people;
    }

    /**
     * @param  list<string>  $sids
     * @return list<array<string, mixed>>
     */
    public function everIdentifiers(array $sids = []): array
    {
        return $this->identifiersBySids($sids);
    }

    public function personKey(string $sid, string $userId): string
    {
        return $sid !== '' ? 'sid:'.mb_strtoupper($sid) : 'user:'.$userId;
    }

    public function sidFromUser(?object $userRow): string
    {
        if ($userRow === null) {
            return '';
        }
        foreach (['sid_code', 'kode_sid', 'nik', 'npk'] as $field) {
            $value = trim((string) ($userRow->{$field} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
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
