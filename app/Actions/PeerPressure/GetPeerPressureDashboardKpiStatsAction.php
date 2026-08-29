<?php

declare(strict_types=1);

namespace App\Actions\PeerPressure;

use App\Models\PeerPressureKejadianEdukasi;
use App\Models\PeerPressurePesertaEdukasi;
use App\Services\PeerPressure\PeerPressurePelaksanaanBaselineService;
use App\Support\PeerPressure\KategoriDeviasiBucket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Agregasi KPI dashboard Peer Pressure dari MySQL (tanpa N+1).
 * Tanpa argumen: seluruh data. Dengan tahun+bulan: filter tanggal temuan dalam bulan kalender tersebut.
 */
final class GetPeerPressureDashboardKpiStatsAction
{
    private const MIN_YEAR = 2025;

    private const MAX_YEAR = 2026;

    public function __construct(
        private readonly PeerPressurePelaksanaanBaselineService $pelaksanaanBaseline,
    ) {}

    /**
     * @return array{
     *   total_cases: int,
     *   total_cases_trend_pct: float|null,
     *   total_cases_trend_label: string,
     *   completion_rate: float,
     *   completion_rate_delta_pp: float|null,
     *   avg_peer_count: float,
     *   avg_duration_minutes: float,
     *   peer_pressure_compliance_pct: float,
     *   peer_pressure_compliance_total: int,
     *   peer_pressure_compliance_comply: int,
     *   pelaksanaan_baseline_total: int,
     *   pelaksanaan_selesai_count: int,
     *   pelaksanaan_belum_count: int,
     *   pelaksanaan_selesai_pct: float,
     *   pelaksanaan_belum_pct: float,
     *   pelaksanaan_status_kosong_count: int,
     *   pelaksanaan_kelompok_kerja_rows: list<array{kelompok: string, selesai: int, belum: int, total: int, pct_selesai: float}>,
     *   peer_lalu_pelanggar_eval_count: int,
     *   peer_lalu_pelanggar_eval_rows: list<array{sid: string, nama: string, tanggal_pertama_sebagai_peer: string, tanggal_pertama_sebagai_pelanggar: string}>
     * }
     */
    public function __invoke(?int $year = null, ?int $month = null): array
    {
        if ($year !== null && $month !== null) {
            $y = max(self::MIN_YEAR, min(self::MAX_YEAR, $year));
            $m = max(1, min(12, $month));

            return $this->forCalendarMonth($y, $m);
        }

        return $this->globalStats();
    }

