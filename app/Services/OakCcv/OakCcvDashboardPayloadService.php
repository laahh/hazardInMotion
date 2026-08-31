<?php

declare(strict_types=1);

namespace App\Services\OakCcv;

use App\Support\OakCcv\OakCcvCompanyClassifier;
use RuntimeException;

/**
 * Membaca agregat JSON OAK CCV (OBSERVASI AREA KRITIS) dan merakit payload dashboard.
 */
final class OakCcvDashboardPayloadService
{
    public function __construct(
        private readonly ?string $payloadPath = null,
    ) {}

    /**
     * @param array{site?: string, week?: string, group?: string, entity?: string} $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $raw = $this->loadRaw();
        $site = trim((string) ($filters['site'] ?? ''));
        $week = trim((string) ($filters['week'] ?? ''));
        $group = strtolower(trim((string) ($filters['group'] ?? 'all')));
        $entity = trim((string) ($filters['entity'] ?? ''));
        if (! in_array($group, ['all', 'bc', 'mitra'], true)) {
            $group = 'all';
        }

        $cube = $this->filterCube($raw['oak_cube'] ?? [], $site, $week, $group, $entity);
        $tools = $this->filterDim($raw['tools'] ?? [], $site, $group, $entity);
        $layers = $this->filterDim($raw['layers'] ?? [], $site, $group, $entity);
        $stopRows = $this->filterStop($raw['stop_rows'] ?? [], $site, $week, $group, $entity);

        $totalRows = (int) array_sum(array_column($cube, 'rows'));
        $totalTasks = (int) array_sum(array_column($cube, 'tasks'));
        $bcRows = (int) array_sum(array_map(static fn (array $r): int => ($r['group'] ?? '') === 'BC' ? (int) $r['rows'] : 0, $cube));
        $mitraRows = $totalRows - $bcRows;

        $weekly = $this->buildWeekly($cube, $raw['weeks'] ?? []);
        $trend = $this->trendFromWeekly($weekly);
        $dailyBcVsMitra = $this->buildDailyBcVsMitra($raw['daily_cube'] ?? [], $site, $week);

        $stopGaps = count($stopRows);
        $stopJobs = count(array_unique(array_map(static fn (array $r) => $r['task'] ?? null, $stopRows)));
        $stopMatched = count(array_unique(array_filter(array_map(
            static fn (array $r) => ! empty($r['matched_oak']) ? ($r['task'] ?? null) : null,
            $stopRows
        ))));

        $kpi = [
            'laporan_rows' => $totalRows,
            'laporan_tasks' => $totalTasks,
            'trend_pct' => $trend['pct'],
            'trend_label' => $trend['label'],
            'bc_rows' => $bcRows,
            'mitra_rows' => $mitraRows,
            'bc_pct' => $totalRows > 0 ? round(100 * $bcRows / $totalRows, 1) : 0.0,
            'mitra_pct' => $totalRows > 0 ? round(100 * $mitraRows / $totalRows, 1) : 0.0,
            'stop_gaps' => $stopGaps,
            'stop_jobs' => $stopJobs,
            'stop_matched' => $stopMatched,
            'stop_per_1000' => $totalTasks > 0 ? round(1000 * $stopJobs / $totalTasks, 2) : 0.0,
        ];

        $aktivitas = $this->aggregateNamed($cube, 'aktivitas', 10);
        $entities = $this->aggregateEntities($cube);
        $sites = $this->aggregateNamed($cube, 'site', 20);
        $stopByAkt = $this->aggregateStopAktivitas($stopRows);
        $heatmap = $this->buildHeatmap($cube, $raw['sites'] ?? []);
        $topMitra = $this->filterTopMitra($raw['top_mitra'] ?? [], $raw['mitra_by_site'] ?? [], $site, $group);
        $toolRows = $this->aggregateNamed($tools, 'tool', 12);
        $layerRows = $this->aggregateNamed($layers, 'layer', 10);

        $eval = $this->evaluation($kpi, $sites, $aktivitas, $stopByAkt, $raw['meta'] ?? [], $totalRows);

        $aktivitasColors = $this->paletteFor($aktivitas);
        $donut = $this->conicGradient($aktivitas, $aktivitasColors);

        return [
            'jenis_data' => (string) ($raw['jenis_data'] ?? 'OBSERVASI AREA KRITIS'),
            'source_file' => (string) ($raw['source_file'] ?? ''),
            'generated_at_utc' => (string) ($raw['generated_at_utc'] ?? ''),
            'meta' => $raw['meta'] ?? [],
            'filters' => [
                'site' => $site,
                'week' => $week,
                'group' => $group,
                'entity' => $entity,
                'sites' => $raw['sites'] ?? [],
                'weeks' => $raw['weeks'] ?? [],
                'entities' => OakCcvCompanyClassifier::ENTITY_ORDER,
            ],
            'kpi' => $kpi,
            'weekly' => $weekly,
            'daily_bc_vs_mitra' => $dailyBcVsMitra,
            'evaluation' => $eval,
            'aktivitas' => $aktivitas,
            'aktivitas_colors' => $aktivitasColors,
            'aktivitas_donut' => $donut,
            'stop_by_aktivitas' => $stopByAkt,
            'sites_rows' => $sites,
            'entities' => $entities,
            'heatmap' => $heatmap,
            'top_mitra' => $topMitra,
            'tools' => $toolRows,
            'layers' => $layerRows,
            'stop_rows' => $stopRows,
            'stop_weeks' => $this->stopWeeksFiltered($raw['stop_weeks'] ?? [], $week),
            'colors' => OakCcvCompanyClassifier::ENTITY_COLORS,
            'entity_companies' => OakCcvCompanyClassifier::ENTITY_COMPANIES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRaw(): array
    {
        $path = $this->payloadPath ?? resource_path('data/oak_ccv_dashboard.json');
        if (! is_readable($path)) {
            throw new RuntimeException('File agregat OAK CCV tidak ditemukan: '.$path);
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Gagal membaca agregat OAK CCV.');
        }
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new RuntimeException('Agregat OAK CCV bukan JSON valid.');
        }

        return $data;
    }

    /**
     * @param list<array<string, mixed>> $cube
     * @return list<array<string, mixed>>
     */
    private function filterCube(array $cube, string $site, string $week, string $group, string $entity): array
    {
        return array_values(array_filter($cube, function (array $row) use ($site, $week, $group, $entity): bool {
            if ($site !== '' && (string) ($row['site'] ?? '') !== $site) {
                return false;
            }
            if ($week !== '' && (string) ($row['week'] ?? '') !== $week) {
                return false;
            }
            if ($group === 'bc' && ($row['group'] ?? '') !== 'BC') {
                return false;
            }
            if ($group === 'mitra' && ($row['group'] ?? '') !== 'Mitra') {
                return false;
            }
            if ($entity !== '' && (string) ($row['entity'] ?? '') !== $entity) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function filterDim(array $rows, string $site, string $group, string $entity): array
    {
        return array_values(array_filter($rows, function (array $row) use ($site, $group, $entity): bool {
            if ($site !== '' && (string) ($row['site'] ?? '') !== $site) {
                return false;
            }
            $ent = (string) ($row['entity'] ?? '');
            $g = OakCcvCompanyClassifier::isBcEntity($ent) ? 'BC' : 'Mitra';
            if ($group === 'bc' && $g !== 'BC') {
                return false;
            }
            if ($group === 'mitra' && $g !== 'Mitra') {
                return false;
            }
            if ($entity !== '' && $ent !== $entity) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function filterStop(array $rows, string $site, string $week, string $group, string $entity): array
    {
        return array_values(array_filter($rows, function (array $row) use ($site, $week, $group, $entity): bool {
            if ($week !== '' && (string) ($row['week'] ?? '') !== $week) {
                return false;
            }
            $oakSite = (string) ($row['oak_site'] ?? '');
            $oakEntity = (string) ($row['oak_entity'] ?? '');
            $matched = ! empty($row['matched_oak']);

            if ($site !== '') {
                if (! $matched || $oakSite !== $site) {
                    return false;
                }
            }
            if ($group === 'bc' || $group === 'mitra' || $entity !== '') {
                if (! $matched) {
                    return false;
                }
                $g = OakCcvCompanyClassifier::isBcEntity($oakEntity) ? 'BC' : 'Mitra';
                if ($group === 'bc' && $g !== 'BC') {
                    return false;
                }
                if ($group === 'mitra' && $g !== 'Mitra') {
                    return false;
                }
                if ($entity !== '' && $oakEntity !== $entity) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param list<array<string, mixed>> $cube
     * @param list<array<string, mixed>> $weekMeta
     * @return list<array<string, mixed>>
     */
    private function buildWeekly(array $cube, array $weekMeta): array
    {
        $byWeek = [];
        foreach ($weekMeta as $w) {
            $key = (string) ($w['week'] ?? '');
            if ($key === '') {
                continue;
            }
            $byWeek[$key] = [
                'week' => $key,
                'label' => (string) ($w['label'] ?? $key),
                'rows' => 0,
                'bc' => 0,
                'mitra' => 0,
            ];
        }
        foreach ($cube as $row) {
            $key = (string) ($row['week'] ?? '');
            if ($key === '') {
                continue;
            }
            if (! isset($byWeek[$key])) {
                $byWeek[$key] = [
                    'week' => $key,
                    'label' => $key,
                    'rows' => 0,
                    'bc' => 0,
                    'mitra' => 0,
                ];
            }
            $n = (int) ($row['rows'] ?? 0);
            $byWeek[$key]['rows'] += $n;
            if (($row['group'] ?? '') === 'BC') {
                $byWeek[$key]['bc'] += $n;
            } else {
                $byWeek[$key]['mitra'] += $n;
            }
        }
        ksort($byWeek);
        $max = 0;
        foreach ($byWeek as $w) {
            $max = max($max, (int) $w['rows']);
        }
        $out = [];
        foreach ($byWeek as $w) {
            $rows = (int) $w['rows'];
            $out[] = [
                'week' => $w['week'],
                'label' => $w['label'],
                'rows' => $rows,
                'bc' => (int) $w['bc'],
                'mitra' => (int) $w['mitra'],
                'bar_height_pct' => $max > 0 ? round(100 * $rows / $max, 2) : 0.0,
                'bc_stack_pct' => $rows > 0 ? round(100 * (int) $w['bc'] / $rows, 2) : 0.0,
                'mitra_stack_pct' => $rows > 0 ? round(100 * (int) $w['mitra'] / $rows, 2) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Grafik harian BC vs mitra. Filter site & minggu tetap; grup/entitas diabaikan
     * supaya perbandingan kedua sisi selalu tampil.
     *
     * @param list<array<string, mixed>> $dailyCube
     * @return list<array{date: string, label: string, weekday: string, week: string, bc: int, mitra: int, rows: int, bc_bar_pct: float, mitra_bar_pct: float}>
     */
    private function buildDailyBcVsMitra(array $dailyCube, string $site, string $week): array
    {
        $byDate = [];
        foreach ($dailyCube as $row) {
            if ($site !== '' && (string) ($row['site'] ?? '') !== $site) {
                continue;
            }
            if ($week !== '' && (string) ($row['week'] ?? '') !== $week) {
                continue;
            }
            $date = (string) ($row['date'] ?? '');
            if ($date === '') {
                continue;
            }
            if (! isset($byDate[$date])) {
                $byDate[$date] = [
                    'date' => $date,
                    'week' => (string) ($row['week'] ?? ''),
                    'bc' => 0,
                    'mitra' => 0,
                ];
            }
            $n = (int) ($row['rows'] ?? 0);
            if (($row['group'] ?? '') === 'BC') {
                $byDate[$date]['bc'] += $n;
            } else {
                $byDate[$date]['mitra'] += $n;
            }
        }
        ksort($byDate);

        $max = 0;
        foreach ($byDate as $d) {
            $max = max($max, (int) $d['bc'], (int) $d['mitra']);
        }

        $months = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
        $weekdays = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $out = [];
        foreach ($byDate as $d) {
            $ts = strtotime($d['date']);
            $label = $d['date'];
            $weekday = '';
            if ($ts !== false) {
                $n = (int) date('n', $ts);
                $label = ((int) date('j', $ts)).' '.($months[$n] ?? date('M', $ts));
                $weekday = $weekdays[(int) date('w', $ts)] ?? '';
            }
            $bc = (int) $d['bc'];
            $mitra = (int) $d['mitra'];
            $out[] = [
                'date' => $d['date'],
                'label' => $label,
                'weekday' => $weekday,
                'week' => $d['week'],
                'bc' => $bc,
                'mitra' => $mitra,
                'rows' => $bc + $mitra,
                'bc_bar_pct' => $max > 0 ? round(100 * $bc / $max, 2) : 0.0,
                'mitra_bar_pct' => $max > 0 ? round(100 * $mitra / $max, 2) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $weekly
     * @return array{pct: float|null, label: string}
     */
    private function trendFromWeekly(array $weekly): array
    {
        $nonZero = array_values(array_filter($weekly, static fn (array $w): bool => (int) ($w['rows'] ?? 0) > 0));
        if (count($nonZero) < 2) {
            return ['pct' => null, 'label' => 'Perlu ≥2 minggu untuk tren'];
        }
        $prev = (int) $nonZero[count($nonZero) - 2]['rows'];
        $last = (int) $nonZero[count($nonZero) - 1]['rows'];
        if ($prev <= 0) {
            return ['pct' => null, 'label' => 'Minggu sebelumnya kosong'];
        }
        $pct = round(100 * ($last - $prev) / $prev, 1);

        return [
            'pct' => $pct,
            'label' => ($pct >= 0 ? '+' : '').$pct.'% vs minggu sebelumnya',
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{name: string, count: int, pct: float, bar_pct: float}>
     */
    private function aggregateNamed(array $rows, string $key, int $limit): array
    {
        $sum = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row[$key] ?? ''));
            if ($name === '') {
                $name = '(Tidak diisi)';
            }
            $sum[$name] = ($sum[$name] ?? 0) + (int) ($row['rows'] ?? 0);
        }
        arsort($sum);
        $total = array_sum($sum);
        $max = $sum === [] ? 0 : max($sum);
        $out = [];
        $i = 0;
        $other = 0;
        foreach ($sum as $name => $count) {
            $i++;
            if ($i <= $limit) {
                $out[] = [
                    'name' => $name,
                    'count' => $count,
                    'pct' => $total > 0 ? round(100 * $count / $total, 1) : 0.0,
                    'bar_pct' => $max > 0 ? round(100 * $count / $max, 1) : 0.0,
                ];
            } else {
                $other += $count;
            }
        }
        if ($other > 0) {
            $out[] = [
                'name' => 'Lainnya',
                'count' => $other,
                'pct' => $total > 0 ? round(100 * $other / $total, 1) : 0.0,
                'bar_pct' => $max > 0 ? round(100 * $other / $max, 1) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $cube
     * @return list<array<string, mixed>>
     */
    private function aggregateEntities(array $cube): array
    {
        $sum = [];
        foreach (OakCcvCompanyClassifier::ENTITY_ORDER as $ent) {
            $sum[$ent] = 0;
        }
        foreach ($cube as $row) {
            $ent = (string) ($row['entity'] ?? 'Mitra');
            if (! isset($sum[$ent])) {
                $ent = 'Mitra';
            }
            $sum[$ent] += (int) ($row['rows'] ?? 0);
        }
        $total = array_sum($sum);
        $max = $sum === [] ? 0 : max($sum);
        $out = [];
        foreach ($sum as $ent => $count) {
            $out[] = [
                'entity' => $ent,
                'company' => OakCcvCompanyClassifier::ENTITY_COMPANIES[$ent] ?? $ent,
                'count' => $count,
                'pct' => $total > 0 ? round(100 * $count / $total, 1) : 0.0,
                'bar_pct' => $max > 0 ? round(100 * $count / $max, 1) : 0.0,
                'color' => OakCcvCompanyClassifier::color($ent),
                'group' => OakCcvCompanyClassifier::isBcEntity($ent) ? 'BC' : 'Mitra',
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $stopRows
     * @return list<array{name: string, count: int, pct: float, bar_pct: float}>
     */
    private function aggregateStopAktivitas(array $stopRows): array
    {
        $mapped = [];
        foreach ($stopRows as $row) {
            $mapped[] = ['aktivitas' => $row['aktivitas'] ?? '(Tidak diisi)', 'rows' => 1];
        }

        return $this->aggregateNamed($mapped, 'aktivitas', 12);
    }

    /**
     * @param list<array<string, mixed>> $cube
     * @param list<string> $sites
     * @return array{sites: list<string>, entities: list<string>, cells: array<string, array<string, int>>, max: int}
     */
    private function buildHeatmap(array $cube, array $sites): array
    {
        $entities = OakCcvCompanyClassifier::ENTITY_ORDER;
        $cells = [];
        $max = 0;
        foreach ($sites as $site) {
            foreach ($entities as $ent) {
                $cells[$site][$ent] = 0;
            }
        }
        foreach ($cube as $row) {
            $site = (string) ($row['site'] ?? '');
            $ent = (string) ($row['entity'] ?? 'Mitra');
            if ($site === '' || ! isset($cells[$site])) {
                continue;
            }
            if (! isset($cells[$site][$ent])) {
                $ent = 'Mitra';
            }
            $cells[$site][$ent] += (int) ($row['rows'] ?? 0);
            $max = max($max, $cells[$site][$ent]);
        }

        return [
            'sites' => array_values($sites),
            'entities' => $entities,
            'cells' => $cells,
            'max' => $max,
        ];
    }

    /**
     * @param list<array<string, mixed>> $topMitra
     * @param list<array<string, mixed>> $mitraBySite
     * @return list<array{company: string, rows: int}>
     */
    private function filterTopMitra(array $topMitra, array $mitraBySite, string $site, string $group): array
    {
        if ($group === 'bc') {
            return [];
        }
        if ($site === '') {
            return array_slice($topMitra, 0, 15);
        }
        $sum = [];
        foreach ($mitraBySite as $row) {
            if ((string) ($row['site'] ?? '') !== $site) {
                continue;
            }
            $co = (string) ($row['company'] ?? '');
            $sum[$co] = ($sum[$co] ?? 0) + (int) ($row['rows'] ?? 0);
        }
        arsort($sum);
        $out = [];
        foreach (array_slice($sum, 0, 15, true) as $co => $n) {
            $out[] = ['company' => $co, 'rows' => $n];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $stopWeeks
     * @return list<array<string, mixed>>
     */
    private function stopWeeksFiltered(array $stopWeeks, string $week): array
    {
        if ($week === '') {
            return $stopWeeks;
        }

        return array_values(array_filter(
            $stopWeeks,
            static fn (array $w): bool => (string) ($w['week'] ?? '') === $week
        ));
    }

    /**
     * @param list<array{name: string, count: int, pct: float}> $rows
     * @return array<string, string>
     */
    private function paletteFor(array $rows): array
    {
        $palette = ['#3952bc', '#72479e', '#0057bd', '#0d9488', '#d97706', '#16a34a', '#b41340', '#64748b', '#ea580c', '#0891b2', '#94a3b8'];
        $map = [];
        foreach ($rows as $i => $row) {
            $map[$row['name']] = $palette[$i % count($palette)];
        }

        return $map;
    }

    /**
     * @param list<array{name: string, pct: float}> $rows
     * @param array<string, string> $colors
     */
    private function conicGradient(array $rows, array $colors): string
    {
        if ($rows === []) {
            return 'conic-gradient(rgb(241 245 249) 0% 100%)';
        }
        $parts = [];
        $cursor = 0.0;
        foreach ($rows as $row) {
            $pct = (float) ($row['pct'] ?? 0);
            $next = min(100.0, $cursor + $pct);
            $col = $colors[$row['name']] ?? '#94a3b8';
            $parts[] = $col.' '.$cursor.'% '.$next.'%';
            $cursor = $next;
        }
        if ($cursor < 100) {
            $parts[] = 'rgb(241 245 249) '.$cursor.'% 100%';
        }

        return 'conic-gradient('.implode(', ', $parts).')';
    }

    /**
     * @param array<string, mixed> $kpi
     * @param list<array{name: string, count: int, pct: float}> $sites
     * @param list<array{name: string, count: int, pct: float}> $aktivitas
     * @param list<array{name: string, count: int, pct: float}> $stopByAkt
     * @param array<string, mixed> $meta
     * @return array{narrative: string, rows: list<array{metric: string, description: string, action_threshold: string, status: string}>}
     */
    private function evaluation(array $kpi, array $sites, array $aktivitas, array $stopByAkt, array $meta, int $totalRows): array
    {
        $topSite = $sites[0] ?? null;
        $topAkt = $aktivitas[0] ?? null;
        $dumpStop = 0.0;
        foreach ($stopByAkt as $row) {
            if (mb_stripos($row['name'], 'dumping') !== false) {
                $dumpStop += (float) $row['pct'];
            }
        }
        $marine = null;
        foreach ($sites as $s) {
            if (strcasecmp($s['name'], 'MARINE') === 0) {
                $marine = $s;
                break;
            }
        }
        $geo = $meta['geotagging'] ?? [];
        $geoYes = (int) ($geo['Geotagging'] ?? 0);
        $geoNo = (int) ($geo['Non Geotagging'] ?? 0);
        $geoTotal = $geoYes + $geoNo;
        $geoPct = $geoTotal > 0 ? round(100 * $geoYes / $geoTotal, 1) : 0.0;

        $siteStatus = 'ok';
        $sitePct = (float) ($topSite['pct'] ?? 0);
        if ($sitePct >= 30) {
            $siteStatus = 'warning';
        }
        if ($sitePct >= 40) {
            $siteStatus = 'critical';
        }

        $dumpStatus = $dumpStop >= 25 ? 'warning' : 'ok';
        if ($dumpStop >= 35) {
            $dumpStatus = 'critical';
        }

        $marineStatus = 'ok';
        $marinePct = (float) ($marine['pct'] ?? 0);
        if ($marine !== null && $marinePct < 3 && $totalRows > 1000) {
            $marineStatus = 'warning';
        }

        $bcPct = (float) ($kpi['bc_pct'] ?? 0);
        $bcStatus = ($bcPct < 5 || $bcPct > 20) ? 'warning' : 'ok';

        $rows = [
            [
                'metric' => 'Konsentrasi site',
                'description' => $topSite
                    ? ($topSite['name'].' menyumbang '.number_format((float) $topSite['pct'], 1).'% observasi OAK ('.number_format((int) $topSite['count']).' laporan).')
                    : 'Belum ada data site.',
                'action_threshold' => '≥30% warning · ≥40% critical',
                'status' => $siteStatus,
            ],
            [
                'metric' => 'BC vs mitra',
                'description' => 'Grup BC '.$kpi['bc_pct'].'% ('.number_format((int) $kpi['bc_rows']).') vs mitra '.$kpi['mitra_pct'].'% ('.number_format((int) $kpi['mitra_rows']).'). Rate BC lebih tinggi dari porsi volume biasanya menandakan intensitas pengawasan internal, bukan otomatis risiko lebih buruk.',
                'action_threshold' => 'Porsi BC di luar 5–20% perlu cek coverage',
                'status' => $bcStatus,
            ],
            [
                'metric' => 'Stop / gap CCV',
                'description' => number_format((int) $kpi['stop_jobs']).' pekerjaan di-stop ('.number_format((int) $kpi['stop_gaps']).' item gap). Dumping ≈ '.number_format($dumpStop, 1).'% dari gap. '.number_format((int) $kpi['stop_matched']).' task ketemu di window observasi OAK.',
                'action_threshold' => 'Dumping ≥25% warning · ≥35% critical',
                'status' => $dumpStatus,
            ],
            [
                'metric' => 'Coverage MARINE',
                'description' => $marine
                    ? ('MARINE '.$marine['pct'].'% dari observasi OAK. Volume rendah vs pit — cek under-reporting CCV marine.')
                    : 'Tidak ada observasi MARINE pada filter ini.',
                'action_threshold' => '<3% dari total (n besar) = warning',
                'status' => $marineStatus,
            ],
            [
                'metric' => 'Aktivitas teratas',
                'description' => $topAkt
                    ? ($topAkt['name'].' '.$topAkt['pct'].'% ('.number_format((int) $topAkt['count']).'). Prioritaskan verifikasi critical control di aktivitas ini.')
                    : 'Belum ada aktivitas.',
                'action_threshold' => 'Fokus 3 aktivitas teratas untuk kampanye CCV',
                'status' => 'ok',
            ],
            [
                'metric' => 'Geotagging',
                'description' => $geoPct.'% observasi ber-geotag ('.number_format($geoYes).' / '.number_format($geoTotal).').',
                'action_threshold' => '<70% warning',
                'status' => $geoPct < 70 ? 'warning' : 'ok',
            ],
        ];

        $narrative = 'Observasi Area Kritis: '.number_format($totalRows).' laporan'
            .' ('.number_format((int) ($meta['days'] ?? 0)).' hari, '.$kpi['bc_pct'].'% grup BC). ';
        if ($topSite) {
            $narrative .= 'Pusat volume di '.$topSite['name'].' ('.$topSite['pct'].'%). ';
        }
        $narrative .= 'Stop work '.number_format((int) $kpi['stop_jobs']).' pekerjaan / '.number_format((int) $kpi['stop_gaps']).' gap CCV tidak sesuai'
            .($dumpStop > 0 ? ', dumping dominan '.number_format($dumpStop, 1).'%' : '').'.';

        return ['narrative' => $narrative, 'rows' => $rows];
    }
}
