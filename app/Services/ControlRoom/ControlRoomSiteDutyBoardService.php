<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\SchedulePlan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Papan status live semua Control Room: hijau jika ada jadwal shift
 * berjalan DAN sudah ada absensi hadir; selain itu merah.
 */
final class ControlRoomSiteDutyBoardService
{
    /** Site yang tampil di papan dashboard — tanpa Marine, Eksplorasi, Jakarta. */
    public const BOARD_SITE_CODES = ['HO', 'BMO1', 'BMO2', 'BMO3', 'GMO', 'LMO', 'PMO', 'SMO'];

    public function __construct(
        private readonly ControlRoomDutyRosterService $dutyRoster,
    ) {}

    /**
     * @return array{
     *     dutyDate: string,
     *     dutyDateLabel: string,
     *     shift: ControlRoomShiftCode,
     *     cards: list<array<string, mixed>>
     * }
     */
    public function build(?CarbonInterface $now = null): array
    {
        $dutyDate = $this->dutyRoster->dutyDate($now);
        $shift = $this->dutyRoster->currentShift($now);
        $cacheKey = 'control-room:site-board:v1:'.$dutyDate->toDateString().':'.$shift->value;
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['cards'], $cached['dutyDate'])) {
            /** @var array{dutyDate: string, dutyDateLabel: string, shift: ControlRoomShiftCode, cards: list<array<string, mixed>>} */
            return $cached;
        }

        $payload = $this->buildUncached($dutyDate, $shift, $now);
        Cache::put($cacheKey, $payload, 45);

        return $payload;
    }

    /**
     * @return array{
     *     dutyDate: string,
     *     dutyDateLabel: string,
     *     shift: ControlRoomShiftCode,
     *     cards: list<array<string, mixed>>
     * }
     */
    private function buildUncached(CarbonInterface $dutyDate, ControlRoomShiftCode $shift, ?CarbonInterface $now): array
    {
        $date = $dutyDate->toDateString();

        $plans = SchedulePlan::query()
            ->select(['id', 'site_code', 'personnel_source_key', 'personnel_name_snapshot'])
            ->whereDate('date', $date)
            ->where('shift_code', $shift->value)
            ->orderBy('personnel_name_snapshot')
            ->get()
            ->groupBy(fn (SchedulePlan $plan): string => $plan->site_code->value);

        $attendances = Attendance::query()
            ->select(['id', 'site_code', 'personnel_source_key', 'personnel_name_snapshot', 'status', 'checked_in_at'])
            ->whereDate('date', $date)
            ->where('shift_code', $shift->value)
            ->whereIn('status', [Attendance::STATUS_SESUAI_JADWAL, Attendance::STATUS_MENGGANTIKAN])
            ->whereNotNull('checked_in_at')
            ->orderBy('personnel_name_snapshot')
            ->get()
            ->groupBy(fn (Attendance $row): string => $row->site_code->value);

        $from = $dutyDate->subDays(6);
        $trendRows = Attendance::query()
            ->selectRaw('site_code, date, count(*) as n')
            ->whereBetween('date', [$from->toDateString(), $date])
            ->whereIn('status', [Attendance::STATUS_SESUAI_JADWAL, Attendance::STATUS_MENGGANTIKAN])
            ->whereNotNull('checked_in_at')
            ->groupBy('site_code', 'date')
            ->get();

        $trendBySite = [];
        foreach ($trendRows as $row) {
            $siteKey = $row->site_code instanceof ControlRoomSiteCode
                ? $row->site_code->value
                : (string) $row->site_code;
            $day = $row->date instanceof CarbonInterface
                ? $row->date->toDateString()
                : (string) $row->date;
            $trendBySite[$siteKey][$day] = (int) $row->n;
        }

        $cards = [];
        foreach (ControlRoomSiteCode::cases() as $site) {
            if (! in_array($site->value, self::BOARD_SITE_CODES, true)) {
                continue;
            }

            $trend = [];
            for ($i = 6; $i >= 0; $i--) {
                $day = $dutyDate->subDays($i)->toDateString();
                $trend[] = $trendBySite[$site->value][$day] ?? 0;
            }

            $cards[] = $this->composeCard(
                $site,
                $plans->get($site->value, collect()),
                $attendances->get($site->value, collect()),
                $trend,
            );
        }

        return [
            'dutyDate' => $date,
            'dutyDateLabel' => $this->dutyRoster->dutyDateLabel($dutyDate),
            'shift' => $shift,
            'cards' => $cards,
        ];
    }

    /**
     * Hijau hanya jika jadwal shift ini ada dan sudah ada yang absen jaga.
     *
     * @return array{tone: string, state: string, hasSchedule: bool, hasDuty: bool}
     */
    public function resolveState(bool $hasSchedule, bool $hasDuty): array
    {
        if ($hasSchedule && $hasDuty) {
            return [
                'tone' => 'green',
                'state' => 'Terjaga',
                'hasSchedule' => true,
                'hasDuty' => true,
            ];
        }

        if ($hasSchedule) {
            return [
                'tone' => 'red',
                'state' => 'Belum absen',
                'hasSchedule' => true,
                'hasDuty' => false,
            ];
        }

        if ($hasDuty) {
            return [
                'tone' => 'red',
                'state' => 'Absen tanpa jadwal',
                'hasSchedule' => false,
                'hasDuty' => true,
            ];
        }

        return [
            'tone' => 'red',
            'state' => 'Tidak ada jadwal',
            'hasSchedule' => false,
            'hasDuty' => false,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SchedulePlan>  $plans
     * @param  \Illuminate\Support\Collection<int, Attendance>  $attendances
     * @param  list<int>  $trend
     * @return array<string, mixed>
     */
    public function composeCard(ControlRoomSiteCode $site, $plans, $attendances, array $trend = []): array
    {
        $state = $this->resolveState($plans->isNotEmpty(), $attendances->isNotEmpty());
        $spark = $this->sparkline($trend === [] ? [0, 0, 0, 0, 0, 0, 0] : $trend);

        return [
            'site' => $site->value,
            'label' => $site->label(),
            'tone' => $state['tone'],
            'state' => $state['state'],
            'hasSchedule' => $state['hasSchedule'],
            'hasDuty' => $state['hasDuty'],
            'scheduledCount' => $plans->count(),
            'presentCount' => $attendances->count(),
            'scheduled' => $plans->map(fn (SchedulePlan $plan): array => [
                'sid' => strtoupper((string) $plan->personnel_source_key),
                'name' => (string) $plan->personnel_name_snapshot,
            ])->values()->all(),
            'present' => $attendances->map(fn (Attendance $row): array => [
                'sid' => strtoupper((string) $row->personnel_source_key),
                'name' => (string) $row->personnel_name_snapshot,
            ])->values()->all(),
            'sparkLine' => $spark['line'],
            'sparkArea' => $spark['area'],
        ];
    }

    /**
     * @param  list<int|float>  $values
     * @return array{line: string, area: string}
     */
    public function sparkline(array $values, int $width = 88, int $height = 36): array
    {
        $count = count($values);
        if ($count < 2) {
            return ['line' => '', 'area' => ''];
        }

        $max = max(1, (int) max($values));
        $points = [];
        foreach (array_values($values) as $index => $value) {
            $x = round(($index / ($count - 1)) * $width, 1);
            $y = round($height - 3 - ((float) $value / $max) * ($height - 8), 1);
            $points[] = $x.','.$y;
        }

        $line = implode(' ', $points);

        return [
            'line' => $line,
            'area' => '0,'.$height.' '.$line.' '.$width.','.$height,
        ];
    }
}