    /**
     * @return array{
     *   total_cases: int,
     *   total_cases_trend_pct: float|null,
     *   total_cases_trend_label: string,
     *   completion_rate: float,
     *   completion_rate_delta_pp: float|null,
     *   avg_peer_count: float,
     *   avg_duration_minutes: float,
     *   peer_pressure_compliance_pct: float,
     *   peer_pressure_compliance_total: int,
     *   peer_pressure_compliance_comply: int,
     *   pelaksanaan_baseline_total: int,
     *   pelaksanaan_selesai_count: int,
     *   pelaksanaan_belum_count: int,
     *   pelaksanaan_selesai_pct: float,
     *   pelaksanaan_belum_pct: float
     * }
     */
    private function forCalendarMonth(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $startS = $start->toDateString();
        $endS = $end->toDateString();

        $base = PeerPressureKejadianEdukasi::query()
            ->where('tanggal_temuan', '>=', $startS)
            ->where('tanggal_temuan', '<=', $endS);

        $total = (clone $base)->count();

        $closedTotal = (clone $base)->where(function ($q): void {
            $q->whereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%CLOSE%'])
                ->orWhereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%SELESAI%']);
        })->count();

        $completionRate = $total > 0 ? round(($closedTotal / $total) * 100, 1) : 0.0;

        $pelBase = $this->pelaksanaanBaseline->compute($year, $month);

        $peerRows = PeerPressurePesertaEdukasi::query()
            ->where('peran', 'peer')
            ->whereHas('kejadian', function ($q) use ($startS, $endS): void {
                $q->where('tanggal_temuan', '>=', $startS)
                    ->where('tanggal_temuan', '<=', $endS);
            })
            ->count();
        $avgPeerCount = $total > 0 ? round($peerRows / $total, 1) : 0.0;

        $avgDur = (clone $base)->avg('durasi_edukasi_menit');
        $avgDurationMinutes = $avgDur !== null ? round((float) $avgDur, 1) : 0.0;

        $prevStart = $start->copy()->subMonth()->startOfMonth();
        $prevEnd = $prevStart->copy()->endOfMonth();
        $prevTotal = PeerPressureKejadianEdukasi::query()
            ->where('tanggal_temuan', '>=', $prevStart->toDateString())
            ->where('tanggal_temuan', '<=', $prevEnd->toDateString())
            ->count();

        $trendPct = null;
        if ($prevTotal > 0) {
            $trendPct = round((($total - $prevTotal) / $prevTotal) * 100, 1);
        } elseif ($total > 0 && $prevTotal === 0) {
            $trendPct = 100.0;
        }

        $trendLabel = 'vs bulan sebelumnya';
        if ($trendPct === null) {
            $trendLabel = $total === 0 && $prevTotal === 0 ? 'Belum ada data di bulan ini' : ($prevTotal === 0 ? 'Tanpa pembanding (bulan lalu 0)' : '—');
        } else {
            $sign = $trendPct > 0 ? '+' : '';
            $trendLabel = $sign . $trendPct . '% ' . $trendLabel;
        }

        $completionDelta = $this->completionRateDeltaMonthVsPreviousMonth($start);

        $compliance = $this->complianceFromComputedPelaksanaan($pelBase);
        $statusKosong = $this->countPelaksanaanStatusKosong($base);
        $kkRows = $this->pelaksanaanKelompokKerjaBreakdown($base);
        $slaChart = $this->buildSlaTemuanKePelaksanaan($base);
        $peerLaluPelanggar = $this->peerLaluPelanggarEval($startS, $endS);

        return [
            'total_cases' => $total,
            'total_cases_trend_pct' => $trendPct,
            'total_cases_trend_label' => $trendLabel,
            'completion_rate' => $completionRate,
            'completion_rate_delta_pp' => $completionDelta,
            'avg_peer_count' => $avgPeerCount,
            'avg_duration_minutes' => $avgDurationMinutes,
            'pelaksanaan_baseline_total' => $pelBase['baseline_total'],
            'pelaksanaan_selesai_count' => $pelBase['selesai'],
            'pelaksanaan_belum_count' => $pelBase['belum'],
            'pelaksanaan_selesai_pct' => $pelBase['pct_selesai'],
            'pelaksanaan_belum_pct' => $pelBase['pct_belum'],
            'pelaksanaan_status_kosong_count' => $statusKosong,
            'pelaksanaan_kelompok_kerja_rows' => $kkRows,
            'sla_temuan_ke_pelaksanaan' => $slaChart,
            ...$peerLaluPelanggar,
            ...$compliance,
        ];
    }

    private function completionRateDeltaMonthVsPreviousMonth(Carbon $monthStart): ?float
    {
        $startS = $monthStart->copy()->startOfMonth()->toDateString();
        $endS = $monthStart->copy()->endOfMonth()->toDateString();

        $currTotal = PeerPressureKejadianEdukasi::query()
            ->where('tanggal_temuan', '>=', $startS)
            ->where('tanggal_temuan', '<=', $endS)
            ->count();
        if ($currTotal === 0) {
            return null;
        }

        $currClosed = PeerPressureKejadianEdukasi::query()
            ->where('tanggal_temuan', '>=', $startS)
            ->where('tanggal_temuan', '<=', $endS)
            ->where(function ($q): void {
                $q->whereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%CLOSE%'])
                    ->orWhereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%SELESAI%']);
            })
            ->count();

        $prevStart = $monthStart->copy()->subMonth()->startOfMonth();
        $prevEnd = $prevStart->copy()->endOfMonth();
        $prevTotal = PeerPressureKejadianEdukasi::query()
            ->where('tanggal_temuan', '>=', $prevStart->toDateString())
            ->where('tanggal_temuan', '<=', $prevEnd->toDateString())
            ->count();
        if ($prevTotal === 0) {
            return null;
        }

        $prevClosed = PeerPressureKejadianEdukasi::query()
            ->where('tanggal_temuan', '>=', $prevStart->toDateString())
            ->where('tanggal_temuan', '<=', $prevEnd->toDateString())
            ->where(function ($q): void {
                $q->whereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%CLOSE%'])
                    ->orWhereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%SELESAI%']);
            })
            ->count();

        $rateRecent = ($currClosed / $currTotal) * 100;
        $ratePrev = ($prevClosed / $prevTotal) * 100;

        return round($rateRecent - $ratePrev, 1);
    }

