<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Metrics;

use App\Services\ControlRoom\ControlRoomSapDutyReader;
use Carbon\CarbonImmutable;

/**
 * Enam sumbu kualitas SAP per personil jaga (0–100) + label komposit.
 */
final class ControlRoomSapQualityEvaluator
{
    public const AXIS_MIX = 'mix';

    public const AXIS_VOLUME = 'volume';

    public const AXIS_VARIETY = 'variety';

    public const AXIS_TIMING = 'timing';

    public const AXIS_LOCATION = 'location';

    public const AXIS_DEPTH = 'depth';

    public const AXIS_LABELS = [
        self::AXIS_MIX => 'kelengkapan mix',
        self::AXIS_VOLUME => 'volume',
        self::AXIS_VARIETY => 'variasi',
        self::AXIS_TIMING => 'disiplin waktu',
        self::AXIS_LOCATION => 'sebaran lokasi',
        self::AXIS_DEPTH => 'kedalaman',
    ];

    public const VOLUME_CAP = 12;

    public const LOCATION_CAP = 8;

    public function __construct(
        private readonly SapAchievement $sapAchievement,
        private readonly FindingVariety $variety,
        private readonly ControlRoomSapDutyReader $dutyWindow,
    ) {}

    /**
     * @param  list<string>  $dutyDates
     * @param  list<array<string, mixed>>  $findings
     * @return array<string, mixed>
     */
    public function evaluate(
        string $sid,
        string $name,
        string $site,
        string $siteLabel,
        array $dutyDates,
        array $findings,
    ): array {
        $sid = strtoupper(trim($sid));
        $dutyDates = array_values(array_unique(array_filter($dutyDates)));
        sort($dutyDates);
        $windows = [];
        foreach ($dutyDates as $date) {
            $windows[$date] = $this->dutyWindow->reportingWindow(CarbonImmutable::parse($date));
        }

        $onDuty = [];
        foreach ($findings as $finding) {
            $assigned = $this->assignDutyDate($finding, $windows);
            if ($assigned === null) {
                continue;
            }
            $finding['duty_date'] = $assigned;
            $onDuty[] = $finding;
        }

        $total = count($onDuty);
        $counts = ['hazard' => 0, 'inspeksi' => 0, 'observasi' => 0, 'oak' => 0];
        foreach ($onDuty as $finding) {
            $component = strtolower((string) ($finding['component'] ?? ''));
            if (isset($counts[$component])) {
                $counts[$component]++;
            }
        }

        $categories = [];
        foreach ($onDuty as $finding) {
            $category = trim((string) ($finding['category'] ?? ''));
            $categories[] = $category === '' ? 'Tanpa kategori' : mb_strtolower($category);
        }

        $mix = $this->mixScore($dutyDates, $onDuty);
        $volume = $total === 0 ? 0.0 : round(min($total, self::VOLUME_CAP) / self::VOLUME_CAP * 100, 1);
        $varietyRaw = $this->variety->score($categories);
        $variety = $varietyRaw === null ? 0.0 : round($varietyRaw * 100, 1);
        $timing = $this->timingScore($onDuty);
        $location = $this->locationScore($onDuty);
        $depth = $this->depthScore($onDuty);

        $scores = [
            self::AXIS_MIX => $mix,
            self::AXIS_VOLUME => $volume,
            self::AXIS_VARIETY => $variety,
            self::AXIS_TIMING => $timing,
            self::AXIS_LOCATION => $location,
            self::AXIS_DEPTH => $depth,
        ];
        $composite = $total === 0 ? 0.0 : round(array_sum($scores) / count($scores), 1);
        $weakest = $this->weakestAxis($scores, $total);

        return [
            'sid' => $sid,
            'name' => $name,
            'site' => $site,
            'site_label' => $siteLabel,
            'duty_dates' => $dutyDates,
            'duty_count' => count($dutyDates),
            'total' => $total,
            'hazard' => $counts['hazard'],
            'inspeksi' => $counts['inspeksi'],
            'observasi' => $counts['observasi'],
            'oak' => $counts['oak'],
            'distinct_categories' => count(array_unique($categories)),
            'scores' => $scores,
            'composite' => $composite,
            'label' => $this->label($total, $composite),
            'weakest' => $weakest,
            'weakest_label' => $weakest === null ? null : self::AXIS_LABELS[$weakest],
            'radar' => array_values($scores),
        ];
    }

    public function label(int $total, float $composite): string
    {
        if ($total === 0) {
            return 'Belum ada temuan';
        }
        if ($composite < 40) {
            return 'Perlu perbaikan';
        }
        if ($composite < 70) {
            return 'Cukup';
        }

        return 'Baik';
    }

