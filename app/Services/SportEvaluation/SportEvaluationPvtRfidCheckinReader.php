<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pembaca read-only check-IN lolos dari bcsid.mv_checkinout_rfid (pgsql_ssh).
 */
final class SportEvaluationPvtRfidCheckinReader
{
    public const TABLE = 'bcsid.mv_checkinout_rfid';

    private const SID_CHUNK = 800;

    /**
     * @var list<string>
     */
    private const CHECKIN_TYPES = [
        'IN',
        'CHECKIN',
        'CHECK-IN',
        'CHECK_IN',
        'CHECK IN',
        'MASUK',
    ];

    /**
     * @var list<string>
     */
    private const PASSED_STATUSES = [
        'PASSED',
        'PASS',
        'LOLOS',
        'YA',
        'YES',
        '1',
        'TRUE',
        'T',
        'Y',
    ];

    public function __construct(
        private readonly McuConnectionService $mcuConnection,
    ) {}

    public function isUp(): bool
    {
        return $this->mcuConnection->isUp();
    }

    /**
     * Check-IN lolos pertama per SID pada tanggal (Asia/Makassar).
     *
     * @param  list<string>  $sids
     * @return array<string, array{
     *     kode_sid: string,
     *     checked_in_at: string,
     *     nama_karyawan: string,
     *     perusahaan: string,
     *     gate: string,
     *     jenis_checkinout: string,
     *     status_lolos: string
     * }>
     */
    public function firstPassedCheckinsForSids(string $date, array $sids): array
    {
        if (! $this->isUp() || $sids === []) {
            return [];
        }

        $normalized = [];
        foreach ($sids as $sid) {
            $trimmed = trim((string) $sid);
            if ($trimmed === '') {
                continue;
            }
            $normalized[mb_strtoupper($trimmed)] = $trimmed;
        }
        if ($normalized === []) {
            return [];
        }

        $day = Carbon::parse($date, config('app.timezone'))->startOfDay();
        $start = $day->format('Y-m-d H:i:s');
        $end = $day->copy()->addDay()->format('Y-m-d H:i:s');

        $merged = [];
        foreach (array_chunk(array_keys($normalized), self::SID_CHUNK) as $chunk) {
            foreach ($this->queryChunk($start, $end, $chunk) as $upper => $row) {
                $merged[$upper] = $row;
            }
        }

        return $merged;
    }

    /**
     * @param  list<string>  $upperSids
     * @return array<string, array{
     *     kode_sid: string,
     *     checked_in_at: string,
     *     nama_karyawan: string,
     *     perusahaan: string,
     *     gate: string,
     *     jenis_checkinout: string,
     *     status_lolos: string
     * }>
     */
    private function queryChunk(string $start, string $end, array $upperSids): array
    {
        $sidPlaceholders = implode(',', array_fill(0, count($upperSids), '?'));
        $typePlaceholders = implode(',', array_fill(0, count(self::CHECKIN_TYPES), '?'));
        $statusPlaceholders = implode(',', array_fill(0, count(self::PASSED_STATUSES), '?'));

        $sql = '
            SELECT DISTINCT ON (UPPER(TRIM(kode_sid)))
                TRIM(kode_sid) AS kode_sid,
                tanggal_checkinout,
                TRIM(COALESCE(nama_karyawan::text, \'\')) AS nama_karyawan,
                TRIM(COALESCE(perusahaan::text, \'\')) AS perusahaan,
                TRIM(COALESCE(gate::text, \'\')) AS gate,
                TRIM(COALESCE(jenis_checkinout::text, \'\')) AS jenis_checkinout,
                TRIM(COALESCE(status_lolos::text, \'\')) AS status_lolos
            FROM '.self::TABLE.'
            WHERE tanggal_checkinout >= ?
              AND tanggal_checkinout < ?
              AND kode_sid IS NOT NULL
              AND TRIM(kode_sid) <> \'\'
              AND UPPER(TRIM(kode_sid)) IN ('.$sidPlaceholders.')
              AND (
                    UPPER(TRIM(jenis_checkinout::text)) IN ('.$typePlaceholders.')
                 OR REPLACE(REPLACE(UPPER(TRIM(jenis_checkinout::text)), \' \', \'\'), \'-\', \'\') IN (\'IN\', \'CHECKIN\', \'MASUK\')
              )
              AND REPLACE(REPLACE(UPPER(TRIM(status_lolos::text)), \' \', \'\'), \'-\', \'\') IN ('.$statusPlaceholders.')
            ORDER BY UPPER(TRIM(kode_sid)), tanggal_checkinout ASC
        ';

        $bindings = array_merge(
            [$start, $end],
            $upperSids,
            self::CHECKIN_TYPES,
            self::PASSED_STATUSES,
        );

        try {
            $rows = DB::connection(McuConnectionService::CONNECTION)->select($sql, $bindings);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $sid = trim((string) ($row->kode_sid ?? ''));
            if ($sid === '') {
                continue;
            }

            $checkedInAt = $row->tanggal_checkinout ?? null;
            if ($checkedInAt instanceof \DateTimeInterface) {
                $checkedInAt = Carbon::instance($checkedInAt)->format('Y-m-d H:i:s');
            } else {
                $checkedInAt = trim((string) $checkedInAt);
            }
            if ($checkedInAt === '') {
                continue;
            }

            $map[mb_strtoupper($sid)] = [
                'kode_sid' => $sid,
                'checked_in_at' => $checkedInAt,
                'nama_karyawan' => trim((string) ($row->nama_karyawan ?? '')),
                'perusahaan' => trim((string) ($row->perusahaan ?? '')),
                'gate' => trim((string) ($row->gate ?? '')),
                'jenis_checkinout' => trim((string) ($row->jenis_checkinout ?? '')),
                'status_lolos' => trim((string) ($row->status_lolos ?? '')),
            ];
        }

        return $map;
    }
}
