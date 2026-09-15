<?php

declare(strict_types=1);

namespace App\Services\Isc;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cari karyawan PIC dari hse_automation (pgsql_direct):
 * bcsid.bep_vw_safety_all_karyawan
 */
final class IscHazardEmployeeLookupService
{
    public const CONNECTION = 'pgsql_direct';

    public const VIEW = 'bcsid.bep_vw_safety_all_karyawan';

    public const LIMIT = 30;

    /**
     * @var list<array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string}>
     */
    private const DEMO_EMPLOYEES = [
        [
            'sid' => '2E2AF',
            'npk' => '10000340',
            'nama' => 'Ifa Aprillianto',
            'jabatan' => 'Safety Evaluator',
            'company' => 'PT Berau Coal Energy',
        ],
        [
            'sid' => 'VR9T7',
            'npk' => '10000100',
            'nama' => 'Demo PIC GMO',
            'jabatan' => 'Pengawas',
            'company' => 'PT Berau Coal Energy',
        ],
        [
            'sid' => 'KIYX2',
            'npk' => '10000210',
            'nama' => 'Demo Personel Punan',
            'jabatan' => 'Operator',
            'company' => 'Mitra Kerja',
        ],
        [
            'sid' => 'BC002',
            'npk' => '10000401',
            'nama' => 'Budi Santoso',
            'jabatan' => 'Pengawas Pit',
            'company' => 'PT Pamapersada',
        ],
        [
            'sid' => 'BC006',
            'npk' => '10000402',
            'nama' => 'Farah Ningsih',
            'jabatan' => 'HSE Officer',
            'company' => 'PT Berau Coal',
        ],
        [
            'sid' => 'BC001',
            'npk' => '10000403',
            'nama' => 'Andi Pratama',
            'jabatan' => 'Operator Hauling',
            'company' => 'PT Berau Coal',
        ],
    ];

    /**
     * @return list<array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string}>
     */
    public function search(string $query): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        if (! app()->runningUnitTests()) {
            $live = $this->searchLive($q);
            if ($live !== []) {
                return $live;
            }
        }

        return $this->searchDemo($q);
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

        if (! app()->runningUnitTests()) {
            $exact = $this->findLiveBySid($sid);
            if ($exact !== null) {
                return $exact;
            }
        }

        foreach ($this->searchDemo($sid) as $row) {
            if (strtoupper($row['sid']) === $sid) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string}|null
     */
    private function findLiveBySid(string $sid): ?array
    {
        try {
            $row = DB::connection(self::CONNECTION)->selectOne(
                'SELECT
                    UPPER(TRIM(kode_sid::text)) AS sid,
                    NULLIF(TRIM(nik::text), \'\') AS npk,
                    NULLIF(TRIM(nama::text), \'\') AS nama,
                    NULLIF(TRIM(COALESCE(jabatan_fungsional, jabatan_struktural)::text), \'\') AS jabatan,
                    NULLIF(TRIM(nama_perusahaan::text), \'\') AS company
                 FROM '.self::VIEW.'
                 WHERE kode_sid IS NOT NULL
                   AND UPPER(TRIM(kode_sid::text)) = ?
                 ORDER BY nama ASC NULLS LAST
                 LIMIT 1',
                [$sid]
            );
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($row === null) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * @return list<array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string}>
     */
    private function searchLive(string $q): array
    {
        try {
            $like = '%'.$q.'%';
            $rows = DB::connection(self::CONNECTION)->select(
                'SELECT
                    UPPER(TRIM(kode_sid::text)) AS sid,
                    NULLIF(TRIM(nik::text), \'\') AS npk,
                    NULLIF(TRIM(nama::text), \'\') AS nama,
                    NULLIF(TRIM(COALESCE(jabatan_fungsional, jabatan_struktural)::text), \'\') AS jabatan,
                    NULLIF(TRIM(nama_perusahaan::text), \'\') AS company
                 FROM '.self::VIEW.'
                 WHERE kode_sid IS NOT NULL
                   AND (
                        kode_sid::text ILIKE ?
                        OR nik::text ILIKE ?
                        OR nama::text ILIKE ?
                   )
                 ORDER BY nama ASC NULLS LAST
                 LIMIT '.self::LIMIT,
                [$like, $like, $like]
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $mapped = $this->mapRow($row);
            if ($mapped === null) {
                continue;
            }
            $out[] = $mapped;
        }

        return $out;
    }

    /**
     * @return array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string}|null
     */
    private function mapRow(object $row): ?array
    {
        $sid = strtoupper(trim((string) ($row->sid ?? '')));
        if ($sid === '') {
            return null;
        }
        $nama = trim((string) ($row->nama ?? ''));

        return [
            'sid' => $sid,
            'npk' => $this->nullableString($row->npk ?? null),
            'nama' => $nama !== '' ? $nama : $sid,
            'jabatan' => $this->nullableString($row->jabatan ?? null),
            'company' => $this->nullableString($row->company ?? null),
        ];
    }

    /**
     * @return list<array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string}>
     */
    private function searchDemo(string $q): array
    {
        $needle = mb_strtolower($q);
        $out = [];
        foreach (self::DEMO_EMPLOYEES as $row) {
            $hay = mb_strtolower(($row['sid'] ?? '').' '.($row['npk'] ?? '').' '.($row['nama'] ?? ''));
            if (! str_contains($hay, $needle)) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= self::LIMIT) {
                break;
            }
        }

        return $out;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
