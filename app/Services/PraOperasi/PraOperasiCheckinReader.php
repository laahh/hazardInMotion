<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Check-in RFID lolos hari ini untuk karyawan berjabatan "Operator", dibaca langsung
 * dari bcsid.mv_checkinout_rfid ⋈ bcsid.m_karyawan ⋈ bcsid.m_jabatan di database
 * hse_automation (Postgres). Satu query — roster & check-in ada di DB yang sama,
 * berbeda dengan pola SportEvaluationPvtDashboardService (roster di MySQL BeWell).
 *
 * Koneksi (tunnel dulu, lalu direct RDS) diambil-alih dari SportEvaluationPvtRfidCheckinReader
 * supaya tidak menduplikasi logic ping tunnel/direct.
 */
final class PraOperasiCheckinReader
{
    private const SID_CHUNK = 800;

    /** @var list<string> */
    private const CHECKIN_TYPES = ['IN', 'CHECKIN', 'CHECK-IN', 'CHECK_IN', 'CHECK IN', 'MASUK'];

    /** @var list<string> */
    private const PASSED_STATUSES = ['PASSED', 'PASS', 'LOLOS', 'YA', 'YES', '1', 'TRUE', 'T', 'Y'];

    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $connectionSource,
    ) {}

    public function isUp(): bool
    {
        return $this->connectionSource->isUp();
    }

    /**
     * Check-in lolos pertama per SID pada tanggal (Asia/Makassar), hanya untuk
     * karyawan yang jabatannya (bcsid.m_jabatan.nama) mengandung kata "OPERATOR".
     *
     * @return list<array{
     *     kode_sid: string, nama: string, jabatan: string, perusahaan: string,
     *     checked_in_at: string, gate: string, status_lolos: string
     * }>
     */
    public function operatorCheckinsForDate(string $date): array
    {
        if (! $this->isUp()) {
            return [];
        }

        $cacheKey = 'pra_operasi:checkins:v1:'.$date;

        return Cache::remember($cacheKey, 30, function () use ($date): array {
            $tz = (string) config('app.timezone');
            $day = Carbon::parse($date, $tz)->startOfDay();
            $start = $day->format('Y-m-d H:i:s');
            $end = $day->copy()->addDay()->format('Y-m-d H:i:s');

            $typePlaceholders = implode(',', array_fill(0, count(self::CHECKIN_TYPES), '?'));
            $statusPlaceholders = implode(',', array_fill(0, count(self::PASSED_STATUSES), '?'));

            $sql = '
                SELECT DISTINCT ON (UPPER(TRIM(r.kode_sid)))
                    TRIM(r.kode_sid) AS kode_sid,
                    TRIM(COALESCE(k.nama, r.nama_karyawan::text, \'\')) AS nama,
                    TRIM(j.nama) AS jabatan,
                    TRIM(COALESCE(p.nama, r.perusahaan::text, \'\')) AS perusahaan,
                    r.tanggal_checkinout AS checked_in_at,
                    TRIM(COALESCE(r.gate::text, \'\')) AS gate,
                    TRIM(COALESCE(r.status_lolos::text, \'\')) AS status_lolos
                FROM bcsid.mv_checkinout_rfid r
                JOIN bcsid.m_karyawan k ON UPPER(TRIM(k.kode_sid)) = UPPER(TRIM(r.kode_sid))
                JOIN bcsid.m_jabatan j ON j.id = k.id_jabatan
                LEFT JOIN bcsid.m_perusahaan p ON p.id = k.id_perusahaan
                WHERE r.tanggal_checkinout >= ?
                  AND r.tanggal_checkinout < ?
                  AND r.kode_sid IS NOT NULL
                  AND TRIM(r.kode_sid) <> \'\'
                  AND UPPER(j.nama) LIKE \'%OPERATOR%\'
                  AND UPPER(j.nama) <> \'VISITOR\'
                  AND (
                        UPPER(TRIM(r.jenis_checkinout::text)) IN ('.$typePlaceholders.')
                     OR REPLACE(REPLACE(UPPER(TRIM(r.jenis_checkinout::text)), \' \', \'\'), \'-\', \'\') IN (\'IN\', \'CHECKIN\', \'MASUK\')
                  )
                  AND REPLACE(REPLACE(UPPER(TRIM(r.status_lolos::text)), \' \', \'\'), \'-\', \'\') IN ('.$statusPlaceholders.')
                ORDER BY UPPER(TRIM(r.kode_sid)), r.tanggal_checkinout ASC
            ';

            $bindings = array_merge([$start, $end], self::CHECKIN_TYPES, self::PASSED_STATUSES);

            try {
                $connection = $this->connectionSource->connectionName();
                if ($connection === null) {
                    return [];
                }
                $rows = DB::connection($connection)->select($sql, $bindings);
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            $list = [];
            foreach ($rows as $row) {
                $sid = trim((string) ($row->kode_sid ?? ''));
                if ($sid === '') {
                    continue;
                }

                $checkedInAt = $row->checked_in_at ?? null;
                if ($checkedInAt instanceof \DateTimeInterface) {
                    $checkedInAt = Carbon::instance($checkedInAt)->timezone($tz)->format('Y-m-d H:i:s');
                } else {
                    $checkedInAt = trim((string) $checkedInAt);
                    if ($checkedInAt !== '') {
                        try {
                            $checkedInAt = Carbon::parse($checkedInAt, $tz)->timezone($tz)->format('Y-m-d H:i:s');
                        } catch (Throwable) {
                            // biarkan string mentah
                        }
                    }
                }
                if ($checkedInAt === '') {
                    continue;
                }

                $list[] = [
                    'kode_sid' => $sid,
                    'nama' => trim((string) ($row->nama ?? '')),
                    'jabatan' => trim((string) ($row->jabatan ?? '')),
                    'perusahaan' => trim((string) ($row->perusahaan ?? '')),
                    'checked_in_at' => $checkedInAt,
                    'gate' => trim((string) ($row->gate ?? '')),
                    'status_lolos' => trim((string) ($row->status_lolos ?? '')),
                ];
            }

            return $list;
        });
    }
}
