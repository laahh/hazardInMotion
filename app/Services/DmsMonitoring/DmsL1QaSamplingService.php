<?php

declare(strict_types=1);

namespace App\Services\DmsMonitoring;

use App\Models\DmsMonitoring\DmsL1QaSample;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Alur QA false negative L1: bcsid tidak punya data ground-truth soal alert
 * mana yang salah di-dismiss L1 (dianggap bukan pelanggaran padahal nyata) —
 * satu-satunya cara tahu adalah audit ulang manual atas SAMPEL alert yang
 * di-dismiss. Ukuran sampel dihitung pakai rumus Slovin (SlovinSamplingCalculator)
 * supaya hasil audit bisa dianggap mewakili populasi, bukan asal comot.
 */
final class DmsL1QaSamplingService
{
    private const MAX_PENDING_LIST = 100;

    public function __construct(
        private readonly DmsAlertMonitoringDataReader $reader,
    ) {}

    /**
     * Buat sampel baru untuk periode ini kalau belum ada — idempotent (SID
     * unik id_alert+period di migration mencegah duplikat kalau dipanggil ulang).
     *
     * @return array{generated:int, population:int, target_sample_size:int, already_sampled:int}
     */
    public function generateSampleForPeriod(string $periodStart, string $periodEnd, float $marginOfError = SlovinSamplingCalculator::DEFAULT_MARGIN_OF_ERROR): array
    {
        $start = Carbon::parse($periodStart)->startOfDay()->format('Y-m-d H:i:s');
        $end = Carbon::parse($periodEnd)->startOfDay()->addDay()->format('Y-m-d H:i:s');

        $population = $this->reader->dismissedL1Count($start, $end);
        $targetSampleSize = SlovinSamplingCalculator::sampleSize($population, $marginOfError);

        $alreadySampled = DmsL1QaSample::query()
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->count();

        $stillNeeded = max(0, $targetSampleSize - $alreadySampled);
        if ($stillNeeded === 0) {
            return ['generated' => 0, 'population' => $population, 'target_sample_size' => $targetSampleSize, 'already_sampled' => $alreadySampled];
        }

        $existingIds = DmsL1QaSample::query()
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->pluck('id_alert')
            ->all();

        $candidates = $this->reader->dismissedL1AlertsForSampling($start, $end, $stillNeeded, $existingIds);

        $generated = 0;
        foreach ($candidates as $candidate) {
            try {
                DmsL1QaSample::query()->create([
                    'id_alert' => $candidate['id_alert'],
                    'kode_sid' => $candidate['kode_sid'],
                    'nama_pelanggaran' => $candidate['nama_pelanggaran'],
                    'unit' => $candidate['unit'],
                    'site' => $candidate['site'],
                    'waktu_deteksi' => $candidate['waktu_deteksi'],
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'margin_of_error' => $marginOfError,
                ]);
                $generated++;
            } catch (Throwable $e) {
                // Unique constraint bentrok (id_alert sudah pernah disampling) — lewati, bukan fatal.
                report($e);
            }
        }

        return [
            'generated' => $generated,
            'population' => $population,
            'target_sample_size' => $targetSampleSize,
            'already_sampled' => $alreadySampled + $generated,
        ];
    }

    public function recordVerdict(int $sampleId, string $verdict, ?string $catatan, ?int $userId): bool
    {
        if (! in_array($verdict, [DmsL1QaSample::VERDICT_BENAR_DISMISS, DmsL1QaSample::VERDICT_FALSE_NEGATIVE, DmsL1QaSample::VERDICT_TIDAK_JELAS], true)) {
            return false;
        }

        try {
            $sample = DmsL1QaSample::query()->find($sampleId);
            if ($sample === null) {
                return false;
            }

            $sample->update([
                'verdict' => $verdict,
                'catatan' => $catatan,
                'audited_by' => $userId,
                'audited_at' => Carbon::now(),
            ]);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * @return list<array{id:int, id_alert:string, kode_sid:string|null, nama_pelanggaran:string|null, unit:string|null, site:string|null, waktu_deteksi:string|null}>
     */
    public function pendingSamples(string $periodStart, string $periodEnd): array
    {
        try {
            return DmsL1QaSample::query()
                ->whereDate('period_start', $periodStart)
                ->whereDate('period_end', $periodEnd)
                ->whereNull('verdict')
                ->orderBy('waktu_deteksi')
                ->limit(self::MAX_PENDING_LIST)
                ->get()
                ->map(static fn (DmsL1QaSample $s): array => [
                    'id' => $s->id,
                    'id_alert' => $s->id_alert,
                    'kode_sid' => $s->kode_sid,
                    'nama_pelanggaran' => $s->nama_pelanggaran,
                    'unit' => $s->unit,
                    'site' => $s->site,
                    'waktu_deteksi' => $s->waktu_deteksi?->format('Y-m-d H:i'),
                ])
                ->values()
                ->all();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return array{
     *     population:int, target_sample_size:int, total_sampled:int, total_audited:int, pending:int,
     *     benar_dismiss:int, false_negative:int, tidak_jelas:int,
     *     false_negative_rate:float|null, estimated_false_negative_count:int|null
     * }
     */
    public function summaryForPeriod(string $periodStart, string $periodEnd, float $marginOfError = SlovinSamplingCalculator::DEFAULT_MARGIN_OF_ERROR): array
    {
        $start = Carbon::parse($periodStart)->startOfDay()->format('Y-m-d H:i:s');
        $end = Carbon::parse($periodEnd)->startOfDay()->addDay()->format('Y-m-d H:i:s');
        $population = $this->reader->dismissedL1Count($start, $end);
        $targetSampleSize = SlovinSamplingCalculator::sampleSize($population, $marginOfError);

        try {
            $samples = DmsL1QaSample::query()
                ->whereDate('period_start', $periodStart)
                ->whereDate('period_end', $periodEnd)
                ->get(['verdict']);
        } catch (Throwable $e) {
            report($e);
            $samples = collect();
        }

        $totalSampled = $samples->count();
        $benarDismiss = $samples->where('verdict', DmsL1QaSample::VERDICT_BENAR_DISMISS)->count();
        $falseNegative = $samples->where('verdict', DmsL1QaSample::VERDICT_FALSE_NEGATIVE)->count();
        $tidakJelas = $samples->where('verdict', DmsL1QaSample::VERDICT_TIDAK_JELAS)->count();
        $totalAudited = $benarDismiss + $falseNegative + $tidakJelas;

        $falseNegativeRate = $totalAudited > 0 ? round($falseNegative / $totalAudited * 100, 1) : null;
        $estimatedCount = $falseNegativeRate !== null ? (int) round($population * $falseNegativeRate / 100) : null;

        return [
            'population' => $population,
            'target_sample_size' => $targetSampleSize,
            'total_sampled' => $totalSampled,
            'total_audited' => $totalAudited,
            'pending' => max(0, $totalSampled - $totalAudited),
            'benar_dismiss' => $benarDismiss,
            'false_negative' => $falseNegative,
            'tidak_jelas' => $tidakJelas,
            'false_negative_rate' => $falseNegativeRate,
            'estimated_false_negative_count' => $estimatedCount,
        ];
    }
}
