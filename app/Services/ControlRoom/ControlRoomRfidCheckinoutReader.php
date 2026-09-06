<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Histori tap RFID check-in/out dari OBDS (bcsid.mv_checkinout_rfid).
 * Jendela Shift 2 lintas tengah malam + grace ±2 jam supaya checkout pagi
 * dan tap keluar-masuk di jam jaga tetap ter-cover.
 */
final class ControlRoomRfidCheckinoutReader
{
    public const GRACE_HOURS = 2;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * @param  list<array{sid: string, date: CarbonInterface, shift: ControlRoomShiftCode}>  $slots
     * @return array<string, list<array{at: string, time: string, date_label: string, type: string, type_label: string, gate: string, passed: bool}>>
     */
    public function forDutySlots(array $slots): array
    {
        if ($slots === [] || ! $this->olap->isReachable()) {
            return [];
        }

        $sids = [];
        foreach ($slots as $slot) {
            $sid = strtoupper(trim($slot['sid']));
            if ($sid !== '') {
                $sids[$sid] = $sid;
            }
        }

        if ($sids === []) {
            return [];
        }

        $range = $this->queryRange($slots);
        $rows = $this->fetchRows(array_values($sids), $range['start'], $range['end']);

        return $this->groupRowsIntoSlots($slots, $rows);
    }

    /**
     * @param  list<array{sid: string, date: CarbonInterface, shift: ControlRoomShiftCode}>  $slots
     * @param  list<object>  $rows
     * @return array<string, list<array{at: string, time: string, date_label: string, type: string, type_label: string, gate: string, passed: bool}>>
     */
    public function groupRowsIntoSlots(array $slots, array $rows): array
    {
        $grouped = [];
        foreach ($slots as $slot) {
            $sid = strtoupper(trim($slot['sid']));
            $key = $this->slotKey($slot['date'], $slot['shift'], $sid);
            $window = $this->window($slot['date'], $slot['shift']);
            $grouped[$key] = $this->eventsInWindow($rows, $sid, $window['start'], $window['end']);
        }

        return $grouped;
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function window(CarbonInterface $dutyDate, ControlRoomShiftCode $shift, int $graceHours = self::GRACE_HOURS): array
    {
        $date = CarbonImmutable::parse($dutyDate)->startOfDay();
        $start = CarbonImmutable::parse($date->toDateString().' '.$shift->start());
        $end = CarbonImmutable::parse($date->toDateString().' '.$shift->end());

        if ($shift->crossesMidnight()) {
            $end = $end->addDay();
        }

        return [
            'start' => $start->subHours($graceHours),
            'end' => $end->addHours($graceHours),
        ];
    }

    public function slotKey(mixed $date, mixed $shift, string $sid): string
    {
        $dateString = $date instanceof CarbonInterface ? $date->toDateString() : (string) $date;
        $shiftCode = $shift instanceof ControlRoomShiftCode ? $shift->value : (string) $shift;

        return $dateString.'|'.$shiftCode.'|'.strtoupper(trim($sid));
    }

    /**
     * @param  list<array{sid: string, date: CarbonInterface, shift: ControlRoomShiftCode}>  $slots
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    private function queryRange(array $slots): array
    {
        $starts = [];
        $ends = [];
        foreach ($slots as $slot) {
            $window = $this->window($slot['date'], $slot['shift']);
            $starts[] = $window['start'];
            $ends[] = $window['end'];
        }

        usort($starts, fn (CarbonImmutable $a, CarbonImmutable $b): int => $a <=> $b);
        usort($ends, fn (CarbonImmutable $a, CarbonImmutable $b): int => $a <=> $b);

        return [
            'start' => $starts[0],
            'end' => $ends[array_key_last($ends)],
        ];
    }

    /**
     * @param  list<string>  $sids
     * @return list<object>
     */
    private function fetchRows(array $sids, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $placeholders = implode(',', array_fill(0, count($sids), '?'));
        $sql = "
            SELECT kode_sid, jenis_checkinout, tanggal_checkinout, gate, status_lolos
            FROM bcsid.mv_checkinout_rfid
            WHERE kode_sid IN ({$placeholders})
              AND tanggal_checkinout >= ?
              AND tanggal_checkinout < ?
            ORDER BY tanggal_checkinout ASC
        ";

        $bindings = [...$sids, $start->toDateTimeString(), $end->toDateTimeString()];

        try {
            return $this->olap->select($sql, $bindings, 8000);
        } catch (Throwable $e) {
            Log::warning('ControlRoom RFID checkinout gagal: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param  list<object>  $rows
     * @return list<array{at: string, time: string, date_label: string, type: string, type_label: string, gate: string, passed: bool}>
     */
    private function eventsInWindow(array $rows, string $sid, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $events = [];
        foreach ($rows as $row) {
            $rowSid = strtoupper(trim((string) ($row->kode_sid ?? '')));
            if ($rowSid !== $sid) {
                continue;
            }

            $at = CarbonImmutable::parse((string) $row->tanggal_checkinout);
            if ($at->lt($start) || $at->gte($end)) {
                continue;
            }

            $isIn = strtoupper(trim((string) ($row->jenis_checkinout ?? ''))) === 'CHECK IN';

            $events[] = [
                'at' => $at->format('Y-m-d H:i:s'),
                'time' => $at->format('H:i'),
                'date_label' => $at->locale('id')->translatedFormat('d M'),
                'type' => $isIn ? 'in' : 'out',
                'type_label' => $isIn ? 'Check-in' : 'Check-out',
                'gate' => trim((string) ($row->gate ?? '')) !== '' ? (string) $row->gate : '—',
                'passed' => strtoupper(trim((string) ($row->status_lolos ?? ''))) === 'PASSED',
            ];
        }

        return $events;
    }
}