    /**
     * @return array{
     *   total_cases: int,
     *   total_cases_trend_pct: float|null,
     *   total_cases_trend_label: string,
     *   completion_rate: float,
     *   completion_rate_delta_pp: float|null,
     *   avg_peer_count: float,
     *   avg_duration_minutes: float,
     *   peer_pressure_compliance_pct: float,
     *   peer_pressure_compliance_total: int,
     *   peer_pressure_compliance_comply: int,
     *   pelaksanaan_baseline_total: int,
     *   pelaksanaan_selesai_count: int,
     *   pelaksanaan_belum_count: int,
     *   pelaksanaan_selesai_pct: float,
     *   pelaksanaan_belum_pct: float
     * }
     */
    private function globalStats(): array
    {
        $total = PeerPressureKejadianEdukasi::query()->count();

        $closedTotal = PeerPressureKejadianEdukasi::query()
            ->where(function ($q): void {
                $q->whereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%CLOSE%'])
                    ->orWhereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%SELESAI%']);
            })
            ->count();

        $completionRate = $total > 0 ? round(($closedTotal / $total) * 100, 1) : 0.0;

        $pelBase = $this->pelaksanaanBaseline->compute(null, null);

        $peerRows = PeerPressurePesertaEdukasi::query()->where('peran', 'peer')->count();
        $avgPeerCount = $total > 0 ? round($peerRows / $total, 1) : 0.0;

        $avgDur = PeerPressureKejadianEdukasi::query()->avg('durasi_edukasi_menit');
        $avgDurationMinutes = $avgDur !== null ? round((float) $avgDur, 1) : 0.0;

        $now = Carbon::now();
        $last30Start = $now->copy()->subDays(30)->startOfDay()->toDateString();
        $last30Count = PeerPressureKejadianEdukasi::query()
            ->where('tanggal_temuan', '>=', $last30Start)
            ->count();

        $prev30Count = PeerPressureKejadianEdukasi::query()
            ->where('tanggal_temuan', '>=', $now->copy()->subDays(60)->startOfDay()->toDateString())
            ->where('tanggal_temuan', '<', $last30Start)
            ->count();

        $trendPct = null;
        if ($prev30Count > 0) {
            $trendPct = round((($last30Count - $prev30Count) / $prev30Count) * 100, 1);
        } elseif ($last30Count > 0 && $prev30Count === 0) {
            $trendPct = 100.0;
        }

        $trendLabel = 'vs 30 hari sebelumnya';
        if ($trendPct === null) {
            $trendLabel = $total === 0 ? 'Belum ada data' : 'Tanpa pembanding';
        } else {
            $sign = $trendPct > 0 ? '+' : '';
            $trendLabel = $sign . $trendPct . '% ' . $trendLabel;
        }

        $completionDelta = $this->completionRateDeltaLast30VsPrev30();

        $baseGlobal = PeerPressureKejadianEdukasi::query();
        $compliance = $this->complianceFromComputedPelaksanaan($pelBase);
        $statusKosong = $this->countPelaksanaanStatusKosong($baseGlobal);
        $kkRows = $this->pelaksanaanKelompokKerjaBreakdown($baseGlobal);
        $slaChart = $this->buildSlaTemuanKePelaksanaan($baseGlobal);
        $peerLaluPelanggar = $this->peerLaluPelanggarEval(null, null);

        return [
            'total_cases' => $total,
            'total_cases_trend_pct' => $trendPct,
            'total_cases_trend_label' => $trendLabel,
            'completion_rate' => $completionRate,
            'completion_rate_delta_pp' => $completionDelta,
            'avg_peer_count' => $avgPeerCount,
            'avg_duration_minutes' => $avgDurationMinutes,
            'pelaksanaan_baseline_total' => $pelBase['baseline_total'],
            'pelaksanaan_selesai_count' => $pelBase['selesai'],
            'pelaksanaan_belum_count' => $pelBase['belum'],
            'pelaksanaan_selesai_pct' => $pelBase['pct_selesai'],
            'pelaksanaan_belum_pct' => $pelBase['pct_belum'],
            'pelaksanaan_status_kosong_count' => $statusKosong,
            'pelaksanaan_kelompok_kerja_rows' => $kkRows,
            'sla_temuan_ke_pelaksanaan' => $slaChart,
            ...$peerLaluPelanggar,
            ...$compliance,
        ];
    }

