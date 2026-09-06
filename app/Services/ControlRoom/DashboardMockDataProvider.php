<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;

/**
 * MOCKUP SEMENTARA — plan-OCR.md T6.2-T6.8. Panel KPI sungguhan menunggu
 * Fase 4 (snapshot SAP) & Fase 5 (agregasi mingguan), yang menunggu keputusan
 * desain di plan-OCR.md 0.6 poin 6 (reuse mv_sap_scorecard_mingguan?) dan
 * beberapa open question lain (Sheet ID TBC, dst — lihat Lampiran D).
 *
 * Class ini HANYA untuk memberi gambaran visual layout dashboard sebelum
 * pipeline data asli selesai. Semua angka di sini FIKTIF, bukan hasil query.
 * HAPUS class ini (dan ganti pemanggilnya di DashboardController) begitu
 * T5.2 (job agregasi) sudah menghasilkan data sungguhan.
 */
final class DashboardMockDataProvider
{
    /**
     * @return array<string, mixed>
     */
    public function build(CarbonInterface $weekStart): array
    {
        return [
            'kpi' => $this->kpiCards(),
            'schedule' => $this->schedulePlanning($weekStart),
            'achievement' => $this->personnelAchievement(),
            'coverageRanking' => $this->coverageRanking(),
            'pareto' => $this->paretoHours(),
            'highlight' => $this->highlightFindings(),
            'quality' => $this->qualityPanel(),
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
            ['label' => 'Ratio SAP (dgn bonus)', 'value' => '108%', 'progress' => 100.0, 'delta' => 6.0, 'deltaLabel' => 'bonus coaching', 'icon' => 'ri-award-line', 'color' => 'success', 'formula' => '%SAP dasar + bonus coaching di atas 100%'],
            ['label' => 'Ratio TBC', 'value' => '34.2%', 'progress' => 34.2, 'delta' => 5.1, 'deltaLabel' => 'vs minggu lalu', 'icon' => 'ri-shield-check-line', 'color' => 'danger', 'formula' => 'temuan tervalidasi TBC / total hazard+inspeksi'],
        ];
    }

    /**
     * Kalender minggu — 1 orang Shift 1 + 1 orang Shift 2 per hari.
     *
     * @return array{days: list<array{date: string, label: string, day_number: string, month_short: string, is_today: bool, is_weekend: bool, s1: list<array{name: string, short_name: string, initial: string, planned: bool, status: string}>, s2: list<array{name: string, short_name: string, initial: string, planned: bool, status: string}>}>}
     */
    private function schedulePlanning(CarbonInterface $weekStart): array
    {
        $statuses = ['sesuai', 'menggantikan', 'tidak_hadir', 'tidak_dijadwalkan', 'anomali'];
        $names = ['BUDI SANTOSO', 'SITI RAHAYU', 'AHMAD FAUZI', 'DEWI LESTARI', 'RUDI HARTONO'];
        $origin = CarbonImmutable::parse($weekStart)->startOfDay();
        $today = now()->toDateString();
        $nameCount = count($names);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $origin->addDays($i);
            $days[] = [
                'date' => $date->toDateString(),
                'label' => $date->locale('id')->translatedFormat('D'),
                'day_number' => $date->format('d'),
                'month_short' => $date->locale('id')->translatedFormat('M'),
                'is_today' => $date->toDateString() === $today,
                'is_weekend' => $date->isWeekend(),
                's1' => [$this->schedulePerson($names[$i % $nameCount], $statuses[$i % 5])],
                's2' => [$this->schedulePerson($names[($i + 2) % $nameCount], $statuses[($i + 2) % 5])],
            ];
        }

        return ['days' => $days];
    }

    /**
     * @return array{name: string, short_name: string, initial: string, planned: bool, status: string}
     */
    private function schedulePerson(string $name, string $status): array
    {
        $parts = explode(' ', $name);

        return [
            'name' => $name,
            'short_name' => $parts[0],
            'initial' => mb_substr($name, 0, 1),
            'planned' => $status !== 'tidak_dijadwalkan',
            'status' => $status,
        ];
    }

    /**
     * @return list<array{date: string, name: string, attendance: string, sap: float, tbc: ?float}>
     */
    private function personnelAchievement(): array
    {
        $data = [
            ['BUDI SANTOSO', 100.0, 45.0],
            ['SITI RAHAYU', 66.7, 20.0],
            ['AHMAD FAUZI', 33.3, null],
            ['DEWI LESTARI', 100.0, 60.0],
            ['RUDI HARTONO', 83.3, 30.0],
        ];

        $rows = [];
        foreach ($data as [$name, $sap, $tbc]) {
            $rows[] = [
                'date' => now()->subDay()->format('d M Y'),
                'name' => $name,
                'attendance' => 'Hadir',
                'sap' => $sap,
                'tbc' => $tbc,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{rank: int, name: string, non_critical: int, critical: int, score: int}>
     */
    private function coverageRanking(): array
    {
        $rows = [
            ['name' => 'BUDI SANTOSO', 'non_critical' => 5, 'critical' => 10],
            ['name' => 'DEWI LESTARI', 'non_critical' => 13, 'critical' => 0],
            ['name' => 'AHMAD FAUZI', 'non_critical' => 8, 'critical' => 4],
            ['name' => 'SITI RAHAYU', 'non_critical' => 3, 'critical' => 2],
            ['name' => 'RUDI HARTONO', 'non_critical' => 6, 'critical' => 1],
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
            ['name' => 'BUDI SANTOSO', 'total_findings' => 20, 'distinct_categories' => 14, 'variety_score' => 0.7, 'tbc' => 6, 'gr' => 3, 'blindspot' => 1],
            ['name' => 'SITI RAHAYU', 'total_findings' => 8, 'distinct_categories' => 2, 'variety_score' => 0.25, 'tbc' => 1, 'gr' => 0, 'blindspot' => 3],
            ['name' => 'AHMAD FAUZI', 'total_findings' => 15, 'distinct_categories' => 9, 'variety_score' => 0.6, 'tbc' => 4, 'gr' => 2, 'blindspot' => 0],
            ['name' => 'DEWI LESTARI', 'total_findings' => 25, 'distinct_categories' => 22, 'variety_score' => 0.88, 'tbc' => 9, 'gr' => 5, 'blindspot' => 0],
            ['name' => 'RUDI HARTONO', 'total_findings' => 12, 'distinct_categories' => 5, 'variety_score' => 0.42, 'tbc' => 3, 'gr' => 1, 'blindspot' => 2],
        ];
    }
}
