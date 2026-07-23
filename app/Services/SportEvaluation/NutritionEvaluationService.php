<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Evaluasi Nutrisi Fase 1 — read-only ke bewell_db.
 * Alert dihitung on-read (tidak menulis ke DB app).
 */
final class NutritionEvaluationService
{
    private const CACHE_TTL = 180;

    private const DEFAULT_CALORIE_TARGET = 2000.0;

    private const DEFAULT_CARB_TARGET = 250.0;

    private const DEFAULT_PROTEIN_TARGET = 75.0;

    private const ABSOLUTE_CALORIE_OVER = 2800.0;

    private const ABSOLUTE_CARB_OVER = 350.0;

    /** @var list<string> */
    private const SUGAR_KEYWORDS = [
        'gula', 'soda', 'teh manis', 'es teh', 'coklat', 'cokelat', 'kue',
        'permen', 'sirup', 'soft drink', 'softdrink', 'boba', 'milkshake',
        'es krim', 'ice cream', 'minuman manis',
    ];

    public function __construct(
        private readonly BewellConnectionService $connection,
    ) {}

    /**
     * Payload lengkap untuk halaman Evaluasi Nutrisi.
     *
     * @return array{
     *     connectionUp:bool,
     *     kpi:array<string,int|float>,
     *     alerts:array<int,array<string,mixed>>,
     *     riskRanking:array<int,array<string,mixed>>,
     *     macroTrendLabels:array<int,string>,
     *     macroTrendCalories:array<int,float>,
     *     macroTrendCarbs:array<int,float>,
     *     recentFoodLogs:array<int,array<string,mixed>>
     * }
     */
    public function dashboard(): array
    {
        $empty = [
            'connectionUp' => false,
            'kpi' => [
                'usersLogged' => 0,
                'totalEntries' => 0,
                'alertCount' => 0,
                'goodScorePct' => 0.0,
            ],
            'alerts' => [],
            'riskRanking' => [],
            'macroTrendLabels' => [],
            'macroTrendCalories' => [],
            'macroTrendCarbs' => [],
            'recentFoodLogs' => [],
        ];

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            return Cache::remember('evaluasi_well:nutrition:dashboard', self::CACHE_TTL, function (): array {
                $from7 = Carbon::now()->subDays(6)->startOfDay();
                $to7 = Carbon::now()->endOfDay();
                $from30 = Carbon::now()->subDays(30)->startOfDay();

                $kpi = $this->buildKpi($from7, $to7);
                $alerts = $this->buildAlerts($from7, $to7, $from30);
                $kpi['alertCount'] = count($alerts);

                return [
                    'connectionUp' => true,
                    'kpi' => $kpi,
                    'alerts' => array_slice($alerts, 0, 50),
                    'riskRanking' => $this->buildRiskRanking($alerts),
                    ...$this->buildMacroTrend($from7, $to7),
                    'recentFoodLogs' => $this->buildRecentFoodLogs(),
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return array{usersLogged:int,totalEntries:int,alertCount:int,goodScorePct:float}
     */
    private function buildKpi(Carbon $from, Carbon $to): array
    {
        $db = $this->db();
        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr = $to->format('Y-m-d H:i:s');

        $usersLogged = (int) $db->table('food_analyses')
            ->whereNotNull('user_id')
            ->whereBetween('created_at', [$fromStr, $toStr])
            ->distinct()
            ->count('user_id');

        $totalEntries = (int) $db->table('food_analyses')
            ->whereBetween('created_at', [$fromStr, $toStr])
            ->count();

        $scoreFrom = $from->format('Y-m-d');
        $scoreTo = $to->format('Y-m-d');

        $scoreStats = $db->table('daily_health_scores')
            ->whereBetween('score_date', [$scoreFrom, $scoreTo])
            ->selectRaw('COUNT(*) AS total_rows')
            ->selectRaw("SUM(CASE WHEN category IN ('good','excellent') THEN 1 ELSE 0 END) AS good_rows")
            ->first();

        $totalRows = (int) ($scoreStats->total_rows ?? 0);
        $goodRows = (int) ($scoreStats->good_rows ?? 0);
        $goodScorePct = $totalRows > 0 ? round($goodRows / $totalRows * 100, 1) : 0.0;

        return [
            'usersLogged' => $usersLogged,
            'totalEntries' => $totalEntries,
            'alertCount' => 0,
            'goodScorePct' => $goodScorePct,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildAlerts(Carbon $from7, Carbon $to7, Carbon $from30): array
    {
        $db = $this->db();
        $from7Str = $from7->format('Y-m-d H:i:s');
        $to7Str = $to7->format('Y-m-d H:i:s');
        $from30Str = $from30->format('Y-m-d H:i:s');
        $scoreFrom = $from7->format('Y-m-d');
        $scoreTo = $to7->format('Y-m-d');

        $candidateIds = $db->table('food_analyses')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $from30Str)
            ->distinct()
            ->limit(2000)
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($candidateIds === []) {
            return [];
        }

        $profiles = $db->table('employee_profiles')
            ->whereIn('id', $candidateIds)
            ->get(['id', 'nama', 'kode_sid', 'divisi'])
            ->keyBy('id');

        $dailyFood = $db->table('food_analyses')
            ->selectRaw('user_id, DATE(created_at) AS d')
            ->selectRaw('COUNT(*) AS entries')
            ->selectRaw('COALESCE(SUM(total_calories), 0) AS calories')
            ->selectRaw('COALESCE(SUM(carbs_g), 0) AS carbs')
            ->selectRaw('COALESCE(SUM(protein_g), 0) AS protein')
            ->whereIn('user_id', $candidateIds)
            ->whereBetween('created_at', [$from7Str, $to7Str])
            ->groupByRaw('user_id, DATE(created_at)')
            ->get()
            ->groupBy('user_id');

        $logDays7 = [];
        foreach ($dailyFood as $userId => $rows) {
            $logDays7[(int) $userId] = $rows->count();
        }

        $scores = $db->table('daily_health_scores')
            ->whereIn('user_id', $candidateIds)
            ->whereBetween('score_date', [$scoreFrom, $scoreTo])
            ->get([
                'user_id',
                'score_date',
                'category',
                'calorie_actual',
                'protein_actual_g',
                'carb_actual_g',
            ])
            ->groupBy('user_id');

        $targets = $db->table('goal_daily_targets')
            ->whereIn('user_id', $candidateIds)
            ->whereBetween('target_date', [$scoreFrom, $scoreTo])
            ->get([
                'user_id',
                'target_date',
                'calorie_target',
                'protein_target_g',
                'carb_target_g',
            ])
            ->groupBy('user_id');

        $sweetCounts = $this->countSweetFoodEntries($candidateIds, $from7Str, $to7Str);

        $alerts = [];

        foreach ($candidateIds as $userId) {
            $profile = $profiles->get($userId);
            if ($profile === null) {
                continue;
            }

            $base = [
                'user_id' => $userId,
                'nama' => (string) ($profile->nama ?: 'User #'.$userId),
                'kode_sid' => (string) ($profile->kode_sid ?: '-'),
                'divisi' => (string) ($profile->divisi ?: '-'),
            ];

            $userDaily = $dailyFood->get($userId) ?? $dailyFood->get((string) $userId, collect());
            $userScores = $scores->get($userId) ?? $scores->get((string) $userId, collect());
            $userTargetGroup = $targets->get($userId) ?? $targets->get((string) $userId, collect());
            $userTargets = $userTargetGroup->keyBy(
                static fn ($row): string => (string) $row->target_date
            );

            $daysByDate = $this->mergeDailyActuals($userDaily, $userScores, $userTargets);

            $calorieOverDays = 0;
            $carbOverDays = 0;
            $proteinUnderDays = 0;

            foreach ($daysByDate as $day) {
                $calTarget = $day['calorie_target'];
                $carbTarget = $day['carb_target'];
                $proteinTarget = $day['protein_target'];

                if ($calTarget > 0) {
                    if ($day['calories'] >= $calTarget * 1.2) {
                        $calorieOverDays++;
                    }
                } elseif ($day['calories'] >= self::ABSOLUTE_CALORIE_OVER) {
                    $calorieOverDays++;
                }

                if ($carbTarget > 0) {
                    if ($day['carbs'] >= $carbTarget * 1.3) {
                        $carbOverDays++;
                    }
                } elseif ($day['carbs'] >= self::ABSOLUTE_CARB_OVER) {
                    $carbOverDays++;
                }

                if ($proteinTarget > 0) {
                    if ($day['protein'] <= $proteinTarget * 0.7) {
                        $proteinUnderDays++;
                    }
                } elseif ($day['protein'] > 0 && $day['protein'] <= self::DEFAULT_PROTEIN_TARGET * 0.7) {
                    $proteinUnderDays++;
                }
            }

            $poorScoreDays = $userScores->filter(
                static fn ($row): bool => in_array((string) $row->category, ['poor', 'need_improvement'], true)
            )->count();

            $daysLogged = $logDays7[$userId] ?? 0;
            $sweetCount = $sweetCounts[$userId] ?? 0;

            if ($calorieOverDays >= 3) {
                $alerts[] = $base + [
                    'code' => 'calorie_over',
                    'title' => 'Kalori berlebih',
                    'severity' => 'high',
                    'evidence' => $calorieOverDays.' hari ≥120% target / ambang kalori',
                    'days_flagged' => $calorieOverDays,
                ];
            }

            if ($carbOverDays >= 3) {
                $alerts[] = $base + [
                    'code' => 'carb_over',
                    'title' => 'Karbohidrat tinggi',
                    'severity' => 'high',
                    'evidence' => $carbOverDays.' hari ≥130% target karbo',
                    'days_flagged' => $carbOverDays,
                ];
            }

            if ($proteinUnderDays >= 3) {
                $alerts[] = $base + [
                    'code' => 'protein_under',
                    'title' => 'Protein kurang',
                    'severity' => 'medium',
                    'evidence' => $proteinUnderDays.' hari ≤70% target protein',
                    'days_flagged' => $proteinUnderDays,
                ];
            }

            if ($poorScoreDays >= 3) {
                $alerts[] = $base + [
                    'code' => 'score_poor',
                    'title' => 'Skor nutrisi buruk',
                    'severity' => 'high',
                    'evidence' => $poorScoreDays.' hari kategori poor / need improvement',
                    'days_flagged' => $poorScoreDays,
                ];
            }

            if ($daysLogged < 3) {
                $alerts[] = $base + [
                    'code' => 'log_inconsistent',
                    'title' => 'Log makanan tidak konsisten',
                    'severity' => 'medium',
                    'evidence' => 'Hanya '.$daysLogged.' hari log dalam 7 hari',
                    'days_flagged' => $daysLogged,
                ];
            }

            if ($sweetCount >= 2 || $carbOverDays >= 2) {
                $parts = [];
                if ($sweetCount >= 2) {
                    $parts[] = $sweetCount.' entri manis';
                }
                if ($carbOverDays >= 2) {
                    $parts[] = $carbOverDays.' hari karbo tinggi';
                }
                $alerts[] = $base + [
                    'code' => 'sugar_risk_estimate',
                    'title' => 'Risiko gula (estimasi)',
                    'severity' => 'medium',
                    'evidence' => implode(' · ', $parts),
                    'days_flagged' => max($sweetCount, $carbOverDays),
                ];
            }
        }

        usort($alerts, static function (array $a, array $b): int {
            $rank = ['high' => 0, 'medium' => 1, 'low' => 2];

            return ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9)
                ?: strcmp($a['nama'], $b['nama']);
        });

        return $alerts;
    }

    /**
     * @param  Collection<int,object>  $userDaily
     * @param  Collection<int,object>  $userScores
     * @param  Collection<string,object>  $userTargets
     * @return array<string,array{calories:float,carbs:float,protein:float,calorie_target:float,carb_target:float,protein_target:float}>
     */
    private function mergeDailyActuals(Collection $userDaily, Collection $userScores, Collection $userTargets): array
    {
        $days = [];

        foreach ($userDaily as $row) {
            $date = (string) $row->d;
            $days[$date] = [
                'calories' => (float) $row->calories,
                'carbs' => (float) $row->carbs,
                'protein' => (float) $row->protein,
                'calorie_target' => self::DEFAULT_CALORIE_TARGET,
                'carb_target' => self::DEFAULT_CARB_TARGET,
                'protein_target' => self::DEFAULT_PROTEIN_TARGET,
            ];
        }

        foreach ($userScores as $row) {
            $date = (string) $row->score_date;
            if (! isset($days[$date])) {
                $days[$date] = [
                    'calories' => 0.0,
                    'carbs' => 0.0,
                    'protein' => 0.0,
                    'calorie_target' => self::DEFAULT_CALORIE_TARGET,
                    'carb_target' => self::DEFAULT_CARB_TARGET,
                    'protein_target' => self::DEFAULT_PROTEIN_TARGET,
                ];
            }
            if ($row->calorie_actual !== null) {
                $days[$date]['calories'] = (float) $row->calorie_actual;
            }
            if ($row->carb_actual_g !== null) {
                $days[$date]['carbs'] = (float) $row->carb_actual_g;
            }
            if ($row->protein_actual_g !== null) {
                $days[$date]['protein'] = (float) $row->protein_actual_g;
            }
        }

        foreach ($days as $date => &$day) {
            $target = $userTargets->get($date);
            if ($target !== null) {
                $day['calorie_target'] = (float) $target->calorie_target;
                $day['carb_target'] = (float) $target->carb_target_g;
                $day['protein_target'] = (float) $target->protein_target_g;
            }
        }
        unset($day);

        return $days;
    }

    /**
     * @param  array<int,int>  $candidateIds
     * @return array<int,int>
     */
    private function countSweetFoodEntries(array $candidateIds, string $from, string $to): array
    {
        $rows = $this->db()->table('food_analyses')
            ->whereIn('user_id', $candidateIds)
            ->whereBetween('created_at', [$from, $to])
            ->get(['user_id', 'food_name']);

        $counts = [];
        foreach ($rows as $row) {
            $name = mb_strtolower((string) $row->food_name);
            foreach (self::SUGAR_KEYWORDS as $keyword) {
                if ($name !== '' && str_contains($name, $keyword)) {
                    $uid = (int) $row->user_id;
                    $counts[$uid] = ($counts[$uid] ?? 0) + 1;
                    break;
                }
            }
        }

        return $counts;
    }

    /**
     * @param  array<int,array<string,mixed>>  $alerts
     * @return array<int,array<string,mixed>>
     */
    private function buildRiskRanking(array $alerts): array
    {
        $byUser = [];

        foreach ($alerts as $alert) {
            $uid = (int) $alert['user_id'];
            if (! isset($byUser[$uid])) {
                $byUser[$uid] = [
                    'user_id' => $uid,
                    'nama' => $alert['nama'],
                    'kode_sid' => $alert['kode_sid'],
                    'divisi' => $alert['divisi'],
                    'high' => 0,
                    'medium' => 0,
                    'total' => 0,
                ];
            }
            if ($alert['severity'] === 'high') {
                $byUser[$uid]['high']++;
            } elseif ($alert['severity'] === 'medium') {
                $byUser[$uid]['medium']++;
            }
            $byUser[$uid]['total']++;
        }

        $ranked = array_values($byUser);
        usort($ranked, static function (array $a, array $b): int {
            return $b['high'] <=> $a['high']
                ?: $b['medium'] <=> $a['medium']
                ?: $b['total'] <=> $a['total']
                ?: strcmp($a['nama'], $b['nama']);
        });

        return array_slice($ranked, 0, 10);
    }

    /**
     * @return array{
     *     macroTrendLabels:array<int,string>,
     *     macroTrendCalories:array<int,float>,
     *     macroTrendCarbs:array<int,float>
     * }
     */
    private function buildMacroTrend(Carbon $from, Carbon $to): array
    {
        $labels = [];
        $calories = [];
        $carbs = [];

        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $labels[] = $d->format('d M');
            $calories[$d->format('Y-m-d')] = 0.0;
            $carbs[$d->format('Y-m-d')] = 0.0;
        }

        $rows = $this->db()->table('food_analyses')
            ->selectRaw('DATE(created_at) AS d')
            ->selectRaw('COALESCE(SUM(total_calories), 0) AS calories')
            ->selectRaw('COALESCE(SUM(carbs_g), 0) AS carbs')
            ->whereBetween('created_at', [
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s'),
            ])
            ->groupByRaw('DATE(created_at)')
            ->get();

        foreach ($rows as $row) {
            $key = (string) $row->d;
            if (array_key_exists($key, $calories)) {
                $calories[$key] = round((float) $row->calories, 1);
                $carbs[$key] = round((float) $row->carbs, 1);
            }
        }

        return [
            'macroTrendLabels' => $labels,
            'macroTrendCalories' => array_values($calories),
            'macroTrendCarbs' => array_values($carbs),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildRecentFoodLogs(): array
    {
        $rows = $this->db()->table('food_analyses as f')
            ->join('employee_profiles as e', 'e.id', '=', 'f.user_id')
            ->orderByDesc('f.created_at')
            ->limit(10)
            ->get([
                'f.id',
                'f.food_name',
                'f.total_calories',
                'f.carbs_g',
                'f.meal_type',
                'f.created_at',
                'e.id as user_id',
                'e.nama',
                'e.kode_sid',
            ]);

        $result = [];
        foreach ($rows as $row) {
            $calories = $row->total_calories !== null
                ? number_format((float) $row->total_calories, 0).' kkal'
                : '-';
            $carbs = $row->carbs_g !== null
                ? number_format((float) $row->carbs_g, 0).' g karbo'
                : '';

            $result[] = [
                'id' => (int) $row->id,
                'title' => (string) ($row->food_name !== '' ? $row->food_name : 'Log makanan'),
                'subtitle' => trim($calories.($carbs !== '' ? ' · '.$carbs : '')),
                'user_id' => (int) $row->user_id,
                'user_name' => (string) ($row->nama ?: 'User #'.$row->user_id),
                'kode_sid' => (string) ($row->kode_sid ?: '-'),
                'at' => Carbon::parse($row->created_at)->format('d M Y H:i'),
                'meal_type' => (string) ($row->meal_type ?: '-'),
            ];
        }

        return $result;
    }

    private function db(): \Illuminate\Database\Connection
    {
        return DB::connection(BewellConnectionService::CONNECTION);
    }
}