    /**
     * Evaluasi individu: pernah tercatat sebagai peer, lalu (lebih belakangan) tercatat sebagai pelanggar.
     * Periode mengikuti filter tanggal temuan (satu bulan atau seluruh data). Perbandingan urutan memakai
     * tanggal temuan lalu id kejadian. SID dikelompokkan case-insensitive setelah trim.
     *
     * @return array{
     *   peer_lalu_pelanggar_eval_count: int,
     *   peer_lalu_pelanggar_eval_rows: list<array{sid: string, nama: string, tanggal_pertama_sebagai_peer: string, tanggal_pertama_sebagai_pelanggar: string}>
     * }
     */
    private function peerLaluPelanggarEval(?string $startS, ?string $endS): array
    {
        $dateSql = '';
        $bind = [];
        if ($startS !== null && $endS !== null) {
            $dateSql = 'AND k.tanggal_temuan >= ? AND k.tanggal_temuan <= ?';
            $bind = [$startS, $endS];
        }

        $cte = <<<SQL
WITH base AS (
  SELECT
    LOWER(TRIM(p.sid)) AS sid_key,
    TRIM(p.sid) AS sid_raw,
    NULLIF(TRIM(p.nama), '') AS nama,
    p.peran,
    k.tanggal_temuan,
    k.id AS kejadian_id
  FROM peer_pressure_peserta_edukasi p
  INNER JOIN peer_pressure_kejadian_edukasi k ON k.id = p.kejadian_edukasi_id
  WHERE TRIM(COALESCE(p.sid, '')) <> ''
  AND p.peran IN ('peer', 'pelanggar')
  {$dateSql}
),
peer_first AS (
  SELECT sid_key, MIN(CONCAT(DATE_FORMAT(tanggal_temuan, '%Y-%m-%d'), ':', LPAD(kejadian_id, 12, '0'))) AS ord
  FROM base
  WHERE peran = 'peer'
  GROUP BY sid_key
),
viol_first AS (
  SELECT sid_key, MIN(CONCAT(DATE_FORMAT(tanggal_temuan, '%Y-%m-%d'), ':', LPAD(kejadian_id, 12, '0'))) AS ord
  FROM base
  WHERE peran = 'pelanggar'
  GROUP BY sid_key
)
SQL;

        $countSql = $cte.'SELECT COUNT(*) AS c FROM peer_first pf INNER JOIN viol_first vf ON pf.sid_key = vf.sid_key AND pf.ord < vf.ord';
        $countRow = DB::selectOne($countSql, $bind);
        $total = (int) ($countRow->c ?? 0);

        $listSql = $cte.<<<'SQL'
SELECT
  SUBSTRING(pf.ord, 1, 10) AS tanggal_pertama_peer,
  SUBSTRING(vf.ord, 1, 10) AS tanggal_pertama_pelanggar,
  (
    SELECT MAX(b.sid_raw) FROM base b WHERE b.sid_key = pf.sid_key
  ) AS sid,
  (
    SELECT MAX(b.nama) FROM base b WHERE b.sid_key = pf.sid_key AND b.nama IS NOT NULL AND TRIM(b.nama) <> ''
  ) AS nama
FROM peer_first pf
INNER JOIN viol_first vf ON pf.sid_key = vf.sid_key AND pf.ord < vf.ord
ORDER BY tanggal_pertama_pelanggar DESC, pf.sid_key ASC
LIMIT 100
SQL;

        $rawList = DB::select($listSql, $bind);
        $rows = [];
        foreach ($rawList as $r) {
            $rows[] = [
                'sid' => (string) ($r->sid ?? ''),
                'nama' => (string) ($r->nama ?? ''),
                'tanggal_pertama_sebagai_peer' => (string) ($r->tanggal_pertama_peer ?? ''),
                'tanggal_pertama_sebagai_pelanggar' => (string) ($r->tanggal_pertama_pelanggar ?? ''),
            ];
        }

        return [
            'peer_lalu_pelanggar_eval_count' => $total,
            'peer_lalu_pelanggar_eval_rows' => $rows,
        ];
    }