    /**
     * @param  array<string, float>  $scores
     */
    public function weakestAxis(array $scores, int $total): ?string
    {
        if ($total === 0 || $scores === []) {
            return null;
        }

        $min = min($scores);
        foreach ($scores as $axis => $score) {
            if ($score === $min) {
                return $axis;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $dutyDates
     * @param  list<array<string, mixed>>  $findings
     */
    /**
     * @param  list<string>  $dutyDates
     * @param  list<array<string, mixed>>  $findings  sudah punya duty_date
     */
    private function mixScore(array $dutyDates, array $findings): float
    {
        if ($dutyDates === []) {
            return 0.0;
        }

        $byDate = [];
        foreach ($findings as $finding) {
            $date = (string) ($finding['duty_date'] ?? '');
            $component = strtolower((string) ($finding['component'] ?? ''));
            if ($date === '' || ! in_array($component, ['hazard', 'inspeksi', 'observasi', 'oak'], true)) {
                continue;
            }
            $byDate[$date][$component] = ($byDate[$date][$component] ?? 0) + 1;
        }

        $percentages = [];
        foreach ($dutyDates as $date) {
            $percentages[] = $this->sapAchievement->percentage([
                'hazard' => $byDate[$date]['hazard'] ?? 0,
                'inspeksi' => $byDate[$date]['inspeksi'] ?? 0,
                'observasi' => $byDate[$date]['observasi'] ?? 0,
                'oak' => $byDate[$date]['oak'] ?? 0,
            ]);
        }

        return round(array_sum($percentages) / count($percentages), 1);
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     */
    private function timingScore(array $findings): float
    {
        if ($findings === []) {
            return 0.0;
        }

        $onH = 0;
        foreach ($findings as $finding) {
            $atDate = CarbonImmutable::parse((string) $finding['at'])->toDateString();
            if ($atDate === (string) ($finding['duty_date'] ?? '')) {
                $onH++;
            }
        }

        return round(($onH / count($findings)) * 100, 1);
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     */
    private function locationScore(array $findings): float
    {
        $keys = [];
        foreach ($findings as $finding) {
            $lokasi = mb_strtolower(trim((string) ($finding['lokasi'] ?? '')));
            $detil = mb_strtolower(trim((string) ($finding['detil_lokasi'] ?? '')));
            if ($lokasi === '' && $detil === '') {
                continue;
            }
            $keys[$lokasi.'|'.$detil] = true;
        }
        $n = count($keys);
        if ($n === 0) {
            return 0.0;
        }

        return round(min($n, self::LOCATION_CAP) / self::LOCATION_CAP * 100, 1);
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     */
    private function depthScore(array $findings): float
    {
        if ($findings === []) {
            return 0.0;
        }

        $deep = 0;
        foreach ($findings as $finding) {
            if ($this->isDeep($finding)) {
                $deep++;
            }
        }

        return round(($deep / count($findings)) * 100, 1);
    }

    /**
     * @param  array<string, mixed>  $finding
     */
    public function isDeep(array $finding): bool
    {
        $hasText = array_key_exists('has_text', $finding)
            ? (bool) $finding['has_text']
            : (trim((string) ($finding['description'] ?? '')) !== '' && trim((string) ($finding['description'] ?? '')) !== '—');
        if (! $hasText) {
            return false;
        }

        if (array_key_exists('has_photo', $finding) || array_key_exists('has_geo', $finding)) {
            return (bool) ($finding['has_photo'] ?? false) || (bool) ($finding['has_geo'] ?? false);
        }

        $photo = trim((string) ($finding['photo_url'] ?? ''));
        $hasPhoto = $photo !== '' && filter_var($photo, FILTER_VALIDATE_URL) !== false;
        $lat = trim((string) ($finding['latitude'] ?? ''));
        $lng = trim((string) ($finding['longitude'] ?? ''));
        $hasGeo = $lat !== '' && $lng !== '';

        return $hasPhoto || $hasGeo;
    }

    /**
     * @param  array<string, mixed>  $finding
     * @param  array<string, array{start: CarbonImmutable, end: CarbonImmutable}>  $windows
     */
    private function assignDutyDate(array $finding, array $windows): ?string
    {
        try {
            $at = CarbonImmutable::parse((string) ($finding['at'] ?? ''));
        } catch (\Throwable) {
            return null;
        }

        foreach ($windows as $date => $window) {
            if ($at->gte($window['start']) && $at->lt($window['end'])) {
                return $date;
            }
        }

        return null;
    }
}
