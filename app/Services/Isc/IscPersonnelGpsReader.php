<?php

declare(strict_types=1);

namespace App\Services\Isc;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class IscPersonnelGpsReader
{
    public const CONNECTION = 'mysql';

    private const GPS_LIMIT = 4000;

    /**
     * @return list<array<string, mixed>>
     */
    public function latest(): array
    {
        try {
            $table = $this->gpsTable();
            $gpsLogs = DB::connection(self::CONNECTION)
                ->table($table)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereNotNull('user_id')
                ->where('latitude', '!=', '')
                ->where('longitude', '!=', '')
                ->orderByDesc('updated_at')
                ->limit(self::GPS_LIMIT)
                ->get();
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $gpsByUser = [];
        foreach ($gpsLogs as $row) {
            $userId = $row->user_id ?? null;
            if ($userId === null || $userId === '') {
                continue;
            }
            $key = (string) $userId;
            if (! isset($gpsByUser[$key])) {
                $gpsByUser[$key] = $row;
            }
        }
        if ($gpsByUser === []) {
            return [];
        }

        try {
            $usersRows = DB::connection(self::CONNECTION)
                ->table('users_besigma')
                ->whereIn('id', array_keys($gpsByUser))
                ->get()
                ->keyBy('id');
        } catch (Throwable $e) {
            report($e);
            $usersRows = collect();
        }

        $people = [];
        foreach ($gpsByUser as $userId => $row) {
            $lat = $this->toFloat($row->latitude ?? null);
            $lng = $this->toFloat($row->longitude ?? null);
            if ($lat === null || $lng === null || $lat == 0.0 || $lng == 0.0) {
                continue;
            }

            $userRow = $usersRows->get($userId) ?? $usersRows->get((int) $userId);
            $sid = $this->sidFromUser($userRow);
            $name = trim((string) ($userRow->fullname ?? ''));
            if ($name === '') {
                $name = 'User '.substr((string) $userId, 0, 8);
            }

            $people[] = [
                'key' => $this->personKey($sid, (string) $userId),
                'user_id' => (string) $userId,
                'sid' => $sid,
                'nik' => $this->nullableString($userRow->nik ?? null),
                'npk' => $this->nullableString($userRow->npk ?? null),
                'name' => $name,
                'company' => $this->companyFromUser($userRow),
                'job_title' => $this->nullableString($userRow->functional_position ?? $userRow->structural_position ?? null),
                'division' => $this->nullableString($userRow->division_name ?? $userRow->department_name ?? null),
                'site' => $this->nullableString($userRow->site_assignment ?? null),
                'lat' => $lat,
                'lng' => $lng,
                'gps_updated_at' => isset($row->updated_at) ? (string) $row->updated_at : null,
            ];
        }

        return $people;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function everIdentifiers(): array
    {
        $people = [];
        try {
            DB::connection(self::CONNECTION)
                ->table('users_besigma')
                ->select(['id', 'sid_code', 'nik', 'npk', 'fullname', 'company_id', 'functional_position', 'structural_position'])
                ->orderBy('id')
                ->chunk(1000, function ($chunk) use (&$people): void {
                    foreach ($chunk as $row) {
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
                            'company' => $this->companyFromUser($row),
                            'job_title' => $this->nullableString($row->functional_position ?? $row->structural_position ?? null),
                        ];
                    }
                });
        } catch (Throwable $e) {
            report($e);
        }

        return $people;
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

    private function gpsTable(): string
    {
        try {
            if (Schema::connection(self::CONNECTION)->hasTable('user_gps_latests')) {
                return 'user_gps_latests';
            }
        } catch (Throwable) {
        }

        return 'user_gps_logs';
    }

    private function companyFromUser(?object $userRow): ?string
    {
        if ($userRow === null) {
            return null;
        }
        foreach (['company_name', 'company', 'perusahaan'] as $field) {
            $value = $this->nullableString($userRow->{$field} ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        return $this->nullableString($userRow->company_id ?? null);
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
