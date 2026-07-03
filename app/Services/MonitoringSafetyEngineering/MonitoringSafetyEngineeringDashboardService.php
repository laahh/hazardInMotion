<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use Illuminate\Http\Request;

class MonitoringSafetyEngineeringDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function buildDashboard(Request $request): array
    {
        $filters = $this->resolveFilters($request);
        $replikasiItems = $this->replikasiItems();
        $safetyEngineeringItems = $this->safetyEngineeringItems();
        $additionalSafetyItems = $this->additionalSafetyItems();

        $activeCategory = $filters['category'];
        $activeItems = match ($activeCategory) {
            'safety_engineering' => $safetyEngineeringItems,
            'additional_safety_engineering' => $additionalSafetyItems,
            default => $replikasiItems,
        };

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'summary' => $this->buildSummary($replikasiItems, $safetyEngineeringItems, $additionalSafetyItems),
            'overdue_summary' => $this->buildOverdueSummary($replikasiItems, $safetyEngineeringItems, $additionalSafetyItems),
            'active_category' => $activeCategory,
            'active_items' => $activeItems,
            'replikasi_items' => $replikasiItems,
            'safety_engineering_items' => $safetyEngineeringItems,
            'additional_safety_items' => $additionalSafetyItems,
            'brief_analysis' => $this->briefAnalysis(),
            'next_todo' => $this->nextTodo(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        $category = (string) $request->get('category', 'replikasi');
        $allowedCategories = ['replikasi', 'safety_engineering', 'additional_safety_engineering'];

        if (! in_array($category, $allowedCategories, true)) {
            $category = 'replikasi';
        }

        return [
            'bar' => (string) $request->get('bar', 'BAP BMO 3'),
            'company' => (string) $request->get('company', ''),
            'review_week' => (string) $request->get('review_week', 'W' . now()->isoWeek()),
            'date_from' => (string) $request->get('date_from', now()->startOfYear()->format('Y-m-d')),
            'date_to' => (string) $request->get('date_to', now()->format('Y-m-d')),
            'category' => $category,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'bars' => ['BAP BMO 3', 'BAP BMO 1', 'BAP BMO 2'],
            'companies' => [
                '' => 'Semua Perusahaan',
                'pama' => 'PAMA',
                'kpc' => 'KPC',
                'mitra-a' => 'Mitra A',
                'mitra-b' => 'Mitra B',
            ],
            'review_weeks' => collect(range(1, 52))->map(fn (int $w) => 'W' . $w)->all(),
            'categories' => [
                'replikasi' => 'Replikasi',
                'safety_engineering' => 'Safety Engineering',
                'additional_safety_engineering' => 'Additional Safety Engineering',
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $replikasi
     * @param  list<array<string, mixed>>  $safety
     * @param  list<array<string, mixed>>  $additional
     * @return array<string, mixed>
     */
    private function buildSummary(array $replikasi, array $safety, array $additional): array
    {
        $totalPlan = count($replikasi) + count($safety) + count($additional);

        return [
            'total_komitmen' => $totalPlan,
            'replikasi' => [
                'count' => count($replikasi),
                'progress' => $this->calculateOverallProgress($replikasi),
            ],
            'safety_engineering' => [
                'count' => count($safety),
                'progress' => $this->calculateOverallProgress($safety),
            ],
            'additional_safety_engineering' => [
                'count' => count($additional),
                'progress' => $this->calculateOverallProgress($additional),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $replikasi
     * @param  list<array<string, mixed>>  $safety
     * @param  list<array<string, mixed>>  $additional
     * @return list<array{label: string, overdue: int}>
     */
    private function buildOverdueSummary(array $replikasi, array $safety, array $additional): array
    {
        return [
            ['label' => 'Replikasi', 'overdue' => $this->sumOverdue($replikasi)],
            ['label' => 'Safety Engineering', 'overdue' => $this->sumOverdue($safety)],
            ['label' => 'Additional Safety Engineering', 'overdue' => $this->sumOverdue($additional)],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function calculateOverallProgress(array $items): int
    {
        if ($items === []) {
            return 0;
        }

        $totalPlan = array_sum(array_column($items, 'plan'));
        $totalDone = array_sum(array_column($items, 'done'));

        if ($totalPlan === 0) {
            return 0;
        }

        return (int) round(($totalDone / $totalPlan) * 100);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function sumOverdue(array $items): int
    {
        return (int) array_sum(array_column($items, 'overdue'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function replikasiItems(): array
    {
        return [
            $this->item('Mining City — penambahan penerangan di area kerja & jalan hauling', 'Titik/Lokasi', 4, 4, '2025-12-31', 0),
            $this->item('CCTV untuk memantau area kerja berisiko tinggi', 'Unit', 2, 1, '2026-06-30', 0),
            $this->item('DMS Unit DT', 'Unit', 50, 0, '2025-12-31', 1),
            $this->item('P2H Online unit DT', 'Unit', 50, 12, '2026-03-31', 0),
            $this->item('Sistem komunikasi darurat di pit', 'Sistem', 1, 0, '2026-09-30', 0),
            $this->item('Rambu & marking jalan hauling', 'Titik/Lokasi', 120, 45, '2026-06-30', 0),
            $this->item('Instalasi barrier di area stockpile', 'Titik/Lokasi', 8, 3, '2026-04-30', 0),
            $this->item('Upgrade lampu sorot area loading', 'Titik/Lokasi', 6, 6, '2025-11-30', 0),
            $this->item('Drone inspection pit wall', 'Kegiatan', 12, 4, '2026-08-31', 0),
            $this->item('APAR portable unit DT', 'Unit', 50, 28, '2026-05-31', 0),
            $this->item('Speed limiter unit hauling', 'Unit', 35, 10, '2026-07-31', 0),
            $this->item('Rear view camera unit DT', 'Unit', 50, 18, '2026-06-30', 0),
            $this->item('Sistem grounding unit fuel truck', 'Unit', 8, 2, '2026-10-31', 0),
            $this->item('Pagar pembatas area highwall', 'Meter', 450, 120, '2026-12-31', 0),
            $this->item('Alarm mundur unit support', 'Unit', 22, 22, '2025-10-31', 0),
            $this->item('SOP digitalisasi inspeksi harian', 'Kegiatan', 1, 0, '2026-11-30', 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function safetyEngineeringItems(): array
    {
        return [
            $this->item('Engineering review highwall stability', 'Kegiatan', 4, 2, '2026-06-30', 0),
            $this->item('Desain sistem ventilasi workshop', 'Kegiatan', 1, 1, '2026-03-31', 0),
            $this->item('Analisis risiko blasting area', 'Kegiatan', 2, 0, '2026-08-31', 0),
            $this->item('Validasi desain stockpile angle', 'Kegiatan', 1, 0, '2026-09-30', 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function additionalSafetyItems(): array
    {
        return [
            $this->item('Additional guardrail jalan pit', 'Meter', 200, 80, '2026-07-31', 0),
            $this->item('Additional emergency shower area fuel', 'Unit', 2, 0, '2026-10-31', 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        string $name,
        string $unit,
        int $plan,
        int $done,
        string $dueDate,
        int $overdue,
    ): array {
        $percentage = $plan > 0 ? (int) round(($done / $plan) * 100) : 0;

        return [
            'name' => $name,
            'unit' => $unit,
            'plan' => $plan,
            'done' => $done,
            'percentage' => $percentage,
            'percentage_color' => $this->percentageColor($percentage),
            'due_date' => $dueDate,
            'due_date_label' => date('d M Y', strtotime($dueDate)),
            'overdue' => $overdue,
        ];
    }

    private function percentageColor(int $percentage): string
    {
        return match (true) {
            $percentage >= 100 => 'green',
            $percentage >= 50 => 'amber',
            $percentage > 0 => 'orange',
            default => 'red',
        };
    }

    /**
     * @return list<array{title: string, points: list<string>}>
     */
    private function briefAnalysis(): array
    {
        return [
            [
                'title' => 'Status Penyelesaian',
                'points' => [
                    'Dari 16 pengendalian replikasi, 5 item sudah selesai 100% dan 4 item berada pada progress 50–75%.',
                    '11 item belum selesai karena keterlambatan pengiriman peralatan, proses re-testing, dan penyesuaian spesifikasi teknis.',
                    '1 item overdue (DMS Unit DT) akibat ketidaksesuaian spesifikasi peralatan dan perluasan scope pekerjaan.',
                ],
            ],
            [
                'title' => 'Faktor Penghambat',
                'points' => [
                    'Lead time pengadaan unit DMS dan integrasi sistem membutuhkan waktu lebih lama dari rencana.',
                    'Koordinasi antar departemen untuk instalasi CCTV dan speed limiter masih dalam proses commissioning.',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function nextTodo(): array
    {
        return [
            'Prioritaskan penyelesaian item dengan gap terbesar: DMS Unit DT, P2H Online unit DT, dan drone inspection pit wall.',
            'Percepat proses commissioning CCTV dan speed limiter unit hauling sebelum Q3 2026.',
            'Lakukan weekly review dengan vendor DMS untuk memastikan spesifikasi sesuai kebutuhan operasional.',
            'Koordinasi dengan tim engineering untuk menyelesaikan SOP digitalisasi inspeksi harian.',
            'Monitoring harian terhadap item overdue dan update progress ke manajemen setiap Review W.',
        ];
    }
}
