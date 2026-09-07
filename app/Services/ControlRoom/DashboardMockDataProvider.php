<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Services\ControlRoom\Metrics\SapAchievement;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Pencapaian Personil dan KPI header dari jadwal + absen + SAP nyata.
 * Coverage ranking site masih mockup.
 */
final class DashboardMockDataProvider
{
    public function __construct(
        private readonly SapAchievement $sapAchievement = new SapAchievement(),
    ) {}

    /**
     * @param  list<array<string, mixed>>  $scheduleDays
     * @param  array<string, array{hazard: int, inspeksi: int, observasi: int}>  $sapCountsBySidDate
     * @param  array{
     *     pareto?: array{s1: list<array{hour: int, count: int, cumulative: float}>, s2: list<array{hour: int, count: int, cumulative: float}>},
     *     highlight?: array{goldenRules: list<array{name: string, count: int}>, blindspotCount: int, blindspotTotal: int, tbcPercentage: ?float},
     *     quality?: list<array<string, mixed>>,
     *     personnelCoverage?: list<array{name: string, lokasi: int, kritis: int, lead: bool}>
     * }  $insights
     * @param  list<array<string, mixed>>  $previousScheduleDays
     * @param  array<string, array{hazard: int, inspeksi: int, observasi: int}>  $previousSapCounts
     * @return array<string, mixed>
     */
    public function build(
        CarbonInterface $weekStart,
        array $scheduleDays = [],
        array $sapCountsBySidDate = [],
        bool $sapLoaded = false,
        array $insights = [],
        array $previousScheduleDays = [],
        array $previousSapCounts = [],
        bool $previousSapLoaded = false,
    ): array {
        $achievementRows = $this->achievementRowsFromSchedule($scheduleDays, $sapCountsBySidDate, $sapLoaded);
        $previousRows = $this->achievementRowsFromSchedule($previousScheduleDays, $previousSapCounts, $previousSapLoaded);
        $kpi = $this->kpiCards(
            $achievementRows,
            $sapLoaded,
            isset($insights['highlight']['tbcPercentage'])
                ? $insights['highlight']['tbcPercentage']
                : null,
            $previousRows,
            $previousSapLoaded,
        );
        if ($achievementRows === []) {
            $achievementRows = $this->personnelAchievementFallback($weekStart);
        }

        return [
            'kpi' => $kpi,
            'achievement' => $achievementRows,
            'achievementGroups' => $this->groupAchievement($achievementRows),
            'personnelCoverage' => $insights['personnelCoverage'] ?? $this->personnelCoverageFrom($achievementRows),
            'coverageRanking' => $this->coverageRanking(),
            'pareto' => $insights['pareto'] ?? ['s1' => [], 's2' => []],
            'highlight' => $insights['highlight'] ?? [
                'goldenRules' => [],
                'blindspotCount' => 0,
                'blindspotTotal' => 0,
                'tbcPercentage' => null,
            ],
            'quality' => $insights['quality'] ?? [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $scheduleDays
     * @param  array<string, array{hazard: int, inspeksi: int, observasi: int}>  $sapCountsBySidDate
     * @return list<array<string, mixed>>
     */
    private function achievementRowsFromSchedule(array $scheduleDays, array $sapCountsBySidDate, bool $sapLoaded): array
    {
        $rows = [];
        foreach ($scheduleDays as $day) {
            $date = (string) ($day['date'] ?? '');
            if ($date === '') {
                continue;
            }

            foreach (['s1', 's2'] as $shiftKey) {
                foreach ($day[$shiftKey] ?? [] as $person) {
                    $name = (string) ($person['name'] ?? '—');
                    $rows[] = $this->achievementRow(
                        $date,
                        $name,
                        $shiftKey === 's1' ? 'S1' : 'S2',
                        $this->attendancePctFromStatus((string) ($person['status'] ?? '')),
                        $person['checkinout'] ?? [],
                        (string) ($person['sid'] ?? ''),
                        $sapCountsBySidDate,
                        $sapLoaded,
                    );
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function personnelAchievementFallback(CarbonInterface $weekStart): array
    {
        $start = CarbonImmutable::parse($weekStart);
        $people = [
            ['Budi Santoso', 'S1', 100.0],
            ['Siti Rahayu', 'S1', 80.0],
            ['Ahmad Fauzi', 'S2', 60.0],
            ['Dewi Lestari', 'S2', 100.0],
            ['Rudi Hartono', 'S1', 85.0],
        ];

        $rows = [];
        for ($i = 0; $i < 4; $i++) {
            $date = $start->addDays($i)->toDateString();
            foreach ($people as [$name, $shift, $attendance]) {
                $rows[] = $this->achievementRow($date, $name, $shift, $attendance, $this->sampleTaps($shift, $date), '', [], false);
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $taps
     * @param  array<string, array{hazard: int, inspeksi: int, observasi: int}>  $sapCountsBySidDate
     * @return array<string, mixed>
     */
    private function achievementRow(
        string $date,
        string $name,
        string $shift,
        ?float $attendancePct,
        array $taps,
        string $sid = '',
        array $sapCountsBySidDate = [],
        bool $sapLoaded = false,
    ): array {
        $sid = strtoupper(trim($sid));
        $emptyCounts = ['hazard' => 0, 'inspeksi' => 0, 'observasi' => 0];
        $counts = $sapCountsBySidDate[$sid.'|'.$date] ?? $emptyCounts;
        $sap = ($sapLoaded && $sid !== '' && $attendancePct !== null)
            ? $this->sapAchievement->percentage($counts)
            : null;

        return [
            'date' => $date,
            'date_label' => CarbonImmutable::parse($date)->format('n/j/Y'),
            'name' => $name,
            'shift' => $shift,
            'sid' => $sid,
            'attendance_pct' => $attendancePct,
            'sap' => $sap,
            'sap_counts' => $counts,
            'sap_hint' => $this->sapHint($counts, $sapLoaded, $sid, $attendancePct),
            'tbc' => null,
            'checkinout' => $taps,
        ];
    }

    /**
     * @param  array{hazard: int, inspeksi: int, observasi: int}  $counts
     */
    private function sapHint(array $counts, bool $sapLoaded, string $sid, ?float $attendancePct): string
    {
        if (! $sapLoaded) {
            return 'Sumber SAP belum termuat.';
        }
        if ($sid === '') {
            return 'SID kosong — % SAP tidak dihitung.';
        }
        if ($attendancePct === null) {
            return 'Menunggu absen — % SAP dihitung setelah status kehadiran ada.';
        }

        $mark = static fn (int $n): string => $n >= 1 ? 'ada' : 'belum';
        $observasi = (int) ($counts['observasi'] ?? 0) + (int) ($counts['oak'] ?? 0);

        return 'Target 1 Hazard, 1 Inspeksi, 1 Observasi/OAK. Hazard: '.$mark((int) ($counts['hazard'] ?? 0))
            .', Inspeksi: '.$mark((int) ($counts['inspeksi'] ?? 0))
            .', Observasi/OAK: '.$mark($observasi).'.';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{date: string, date_label: string, rows: list<array<string, mixed>>}>
     */
    private function groupAchievement(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = (string) $row['date'];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'date' => $key,
                    'date_label' => (string) $row['date_label'],
                    'rows' => [],
                ];
            }
            $groups[$key]['rows'][] = $row;
        }

        return array_values($groups);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{name: string, lokasi: int, kritis: int, lead: bool}>
     */
    private function personnelCoverageFrom(array $rows): array
    {
        $byName = [];
        foreach ($rows as $row) {
            $name = (string) $row['name'];
            if ($name === '' || $name === '—') {
                continue;
            }
            $byName[$name] = $name;
        }

        ksort($byName, SORT_NATURAL | SORT_FLAG_CASE);

        $coverage = [];
        foreach ($byName as $name) {
            $coverage[] = [
                'name' => $name,
                'lokasi' => 0,
                'kritis' => 0,
                'lead' => false,
            ];
        }

        if ($coverage !== []) {
            $coverage[0]['lead'] = true;
        }

        return $coverage;
    }

    private function attendancePctFromStatus(string $status): ?float
    {
        return match ($status) {
            'sesuai', 'menggantikan', 'tidak_dijadwalkan' => 100.0,
            'tidak_hadir' => 0.0,
            default => null,
        };
    }

    /**
     * @return list<array{time: string, date_label: string, type: string, type_label: string, gate: string, passed: bool}>
     */
    private function sampleTaps(string $shift, string $date): array
    {
        if ($shift === 'S2') {
            return [
                ['time' => '18:01', 'date_label' => CarbonImmutable::parse($date)->format('d M'), 'type' => 'in', 'type_label' => 'Check-in', 'gate' => 'POS 2', 'passed' => true],
                ['time' => '07:30', 'date_label' => CarbonImmutable::parse($date)->addDay()->format('d M'), 'type' => 'out', 'type_label' => 'Check-out', 'gate' => 'POS 2', 'passed' => true],
            ];
        }

        return [
            ['time' => '07:30', 'date_label' => CarbonImmutable::parse($date)->format('d M'), 'type' => 'in', 'type_label' => 'Check-in', 'gate' => 'POS 1', 'passed' => true],
            ['time' => '12:20', 'date_label' => CarbonImmutable::parse($date)->format('d M'), 'type' => 'out', 'type_label' => 'Check-out', 'gate' => 'POS 1', 'passed' => true],
            ['time' => '12:58', 'date_label' => CarbonImmutable::parse($date)->format('d M'), 'type' => 'in', 'type_label' => 'Check-in', 'gate' => 'POS 1', 'passed' => true],
            ['time' => '17:32', 'date_label' => CarbonImmutable::parse($date)->format('d M'), 'type' => 'out', 'type_label' => 'Check-out', 'gate' => 'POS 1', 'passed' => true],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $previousRows
     * @return list<array{label: string, value: string, progress: float, delta: ?float, deltaLabel: string, icon: string, color: string, formula: string}>
     */
    private function kpiCards(
        array $rows,
        bool $sapLoaded,
        ?float $tbcPercentage,
        array $previousRows,
        bool $previousSapLoaded,
    ): array {
        $attendance = $this->averageField($rows, 'attendance_pct');
        $sap = $sapLoaded ? $this->averageField($rows, 'sap') : null;
        $previousAttendance = $this->averageField($previousRows, 'attendance_pct');
        $previousSap = $previousSapLoaded ? $this->averageField($previousRows, 'sap') : null;

        return [
            $this->kpiCard(
                label: '% Total Kehadiran',
                value: $attendance,
                delta: $this->delta($attendance, $previousAttendance),
                icon: 'ri-user-follow-line',
                color: 'success',
                formula: 'Rata-rata kehadiran slot jaga: sesuai/menggantikan/tidak dijadwalkan = 100%, tidak hadir = 0%. Belum absen tidak dihitung.',
            ),
            $this->kpiCard(
                label: '% Avg SAP',
                value: $sap,
                delta: $this->delta($sap, $previousSap),
                icon: 'ri-file-list-3-line',
                color: 'primary',
                formula: 'Rata-rata % SAP orang jaga. Target per slot: 1 Hazard + 1 Inspeksi + 1 Observasi/OAK.',
            ),
            $this->kpiCard(
                label: 'Ratio TBC',
                value: $tbcPercentage,
                delta: null,
                icon: 'ri-shield-check-line',
                color: 'danger',
                formula: 'Jumlah temuan TBC HSECM / total hazard + inspeksi minggu ini. Kosong bila tabel TBC belum ada.',
            ),
        ];
    }

    /**
     * @return array{label: string, value: string, progress: float, delta: ?float, deltaLabel: string, icon: string, color: string, formula: string}
     */
    private function kpiCard(
        string $label,
        ?float $value,
        ?float $delta,
        string $icon,
        string $color,
        string $formula,
    ): array {
        return [
            'label' => $label,
            'value' => $value === null ? '—' : $this->formatKpi($value).'%',
            'progress' => $value ?? 0.0,
            'delta' => $delta,
            'deltaLabel' => 'vs minggu lalu',
            'icon' => $icon,
            'color' => $color,
            'formula' => $formula,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function averageField(array $rows, string $key): ?float
    {
        $values = [];
        foreach ($rows as $row) {
            if ($row[$key] !== null) {
                $values[] = (float) $row[$key];
            }
        }
        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 1);
    }

    private function delta(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return round($current - $previous, 1);
    }

    private function formatKpi(float $value): string
    {
        if (abs($value - round($value)) < 0.05) {
            return number_format($value, 0);
        }

        return number_format($value, 1);
    }

    /**
     * @return list<array{rank: int, name: string, non_critical: int, critical: int, score: int}>
     */
    private function coverageRanking(): array
    {
        $rows = [
            ['name' => 'BMO 1', 'non_critical' => 18, 'critical' => 10],
            ['name' => 'GMO', 'non_critical' => 14, 'critical' => 8],
            ['name' => 'BMO 2', 'non_critical' => 12, 'critical' => 6],
            ['name' => 'LMO', 'non_critical' => 11, 'critical' => 4],
            ['name' => 'PMO', 'non_critical' => 9, 'critical' => 3],
        ];

        foreach ($rows as &$row) {
            $row['score'] = $row['non_critical'] * 1 + $row['critical'] * 2;
        }
        unset($row);

        usort($rows, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_values(array_map(
            fn (int $rank, array $row): array => ['rank' => $rank + 1, ...$row],
            array_keys($rows),
            $rows
        ));
    }
}
