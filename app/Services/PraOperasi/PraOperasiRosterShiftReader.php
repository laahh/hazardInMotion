<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Penentuan Shift 1 (Siang) / Shift 2 (Malam) TANPA bergantung pada check-out
 * (banyak karyawan tidak pernah tap keluar di RFID, jadi checked_out_at kosong
 * bukan sinyal yang bisa dipakai). Dua sumber, roster lebih diutamakan:
 *
 *   1. bcsid.dms_roster — jadwal shift resmi per driver_id per tanggal
 *      (shift_type '1-DAY'/'2-NIGHT'). Satu driver bisa muncul 2 baris per
 *      tanggal (roster D dan N sekaligus) — jam checkin dipakai sebagai
 *      tie-breaker untuk memilih baris yang benar-benar terjadi.
 *   2. Pola jam checkin murni (06:00-17:59 = Shift 1, 18:00-05:59 = Shift 2)
 *      — dipakai kalau tidak ada baris roster sama sekali untuk SID itu.
 */
final class PraOperasiRosterShiftReader
{
    private const TABLE = 'bcsid.dms_roster';

    private const SID_CHUNK = 500;

    public const SHIFT_1 = '1';

    public const SHIFT_2 = '2';

    private const DAY_START_HOUR = 6;

    private const DAY_END_HOUR = 18;

    /** Toleransi lembur setelah batas shift sebelum dianggap tidak lagi beroperasi. */
    private const OVERTIME_GRACE_HOURS = 4;

    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $connectionSource,
    ) {}

    public function isUp(): bool
    {
        return $this->connectionSource->connectionName() !== null;
    }

    /** Shift murni dari jam checkin, tanpa roster maupun checkout. */
    public static function fromCheckinHour(Carbon $checkinAt): string
    {
        $hour = (int) $checkinAt->format('G');

        return ($hour >= self::DAY_START_HOUR && $hour < self::DAY_END_HOUR) ? self::SHIFT_1 : self::SHIFT_2;
    }

    /**
     * Batas akhir jendela operasi shift (+ toleransi lembur) — dipakai untuk
     * "masih beroperasi" tanpa perlu checked_out_at sama sekali.
     */
    public static function operatingWindowEnd(Carbon $checkinAt, string $shift): Carbon
    {
        $hour = (int) $checkinAt->format('G');

        if ($shift === self::SHIFT_1) {
            $end = $checkinAt->copy()->setTime(self::DAY_END_HOUR, 0);
        } else {
            $end = $hour >= self::DAY_END_HOUR
                ? $checkinAt->copy()->addDay()->setTime(self::DAY_START_HOUR, 0)
                : $checkinAt->copy()->setTime(self::DAY_START_HOUR, 0);
        }

        return $end->addHours(self::OVERTIME_GRACE_HOURS);
    }

    /**
     * @param  list<string>  $sids
     * @return array<string, list<array{shift_type:string, day_or_night:string}>>  keyed by UPPER(driver_id)
     */
    public function rosterForSidsOnDate(array $sids, string $date): array
    {
        $connection = $this->connectionSource->connectionName();
        $upperSids = array_values(array_unique(array_filter(array_map(
            static fn (string $s): string => mb_strtoupper(trim($s)),
            $sids
        ), static fn (string $s): bool => $s !== '')));

        if ($connection === null || $upperSids === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($upperSids, self::SID_CHUNK) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = '
                SELECT UPPER(TRIM(driver_id)) AS driver_id, shift_type, day_or_night
                FROM '.self::TABLE.'
                WHERE date = ? AND is_active = true
                  AND UPPER(TRIM(driver_id)) IN ('.$placeholders.')
            ';

            try {
                $rows = DB::connection($connection)->select($sql, array_merge([$date], $chunk));
            } catch (Throwable $e) {
                report($e);
                continue;
            }

            foreach ($rows as $row) {
                $sid = trim((string) ($row->driver_id ?? ''));
                if ($sid === '') {
                    continue;
                }
                $out[$sid][] = [
                    'shift_type' => trim((string) ($row->shift_type ?? '')),
                    'day_or_night' => trim((string) ($row->day_or_night ?? '')),
                ];
            }
        }

        return $out;
    }

    /**
     * Resolusi shift final per SID: utamakan dms_roster (cocokkan ke pola jam
     * checkin kalau SID itu punya >1 baris roster hari itu), fallback ke pola
     * jam checkin murni kalau tidak ada baris roster sama sekali.
     *
     * @param  array<string, string>  $checkinAtBySid  UPPER(kode_sid) => waktu checkin (Y-m-d H:i:s)
     * @return array<string, array{shift:string, source:string, roster_code:string|null}>  source: roster|pattern; roster_code mis. "D1"/"N3" (kolom day_or_night dms_roster)
     */
    public function resolveForCheckins(array $checkinAtBySid, string $date): array
    {
        if ($checkinAtBySid === []) {
            return [];
        }

        $roster = $this->rosterForSidsOnDate(array_keys($checkinAtBySid), $date);
        $tz = (string) config('app.timezone');

        $out = [];
        foreach ($checkinAtBySid as $upper => $checkinAtRaw) {
            try {
                $checkinAt = Carbon::parse($checkinAtRaw, $tz);
            } catch (Throwable) {
                continue;
            }

            $pattern = self::fromCheckinHour($checkinAt);
            $rows = $roster[$upper] ?? [];

            if ($rows === []) {
                $out[$upper] = ['shift' => $pattern, 'source' => 'pattern', 'roster_code' => null];

                continue;
            }

            $matchedRow = null;
            foreach ($rows as $row) {
                $rosterShift = str_starts_with($row['shift_type'], '1') ? self::SHIFT_1 : self::SHIFT_2;
                if ($rosterShift === $pattern) {
                    $matchedRow = $row;
                    break;
                }
            }

            $matchedRow ??= $rows[0];
            $matched = str_starts_with($matchedRow['shift_type'], '1') ? self::SHIFT_1 : self::SHIFT_2;

            $out[$upper] = [
                'shift' => $matched,
                'source' => 'roster',
                'roster_code' => $matchedRow['day_or_night'] !== '' ? $matchedRow['day_or_night'] : null,
            ];
        }

        return $out;
    }
}
