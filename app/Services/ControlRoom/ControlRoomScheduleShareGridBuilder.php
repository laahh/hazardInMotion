<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\SchedulePlan;
use App\Models\OhsDashboard\Employee;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Matriks jadwal 1 minggu (Minggu–Sabtu) untuk dibagikan: baris personil,
 * kolom tanggal plan, sel = shift yang benar-benar ada di database.
 */
final class ControlRoomScheduleShareGridBuilder
{
    private const DAY_SHORT = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    private const GROUP_SUPERINTENDENT = 'superintendent';

    private const GROUP_SUPERVISOR = 'supervisor';

    private const GROUP_FOREMAN = 'foreman';

    private const GROUP_EVALUATOR = 'evaluator';

    private const GROUP_OTHER = 'other';

    /** @var array<string, array{title: string, subtitle: string, sort: int}> */
    private const GROUPS = [
        self::GROUP_SUPERINTENDENT => [
            'title' => 'Safety Superintendent',
            'subtitle' => '',
            'sort' => 1,
        ],
        self::GROUP_SUPERVISOR => [
            'title' => 'Site Safety Supervisor',
            'subtitle' => 'Shift 1',
            'sort' => 2,
        ],
        self::GROUP_FOREMAN => [
            'title' => 'Site Safety Foreman',
            'subtitle' => 'Shift 2',
            'sort' => 3,
        ],
        self::GROUP_EVALUATOR => [
            'title' => 'Evaluator',
            'subtitle' => '',
            'sort' => 4,
        ],
        self::GROUP_OTHER => [
            'title' => 'Personil Jaga',
            'subtitle' => '',
            'sort' => 5,
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function fromDatabase(
        ControlRoomIsoWeekPeriod $period,
        ControlRoomSiteCode $site,
        bool $allSites,
    ): array {
        $query = SchedulePlan::query()
            ->select(['site_code', 'date', 'shift_code', 'personnel_source_key', 'personnel_name_snapshot'])
            ->whereDate('date', '>=', $period->start->toDateString())
            ->whereDate('date', '<=', $period->end->startOfDay()->toDateString())
            ->orderBy('site_code')
            ->orderBy('personnel_name_snapshot');

        if (! $allSites) {
            $query->where('site_code', $site->value);
        }

        $plans = $query->get()->map(static function (SchedulePlan $plan): array {
            $date = $plan->date === null
                ? ''
                : CarbonImmutable::parse($plan->date)->toDateString();
            $shift = $plan->shift_code instanceof ControlRoomShiftCode
                ? $plan->shift_code->value
                : (string) $plan->shift_code;

            return [
                'site_code' => $plan->site_code instanceof ControlRoomSiteCode
                    ? $plan->site_code->value
                    : (string) $plan->site_code,
                'date' => $date,
                'shift_code' => $shift,
                'personnel_source_key' => strtoupper(trim((string) $plan->personnel_source_key)),
                'personnel_name_snapshot' => trim((string) $plan->personnel_name_snapshot),
            ];
        })->all();

        return $this->build(
            $period,
            $allSites ? null : $site,
            $plans,
            $this->positionsFor($plans),
        );
    }

    /**
     * @param  list<array{site_code: string, date: string, shift_code: string, personnel_source_key: string, personnel_name_snapshot: string}>  $plans
     * @param  array<string, string>  $positionsBySid
     * @return array<string, mixed>
     */
    public function build(
        ControlRoomIsoWeekPeriod $period,
        ?ControlRoomSiteCode $site,
        array $plans,
        array $positionsBySid = [],
    ): array {
        $days = $this->weekDays($period);
        $dateSet = array_fill_keys(array_column($days, 'date'), true);
        $people = [];

        foreach ($plans as $plan) {
            $date = (string) ($plan['date'] ?? '');
            if (! isset($dateSet[$date])) {
                continue;
            }
            $sid = strtoupper(trim((string) ($plan['personnel_source_key'] ?? '')));
            $siteCode = (string) ($plan['site_code'] ?? '');
            if ($sid === '' || $siteCode === '') {
                continue;
            }
            $key = $siteCode.'|'.$sid;
            if (! isset($people[$key])) {
                $people[$key] = [
                    'site_code' => $siteCode,
                    'sid' => $sid,
                    'name' => (string) ($plan['personnel_name_snapshot'] ?? $sid),
                    'shifts' => [],
                    'cells' => array_fill_keys(array_keys($dateSet), []),
                ];
            }
            if ($people[$key]['name'] === '' || $people[$key]['name'] === $sid) {
                $name = trim((string) ($plan['personnel_name_snapshot'] ?? ''));
                if ($name !== '') {
                    $people[$key]['name'] = $name;
                }
            }
            $shift = strtoupper(trim((string) ($plan['shift_code'] ?? '')));
            if ($shift === '') {
                continue;
            }
            $people[$key]['shifts'][$shift] = true;
            $label = $this->shiftLabel($shift);
            if (! in_array($label, $people[$key]['cells'][$date], true)) {
                $people[$key]['cells'][$date][] = $label;
            }
        }

        $grouped = [];
        foreach ($people as $person) {
            $groupKey = $this->groupKey(
                $positionsBySid[$person['sid']] ?? '',
                $person['shifts'],
            );
            $grouped[$groupKey][] = $this->presentRow($person, $days);
        }

        $groups = [];
        foreach (self::GROUPS as $key => $meta) {
            $rows = $grouped[$key] ?? [];
            if ($rows === []) {
                continue;
            }
            usort($rows, static function (array $a, array $b): int {
                $bySite = strcasecmp((string) $a['site'], (string) $b['site']);
                if ($bySite !== 0) {
                    return $bySite;
                }

                return strcasecmp((string) $a['name'], (string) $b['name']);
            });
            $groups[] = [
                'key' => $key,
                'title' => $meta['title'],
                'subtitle' => $meta['subtitle'],
                'rows' => $rows,
            ];
        }

        $slotCount = 0;
        foreach ($people as $person) {
            foreach ($person['cells'] as $labels) {
                $slotCount += count($labels);
            }
        }

        return [
            'year' => $period->year,
            'week' => $period->week,
            'week_start' => $period->start->toDateString(),
            'week_end' => $period->end->startOfDay()->toDateString(),
            'week_label' => $period->rangeLabel(),
            'scope' => $site === null ? 'all' : 'site',
            'site' => $site?->value,
            'site_label' => $site?->label() ?? 'Semua site',
            'days' => $days,
            'groups' => $groups,
            'people_count' => count($people),
            'slot_count' => $slotCount,
        ];
    }

    /**
     * @param  list<array{personnel_source_key?: string}>  $plans
     * @return array<string, string>
     */
    public function positionsFor(array $plans): array
    {
        $sids = [];
        foreach ($plans as $plan) {
            $sid = strtoupper(trim((string) ($plan['personnel_source_key'] ?? '')));
            if ($sid !== '') {
                $sids[$sid] = $sid;
            }
        }
        if ($sids === []) {
            return [];
        }

        try {
            $rows = Employee::query()
                ->select(['sid', 'position'])
                ->whereIn('sid', array_values($sids))
                ->get();
        } catch (Throwable) {
            return [];
        }

        $positions = [];
        foreach ($rows as $row) {
            $sid = strtoupper(trim((string) ($row->sid ?? '')));
            if ($sid === '') {
                continue;
            }
            $positions[$sid] = trim((string) ($row->position ?? ''));
        }

        return $positions;
    }

    /**
     * @return list<array{date: string, label: string, display: string, header: string}>
     */
    private function weekDays(ControlRoomIsoWeekPeriod $period): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $period->start->addDays($i);
            $days[] = [
                'date' => $date->toDateString(),
                'label' => self::DAY_SHORT[(int) $date->dayOfWeek] ?? $date->toDateString(),
                'display' => $date->locale('id')->translatedFormat('j M'),
                'header' => $date->format('d/m/Y'),
            ];
        }

        return $days;
    }