    /**
     * SLA: hari dari tanggal temuan ke tanggal pelaksanaan peer pressure (kolom tanggal_edukasi),
     * per jenis sumber temuan (BeRecord / blindspot / tidak speak up) dari kategori deviasi + id_berecord.
     *
     * @return array{
     *   buckets: list<array{key: string, label: string, berecord: int, blindspot: int, speakup_fatigue: int}>,
     *   sources: list<array{key: string, label: string, color: string}>,
     *   summary: array<string, array{avg_days: float, count: int}>,
     *   total_classified: int,
     *   max_bar: int
     * }
     */
    private function buildSlaTemuanKePelaksanaan(Builder $base): array
    {
        $lagBucketDefs = [
            ['key' => 'd0_3', 'label' => '0–3 hari', 'min' => 0, 'max' => 3],
            ['key' => 'd4_7', 'label' => '4–7 hari', 'min' => 4, 'max' => 7],
            ['key' => 'd8_14', 'label' => '8–14 hari', 'min' => 8, 'max' => 14],
            ['key' => 'd15_30', 'label' => '15–30 hari', 'min' => 15, 'max' => 30],
            ['key' => 'd31p', 'label' => '31+ hari', 'min' => 31, 'max' => null],
        ];

        $counts = [];
        foreach ($lagBucketDefs as $def) {
            $counts[$def['key']] = [
                'berecord' => 0,
                'blindspot' => 0,
                'speakup_fatigue' => 0,
            ];
        }

        $lagsBySource = [
            'berecord' => [],
            'blindspot' => [],
            'speakup_fatigue' => [],
        ];

        $rows = (clone $base)
            ->whereNotNull('tanggal_temuan')
            ->whereNotNull('tanggal_edukasi')
            ->get(['tanggal_temuan', 'tanggal_edukasi', 'kategori_deviasi', 'id_berecord']);

        foreach ($rows as $r) {
            $catBucket = KategoriDeviasiBucket::bucket($r->kategori_deviasi);
            $sourceKey = null;
            if ($catBucket === 'tidak_speak_up_fatigue') {
                $sourceKey = 'speakup_fatigue';
            } elseif ($catBucket === 'blindspot_to_be_concerned') {
                $sourceKey = 'blindspot';
            } elseif ($r->id_berecord !== null || KategoriDeviasiBucket::isBerecordPolicyBucket($catBucket)) {
                $sourceKey = 'berecord';
            }

            if ($sourceKey === null) {
                continue;
            }

            $temuan = $r->tanggal_temuan;
            $edukasi = $r->tanggal_edukasi;
            if (! $temuan instanceof \DateTimeInterface || ! $edukasi instanceof \DateTimeInterface) {
                continue;
            }

            $t0 = Carbon::parse($temuan);
            $t1 = Carbon::parse($edukasi);
            $lagSigned = (int) $t0->diffInDays($t1, false);
            $lag = max(0, $lagSigned);

            $lagKey = 'd31p';
            foreach ($lagBucketDefs as $def) {
                if ($def['max'] === null) {
                    if ($lag >= $def['min']) {
                        $lagKey = $def['key'];
                    }
                    break;
                }
                if ($lag >= $def['min'] && $lag <= $def['max']) {
                    $lagKey = $def['key'];
                    break;
                }
            }

            $counts[$lagKey][$sourceKey]++;
            $lagsBySource[$sourceKey][] = $lag;
        }

        $summary = [];
        foreach (['berecord', 'blindspot', 'speakup_fatigue'] as $sk) {
            $list = $lagsBySource[$sk];
            $n = count($list);
            $summary[$sk] = [
                'avg_days' => $n > 0 ? round(array_sum($list) / $n, 1) : 0.0,
                'count' => $n,
            ];
        }

        $totalClassified = $summary['berecord']['count'] + $summary['blindspot']['count'] + $summary['speakup_fatigue']['count'];

        $maxBar = 0;
        foreach ($counts as $bySource) {
            foreach ($bySource as $c) {
                $maxBar = max($maxBar, $c);
            }
        }
        if ($maxBar === 0) {
            $maxBar = 1;
        }

        $bucketsOut = [];
        foreach ($lagBucketDefs as $def) {
            $k = $def['key'];
            $row = $counts[$k];
            $bucketsOut[] = [
                'key' => $k,
                'label' => $def['label'],
                'berecord' => $row['berecord'],
                'blindspot' => $row['blindspot'],
                'speakup_fatigue' => $row['speakup_fatigue'],
            ];
        }

        return [
            'buckets' => $bucketsOut,
            'sources' => [
                ['key' => 'berecord', 'label' => 'BeRecord (PSPP / Golden rules / insiden)', 'color' => '#0369a1'],
                ['key' => 'blindspot', 'label' => 'Validasi blindspot', 'color' => '#7c3aed'],
                ['key' => 'speakup_fatigue', 'label' => 'Tidak speak up fatigue', 'color' => '#c2410c'],
            ],
            'summary' => $summary,
            'total_classified' => $totalClassified,
            'max_bar' => $maxBar,
        ];
    }

