<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * MOCKUP SEMENTARA — plan-OCR.md T6.2-T6.8. Panel KPI sungguhan menunggu
 * Fase 4 (snapshot SAP) & Fase 5 (agregasi mingguan). Semua angka FIKTIF.
 */
final class DashboardMockDataProvider
{
    /**
     * @param  list<array<string, mixed>>  $scheduleDays
     * @return array<string, mixed>
     */
    public function build(CarbonInterface $weekStart, array $scheduleDays = []): array
    {
        $achievementRows = $this->achievementRowsFromSchedule($scheduleDays);
        if ($achievementRows === []) {
            $achievementRows = $this->personnelAchievementFallback($weekStart);
        }

        return [
            'kpi' => $this->kpiCards(),
            'achievement' => $achievementRows,
            'achievementGroups' => $this->groupAchievement($achievementRows),
            'personnelCoverage' => $this->personnelCoverageFrom($achievementRows),
            'coverageRanking' => $this->coverageRanking(),
            'pareto' => $this->paretoHours(),
            'highlight' => $this->highlightFindings(),
            'quality' => $this->qualityPanel(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $scheduleDays
     * @return list<array<string, mixed>>
     */
    private function achievementRowsFromSchedule(array $scheduleDays): array
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
                $rows[] = $this->achievementRow($date, $name, $shift, $attendance, $this->sampleTaps($shift, $date));
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $taps
     * @return array<string, mixed>
     */
    private function achievementRow(string $date, string $name, string $shift, ?float $attendancePct, array $taps): array
    {
        return [
            'date' => $date,
            'date_label' => CarbonImmutable::parse($date)->format('n/j/Y'),
            'name' => $name,
            'shift' => $shift,
            'attendance_pct' => $attendancePct,
            'sap' => $this->mockMetric($name.$date, 'sap'),
            'tbc' => $this->mockMetric($name.$date, 'tbc'),
            'checkinout' => $taps,
        ];
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

        if ($byName === []) {
            $byName = ['Budi Santoso' => 'Budi Santoso', 'Siti Rahayu' => 'Siti Rahayu', 'Ahmad Fauzi' => 'Ahmad Fauzi'];
        }

        ksort($byName, SORT_NATURAL | SORT_FLAG_CASE);

        $coverage = [];
        foreach ($byName as $name) {
            $seed = abs(crc32($name));
            $coverage[] = [
                'name' => $name,
                'lokasi' => 4 + ($seed % 22),
                'kritis' => $seed % 12,
                'lead' => false,
            ];
        }

        usort($coverage, fn (array $a, array $b): int => $b['lokasi'] <=> $a['lokasi']);
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

    private function mockMetric(string $seed, string $kind): ?float
    {
        $n = abs(crc32($seed.$kind));
        if ($kind === 'tbc' && ($n % 9) === 0) {
            return null;
        }

        $choices = [100.0, 100.0, 97.0, 80.0, 67.0, 50.0, 33.0];

        return $choices[$n % count($choices)];
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
     * @return list<array{label: string, value: string, progress: float, delta: float, deltaLabel: string, icon: string, color: string, formula: string}>
     */
    private function kpiCards(): array
    {
        return [
            ['label' => '% Total Kehadiran', 'value' => '87.5%', 'progress' => 87.5, 'delta' => 2.3, 'deltaLabel' => 'vs minggu lalu', 'icon' => 'ri-user-follow-line', 'color' => 'success', 'formula' => 'hadir_sesuai_jadwal + hadir_menggantikan / total jadwal'],
            ['label' => '% Avg SAP', 'value' => '92.1%', 'progress' => 92.1, 'delta' => -1.4, 'deltaLabel' => 'vs minggu lalu', 'icon' => 'ri-file-list-3-line', 'color' => 'primary', 'formula' => 'rata-rata SapAchievement seluruh personil'],
            ['label' => 'Coverage Detail Lokasi', 'value' => '68 / 95', 'progress' => 71.6, 'delta' => 4.1, 'deltaLabel' => 'lokasi baru tercover', 'icon' => 'ri-map-pin-line', 'color' => 'info', 'formula' => 'lokasi tersentuh SAP / total lokasi terdaftar'],
            ['label' => 'Coverage Area Kritis', 'value' => '24 / 30', 'progress' => 80.0, 'delta' => 0.0, 'deltaLabel' => 'tidak berubah', 'icon' => 'ri-alarm-warning-line', 'color' => 'warning', 'formula' => 'area kritis tersentuh SAP / total area kritis'],
            ['label' => 'Ratio SAP dgn bonus', 'value' => '108%', 'progress' => 100.0, 'delta' => 6.0, 'deltaLabel' => 'bonus coaching', 'icon' => 'ri-award-line', 'color' => 'success', 'formula' => '%SAP dasar + bonus coaching di atas 100%'],
            ['label' => 'Ratio TBC', 'value' => '34.2%', 'progress' => 34.2, 'delta' => 5.1, 'deltaLabel' => 'vs minggu lalu', 'icon' => 'ri-shield-check-line', 'color' => 'danger', 'formula' => 'temuan tervalidasi TBC / total hazard+inspeksi'],
        ];
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

    /**
     * @return array{s1: list<array{hour: int, count: int, cumulative: float}>, s2: list<array{hour: int, count: int, cumulative: float}>}
     */
    private function paretoHours(): array
    {
        $s1Counts = [7 => 42, 8 => 65, 9 => 38, 10 => 51, 11 => 29, 12 => 15, 13 => 22, 14 => 30, 15 => 18, 16 => 12, 17 => 8];
        $s2Counts = [19 => 35, 20 => 48, 21 => 40, 22 => 25, 23 => 15, 0 => 10, 1 => 6, 2 => 4, 3 => 3, 4 => 5, 5 => 9];

        return [
            's1' => $this->toParetoSeries($s1Counts),
            's2' => $this->toParetoSeries($s2Counts),
        ];
    }

    /**
     * @param  array<int, int>  $counts
     * @return list<array{hour: int, count: int, cumulative: float}>
     */
    private function toParetoSeries(array $counts): array
    {
        arsort($counts);
        $total = array_sum($counts);
        $running = 0;
        $series = [];

        foreach ($counts as $hour => $count) {
            $running += $count;
            $series[] = [
                'hour' => $hour,
                'count' => $count,
                'cumulative' => $total > 0 ? round(($running / $total) * 100, 1) : 0.0,
            ];
        }

        return $series;
    }

    /**
     * @return array{goldenRules: list<array{name: string, count: int}>, blindspotCount: int, blindspotTotal: int, tbcPercentage: float}
     */
    private function highlightFindings(): array
    {
        return [
            'goldenRules' => [
                ['name' => 'Tidak Melanggar Golden Rules', 'count' => 142],
                ['name' => 'Isolasi Energi', 'count' => 38],
                ['name' => 'Bekerja di Ketinggian', 'count' => 27],
                ['name' => 'Alat Berat & Kendaraan', 'count' => 19],
                ['name' => 'Ruang Terbatas', 'count' => 8],
            ],
            'blindspotCount' => 12,
            'blindspotTotal' => 95,
            'tbcPercentage' => 34.2,
        ];
    }

    /**
     * @return list<array{name: string, total_findings: int, distinct_categories: int, variety_score: float, tbc: int, gr: int, blindspot: int}>
     */
    private function qualityPanel(): array
    {
        return [
            ['name' => 'Budi Santoso', 'total_findings' => 20, 'distinct_categories' => 14, 'variety_score' => 0.7, 'tbc' => 6, 'gr' => 3, 'blindspot' => 1],
            ['name' => 'Siti Rahayu', 'total_findings' => 8, 'distinct_categories' => 2, 'variety_score' => 0.25, 'tbc' => 1, 'gr' => 0, 'blindspot' => 3],
            ['name' => 'Ahmad Fauzi', 'total_findings' => 15, 'distinct_categories' => 9, 'variety_score' => 0.6, 'tbc' => 4, 'gr' => 2, 'blindspot' => 0],
            ['name' => 'Dewi Lestari', 'total_findings' => 25, 'distinct_categories' => 22, 'variety_score' => 0.88, 'tbc' => 9, 'gr' => 5, 'blindspot' => 0],
            ['name' => 'Rudi Hartono', 'total_findings' => 12, 'distinct_categories' => 5, 'variety_score' => 0.42, 'tbc' => 3, 'gr' => 1, 'blindspot' => 2],
        ];
    }
}