    /**
     * @param  array<string, true>  $shifts
     */
    private function groupKey(string $position, array $shifts): string
    {
        $normalized = mb_strtolower($position);
        if ($normalized !== '') {
            if (str_contains($normalized, 'superintendent') || str_contains($normalized, 'superintend')) {
                return self::GROUP_SUPERINTENDENT;
            }
            if (str_contains($normalized, 'evaluator')) {
                return self::GROUP_EVALUATOR;
            }
            if (str_contains($normalized, 'foreman') || str_contains($normalized, 'mandor')) {
                return self::GROUP_FOREMAN;
            }
            if (str_contains($normalized, 'supervisor') || str_contains($normalized, 'pengawas')) {
                return self::GROUP_SUPERVISOR;
            }
        }

        if (isset($shifts['S2']) && ! isset($shifts['S1'])) {
            return self::GROUP_FOREMAN;
        }
        if (isset($shifts['S1'])) {
            return self::GROUP_SUPERVISOR;
        }

        return self::GROUP_OTHER;
    }

    /**
     * @param  array{site_code: string, sid: string, name: string, cells: array<string, list<string>>}  $person
     * @param  list<array{date: string}>  $days
     * @return array{site: string, sid: string, name: string, cells: list<array{date: string, labels: list<string>, text: string, tone: string}>}
     */
    private function presentRow(array $person, array $days): array
    {
        $site = ControlRoomSiteCode::tryFrom($person['site_code']);
        $cells = [];
        foreach ($days as $day) {
            $labels = $person['cells'][$day['date']] ?? [];
            $cells[] = [
                'date' => $day['date'],
                'labels' => $labels,
                'text' => implode(' · ', $labels),
                'tone' => $this->cellTone($labels),
            ];
        }

        return [
            'site' => $site?->label() ?? $person['site_code'],
            'sid' => $person['sid'],
            'name' => $person['name'] !== '' ? $person['name'] : $person['sid'],
            'cells' => $cells,
        ];
    }

    /**
     * @param  list<string>  $labels
     */
    private function cellTone(array $labels): string
    {
        $joined = mb_strtolower(implode(' ', $labels));
        $hasS1 = str_contains($joined, 'shift 1');
        $hasS2 = str_contains($joined, 'shift 2');
        if ($hasS1 && $hasS2) {
            return 'mix';
        }
        if ($hasS2) {
            return 's2';
        }
        if ($hasS1) {
            return 's1';
        }

        return $labels === [] ? 'empty' : 's1';
    }

    private function shiftLabel(string $shift): string
    {
        $code = ControlRoomShiftCode::tryFrom($shift);

        return $code?->label() ?? $shift;
    }
}
