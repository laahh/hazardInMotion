<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Services\ControlRoom\Metrics\SapAchievement;
use Carbon\CarbonImmutable;

/**
 * Tabel Pencapaian Pengawas (baris = orang, kolom = hari) dan kartu KPI.
 * Tanpa kolom kehadiran: pengawas tidak punya jadwal, jadi % SAP dihitung
 * untuk setiap hari yang sudah berjalan.
 */
final class PengawasAchievementBuilder
{
    public function __construct(
        private readonly SapAchievement $sapAchievement = new SapAchievement(),
    ) {}

    /**
     * @param  list<array{sid: string, name: string, site: \App\Enums\ControlRoomSiteCode}>  $people
     * @param  list<string>  $dates  hari berjalan (<= hari ini)
     * @param  array<string, array<string, int>>  $counts  key "SID|Y-m-d"
     * @param  array<string, array{matched: int, total: int, percent: ?float}>  $tbcMetaBySid
     * @return list<array<string, mixed>>
     */
    public function rows(array $people, array $dates, array $counts, bool $sapLoaded, array $tbcMetaBySid = []): array
    {
        $rows = [];
        foreach ($people as $person) {
            $cells = [];
            $activeDays = 0;
            $sapValues = [];
            foreach ($dates as $date) {
                $dayCounts = $counts[$person['sid'].'|'.$date] ?? ['hazard' => 0, 'inspeksi' => 0, 'observasi' => 0];
                $sap = $sapLoaded ? $this->sapAchievement->percentage($dayCounts) : null;
                if ($sap !== null) {
                    $sapValues[] = $sap;
                }
                if ($this->totalReports($dayCounts) > 0) {
                    $activeDays++;
                }
                $cells[$date] = [
                    'sap' => $sap,
                    'hint' => $this->sapHint($dayCounts, $sapLoaded),
                ];
            }

            $tbcMeta = $tbcMetaBySid[$person['sid']] ?? null;
            $rows[] = [
                'sid' => $person['sid'],
                'name' => $person['name'],
                'site' => $person['site']->value,
                'site_label' => $person['site']->label(),
                'cells' => $cells,
                'active_days' => $sapLoaded ? $activeDays : null,
                'running_days' => count($dates),
                'avg_sap' => $sapValues === [] ? null : round(array_sum($sapValues) / count($sapValues), 1),
                'tbc' => is_array($tbcMeta) ? ($tbcMeta['percent'] ?? null) : null,
                'tbc_hint' => $this->tbcHint($tbcMeta),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $previousRows
     * @return list<array<string, mixed>>
     */
    public function kpiCards(array $rows, ?float $tbcPercentage, array $previousRows): array
    {
        $active = $this->activeRate($rows);
        $sap = $this->average(array_column($rows, 'avg_sap'));

        return [
            $this->card('Pengawas dimonitor', (string) count($rows), 100.0, null, 'ri-team-line', 'info',
                'Jumlah pengawas pada site terpilih (daftar di config/control-room-pengawas.php).'),
            $this->card('% Hari Aktif', $this->pct($active), $active ?? 0.0, $this->delta($active, $this->activeRate($previousRows)),
                'ri-user-follow-line', 'success', 'Pengawas-hari dengan minimal 1 laporan SAP ÷ seluruh pengawas-hari yang sudah berjalan.'),
            $this->card('% Avg SAP', $this->pct($sap), $sap ?? 0.0, $this->delta($sap, $this->average(array_column($previousRows, 'avg_sap'))),
                'ri-file-list-3-line', 'primary', 'Rata-rata % SAP harian. Target per hari: 1 Hazard + 1 Inspeksi + 1 Observasi/OAK.'),
            $this->card('Ratio TBC', $this->pct($tbcPercentage), $tbcPercentage ?? 0.0, null, 'ri-shield-check-line', 'danger',
                'Valid TBC ÷ (Hazard + Inspeksi) semua pengawas minggu ini. Observasi/OAK tidak dihitung.'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function activeRate(array $rows): ?float
    {
        $active = 0;
        $total = 0;
        foreach ($rows as $row) {
            if ($row['active_days'] === null) {
                continue;
            }
            $active += (int) $row['active_days'];
            $total += (int) $row['running_days'];
        }

        return $total === 0 ? null : round($active / $total * 100, 1);
    }

    /**
     * @param  list<?float>  $values
     */
    private function average(array $values): ?float
    {
        $values = array_values(array_filter($values, static fn (mixed $v): bool => $v !== null));

        return $values === [] ? null : round(array_sum($values) / count($values), 1);
    }

    private function delta(?float $current, ?float $previous): ?float
    {
        return ($current === null || $previous === null) ? null : round($current - $previous, 1);
    }

    private function pct(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return (abs($value - round($value)) < 0.05 ? number_format($value, 0) : number_format($value, 1)).'%';
    }

    /**
     * @return array<string, mixed>
     */
    private function card(string $label, string $value, float $progress, ?float $delta, string $icon, string $color, string $formula): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'progress' => $progress,
            'delta' => $delta,
            'deltaLabel' => 'vs minggu lalu',
            'icon' => $icon,
            'color' => $color,
            'formula' => $formula,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function totalReports(array $counts): int
    {
        return (int) ($counts['hazard'] ?? 0) + (int) ($counts['inspeksi'] ?? 0)
            + (int) ($counts['observasi'] ?? 0) + (int) ($counts['oak'] ?? 0);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function sapHint(array $counts, bool $sapLoaded): string
    {
        if (! $sapLoaded) {
            return 'Sumber SAP belum termuat.';
        }

        return sprintf(
            'Hazard %d · Inspeksi %d · Observasi/OAK %d. Klik untuk detail.',
            (int) ($counts['hazard'] ?? 0),
            (int) ($counts['inspeksi'] ?? 0),
            (int) ($counts['observasi'] ?? 0) + (int) ($counts['oak'] ?? 0),
        );
    }

    /**
     * @param  array{matched: int, total: int, percent: ?float}|null  $meta
     */
    private function tbcHint(?array $meta): string
    {
        if ($meta === null) {
            return 'Sumber TBC belum termuat.';
        }
        if ((int) ($meta['total'] ?? 0) === 0) {
            return 'Tidak ada Hazard/Inspeksi minggu ini — % TBC tidak dihitung.';
        }

        return sprintf('%d dari %d Hazard/Inspeksi sudah valid TBC.', (int) $meta['matched'], (int) $meta['total']);
    }

    /**
     * @param  list<string>  $dates
     * @return list<array{date: string, weekday: string, label: string, is_today: bool}>
     */
    public function dayHeads(array $dates): array
    {
        $today = CarbonImmutable::now()->toDateString();

        return array_map(static function (string $date) use ($today): array {
            $day = CarbonImmutable::parse($date)->locale('id');

            return [
                'date' => $date,
                'weekday' => $day->translatedFormat('D'),
                'label' => $day->translatedFormat('d M'),
                'is_today' => $date === $today,
            ];
        }, $dates);
    }
}
