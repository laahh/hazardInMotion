<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Carbon;

/**
 * Agregasi murni metrik Tren Aktivitas dari baris workout yang sudah di-parse.
 * Tidak menyentuh database — mudah diuji.
 */
final class SportEvaluationWorkoutActivityAggregator
{
    public function __construct(
        private readonly WorkoutMetricParser $parser,
    ) {
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function parseWorkoutRow(array $row, string $resolvedSite): array
    {
        $activityType = trim((string) ($row['activity_type'] ?? ''));
        $createdAt = (string) ($row['created_at'] ?? '');
        $local = $createdAt !== '' ? Carbon::parse($createdAt) : Carbon::now();
        $durationSeconds = $this->parser->durationToSeconds(
            isset($row['workout_time']) ? (string) $row['workout_time'] : null
        );
        $calories = $this->parser->resolveCalories(
            $row['calories_kcal'] ?? null,
            isset($row['active_kilocalories']) ? (string) $row['active_kilocalories'] : null,
            isset($row['total_kilocalories']) ? (string) $row['total_kilocalories'] : null,
        );
        $isRunOrWalk = $this->parser->isRunOrWalk($activityType);
        $distanceKm = $this->parser->runWalkDistanceKm(
            $activityType,
            isset($row['distance']) ? (string) $row['distance'] : null
        );

        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'nama' => $this->displayOrDash($row['nama'] ?? null),
            'kode_sid' => $this->displayOrDash($row['kode_sid'] ?? null),
            'site' => $resolvedSite !== '' ? $resolvedSite : '-',
            'company' => $this->displayOrDash($row['nama_perusahaan'] ?? ($row['company'] ?? null)),
            'divisi' => $this->displayOrDash($row['divisi'] ?? null),
            'activity_type' => $activityType !== '' ? $activityType : 'Lainnya',
            'calories_kcal' => $calories,
            'calories_kcal_raw' => $row['calories_kcal'] ?? null,
            'workout_time_raw' => trim((string) ($row['workout_time'] ?? '')),
            'duration_seconds' => $durationSeconds,
            'duration_minutes' => $durationSeconds !== null ? round($durationSeconds / 60, 1) : null,
            'distance_raw' => trim((string) ($row['distance'] ?? '')),
            'distance_km' => $distanceKm,
            'is_run_or_walk' => $isRunOrWalk,
            'avg_heart_rate' => trim((string) ($row['avg_heart_rate'] ?? '')),
            'created_at' => $createdAt,
            'local_date' => $local->format('Y-m-d'),
            'local_datetime' => $local->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $parsedWorkouts
     * @param  array<int, float>  $foodKcalByUser  user_id => kkal
     * @param  array<string, float>  $foodKcalByDate  Y-m-d => kkal
     * @return array{
     *     kpi: array<string, mixed>,
     *     trendDaily: array{labels: list<string>, sesi: list<int>, users: list<int>, kcal_out: list<float>, kcal_in: list<float>, menit: list<float>, km: list<float>},
     *     trendWeekly: array{labels: list<string>, sesi: list<int>, users: list<int>, kcal_out: list<float>, kcal_in: list<float>, menit: list<float>, km: list<float>},
     *     distribution: array{labels: list<string>, counts: list<int>},
     *     topCompanies: array{labels: list<string>, counts: list<int>},
     *     users: list<array<string, mixed>>,
     *     rawWorkouts: list<array<string, mixed>>
     * }
     */
    public function aggregate(
        array $parsedWorkouts,
        array $foodKcalByUser,
        array $foodKcalByDate,
        int $periodDays,
        string $fromDate,
        string $toDate,
    ): array {
        $weeks = max(1.0, $periodDays / 7);

        $totalSessions = count($parsedWorkouts);
        $totalMinutes = 0.0;
        $totalKm = 0.0;
        $kcalOut = 0.0;
        $activeUserIds = [];
        $typeCounts = [];
        $companyCounts = [];
        $users = [];
        $daily = [];

        foreach ($parsedWorkouts as $workout) {
            $userId = (int) $workout['user_id'];
            $activeUserIds[$userId] = true;
            $company = (string) $workout['company'];
            $type = (string) $workout['activity_type'];
            $date = (string) $workout['local_date'];

            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            $companyCounts[$company] = ($companyCounts[$company] ?? 0) + 1;

            $minutes = (float) ($workout['duration_minutes'] ?? 0);
            $km = (float) ($workout['distance_km'] ?? 0);
            $out = (float) ($workout['calories_kcal'] ?? 0);

            $totalMinutes += $minutes;
            $totalKm += $km;
            $kcalOut += $out;

            if (! isset($daily[$date])) {
                $daily[$date] = [
                    'sesi' => 0,
                    'users' => [],
                    'kcal_out' => 0.0,
                    'menit' => 0.0,
                    'km' => 0.0,
                ];
            }
            $daily[$date]['sesi']++;
            $daily[$date]['users'][$userId] = true;
            $daily[$date]['kcal_out'] += $out;
            $daily[$date]['menit'] += $minutes;
            $daily[$date]['km'] += $km;

            if (! isset($users[$userId])) {
                $users[$userId] = [
                    'id' => $userId,
                    'nama' => (string) $workout['nama'],
                    'kode_sid' => (string) $workout['kode_sid'],
                    'site' => (string) $workout['site'],
                    'company' => $company,
                    'divisi' => (string) $workout['divisi'],
                    'sesi' => 0,
                    'duration_minutes' => 0.0,
                    'distance_km' => 0.0,
                    'kcal_out' => 0.0,
                    'kcal_in' => round((float) ($foodKcalByUser[$userId] ?? 0), 1),
                    'last_workout_at' => $workout['local_datetime'],
                    'last_workout_sort' => $workout['created_at'],
                ];
            }

            $users[$userId]['sesi']++;
            $users[$userId]['duration_minutes'] += $minutes;
            $users[$userId]['distance_km'] += $km;
            $users[$userId]['kcal_out'] += $out;
            if ((string) $workout['created_at'] > (string) $users[$userId]['last_workout_sort']) {
                $users[$userId]['last_workout_at'] = $workout['local_datetime'];
                $users[$userId]['last_workout_sort'] = $workout['created_at'];
            }
        }

        $kcalIn = array_sum($foodKcalByDate);
        if ($kcalIn <= 0 && $foodKcalByUser !== []) {
            $kcalIn = array_sum($foodKcalByUser);
        }

        $activeUsers = count($activeUserIds);

        foreach ($users as &$user) {
            $user['duration_minutes'] = round((float) $user['duration_minutes'], 1);
            $user['distance_km'] = round((float) $user['distance_km'], 2);
            $user['kcal_out'] = round((float) $user['kcal_out'], 1);
            $user['kcal_in'] = round((float) $user['kcal_in'], 1);
            $user['kcal_net'] = round((float) $user['kcal_in'] - (float) $user['kcal_out'], 1);
            $user['sessions_per_week'] = round($user['sesi'] / $weeks, 2);
            $user['last_workout_at'] = $this->formatDisplayDatetime((string) $user['last_workout_at']);
            unset($user['last_workout_sort']);
        }
        unset($user);

        usort($users, static function (array $a, array $b): int {
            return $b['sesi'] <=> $a['sesi'] ?: strcmp((string) $a['nama'], (string) $b['nama']);
        });

        arsort($typeCounts);
        $typeCounts = array_slice($typeCounts, 0, 12, true);

        arsort($companyCounts);
        $companyCounts = array_slice($companyCounts, 0, 10, true);

        $trendDaily = $this->buildDailyTrend($fromDate, $toDate, $daily, $foodKcalByDate);
        $trendWeekly = $this->rollUpWeekly($trendDaily);

        $rawWorkouts = $parsedWorkouts;
        usort($rawWorkouts, static function (array $a, array $b): int {
            return strcmp((string) $b['created_at'], (string) $a['created_at']);
        });

        return [
            'kpi' => [
                'total_sessions' => $totalSessions,
                'active_users' => $activeUsers,
                'total_minutes' => (int) round($totalMinutes),
                'total_km' => round($totalKm, 1),
                'kcal_out' => (int) round($kcalOut),
                'kcal_in' => (int) round((float) $kcalIn),
                'avg_sessions_per_week' => round($totalSessions / $weeks, 1),
                'avg_sessions_per_user' => $activeUsers > 0 ? round($totalSessions / $activeUsers, 1) : 0.0,
                'period_days' => $periodDays,
            ],
            'trendDaily' => $trendDaily,
            'trendWeekly' => $trendWeekly,
            'distribution' => [
                'labels' => array_keys($typeCounts),
                'counts' => array_map(static fn ($v): int => (int) $v, array_values($typeCounts)),
            ],
            'topCompanies' => [
                'labels' => array_keys($companyCounts),
                'counts' => array_map(static fn ($v): int => (int) $v, array_values($companyCounts)),
            ],
            'users' => array_values($users),
            'rawWorkouts' => $rawWorkouts,
        ];
    }

    /**
     * @param  array<string, array{sesi:int, users:array<int, bool>, kcal_out:float, menit:float, km:float}>  $daily
     * @param  array<string, float>  $foodKcalByDate
     * @return array{labels: list<string>, sesi: list<int>, users: list<int>, kcal_out: list<float>, kcal_in: list<float>, menit: list<float>, km: list<float>}
     */
    private function buildDailyTrend(string $fromDate, string $toDate, array $daily, array $foodKcalByDate): array
    {
        $labels = [];
        $sesi = [];
        $users = [];
        $kcalOut = [];
        $kcalIn = [];
        $menit = [];
        $km = [];

        $cursor = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m-d');
            $bucket = $daily[$key] ?? [
                'sesi' => 0,
                'users' => [],
                'kcal_out' => 0.0,
                'menit' => 0.0,
                'km' => 0.0,
            ];

            $labels[] = $cursor->format('d M');
            $sesi[] = (int) $bucket['sesi'];
            $users[] = count($bucket['users']);
            $kcalOut[] = round((float) $bucket['kcal_out'], 1);
            $kcalIn[] = round((float) ($foodKcalByDate[$key] ?? 0), 1);
            $menit[] = round((float) $bucket['menit'], 1);
            $km[] = round((float) $bucket['km'], 2);

            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'sesi' => $sesi,
            'users' => $users,
            'kcal_out' => $kcalOut,
            'kcal_in' => $kcalIn,
            'menit' => $menit,
            'km' => $km,
        ];
    }

    /**
     * @param  array{labels: list<string>, sesi: list<int>, users: list<int>, kcal_out: list<float>, kcal_in: list<float>, menit: list<float>, km: list<float>}  $daily
     * @return array{labels: list<string>, sesi: list<int>, users: list<int>, kcal_out: list<float>, kcal_in: list<float>, menit: list<float>, km: list<float>}
     */
    private function rollUpWeekly(array $daily): array
    {
        $labels = [];
        $sesi = [];
        $users = [];
        $kcalOut = [];
        $kcalIn = [];
        $menit = [];
        $km = [];

        $chunkSize = 7;
        $count = count($daily['labels']);
        for ($offset = 0; $offset < $count; $offset += $chunkSize) {
            $end = min($offset + $chunkSize, $count) - 1;
            $labels[] = $daily['labels'][$offset].' – '.$daily['labels'][$end];
            $sesi[] = (int) array_sum(array_slice($daily['sesi'], $offset, $chunkSize));
            $users[] = (int) max(array_slice($daily['users'], $offset, $chunkSize) ?: [0]);
            $kcalOut[] = round((float) array_sum(array_slice($daily['kcal_out'], $offset, $chunkSize)), 1);
            $kcalIn[] = round((float) array_sum(array_slice($daily['kcal_in'], $offset, $chunkSize)), 1);
            $menit[] = round((float) array_sum(array_slice($daily['menit'], $offset, $chunkSize)), 1);
            $km[] = round((float) array_sum(array_slice($daily['km'], $offset, $chunkSize)), 2);
        }

        return [
            'labels' => $labels,
            'sesi' => $sesi,
            'users' => $users,
            'kcal_out' => $kcalOut,
            'kcal_in' => $kcalIn,
            'menit' => $menit,
            'km' => $km,
        ];
    }

    private function displayOrDash(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '-';
    }

    private function formatDisplayDatetime(string $value): string
    {
        if ($value === '') {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d M Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }
}
