<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Services\Besigma\BesigmaConnectionService;
use App\Services\Besigma\BesigmaSchema;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cari karyawan Besigma untuk PIC / Pelapor (SID, NPK, Nama).
 */
final class IscHazardEmployeeLookupService
{
    public const CONNECTION = 'besigma_db';

    public const LIMIT = 30;

    public function __construct(
        private readonly BesigmaConnectionService $connection,
    ) {}

    /**
     * @return list<array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string}>
     */
    public function search(string $query): array
    {
        $q = trim($query);
        if ($q === '' || ! $this->connection->isUp()) {
            return [];
        }

        try {
            $like = '%'.$q.'%';
            $rows = DB::connection(self::CONNECTION)->select(
                'SELECT
                    u.sid_code,
                    u.npk,
                    u.fullname,
                    u.functional_position,
                    u.structural_position,
                    c.name AS company_name
                 FROM '.BesigmaSchema::qualify('users').' u
                 LEFT JOIN '.BesigmaSchema::qualify('companies').' c ON c.id = u.company_id
                 WHERE '.BesigmaSchema::flagIsFalse('u.is_deleted').'
                   AND (
                        u.sid_code ILIKE ?
                        OR CAST(u.npk AS TEXT) ILIKE ?
                        OR u.fullname ILIKE ?
                   )
                 ORDER BY u.fullname ASC
                 LIMIT '.self::LIMIT,
                [$like, $like, $like]
            );
        } catch (Throwable $e) {
            report($e);
            $this->connection->rememberFailure($e);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $sid = strtoupper(trim((string) ($row->sid_code ?? '')));
            $nama = trim((string) ($row->fullname ?? ''));
            if ($sid === '' && $nama === '') {
                continue;
            }
            $jabatan = trim((string) ($row->functional_position ?? $row->structural_position ?? ''));
            $npk = trim((string) ($row->npk ?? ''));
            $out[] = [
                'sid' => $sid,
                'npk' => $npk !== '' ? $npk : null,
                'nama' => $nama !== '' ? $nama : $sid,
                'jabatan' => $jabatan !== '' ? $jabatan : null,
                'company' => isset($row->company_name) && trim((string) $row->company_name) !== ''
                    ? trim((string) $row->company_name)
                    : null,
            ];
        }

        return $out;
    }

    /**
     * @return array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string}|null
     */
    public function findBySid(string $sid): ?array
    {
        $sid = strtoupper(trim($sid));
        if ($sid === '') {
            return null;
        }

        foreach ($this->search($sid) as $row) {
            if (strtoupper($row['sid']) === $sid) {
                return $row;
            }
        }

        return null;
    }
}
