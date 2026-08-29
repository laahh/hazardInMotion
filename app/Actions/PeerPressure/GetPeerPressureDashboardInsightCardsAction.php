<?php

declare(strict_types=1);

namespace App\Actions\PeerPressure;

use App\Models\PeerPressureKejadianEdukasi;
use App\Services\PeerPressure\PeerPressureKaryawanNitipService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Empat kartu insight dashboard (Deviation, Compliance radar, Lokasi, Profiling pelanggar).
 * Tanpa tahun+bulan: seluruh data. Dengan tahun+bulan: filter tanggal temuan di bulan kalender tersebut.
 */
final class GetPeerPressureDashboardInsightCardsAction
{
    private const MIN_YEAR = 2025;

    private const MAX_YEAR = 2026;

    private const PROFILING_PELANGGAR_LIMIT = 10;

    public function __construct(
        private readonly PeerPressureKaryawanNitipService $karyawanNitip,
    ) {}

    /**
     * @return array{
     *   deviation: array{total: int, total_label: string, conic_gradient: string, categories: list<array{kategori_deviasi: string, jumlah: int, pct: float, color: string}>},
     *   compliance: array{berecord_pct: float, evidence_pct: float, size_pct: float, h1_pct: float, duration_label: string, triangle_rotate_deg: float},
     *   locations: list<array{name: string, count: int, bar_pct: float}>,
     *   profiling_pelanggar: list<array{sid: string, nama: string, kasus: int, insiden_share_pct: float, foto_url: string|null}>
     * }
     */
    public function __invoke(?int $year = null, ?int $month = null): array
    {
        $base = $this->scopedBaseQuery($year, $month);

        $total = (clone $base)->count();

        $deviation = $this->buildDeviationSegments($base, $total);
        $compliance = $this->buildCompliance($base, $total);
        $locations = $this->buildLocations($base);
        $profilingPelanggar = $this->buildProfilingPelanggar($base, $total);

        return [
            'deviation' => $deviation,
            'compliance' => $compliance,
            'locations' => $locations,
            'profiling_pelanggar' => $profilingPelanggar,
        ];
    }

