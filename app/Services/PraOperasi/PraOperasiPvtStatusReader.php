<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Services\SportEvaluation\BewellConnectionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Status PVT per SID pada tanggal tertentu, dicocokkan ke waktu check-in.
 * Logic pencocokan sama persis dengan SportEvaluationPvtDashboardService
 * (loadPvtBySid + matchPvtForCheckin + resolvePvtStatus) — diduplikasi ringkas
 * di sini karena method aslinya private dan kelas itu membangun roster-nya
 * sendiri dari employee_profiles, sedangkan Pra Operasi butuh mencocokkan ke
 * roster hasil query hse_automation. Sumber data (cognitive_pvt_results) sama.
 */
final class PraOperasiPvtStatusReader
{
    private const SID_CHUNK = 500;

    public function __construct(
        private readonly BewellConnectionService $bewell,
    ) {}

    public function isUp(): bool
    {
        return $this->bewell->isUp();
    }

    /**
     * @param  array<string, string>  $checkinAtBySid  UPPER(kode_sid) => waktu check-in (Y-m-d H:i:s)
     * @return array<string, array{
     *     status: string, mean_rt_ms: int|null, median_rt_ms: int|null,
     *     lapses: int|null, false_starts: int|null, evaluation_label: string, tested_at: string
     * }>  keyed by UPPER(kode_sid); status: belum|lulus|tidak_lulus
     */
    public function statusForCheckins(array $checkinAtBySid, string $date): array
    {
        if (! $this->isUp() || $checkinAtBySid === []) {
            return [];
        }

        $upperSids = array_keys($checkinAtBySid);

        $cacheKey = 'pra_operasi:pvt:v1:'.$date.':'.md5(implode(',', $upperSids));

        $pvtBySid = Cache::remember($cacheKey, 30, function () use ($upperSids, $date): array {
            return $this->loadPvtBySid($upperSids, $date);
        });

        $result = [];
        foreach ($upperSids as $sid) {
            $matched = $this->matchPvtForCheckin($pvtBySid[$sid] ?? [], $checkinAtBySid[$sid] ?? '');
            $result[$sid] = [
                'status' => $this->resolveStatus($matched),
                'mean_rt_ms' => $matched['mean_rt_ms'] ?? null,
                'median_rt_ms' => $matched['median_rt_ms'] ?? null,
                'lapses' => $matched['lapses'] ?? null,
                'false_starts' => $matched['false_starts'] ?? null,
                'evaluation_label' => $matched['evaluation_label'] ?? '',
                'tested_at' => $matched['tested_at'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * Riwayat hasil PVT satu SID selama N hari terakhir (untuk panel detail
     * operator) — beda dari statusForCheckins() yang hanya mencocokkan SATU
     * hasil per hari ke waktu check-in.
     *
     * @return list<array{date:string, status:string, mean_rt_ms:int|null, lapses:int|null, evaluation_label:string, tested_at:string}>
     */
    public function historyForSid(string $kodeSid, string $untilDate, int $days = 30): array
    {
        if (! $this->isUp()) {
            return [];
        }

        $tz = (string) config('app.timezone');
        $end = Carbon::parse($untilDate, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');
        $start = Carbon::parse($untilDate, $tz)->startOfDay()->subDays($days)->format('Y-m-d H:i:s');

        try {
            $rows = DB::connection(BewellConnectionService::CONNECTION)
                ->table('cognitive_pvt_results as p')
                ->join('employee_profiles as e', 'e.id', '=', 'p.user_id')
                ->select(['p.tested_at', 'p.passed', 'p.evaluation_label', 'p.mean_rt_ms', 'p.lapses'])
                ->where('p.tested_at', '>=', $start)
                ->where('p.tested_at', '<', $end)
                ->whereRaw('UPPER(TRIM(e.kode_sid)) = ?', [mb_strtoupper($kodeSid)])
                ->orderByDesc('p.tested_at')
                ->limit(30)
                ->get();
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $tested = $this->parseAppDateTime($row->tested_at ?? null);
            if ($tested === null) {
                continue;
            }
            $out[] = [
                'date' => $tested->toDateString(),
                'status' => $this->resolveStatus([
                    'passed' => $row->passed === null ? null : (int) $row->passed,
                    'evaluation_label' => $row->evaluation_label,
                ]),
                'mean_rt_ms' => $row->mean_rt_ms === null ? null : (int) $row->mean_rt_ms,
                'lapses' => $row->lapses === null ? null : (int) $row->lapses,
                'evaluation_label' => trim((string) ($row->evaluation_label ?? '')),
                'tested_at' => $tested->format('Y-m-d H:i'),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $upperSids
     * @return array<string, list<array<string, mixed>>>
     */
    private function loadPvtBySid(array $upperSids, string $date): array
    {
        $tz = (string) config('app.timezone');
        $day = Carbon::parse($date, $tz)->startOfDay();
        $start = $day->copy()->subHours(12)->format('Y-m-d H:i:s');
        $end = $day->copy()->addDay()->addHours(12)->format('Y-m-d H:i:s');

        $rows = collect();
        foreach (array_chunk($upperSids, self::SID_CHUNK) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            try {
                $chunkRows = DB::connection(BewellConnectionService::CONNECTION)
                    ->table('cognitive_pvt_results as p')
                    ->join('employee_profiles as e', 'e.id', '=', 'p.user_id')
                    ->select(['e.kode_sid', 'p.tested_at', 'p.passed', 'p.evaluation_label', 'p.mean_rt_ms', 'p.median_rt_ms', 'p.lapses', 'p.false_starts'])
                    ->where('p.tested_at', '>=', $start)
                    ->where('p.tested_at', '<', $end)
                    ->whereRaw('UPPER(TRIM(e.kode_sid)) IN ('.$placeholders.')', $chunk)
                    ->orderBy('p.tested_at')
                    ->get();
                $rows = $rows->concat($chunkRows);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $grouped = [];
        foreach ($rows as $row) {
            $sid = mb_strtoupper(trim((string) ($row->kode_sid ?? '')));
            if ($sid === '') {
                continue;
            }
            $tested = $this->parseAppDateTime($row->tested_at ?? null);
            if ($tested === null || $tested->toDateString() !== $date) {
                // window ±12 jam bisa menangkap tanggal tetangga — buang yang bukan tanggal target
                continue;
            }
            $grouped[$sid][] = [
                'tested_at' => $tested->format('Y-m-d H:i:s'),
                'passed' => $row->passed === null ? null : (int) $row->passed,
                'evaluation_label' => trim((string) ($row->evaluation_label ?? '')),
                'mean_rt_ms' => $row->mean_rt_ms === null ? null : (int) $row->mean_rt_ms,
                'median_rt_ms' => $row->median_rt_ms === null ? null : (int) $row->median_rt_ms,
                'lapses' => $row->lapses === null ? null : (int) $row->lapses,
                'false_starts' => $row->false_starts === null ? null : (int) $row->false_starts,
            ];
        }

        return $grouped;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, mixed>|null
     */
    private function matchPvtForCheckin(array $candidates, string $checkedInAt): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $checkin = $this->parseAppDateTime($checkedInAt);
        $afterCheckin = [];
        foreach ($candidates as $candidate) {
            $tested = $this->parseAppDateTime($candidate['tested_at'] ?? null);
            if ($tested === null) {
                continue;
            }
            if ($checkin === null || $tested->timestamp >= $checkin->timestamp) {
                $afterCheckin[] = $candidate;
            }
        }

        $pool = $afterCheckin !== [] ? $afterCheckin : $candidates;

        $matched = null;
        $matchedTs = null;
        foreach ($pool as $candidate) {
            $tested = $this->parseAppDateTime($candidate['tested_at'] ?? null);
            if ($tested === null) {
                continue;
            }
            if ($matched === null || $tested->timestamp >= $matchedTs) {
                $matched = $candidate;
                $matchedTs = $tested->timestamp;
            }
        }

        return $matched;
    }

    private function resolveStatus(?array $pvt): string
    {
        if ($pvt === null) {
            return 'belum';
        }
        $passed = $pvt['passed'] ?? null;
        if ($passed === null) {
            $label = mb_strtolower((string) ($pvt['evaluation_label'] ?? ''));

            return str_contains($label, 'memenuhi') ? 'lulus' : 'tidak_lulus';
        }

        return ((int) $passed === 1) ? 'lulus' : 'tidak_lulus';
    }

    private function parseAppDateTime(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->timezone((string) config('app.timezone'));
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        try {
            $tz = (string) config('app.timezone');
            $hasOffset = preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $raw) === 1;

            return ($hasOffset ? Carbon::parse($raw) : Carbon::parse($raw, $tz))->timezone($tz);
        } catch (Throwable) {
            return null;
        }
    }
}
