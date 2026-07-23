<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Layanan baca-saja untuk modul Evaluasi Olahraga & Aktivitas.
 * Semua akses ke DB aplikasi BeWell dilakukan lewat koneksi 'bewell_db'
 * (read-only) dan hasilnya di-cache Redis. TIDAK pernah menulis ke bewell_db.
 */
final class SportEvaluationService
{
    private const CONNECTION = 'bewell_db';

    private const CACHE_TTL = 600; // 10 menit

    /**
     * Normalisasi input filter dari request menjadi struktur baku.
     * $forced dipakai untuk scoping wajib (mis. Manajer per divisi) yang
     * tidak boleh ditimpa user.
     *
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $forced
     * @return array{from:string,to:string,divisi:?string,perusahaan:?string,site:?string}
     */
    public function normalizeFilters(array $input, array $forced = []): array
    {
        $from = $this->parseDate($input['from'] ?? null, Carbon::now()->subDays(30))->startOfDay();
        $to = $this->parseDate($input['to'] ?? null, Carbon::now())->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $clean = static fn ($v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null;

        return [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
            'divisi' => $forced['divisi'] ?? $clean($input['divisi'] ?? null),
            'perusahaan' => $forced['perusahaan'] ?? $clean($input['perusahaan'] ?? null),
            'site' => $forced['site'] ?? $clean($input['site'] ?? null),
        ];
    }

    /**
     * Opsi dropdown filter (divisi / perusahaan / site).
     *
     * @return array{divisi:array<int,string>,perusahaan:array<int,string>,site:array<int,string>}
     */
    public function filterOptions(): array
    {
        return Cache::remember('sport_eval:filter_options', self::CACHE_TTL, function (): array {
            $pluck = fn (string $col): array => $this->conn()
                ->table('employee_profiles')
                ->whereNotNull($col)
                ->where($col, '!=', '')
                ->distinct()
                ->orderBy($col)
                ->pluck($col)
                ->all();

            return [
                'divisi' => $pluck('divisi'),
                'perusahaan' => $pluck('nama_perusahaan'),
                'site' => $pluck('site'),
            ];
        });
    }

    /**
     * KPI ringkasan untuk kartu dashboard.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function summaryKpi(array $filters): array
    {
        return $this->remember('summary', $filters, function () use ($filters): array {
            $totalKaryawan = (int) $this->scopedEmployees($filters)->count();

            $strava = $this->scopeEmployee(
                $this->conn()->table('strava_activities as a')
                    ->join('employee_profiles as e', 'e.id', '=', 'a.user_id')
                    ->whereBetween('a.start_date', [$filters['from'], $filters['to']]),
                $filters
            )->selectRaw('COUNT(a.id) as sesi, COALESCE(SUM(a.distance_m),0) as meter, COALESCE(SUM(a.moving_time_s),0) as detik, COALESCE(SUM(a.calories),0) as kalori')
                ->first();

            $connected = (int) $this->scopeEmployee(
                $this->conn()->table('strava_connections as c')
                    ->join('employee_profiles as e', 'e.id', '=', 'c.user_id'),
                $filters
            )->count();

            $aktif = (int) $this->conn()
                ->query()
                ->fromSub($this->activeUserIdsUnion($filters), 'u')
                ->distinct()
                ->count('u.user_id');

            $score = $this->scopeEmployee(
                $this->conn()->table('daily_health_scores as s')
                    ->join('employee_profiles as e', 'e.id', '=', 's.user_id')
                    ->whereBetween('s.score_date', [
                        Carbon::parse($filters['from'])->format('Y-m-d'),
                        Carbon::parse($filters['to'])->format('Y-m-d'),
                    ]),
                $filters
            )->selectRaw('AVG(s.exercise_actual_min) as avg_actual, AVG(s.exercise_score) as avg_score')
                ->first();

            $targetMin = $this->scopeEmployee(
                $this->conn()->table('goal_daily_targets as t')
                    ->join('employee_profiles as e', 'e.id', '=', 't.user_id')
                    ->whereBetween('t.target_date', [
                        Carbon::parse($filters['from'])->format('Y-m-d'),
                        Carbon::parse($filters['to'])->format('Y-m-d'),
                    ]),
                $filters
            )->avg('t.exercise_duration_target_min');

            return [
                'total_karyawan' => $totalKaryawan,
                'aktif_olahraga' => $aktif,
                'aktif_pct' => $totalKaryawan > 0 ? round($aktif / $totalKaryawan * 100, 1) : 0.0,
                'total_sesi' => (int) ($strava->sesi ?? 0),
                'total_km' => round((float) ($strava->meter ?? 0) / 1000, 1),
                'total_menit' => (int) round((float) ($strava->detik ?? 0) / 60),
                'total_kalori' => (int) round((float) ($strava->kalori ?? 0)),
                'strava_connected' => $connected,
                'connect_rate' => $totalKaryawan > 0 ? round($connected / $totalKaryawan * 100, 1) : 0.0,
                'avg_active_min' => (int) round((float) ($score->avg_actual ?? 0)),
                'avg_target_min' => (int) round((float) ($targetMin ?? 0)),
                'avg_exercise_score' => round((float) ($score->avg_score ?? 0), 1),
            ];
        });
    }

    /**
     * Tren aktivitas harian (jumlah sesi Strava + total km) untuk chart garis.
     *
     * @param  array<string,mixed>  $filters
     * @return array{labels:array<int,string>,sesi:array<int,int>,km:array<int,float>}
     */
    public function dailyTrend(array $filters): array
    {
        return $this->remember('trend', $filters, function () use ($filters): array {
            $rows = $this->scopeEmployee(
                $this->conn()->table('strava_activities as a')
                    ->join('employee_profiles as e', 'e.id', '=', 'a.user_id')
                    ->whereBetween('a.start_date', [$filters['from'], $filters['to']]),
                $filters
            )
                ->selectRaw('DATE(a.start_date) as d, COUNT(a.id) as sesi, COALESCE(SUM(a.distance_m),0)/1000 as km')
                ->groupBy('d')
                ->orderBy('d')
                ->get();

            return [
                'labels' => $rows->pluck('d')->map(fn ($d) => (string) $d)->all(),
                'sesi' => $rows->pluck('sesi')->map(fn ($v) => (int) $v)->all(),
                'km' => $rows->pluck('km')->map(fn ($v) => round((float) $v, 1))->all(),
            ];
        });
    }

    /**
     * Distribusi jenis olahraga (Strava sport_type) untuk chart donut.
     *
     * @param  array<string,mixed>  $filters
     * @return array{labels:array<int,string>,counts:array<int,int>}
     */
    public function sportDistribution(array $filters): array
    {
        return $this->remember('distribution', $filters, function () use ($filters): array {
            $rows = $this->scopeEmployee(
                $this->conn()->table('strava_activities as a')
                    ->join('employee_profiles as e', 'e.id', '=', 'a.user_id')
                    ->whereBetween('a.start_date', [$filters['from'], $filters['to']]),
                $filters
            )
                ->selectRaw("COALESCE(NULLIF(a.sport_type,''), NULLIF(a.type,''), 'Lainnya') as jenis, COUNT(a.id) as total")
                ->groupBy('jenis')
                ->orderByDesc('total')
                ->limit(12)
                ->get();

            return [
                'labels' => $rows->pluck('jenis')->map(fn ($v) => (string) $v)->all(),
                'counts' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
            ];
        });
    }

    /**
     * Leaderboard olahraga per divisi (total km & sesi).
     *
     * @param  array<string,mixed>  $filters
     * @return array<int,array{divisi:string,sesi:int,km:float}>
     */
    public function divisionLeaderboard(array $filters): array
    {
        return $this->remember('leaderboard', $filters, function () use ($filters): array {
            return $this->scopeEmployee(
                $this->conn()->table('strava_activities as a')
                    ->join('employee_profiles as e', 'e.id', '=', 'a.user_id')
                    ->whereBetween('a.start_date', [$filters['from'], $filters['to']])
                    ->whereNotNull('e.divisi')
                    ->where('e.divisi', '!=', ''),
                $filters
            )
                ->selectRaw('e.divisi as divisi, COUNT(a.id) as sesi, COALESCE(SUM(a.distance_m),0)/1000 as km')
                ->groupBy('e.divisi')
                ->orderByDesc('km')
                ->limit(15)
                ->get()
                ->map(fn ($r): array => [
                    'divisi' => (string) $r->divisi,
                    'sesi' => (int) $r->sesi,
                    'km' => round((float) $r->km, 1),
                ])
                ->all();
        });
    }

    /**
     * Dataset untuk DataTables server-side (gabungan Strava + manual).
     *
     * @param  array<string,mixed>  $filters
     * @return array{recordsTotal:int,recordsFiltered:int,data:array<int,array<string,mixed>>}
     */
    public function activityDataset(array $filters, int $start, int $length, ?string $search): array
    {
        $base = fn (): Builder => $this->conn()->query()->fromSub($this->activityUnion($filters), 't');

        $recordsTotal = (int) $base()->count();

        $filtered = $base();
        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $filtered->where(function (Builder $q) use ($like): void {
                $q->where('t.nama', 'like', $like)
                    ->orWhere('t.divisi', 'like', $like)
                    ->orWhere('t.jenis', 'like', $like)
                    ->orWhere('t.source', 'like', $like);
            });
        }

        $recordsFiltered = (int) (clone $filtered)->count();

        $rows = $filtered
            ->orderByDesc('t.started_at')
            ->offset(max(0, $start))
            ->limit($length > 0 ? $length : 25)
            ->get();

        $data = $rows->map(function ($r): array {
            $durationS = $r->duration_s !== null ? (int) $r->duration_s : null;
            $distanceM = $r->distance_m !== null ? (float) $r->distance_m : null;

            return [
                'user_id' => (int) $r->user_id,
                'started_at' => $r->started_at ? Carbon::parse($r->started_at)->format('d M Y H:i') : '-',
                'nama' => (string) ($r->nama ?? '-'),
                'divisi' => (string) ($r->divisi ?? '-'),
                'source' => (string) $r->source,
                'jenis' => (string) ($r->jenis ?? '-'),
                'durasi' => $durationS !== null ? $this->formatDuration($durationS) : '-',
                'jarak_km' => $distanceM !== null ? round($distanceM / 1000, 2) : null,
                'kalori' => $r->calories !== null ? (int) round((float) $r->calories) : null,
                'avg_hr' => $r->avg_hr !== null ? (int) round((float) $r->avg_hr) : null,
            ];
        })->all();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    /**
     * Profil olahraga 360 untuk satu karyawan.
     *
     * @return array<string,mixed>|null
     */
    public function employeeProfile(int $userId): ?array
    {
        return Cache::remember("sport_eval:profile:{$userId}", self::CACHE_TTL, function () use ($userId): ?array {
            $employee = $this->conn()->table('employee_profiles')
                ->select(['id', 'kode_sid', 'nama', 'divisi', 'departement', 'nama_perusahaan', 'site', 'level_jabatan'])
                ->where('id', $userId)
                ->first();

            if ($employee === null) {
                return null;
            }

            $from30 = Carbon::now()->subDays(30)->startOfDay()->format('Y-m-d H:i:s');
            $now = Carbon::now()->endOfDay()->format('Y-m-d H:i:s');

            $strava = $this->conn()->table('strava_activities')
                ->where('user_id', $userId)
                ->whereBetween('start_date', [$from30, $now])
                ->selectRaw('COUNT(id) as sesi, COALESCE(SUM(distance_m),0)/1000 as km, COALESCE(SUM(moving_time_s),0)/60 as menit, COALESCE(SUM(calories),0) as kalori, AVG(average_heartrate) as avg_hr')
                ->first();

            $manual = $this->conn()->table('workout_analyses')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$from30, $now])
                ->selectRaw('COUNT(id) as sesi, COALESCE(SUM(calories_kcal),0) as kalori')
                ->first();

            $connection = $this->conn()->table('strava_connections')
                ->select(['athlete_firstname', 'athlete_lastname', 'connected_at', 'last_synced_at'])
                ->where('user_id', $userId)
                ->first();

            $score = $this->conn()->table('daily_health_scores')
                ->where('user_id', $userId)
                ->whereBetween('score_date', [
                    Carbon::now()->subDays(7)->format('Y-m-d'),
                    Carbon::now()->format('Y-m-d'),
                ])
                ->selectRaw('AVG(exercise_actual_min) as avg_actual, AVG(exercise_score) as avg_score, AVG(steps_actual) as avg_steps')
                ->first();

            $target = $this->conn()->table('goal_daily_targets')
                ->where('user_id', $userId)
                ->whereBetween('target_date', [
                    Carbon::now()->subDays(7)->format('Y-m-d'),
                    Carbon::now()->format('Y-m-d'),
                ])
                ->selectRaw('AVG(exercise_duration_target_min) as avg_target, AVG(step_target) as avg_step_target')
                ->first();

            $openPlay = (int) $this->conn()->table('open_play_participants')
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->count();

            $recent = $this->conn()->table('strava_activities')
                ->where('user_id', $userId)
                ->orderByDesc('start_date')
                ->limit(10)
                ->get([
                    'name', 'sport_type', 'type', 'distance_m', 'moving_time_s',
                    'calories', 'average_heartrate', 'start_date', 'map_summary_polyline',
                ])
                ->map(fn ($a): array => [
                    'name' => (string) ($a->name ?? '-'),
                    'jenis' => (string) ($a->sport_type ?: $a->type ?: 'Lainnya'),
                    'jarak_km' => round((float) ($a->distance_m ?? 0) / 1000, 2),
                    'durasi' => $this->formatDuration((int) ($a->moving_time_s ?? 0)),
                    'kalori' => (int) round((float) ($a->calories ?? 0)),
                    'avg_hr' => $a->average_heartrate !== null ? (int) round((float) $a->average_heartrate) : null,
                    'tanggal' => $a->start_date ? Carbon::parse($a->start_date)->format('d M Y H:i') : '-',
                    'polyline' => $a->map_summary_polyline,
                ])
                ->all();

            return [
                'employee' => (array) $employee,
                'strava' => [
                    'sesi' => (int) ($strava->sesi ?? 0),
                    'km' => round((float) ($strava->km ?? 0), 1),
                    'menit' => (int) round((float) ($strava->menit ?? 0)),
                    'kalori' => (int) round((float) ($strava->kalori ?? 0)),
                    'avg_hr' => $strava->avg_hr !== null ? (int) round((float) $strava->avg_hr) : null,
                ],
                'manual' => [
                    'sesi' => (int) ($manual->sesi ?? 0),
                    'kalori' => (int) round((float) ($manual->kalori ?? 0)),
                ],
                'connection' => $connection ? (array) $connection : null,
                'target_vs_actual' => [
                    'avg_actual_min' => (int) round((float) ($score->avg_actual ?? 0)),
                    'avg_target_min' => (int) round((float) ($target->avg_target ?? 0)),
                    'avg_score' => round((float) ($score->avg_score ?? 0), 1),
                    'avg_steps' => (int) round((float) ($score->avg_steps ?? 0)),
                    'avg_step_target' => (int) round((float) ($target->avg_step_target ?? 0)),
                ],
                'open_play_joined' => $openPlay,
                'recent_activities' => $recent,
            ];
        });
    }

    /**
     * Union user_id yang aktif berolahraga (Strava atau manual) dalam rentang.
     *
     * @param  array<string,mixed>  $filters
     */
    private function activeUserIdsUnion(array $filters): Builder
    {
        $strava = $this->scopeEmployee(
            $this->conn()->table('strava_activities as a')
                ->join('employee_profiles as e', 'e.id', '=', 'a.user_id')
                ->whereBetween('a.start_date', [$filters['from'], $filters['to']]),
            $filters
        )->select('a.user_id as user_id');

        $manual = $this->scopeEmployee(
            $this->conn()->table('workout_analyses as w')
                ->join('employee_profiles as e', 'e.id', '=', 'w.user_id')
                ->whereBetween('w.created_at', [$filters['from'], $filters['to']]),
            $filters
        )->select('w.user_id as user_id');

        return $strava->unionAll($manual);
    }

    /**
     * Union baris aktivitas (Strava + manual) untuk tabel detail.
     *
     * @param  array<string,mixed>  $filters
     */
    private function activityUnion(array $filters): Builder
    {
        $strava = $this->scopeEmployee(
            $this->conn()->table('strava_activities as a')
                ->join('employee_profiles as e', 'e.id', '=', 'a.user_id')
                ->whereBetween('a.start_date', [$filters['from'], $filters['to']]),
            $filters
        )->selectRaw("a.user_id as user_id, e.nama as nama, e.divisi as divisi, 'strava' as source, COALESCE(NULLIF(a.sport_type,''), NULLIF(a.type,''), 'Lainnya') as jenis, a.start_date as started_at, a.moving_time_s as duration_s, a.distance_m as distance_m, a.calories as calories, a.average_heartrate as avg_hr");

        $manual = $this->scopeEmployee(
            $this->conn()->table('workout_analyses as w')
                ->join('employee_profiles as e', 'e.id', '=', 'w.user_id')
                ->whereBetween('w.created_at', [$filters['from'], $filters['to']]),
            $filters
        )->selectRaw("w.user_id as user_id, e.nama as nama, e.divisi as divisi, 'manual' as source, COALESCE(NULLIF(w.activity_type,''), 'Lainnya') as jenis, w.created_at as started_at, NULL as duration_s, NULL as distance_m, w.calories_kcal as calories, NULL as avg_hr");

        return $strava->unionAll($manual);
    }

    /**
     * Query employee_profiles yang sudah discope filter.
     *
     * @param  array<string,mixed>  $filters
     */
    private function scopedEmployees(array $filters): Builder
    {
        $q = $this->conn()->table('employee_profiles as e');

        return $this->applyScope($q, $filters);
    }

    /**
     * Terapkan filter scope (divisi/perusahaan/site) ke builder ber-alias 'e'.
     *
     * @param  array<string,mixed>  $filters
     */
    private function scopeEmployee(Builder $query, array $filters): Builder
    {
        return $this->applyScope($query, $filters);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function applyScope(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['divisi'] ?? null, fn (Builder $q, $v) => $q->where('e.divisi', $v))
            ->when($filters['perusahaan'] ?? null, fn (Builder $q, $v) => $q->where('e.nama_perusahaan', $v))
            ->when($filters['site'] ?? null, fn (Builder $q, $v) => $q->where('e.site', $v));
    }

    /**
     * Bungkus pemanggilan dengan cache Redis.
     *
     * @param  array<string,mixed>  $filters
     */
    private function remember(string $bucket, array $filters, \Closure $callback): mixed
    {
        $key = 'sport_eval:'.$bucket.':'.md5(json_encode($filters) ?: '');

        return Cache::remember($key, self::CACHE_TTL, $callback);
    }

    private function conn(): \Illuminate\Database\Connection
    {
        /** @var \Illuminate\Database\Connection $connection */
        $connection = DB::connection(self::CONNECTION);

        return $connection;
    }

    private function parseDate(mixed $value, Carbon $default): Carbon
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return $default->copy();
            }
        }

        return $default->copy();
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0m';
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $h > 0 ? "{$h}j {$m}m" : "{$m}m";
    }
}
