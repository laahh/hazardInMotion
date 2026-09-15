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
        [
            'sid' => 'S69PK',
            'npk' => '11001584',
            'nama' => 'INDRA NUR SIDIQ',
            'jabatan' => 'Manager',
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
            // SID/kode pendek: exact dulu, jangan lanjut ke ILIKE full-scan.
            if ($this->looksLikeSid($q)) {
                try {
                    $exact = $this->findLiveBySid(strtoupper($q));
                    if ($exact !== null) {
                        return [$exact];
                    }

                    return $this->searchDemo($q);
                } catch (Throwable $e) {
                    report($e);

                    return $this->searchDemo($q);
                }
            }

            try {
                $live = $this->searchLive($q);
                if ($live !== []) {
                    return $live;
                }
            } catch (Throwable $e) {
                report($e);
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
            try {
                $exact = $this->findLiveBySid($sid);
                if ($exact !== null) {
                    return $exact;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        foreach ($this->searchDemo($sid) as $row) {
            if (strtoupper($row['sid']) === $sid) {
                return $row;
            }
        }

        return null;
    }

    private function looksLikeSid(string $q): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{3,12}$/', $q);
    }

    /**
     * @return array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string}|null
     */
    private function findLiveBySid(string $sid): ?array
    {
        $this->applyStatementTimeout();

        // Equality tanpa UPPER/TRIM di WHERE agar bisa pakai index view/base table.
        $row = DB::connection(self::CONNECTION)->selectOne(
            'SELECT
                UPPER(TRIM(kode_sid::text)) AS sid,
                NULLIF(TRIM(nik::text), \'\') AS npk,
                NULLIF(TRIM(nama::text), \'\') AS nama,
                NULLIF(TRIM(COALESCE(jabatan_fungsional, jabatan_struktural)::text), \'\') AS jabatan,
                NULLIF(TRIM(nama_perusahaan::text), \'\') AS company
             FROM '.self::VIEW.'
             WHERE kode_sid = ?
             LIMIT 1',
            [$sid]
        );

        // Fallback: case-insensitive bila data tersimpan beda casing.
        if ($row === null) {
            $row = DB::connection(self::CONNECTION)->selectOne(
                'SELECT
                    UPPER(TRIM(kode_sid::text)) AS sid,
                    NULLIF(TRIM(nik::text), \'\') AS npk,
                    NULLIF(TRIM(nama::text), \'\') AS nama,
                    NULLIF(TRIM(COALESCE(jabatan_fungsional, jabatan_struktural)::text), \'\') AS jabatan,
                    NULLIF(TRIM(nama_perusahaan::text), \'\') AS company
                 FROM '.self::VIEW.'
                 WHERE kode_sid ILIKE ?
                 LIMIT 1',
                [$sid]
            );
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
            $this->applyStatementTimeout();
            $prefix = $q.'%';
            $contains = '%'.$q.'%';
            $looksLikeCode = (bool) preg_match('/^[A-Za-z0-9]{2,20}$/', $q);

            // Kode SID/NIK: hanya prefix (hindari full-scan ILIKE '%q%' di kolom besar).
            if ($looksLikeCode) {
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
                       )
                     ORDER BY
                        CASE WHEN kode_sid::text ILIKE ? THEN 0 ELSE 1 END,
                        nama ASC NULLS LAST
                     LIMIT '.self::LIMIT,
                    [$prefix, $prefix, $q]
                );
            } else {
                $rows = DB::connection(self::CONNECTION)->select(
                    'SELECT
                        UPPER(TRIM(kode_sid::text)) AS sid,
                        NULLIF(TRIM(nik::text), \'\') AS npk,
                        NULLIF(TRIM(nama::text), \'\') AS nama,
                        NULLIF(TRIM(COALESCE(jabatan_fungsional, jabatan_struktural)::text), \'\') AS jabatan,
                        NULLIF(TRIM(nama_perusahaan::text), \'\') AS company
                     FROM '.self::VIEW.'
                     WHERE kode_sid IS NOT NULL
                       AND nama::text ILIKE ?
                     ORDER BY nama ASC NULLS LAST
                     LIMIT '.self::LIMIT,
                    [$contains]
                );
            }
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

    private function applyStatementTimeout(): void
    {
        try {
            // SET (bukan LOCAL) agar berlaku di luar transaction block.
            DB::connection(self::CONNECTION)->statement("SET statement_timeout = '8000'");
        } catch (Throwable) {
            // Beberapa role tidak boleh SET; biarkan query jalan tanpa timeout eksplisit.
        }
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