    private function countPelaksanaanStatusKosong(Builder $base): int
    {
        return (clone $base)
            ->whereRaw('TRIM(COALESCE(status_pelaksanaan_edukasi, \'\')) = \'\'')
            ->count();
    }

    /**
     * Label tampilan agregat: beberapa nilai DB digabung menjadi satu kelompok.
     */
    private function canonicalKelompokKerjaLabel(string $kelompok): string
    {
        $trim = trim($kelompok);
        if ($trim === '') {
            return '(Tidak diisi)';
        }
        $n = mb_strtolower(preg_replace('/\s+/u', ' ', $trim), 'UTF-8');

        if ($n === 'grup sbs coaching' || $n === 'grup sbs') {
            return 'Grup SBS';
        }

        if ($n === 'fleet' || $n === 'grup kerja (under 1 gl)') {
            return 'Fleet';
        }

        return $trim;
    }

    /**
     * Agregasi per jenis kelompok kerja: selesai (CLOSED/SELESAI) vs belum, untuk modal pelaksanaan.
     * Variasi label (mis. GRUP SBS COACHING + Grup SBS, Grup Kerja (Under 1 GL) + Fleet) dilebur per {@see canonicalKelompokKerjaLabel}.
     *
     * @return list<array{kelompok: string, selesai: int, belum: int, total: int, pct_selesai: float}>
     */
    private function pelaksanaanKelompokKerjaBreakdown(Builder $base): array
    {
        $labelExpr = "COALESCE(NULLIF(TRIM(jenis_kelompok_kerja), ''), '(Tidak diisi)')";
        $selesaiWhen = '(UPPER(TRIM(COALESCE(status_pelaksanaan_edukasi, \'\'))) LIKE \'%CLOSE%\' OR UPPER(TRIM(COALESCE(status_pelaksanaan_edukasi, \'\'))) LIKE \'%SELESAI%\')';

        $rows = (clone $base)
            ->selectRaw($labelExpr.' as kelompok')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN '.$selesaiWhen.' THEN 1 ELSE 0 END) as selesai')
            ->groupBy(DB::raw($labelExpr))
            ->orderByDesc('total')
            ->limit(80)
            ->get();

