<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MonitoringSafetyEngineeringEffectivenessService
{
    private const HAZARD_HIGH_FREQUENT = 500;

    private const HAZARD_HIGH_REPEAT = 100;

    /**
     * @return array<string, mixed>
     */
    public function buildDashboard(Request $request): array
    {
        $filters = $this->resolveFilters($request);

        if (! $this->tablesReady()) {
            return $this->emptyDashboard($filters);
        }

        $records = $this->fetchFilteredRecords($filters);
        $items = $records->map(fn (MonitoringSafetyEngineeringRecord $record): array => $this->recordToItem($record))->all();

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'summary' => $this->buildSummary($items),
            'risk_distribution' => $this->riskDistribution($items),
            'validation_recap' => $this->validationRecap($items),
            'validation_matrix' => $this->validationMatrix(),
            'priority_list' => $this->priorityList($items),
            'brief_analysis' => $this->buildBriefAnalysis($records, $items),
            'next_todo' => $this->buildNextTodo($records),
        ];
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('monitoring_safety_engineering_records');
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboard(array $filters): array
    {
        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'summary' => $this->buildSummary([]),
            'risk_distribution' => $this->riskDistribution([]),
            'validation_recap' => $this->validationRecap([]),
            'validation_matrix' => $this->validationMatrix(),
            'priority_list' => [],
            'brief_analysis' => [
                [
                    'title' => 'Ringkasan Evaluasi',
                    'points' => ['Tabel monitoring_safety_engineering_records belum tersedia. Jalankan migration terlebih dahulu.'],
                ],
            ],
            'next_todo' => ['Upload atau input data rekayasa melalui menu Update Data.'],
        ];
    }

    /**
     * @return Collection<int, MonitoringSafetyEngineeringRecord>
     */
    private function fetchFilteredRecords(array $filters): Collection
    {
        $query = MonitoringSafetyEngineeringRecord::query()
            ->select([
                'id',
                'site',
                'perusahaan',
                'pengendalian_rekayasa',
                'tanggal_ideation',
                'deteksi_deviasi',
                'intervensi_deviasi',
                'prediksi_penurunan_tangga_risiko',
                'terkait_hazard',
                'terkait_insiden',
                'potensi_peningkatan_efektivitas',
                'pengendalian_peningkatan_efektivitas',
                'brief_analysis_challenge',
                'next_to_do',
                'period_year',
                'created_at',
            ])
            ->orderBy('sort_order')
            ->orderBy('row_no')
            ->orderBy('id');

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * @param  Builder<MonitoringSafetyEngineeringRecord>  $query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['company'] !== '') {
            $query->where('perusahaan', $filters['company']);
        }

        if ($filters['period_year'] > 0) {
            $query->where('period_year', $filters['period_year']);
        }

        if ($filters['date_from'] !== '') {
            $query->where(function (Builder $builder) use ($filters): void {
                $builder->whereDate('tanggal_ideation', '>=', $filters['date_from'])
                    ->orWhereDate('created_at', '>=', $filters['date_from']);
            });
        }

        if ($filters['date_to'] !== '') {
            $query->where(function (Builder $builder) use ($filters): void {
                $builder->whereDate('tanggal_ideation', '<=', $filters['date_to'])
                    ->orWhereDate('created_at', '<=', $filters['date_to']);
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function recordToItem(MonitoringSafetyEngineeringRecord $record): array
    {
        $prediksi = $record->prediksi_penurunan_tangga_risiko;
        $deteksi = $record->deteksi_deviasi;
        $hasHazard = ($deteksi !== null && $deteksi > 0) || (bool) $record->terkait_hazard;
        $hasInsiden = (bool) $record->terkait_insiden;
        $validasi = $this->resolveValidasi($hasHazard, $hasInsiden, $prediksi);

        $tindakLanjut = trim((string) ($record->pengendalian_peningkatan_efektivitas ?? ''));
        if ($tindakLanjut === '') {
            $tindakLanjut = $validasi['tindak_lanjut'];
        }

        return [
            'id' => $record->id,
            'name' => $record->pengendalian_rekayasa,
            'perusahaan' => trim($record->perusahaan . ' ' . $record->site),
            'prediksi' => $this->prediksiLabel($prediksi),
            'prediksi_value' => $prediksi,
            'hazard' => $this->hazardLabel($deteksi, $hasHazard),
            'hazard_up' => $this->isHighHazard($deteksi, $hasHazard),
            'insiden' => $hasInsiden ? 'Ada' : 'Tidak ada',
            'validasi' => $validasi['label'],
            'validasi_class' => $validasi['class'],
            'tindak_lanjut' => $tindakLanjut,
            'priority_score' => $this->priorityScore($validasi['label'], $deteksi, $hasHazard, (bool) $record->potensi_peningkatan_efektivitas),
            'potensi_naik_level' => $this->hasUpgradePotential($validasi['label'], (bool) $record->potensi_peningkatan_efektivitas),
        ];
    }

    /**
     * @return array{label: string, class: string, tindak_lanjut: string}
     */
    private function resolveValidasi(bool $hasHazard, bool $hasInsiden, ?int $prediksi): array
    {
        if ($prediksi === null && ! $hasHazard && ! $hasInsiden) {
            return [
                'label' => 'Perlu Validasi Data',
                'class' => 'mse-level-chip--neutral',
                'tindak_lanjut' => 'Lengkapi data prediksi risiko, hazard, dan insiden untuk validasi efektivitas',
            ];
        }

        if (! $hasHazard && ! $hasInsiden) {
            return [
                'label' => 'Efektif',
                'class' => 'mse-level-chip--good',
                'tindak_lanjut' => 'Pertahankan Level Rekayasa',
            ];
        }

        if ($hasHazard && ! $hasInsiden) {
            return [
                'label' => 'Efektif Sebagian',
                'class' => 'mse-level-chip--warn',
                'tindak_lanjut' => 'Hazard Rendah: Pertahankan Level Rekayasa; Hazard Tinggi/Berulang: Menaikkan Level Rekayasa',
            ];
        }

        return [
            'label' => 'Tidak Efektif',
            'class' => 'mse-level-chip--bad',
            'tindak_lanjut' => 'Menaikkan Level Rekayasa',
        ];
    }

    private function prediksiLabel(?int $prediksi): string
    {
        return match ($prediksi) {
            1 => 'Turun 1 Tangga',
            2 => 'Turun 2 Tangga',
            3 => 'Turun 3 Tangga',
            default => 'Belum Ada Prediksi',
        };
    }

    private function hazardLabel(?int $deteksi, bool $hasHazard): string
    {
        if ($deteksi !== null && $deteksi > 0) {
            return match (true) {
                $deteksi >= self::HAZARD_HIGH_FREQUENT => 'Tinggi (Sering)',
                $deteksi >= self::HAZARD_HIGH_REPEAT => 'Tinggi (Berulang)',
                default => 'Rendah (Sesekali)',
            };
        }

        return $hasHazard ? 'Ada' : 'Tidak ada';
    }

    private function isHighHazard(?int $deteksi, bool $hasHazard): bool
    {
        if ($deteksi !== null && $deteksi >= self::HAZARD_HIGH_REPEAT) {
            return true;
        }

        return $hasHazard && $deteksi !== null && $deteksi > 0;
    }

    private function hasUpgradePotential(string $validasi, bool $potensiFlag): bool
    {
        if ($potensiFlag) {
            return true;
        }

        return in_array($validasi, ['Tidak Efektif', 'Efektif Sebagian'], true);
    }

    private function priorityScore(string $validasi, ?int $deteksi, bool $hasHazard, bool $potensiFlag): int
    {
        $score = match ($validasi) {
            'Tidak Efektif' => 100,
            'Efektif Sebagian' => 60,
            'Perlu Validasi Data' => 40,
            default => 10,
        };

        if ($this->isHighHazard($deteksi, $hasHazard)) {
            $score += 25;
        }

        if ($potensiFlag) {
            $score += 15;
        }

        if ($deteksi !== null) {
            $score += min(20, (int) floor($deteksi / 50));
        }

        return $score;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, int>
     */
    private function buildSummary(array $items): array
    {
        return [
            'total' => count($items),
            'turun_1' => count(array_filter($items, static fn (array $item): bool => $item['prediksi_value'] === 1)),
            'turun_2' => count(array_filter($items, static fn (array $item): bool => $item['prediksi_value'] === 2)),
            'turun_3' => count(array_filter($items, static fn (array $item): bool => $item['prediksi_value'] === 3)),
            'belum_ada_prediksi' => count(array_filter($items, static fn (array $item): bool => $item['prediksi_value'] === null || $item['prediksi_value'] === 0)),
            'potensi_naik_level' => count(array_filter($items, static fn (array $item): bool => $item['potensi_naik_level'] === true)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function riskDistribution(array $items): array
    {
        $summary = $this->buildSummary($items);

        return [
            'labels' => ['Turun 1 Tangga', 'Turun 2 Tangga', 'Turun 3 Tangga', 'Belum Ada Prediksi'],
            'data' => [
                $summary['turun_1'],
                $summary['turun_2'],
                $summary['turun_3'],
                $summary['belum_ada_prediksi'],
            ],
            'colors' => ['#2563eb', '#eab308', '#dc2626', '#9ca3af'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function validationRecap(array $items): array
    {
        $labels = ['Efektif', 'Efektif Sebagian', 'Tidak Efektif', 'Perlu Validasi Data'];
        $counts = array_fill_keys($labels, 0);

        foreach ($items as $item) {
            $label = (string) $item['validasi'];
            if (isset($counts[$label])) {
                $counts[$label]++;
            }
        }

        return [
            'labels' => $labels,
            'data' => array_values($counts),
            'colors' => ['#15803d', '#b45309', '#b91c1c', '#64748b'],
        ];
    }

    /**
     * @return list<array{hazard: string, insiden: string, validasi: string, validasi_class: string, tindak_lanjut: string}>
     */
    private function validationMatrix(): array
    {
        return [
            ['hazard' => 'Tidak ada', 'insiden' => 'Tidak ada', 'validasi' => 'Efektif', 'validasi_class' => 'mse-level-chip--good', 'tindak_lanjut' => 'Pertahankan Level Rekayasa'],
            ['hazard' => 'Ada', 'insiden' => 'Tidak ada', 'validasi' => 'Efektif Sebagian', 'validasi_class' => 'mse-level-chip--warn', 'tindak_lanjut' => 'Hazard Rendah: Pertahankan Level Rekayasa; Hazard Tinggi/Berulang: Menaikkan Level Rekayasa'],
            ['hazard' => 'Tidak ada', 'insiden' => 'Ada', 'validasi' => 'Tidak Efektif', 'validasi_class' => 'mse-level-chip--bad', 'tindak_lanjut' => 'Menaikkan Level Rekayasa'],
            ['hazard' => 'Ada', 'insiden' => 'Ada', 'validasi' => 'Tidak Efektif', 'validasi_class' => 'mse-level-chip--bad', 'tindak_lanjut' => 'Menaikkan Level Rekayasa'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function priorityList(array $items): array
    {
        $sorted = $items;
        usort($sorted, static fn (array $a, array $b): int => $b['priority_score'] <=> $a['priority_score']);

        return array_map(static function (array $item): array {
            unset($item['priority_score'], $item['prediksi_value'], $item['potensi_naik_level']);

            return $item;
        }, $sorted);
    }

    /**
     * @param  Collection<int, MonitoringSafetyEngineeringRecord>  $records
     * @param  list<array<string, mixed>>  $items
     * @return list<array{title: string, points: list<string>}>
     */
    private function buildBriefAnalysis(Collection $records, array $items): array
    {
        if ($items === []) {
            return [
                [
                    'title' => 'Ringkasan Evaluasi',
                    'points' => ['Belum ada data evaluasi efektivitas pada filter yang dipilih.'],
                ],
            ];
        }

        $summary = $this->buildSummary($items);
        $recap = $this->validationRecap($items);
        $turun1Pct = $summary['total'] > 0 ? round(($summary['turun_1'] / $summary['total']) * 100, 1) : 0;
        $sebagianPct = $summary['total'] > 0 ? round(($recap['data'][1] / $summary['total']) * 100, 1) : 0;
        $potensiPct = $summary['total'] > 0 ? round(($summary['potensi_naik_level'] / $summary['total']) * 100, 1) : 0;

        $points = [
            sprintf(
                '%d rekayasa telah dievaluasi, %s%% (%d item) diprediksi turun 1 tangga risiko.',
                $summary['total'],
                $turun1Pct,
                $summary['turun_1'],
            ),
            sprintf(
                '%d item (%s%%) tervalidasi efektif sebagian dan berpotensi diupgrade level rekayasanya.',
                $recap['data'][1],
                $sebagianPct,
            ),
            sprintf(
                '%d item (%s%%) memiliki potensi naik level — prioritaskan yang berhazard tinggi/berulang.',
                $summary['potensi_naik_level'],
                $potensiPct,
            ),
        ];

        $dbPoints = $records
            ->pluck('brief_analysis_challenge')
            ->filter(static fn (?string $text): bool => $text !== null && trim($text) !== '')
            ->map(static fn (string $text): string => trim($text))
            ->unique()
            ->take(2)
            ->values()
            ->all();

        if ($dbPoints !== []) {
            $points = array_merge($points, $dbPoints);
        }

        return [
            [
                'title' => 'Ringkasan Evaluasi',
                'points' => $points,
            ],
        ];
    }

    /**
     * @param  Collection<int, MonitoringSafetyEngineeringRecord>  $records
     * @return list<string>
     */
    private function buildNextTodo(Collection $records): array
    {
        $todos = $records
            ->pluck('next_to_do')
            ->filter(static fn (?string $text): bool => $text !== null && trim($text) !== '')
            ->flatMap(static function (string $text): array {
                $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];

                return array_values(array_filter(array_map('trim', $lines)));
            })
            ->unique()
            ->take(8)
            ->values()
            ->all();

        if ($todos !== []) {
            return $todos;
        }

        return [
            'Upgrade prioritas rekayasa dengan hazard tinggi/berulang dan validasi Tidak Efektif menjadi kontrol otomatis (interlock/limiter).',
            'Lengkapi evidence lapangan untuk item dengan status Perlu Validasi Data agar klasifikasi efektivitas lebih akurat.',
            'Jadwalkan review efektivitas berkala (quarterly) untuk item dengan status Efektif Sebagian.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        $dateFrom = (string) $request->get('date_from', now()->startOfYear()->format('Y-m-d'));
        $periodYear = (int) date('Y', strtotime($dateFrom) ?: time());

        return [
            'company' => (string) $request->get('company', ''),
            'date_from' => $dateFrom,
            'date_to' => (string) $request->get('date_to', now()->format('Y-m-d')),
            'period_year' => $periodYear > 0 ? $periodYear : (int) now()->year,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        $companies = config('monitoring_safety_engineering.perusahaan', []);

        if ($this->tablesReady()) {
            $companies = array_values(array_unique(array_merge(
                $companies,
                MonitoringSafetyEngineeringRecord::query()
                    ->select('perusahaan')
                    ->distinct()
                    ->orderBy('perusahaan')
                    ->pluck('perusahaan')
                    ->filter()
                    ->all(),
            )));
        }

        return [
            'companies' => array_merge(
                ['' => 'Semua Perusahaan Inisiator'],
                array_combine($companies, $companies) ?: [],
            ),
        ];
    }
}
