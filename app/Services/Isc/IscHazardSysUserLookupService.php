<?php

declare(strict_types=1);

namespace App\Services\Isc;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Lookup akses pelapor dari hse_automation (pgsql_direct):
 * bcbeats.bep_vw_karyawan_sysuser_user_role
 *
 * Catatan: kolom Username/Password case-sensitive (quoted identifiers).
 */
final class IscHazardSysUserLookupService
{
    public const CONNECTION = 'pgsql_direct';

    public const VIEW = 'bcbeats.bep_vw_karyawan_sysuser_user_role';

    public const LIMIT = 30;

    /**
     * @var list<array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string,username:?string,password:?string}>
     */
    private const DEMO_USERS = [
        [
            'sid' => '2E2AF',
            'npk' => '10000340',
            'nama' => 'Ifa Aprillianto',
            'jabatan' => 'Safety Evaluator',
            'company' => 'PT Berau Coal Energy',
            'username' => 'ifa.aprillianto',
            'password' => 'demo-pass-2e2af',
        ],
        [
            'sid' => 'VR9T7',
            'npk' => '10000100',
            'nama' => 'Demo PIC GMO',
            'jabatan' => 'Pengawas',
            'company' => 'PT Berau Coal Energy',
            'username' => 'demo.pic.gmo',
            'password' => 'demo-pass-vr9t7',
        ],
        [
            'sid' => 'KIYX2',
            'npk' => '10000210',
            'nama' => 'Demo Personel Punan',
            'jabatan' => 'Operator',
            'company' => 'Mitra Kerja',
            'username' => 'demo.punan',
            'password' => 'demo-pass-kiyx2',
        ],
        [
            'sid' => 'BC002',
            'npk' => '10000401',
            'nama' => 'Budi Santoso',
            'jabatan' => 'Pengawas Pit',
            'company' => 'PT Pamapersada',
            'username' => 'budi.santoso',
            'password' => 'demo-pass-bc002',
        ],
        [
            'sid' => 'BC006',
            'npk' => '10000402',
            'nama' => 'Farah Ningsih',
            'jabatan' => 'HSE Officer',
            'company' => 'PT Berau Coal',
            'username' => 'farah.ningsih',
            'password' => 'demo-pass-bc006',
        ],
        [
            'sid' => 'BC001',
            'npk' => '10000403',
            'nama' => 'Andi Pratama',
            'jabatan' => 'Operator Hauling',
            'company' => 'PT Berau Coal',
            'username' => 'andi.pratama',
            'password' => 'demo-pass-bc001',
        ],
    ];

    /**
     * @return list<array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string,username:?string,password:?string}>
     */
    public function search(string $query): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        if (! app()->runningUnitTests()) {
            if (preg_match('/^[A-Za-z0-9]{3,12}$/', $q) === 1) {
                $exact = $this->findLiveBySid(strtoupper($q));
                if ($exact !== null) {
                    return [$exact];
                }
            }

            $live = $this->searchLive($q);
            if ($live !== []) {
                return $live;
            }
        }

        return $this->searchDemo($q);
    }

    /**
     * @return array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string,username:?string,password:?string}|null
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
     * @return array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string,username:?string,password:?string}|null
     */
    private function findLiveBySid(string $sid): ?array
    {
        try {
            $row = DB::connection(self::CONNECTION)->selectOne(
                'SELECT
                    UPPER(TRIM(kode_sid::text)) AS sid,
                    NULLIF(TRIM(nama::text), \'\') AS nama,
                    NULLIF(TRIM(jabatan_fungsional::text), \'\') AS jabatan,
                    NULLIF(TRIM(nama_perusahaan::text), \'\') AS company,
                    NULLIF(TRIM("Username"::text), \'\') AS username,
                    NULLIF(TRIM("Password"::text), \'\') AS password
                 FROM '.self::VIEW.'
                 WHERE kode_sid = ?
                 ORDER BY tanggal_buat DESC NULLS LAST
                 LIMIT 1',
                [$sid]
            );

            if ($row === null) {
                $row = DB::connection(self::CONNECTION)->selectOne(
                    'SELECT
                        UPPER(TRIM(kode_sid::text)) AS sid,
                        NULLIF(TRIM(nama::text), \'\') AS nama,
                        NULLIF(TRIM(jabatan_fungsional::text), \'\') AS jabatan,
                        NULLIF(TRIM(nama_perusahaan::text), \'\') AS company,
                        NULLIF(TRIM("Username"::text), \'\') AS username,
                        NULLIF(TRIM("Password"::text), \'\') AS password
                     FROM '.self::VIEW.'
                     WHERE kode_sid ILIKE ?
                     ORDER BY tanggal_buat DESC NULLS LAST
                     LIMIT 1',
                    [$sid]
                );
            }
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
     * @return list<array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string,username:?string,password:?string}>
     */
    private function searchLive(string $q): array
    {
        try {
            $like = '%'.$q.'%';
            $rows = DB::connection(self::CONNECTION)->select(
                'SELECT
                    UPPER(TRIM(kode_sid::text)) AS sid,
                    NULLIF(TRIM(nama::text), \'\') AS nama,
                    NULLIF(TRIM(jabatan_fungsional::text), \'\') AS jabatan,
                    NULLIF(TRIM(nama_perusahaan::text), \'\') AS company,
                    NULLIF(TRIM("Username"::text), \'\') AS username,
                    NULLIF(TRIM("Password"::text), \'\') AS password
                 FROM '.self::VIEW.'
                 WHERE kode_sid IS NOT NULL
                   AND (
                        kode_sid::text ILIKE ?
                        OR nama::text ILIKE ?
                        OR "Username"::text ILIKE ?
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
     * @return array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string,username:?string,password:?string}|null
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
            'npk' => null,
            'nama' => $nama !== '' ? $nama : $sid,
            'jabatan' => $this->nullableString($row->jabatan ?? null),
            'company' => $this->nullableString($row->company ?? null),
            'username' => $this->nullableString($row->username ?? null),
            'password' => $this->nullableString($row->password ?? null),
        ];
    }

    /**
     * @return list<array{sid:string,npk:?string,nama:string,jabatan:?string,company:?string,username:?string,password:?string}>
     */
    private function searchDemo(string $q): array
    {
        $needle = mb_strtolower($q);
        $out = [];
        foreach (self::DEMO_USERS as $row) {
            $hay = mb_strtolower(($row['sid'] ?? '').' '.($row['nama'] ?? '').' '.($row['username'] ?? ''));
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