    private function scopedBaseQuery(?int $year, ?int $month): Builder
    {
        $q = PeerPressureKejadianEdukasi::query();
        if ($year !== null && $month !== null) {
            $y = max(self::MIN_YEAR, min(self::MAX_YEAR, $year));
            $m = max(1, min(12, $month));
            $start = Carbon::create($y, $m, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $q->where('tanggal_temuan', '>=', $start->toDateString())
                ->where('tanggal_temuan', '<=', $end->toDateString());
        }

        return $q;
    }

    /**
     * @return array{total: int, total_label: string, conic_gradient: string, categories: list<array{kategori_deviasi: string, jumlah: int, pct: float, color: string}>}
     */
    private function buildDeviationSegments(Builder $base, int $total): array
    {
        $grouped = (clone $base)
            ->selectRaw('kategori_deviasi, COUNT(*) as c')
            ->whereNotNull('kategori_deviasi')
            ->where('kategori_deviasi', '!=', '')
            ->groupBy('kategori_deviasi')
            ->pluck('c', 'kategori_deviasi');

        $sumNonEmpty = (int) $grouped->sum();
        $missing = $total - $sumNonEmpty;

        $categories = [];
        foreach ($grouped->sortDesc() as $kat => $c) {
            $categories[] = [
                'kategori_deviasi' => (string) $kat,
                'jumlah' => (int) $c,
            ];
        }
        if ($missing > 0) {
            $categories[] = [
                'kategori_deviasi' => 'Tidak diisi',
                'jumlah' => $missing,
            ];
        }
        usort($categories, static fn (array $a, array $b): int => $b['jumlah'] <=> $a['jumlah']);

        foreach ($categories as &$row) {
            $slicePct = $total > 0 ? ($row['jumlah'] / $total) * 100 : 0.0;
            $row['pct'] = round($slicePct, 1);
            $row['color'] = $this->deviationColorForCategory((string) $row['kategori_deviasi']);
        }
        unset($row);

        $conic = $this->buildDeviationConicGradient($categories, $total);

        return [
            'total' => $total,
            'total_label' => $this->formatCompactTotal($total),
            'conic_gradient' => $conic,
            'categories' => $categories,
        ];
    }

    /**
     * Warna berbeda per kategori: cocokkan pola nama dulu, lalu palet stabil dari hash label.
     */
    private function deviationColorForCategory(string $label): string
    {
        $k = mb_strtolower(trim($label));

        if ($k === 'tidak diisi' || $k === '(kosong)') {
            return 'hsl(215 14% 62%)';
        }

        if (
            str_contains($k, 'blindspot')
            || str_contains($k, 'tbc')
            || (str_contains($k, 'concerned') && str_contains($k, 'hazard'))
        ) {
            return 'hsl(173 58% 36%)';
        }

        if (
            str_contains($k, 'fatigue')
            || str_contains($k, 'speak up')
            || str_contains($k, 'speak-up')
            || str_contains($k, 'speakup')
        ) {
            return 'hsl(28 92% 48%)';
        }

        if (str_contains($k, 'golden')) {
            return 'hsl(262 58% 52%)';
        }

        if (str_contains($k, 'pspp')) {
            return 'hsl(221 83% 53%)';
        }

        if (str_contains($k, 'insiden')) {
            return 'hsl(346 72% 48%)';
        }

        /** @var list<string> */
        $palette = [
            'hsl(221 83% 53%)',
            'hsl(173 58% 36%)',
            'hsl(28 92% 48%)',
            'hsl(262 58% 52%)',
            'hsl(346 72% 48%)',
            'hsl(142 65% 40%)',
            'hsl(199 85% 48%)',
            'hsl(48 96% 46%)',
            'hsl(291 58% 50%)',
            'hsl(16 85% 52%)',
            'hsl(195 80% 42%)',
            'hsl(84 72% 42%)',
        ];

        $h = crc32($label);
        $idx = abs($h) % count($palette);

        return $palette[$idx];
    }

    /**
     * @param list<array{jumlah: int, color: string}> $categories
     */
    private function buildDeviationConicGradient(array $categories, int $total): string
    {
        if ($total === 0 || $categories === []) {
            return 'conic-gradient(hsl(210 20% 96%) 0% 100%)';
        }

        $cum = 0.0;
        $stops = [];
        foreach ($categories as $row) {
            $slice = $total > 0 ? ($row['jumlah'] / $total) * 100.0 : 0.0;
            $start = $cum;
            $end = min(100.0, $cum + $slice);
            $color = $row['color'] ?? 'hsl(215 14% 72%)';
            if ($end > $start + 0.0001) {
                $stops[] = sprintf(
                    '%s %.5f%% %.5f%%',
                    $color,
                    $start,
                    $end
                );
            }
            $cum = $end;
        }

        if ($stops === []) {
            return 'conic-gradient(hsl(215 14% 82%) 0% 100%)';
        }

        return 'conic-gradient(' . implode(', ', $stops) . ')';
    }

    private function formatCompactTotal(int $n): string
    {
        if ($n >= 1_000_000) {
            return round($n / 1_000_000, 1) . 'jt';
        }
        if ($n >= 1000) {
            return round($n / 1000, 1) . 'k';
        }

        return (string) $n;
    }

    /**
     * @return array{berecord_pct: float, evidence_pct: float, size_pct: float, h1_pct: float, duration_label: string, triangle_rotate_deg: float}
     */
    private function buildCompliance(Builder $base, int $total): array
    {
        if ($total === 0) {
            return [
                'berecord_pct' => 0.0,
                'evidence_pct' => 0.0,
                'size_pct' => 0.0,
                'h1_pct' => 0.0,
                'duration_label' => '—',
                'triangle_rotate_deg' => 12.0,
            ];
        }

        $b = clone $base;
        $withBe = (clone $b)->whereNotNull('id_berecord')->whereRaw('TRIM(id_berecord) != ?', [''])->count();
        $withEv = (clone $b)->whereNotNull('evidence_url')->whereRaw('TRIM(evidence_url) != ?', [''])->count();
        $withTask = (clone $b)->whereNotNull('tasklist_temuan')->whereRaw('TRIM(tasklist_temuan) != ?', [''])->count();
        $h1 = (clone $b)->whereRaw('DATEDIFF(tanggal_edukasi, tanggal_temuan) >= 0')
            ->whereRaw('DATEDIFF(tanggal_edukasi, tanggal_temuan) <= 1')
            ->count();

        $avgDur = (clone $b)->avg('durasi_edukasi_menit');
        $avgDurF = $avgDur !== null ? round((float) $avgDur, 0) : 0.0;

        $berecordPct = round(($withBe / $total) * 100, 1);
        $evidencePct = round(($withEv / $total) * 100, 1);
        $sizePct = round(($withTask / $total) * 100, 1);
        $h1Pct = round(($h1 / $total) * 100, 1);

        $closed = (clone $b)->where(function ($q): void {
            $q->whereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%CLOSE%'])
                ->orWhereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%SELESAI%']);
        })->count();
        $completionRate = round(($closed / $total) * 100, 1);
        $triangleRotate = min(45.0, max(-45.0, ($completionRate - 50.0) * 0.9));

        return [
            'berecord_pct' => $berecordPct,
            'evidence_pct' => $evidencePct,
            'size_pct' => $sizePct,
            'h1_pct' => $h1Pct,
            'duration_label' => $avgDurF > 0 ? (string) (int) $avgDurF . 'm' : '—',
            'triangle_rotate_deg' => $triangleRotate,
        ];
    }

    /**
     * @return list<array{name: string, count: int, bar_pct: float}>
     */
    private function buildLocations(Builder $base): array
    {
        $locExpr = 'COALESCE(NULLIF(TRIM(lokasi_temuan), ""), \'Tidak diisi\')';
        $rows = (clone $base)
            ->selectRaw($locExpr . ' as loc, COUNT(*) as c')
            ->groupByRaw($locExpr)
            ->orderByDesc('c')
            ->get();

        $max = 0;
        foreach ($rows as $r) {
            $max = max($max, (int) $r->c);
        }

        $out = [];
        foreach ($rows as $r) {
            $c = (int) $r->c;
            $out[] = [
                'name' => (string) $r->loc,
                'count' => $c,
                'bar_pct' => $max > 0 ? round(($c / $max) * 100, 1) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Pelanggar dengan jumlah kejadian terbanyak; korelasi = porsi terhadap total insiden periode.
     *
     * @return list<array{sid: string, nama: string, kasus: int, insiden_share_pct: float, foto_url: string|null}>
     */
    private function buildProfilingPelanggar(Builder $base, int $totalKejadian): array
    {
        $rows = DB::table('peer_pressure_peserta_edukasi as p')
            ->where('p.peran', 'pelanggar')
            ->whereIn('p.kejadian_edukasi_id', (clone $base)->select('id'))
            ->selectRaw('p.sid, MAX(p.nama) as nama, COUNT(*) as kasus')
            ->groupBy('p.sid')
            ->orderByDesc('kasus')
            ->limit(self::PROFILING_PELANGGAR_LIMIT)
            ->get();

        $sids = [];
        foreach ($rows as $r) {
            $sids[] = (string) $r->sid;
        }

        $fotoMap = $this->karyawanNitip->fotoUrlsByKodeSids($sids);

        $out = [];
        foreach ($rows as $r) {
            $sid = (string) $r->sid;
            $kasus = (int) $r->kasus;
            $key = Str::lower(trim($sid));
            $foto = $fotoMap[$key] ?? null;
            $share = $totalKejadian > 0 ? round(($kasus / $totalKejadian) * 100, 1) : 0.0;
            $out[] = [
                'sid' => $sid,
                'nama' => (string) $r->nama,
                'kasus' => $kasus,
                'insiden_share_pct' => $share,
                'foto_url' => $foto !== '' ? $foto : null,
            ];
        }

        return $out;
    }
}