        /** @var array<string, array{selesai: int, total: int}> $merged */
        $merged = [];
        foreach ($rows as $r) {
            $rawLabel = (string) $r->kelompok;
            $key = $this->canonicalKelompokKerjaLabel($rawLabel);
            if (! isset($merged[$key])) {
                $merged[$key] = ['selesai' => 0, 'total' => 0];
            }
            $merged[$key]['total'] += (int) $r->total;
            $merged[$key]['selesai'] += (int) $r->selesai;
        }

        $out = [];
        foreach ($merged as $label => $cell) {
            $total = $cell['total'];
            $selesai = $cell['selesai'];
            $belum = max(0, $total - $selesai);
            $pct = $total > 0 ? round(100 * $selesai / $total, 1) : 0.0;
            $out[] = [
                'kelompok' => $label,
                'selesai' => $selesai,
                'belum' => $belum,
                'total' => $total,
                'pct_selesai' => $pct,
            ];
        }

        usort($out, static fn (array $a, array $b): int => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));

        return array_slice($out, 0, 15);
    }

    /**
     * Metrik "Pelaksanaan Comply" pada KPI diselaraskan dengan baseline pelaksanaan:
     * tujuh kontraktor terpilih, BeRecord (CH + golden_rules) + validasi TBC + fatigue.
     * Total = item baseline; comply = yang sudah memenuhi pelaksanaan selesai (sama {@see PeerPressurePelaksanaanBaselineService::compute}).
     *
     * @return array{
     *   peer_pressure_compliance_pct: float,
     *   peer_pressure_compliance_total: int,
     *   peer_pressure_compliance_comply: int
     * }
     */
    /**
     * @param  array{pct_selesai: float, baseline_total: int, selesai: int}  $p
     * @return array{
     *   peer_pressure_compliance_pct: float,
     *   peer_pressure_compliance_total: int,
     *   peer_pressure_compliance_comply: int
     * }
     */
    private function complianceFromComputedPelaksanaan(array $p): array
    {
        return [
            'peer_pressure_compliance_pct' => $p['pct_selesai'],
            'peer_pressure_compliance_total' => $p['baseline_total'],
            'peer_pressure_compliance_comply' => $p['selesai'],
        ];
    }

    private function completionRateDeltaLast30VsPrev30(): ?float
    {
        $now = Carbon::now();
        $last30Start = $now->copy()->subDays(30)->startOfDay()->toDateString();

        $last30Total = PeerPressureKejadianEdukasi::query()
            ->where('tanggal_temuan', '>=', $last30Start)
            ->count();
        if ($last30Total === 0) {
            return null;
        }

        $last30Closed = PeerPressureKejadianEdukasi::query()
            ->where('tanggal_temuan', '>=', $last30Start)
            ->where(function ($q): void {
                $q->whereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%CLOSE%'])
                    ->orWhereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%SELESAI%']);
            })
            ->count();

        $prev30Total = PeerPressureKejadianEdukasi::query()
            ->where('tanggal_temuan', '>=', $now->copy()->subDays(60)->startOfDay()->toDateString())
            ->where('tanggal_temuan', '<', $last30Start)
            ->count();

        if ($prev30Total === 0) {
            return null;
        }

        $prev30Closed = PeerPressureKejadianEdukasi::query()
            ->where('tanggal_temuan', '>=', $now->copy()->subDays(60)->startOfDay()->toDateString())
            ->where('tanggal_temuan', '<', $last30Start)
            ->where(function ($q): void {
                $q->whereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%CLOSE%'])
                    ->orWhereRaw('UPPER(TRIM(status_pelaksanaan_edukasi)) LIKE ?', ['%SELESAI%']);
            })
            ->count();

        $rateRecent = ($last30Closed / $last30Total) * 100;
        $ratePrev = ($prev30Closed / $prev30Total) * 100;

        return round($rateRecent - $ratePrev, 1);
    }
}
