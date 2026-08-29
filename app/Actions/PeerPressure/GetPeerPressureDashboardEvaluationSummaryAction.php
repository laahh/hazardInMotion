<?php

declare(strict_types=1);

namespace App\Actions\PeerPressure;

use App\Models\PeerPressureKejadianEdukasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan evaluasi dari data aktual DB — metrik repetitif pelanggaran mengikuti periode chart (semua data vs bulan).
 */
final class GetPeerPressureDashboardEvaluationSummaryAction
{
    private const REPEAT_MIN_CASES = 3;

    private const RECENCY_HIGH_RISK_DAYS = 7;

    private const VARIETY_PATTERN_MIN = 3;

    /**
     * @return array{
     *   generated_at: string,
     *   total_kejadian: int,
     *   rows: list<array{
     *     key: string,
     *     metric: string,
     *     description: string,
     *     action_threshold: string,
     *     status: 'critical'|'warning'|'ok'|'neutral',
     *     detail_bullets: list<string>,
     *     violators_detail?: list<array{nama: string, sid: string, departemen: string, kasus: int, kejadian_list: list<array{kejadian_id: int, tanggal_temuan: string, tanggal_label: string, kategori_deviasi: string}>}>,
     *     recency_detail?: array{latest: array{kejadian_id: int, tanggal_label: string, kategori_deviasi: string}, previous: array{kejadian_id: int, tanggal_label: string, kategori_deviasi: string}, gap_days: int, footnote: string, pelanggar_nama: string, pelanggar_sid: string, metric_explanation: string},
     *     deviation_variety_detail?: array{distinct_count: int, categories: list<array{kategori_deviasi: string, jumlah: int}>},
     *     peer_correlation_detail?: array{definition: string, total_unique_pairs: int, pairs_with_freq_gte_2: int, max_pair_frequency: int, top_pairs: list<array{pelanggar_sid: string, peer_sid: string, frekuensi: int}>}
     *   }>,
     *   narrative: string,
     *   repeat_period_caption: string,
     *   chart_period_month: bool
     * }
     */
    public function __invoke(?int $chartYear = null, ?int $chartMonth = null): array
    {
        $total = PeerPressureKejadianEdukasi::query()->count();

        $y = $chartYear !== null && $chartMonth !== null
            ? max(GetPeerPressureDashboardWeeklyTrendAction::MIN_YEAR, min(GetPeerPressureDashboardWeeklyTrendAction::MAX_YEAR, $chartYear))
            : null;
        $m = $chartYear !== null && $chartMonth !== null ? max(1, min(12, $chartMonth)) : null;

        $repeatPeriodCaption = $y !== null && $m !== null
            ? $this->monthNameId($m) . ' ' . $y
            : 'Seluruh data';

        $rows = [
            $this->rowRepeatViolator($y, $m, $repeatPeriodCaption),
            $this->rowRecency($y, $m, $repeatPeriodCaption),
            $this->rowDeviationVariety(),
            $this->rowPeerCorrelation(),
        ];

        $narrative = $this->buildNarrative($total, $rows);

        return [
            'generated_at' => now()->format('d M Y, H:i'),
            'total_kejadian' => $total,
            'rows' => $rows,
            'narrative' => $narrative,
            'repeat_period_caption' => $repeatPeriodCaption,
            'chart_period_month' => $y !== null && $m !== null,
        ];
    }

    private function monthNameId(int $month): string
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$month] ?? (string) $month;
    }

    private function tanggalTemuanIso(mixed $t): string
    {
        if ($t instanceof \DateTimeInterface) {
            return Carbon::instance($t)->toDateString();
        }
        $s = trim((string) $t);
        if ($s === '') {
            return '1970-01-01';
        }
        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return '1970-01-01';
        }
    }

    private function formatTanggalTemuanLabel(mixed $t): string
    {
        if ($t instanceof \DateTimeInterface) {
            return Carbon::instance($t)->locale('id')->translatedFormat('d M Y');
        }
        $s = trim((string) $t);
        if ($s === '') {
            return '—';
        }
        try {
            return Carbon::parse($s)->locale('id')->translatedFormat('d M Y');
        } catch (\Throwable) {
            return $s;
        }
    }

    /**
     * @return array{key: string, metric: string, description: string, action_threshold: string, status: 'critical'|'warning'|'ok'|'neutral', detail_bullets: list<string>, violators_detail?: list<array{nama: string, sid: string, departemen: string, kasus: int, kejadian_list: list<array{kejadian_id: int, tanggal_temuan: string, tanggal_label: string, kategori_deviasi: string}>}>}
     */
    /**
     * @param positive-int|null $year
     * @param int<1,12>|null $month
     */
    private function rowRepeatViolator(?int $year, ?int $month, string $repeatPeriodCaption): array
    {
        $base = DB::table('peer_pressure_peserta_edukasi as p')
            ->join('peer_pressure_kejadian_edukasi as k', 'k.id', '=', 'p.kejadian_edukasi_id')
            ->where('p.peran', 'pelanggar');

        if ($year !== null && $month !== null) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $base->where('k.tanggal_temuan', '>=', $start->toDateString())
                ->where('k.tanggal_temuan', '<=', $end->toDateString());
            $desc = 'Pelanggar dengan ≥ 3 kasus dalam bulan terpilih (sesuai periode chart)';
        } else {
            $desc = 'Pelanggar dengan ≥ 3 kasus (seluruh data, tanggal temuan)';
        }

        $raw = $base
            ->orderByDesc('k.tanggal_temuan')
            ->orderByDesc('k.id')
            ->select([
                'p.sid',
                'p.nama',
                'k.departemen',
                'k.id as kejadian_id',
                'k.tanggal_temuan',
                'k.kategori_deviasi',
            ])
            ->get();

        /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, object>> $bySid */
        $bySid = $raw->groupBy('sid');

        $violatorsDetail = [];
        foreach ($bySid as $sid => $items) {
            $nKejadianUnik = $items->pluck('kejadian_id')->unique()->count();
            if ($nKejadianUnik < self::REPEAT_MIN_CASES) {
                continue;
            }

            $latest = $items->sortByDesc(static function ($i): string {
                $t = $i->tanggal_temuan ?? '';
                if ($t instanceof \DateTimeInterface) {
                    $t = $t->format('Y-m-d');
                } else {
                    $t = (string) $t;
                }
                $id = (int) ($i->kejadian_id ?? 0);

                return $t . '-' . str_pad((string) $id, 12, '0', STR_PAD_LEFT);
            })->first();

            $namaRaw = $latest && isset($latest->nama) ? trim((string) $latest->nama) : '';
            $nama = $namaRaw !== '' ? $namaRaw : '—';

            $depts = $items->pluck('departemen')
                ->map(static function ($d): string {
                    if ($d === null) {
                        return '';
                    }
                    if ($d instanceof \DateTimeInterface) {
                        return '';
                    }

                    return trim((string) $d);
                })
                ->filter(static fn (string $d): bool => $d !== '')
                ->unique()
                ->values();

            $departemenLabel = $depts->isEmpty() ? '—' : $depts->implode(', ');

            $kejadianList = [];
            foreach ($items->groupBy('kejadian_id') as $kid => $grp) {
                $first = $grp->first();
                $rawDate = $first->tanggal_temuan ?? null;
                $katRaw = $first->kategori_deviasi ?? null;
                $katStr = $katRaw !== null && !($katRaw instanceof \DateTimeInterface) ? trim((string) $katRaw) : '';

                $kejadianList[] = [
                    'kejadian_id' => (int) $kid,
                    'tanggal_temuan' => $this->tanggalTemuanIso($rawDate),
                    'tanggal_label' => $this->formatTanggalTemuanLabel($rawDate),
                    'kategori_deviasi' => $katStr !== '' ? $katStr : '—',
                ];
            }

            usort($kejadianList, static function (array $a, array $b): int {
                $c = strcmp($b['tanggal_temuan'], $a['tanggal_temuan']);

                return $c !== 0 ? $c : $b['kejadian_id'] <=> $a['kejadian_id'];
            });

            $kasus = count($kejadianList);

            $violatorsDetail[] = [
                'nama' => $nama,
                'sid' => (string) $sid,
                'departemen' => $departemenLabel,
                'kasus' => $kasus,
                'kejadian_list' => $kejadianList,
            ];
        }

        usort($violatorsDetail, static fn (array $a, array $b): int => $b['kasus'] <=> $a['kasus']);

        $pelanggarCount = count($violatorsDetail);
        $critical = $pelanggarCount > 0;

        $threshold = $critical
            ? 'Coaching segera · ' . $pelanggarCount . ' pelanggar memenuhi kriteria'
            : 'Tidak memenuhi ambang · 0 pelanggar (≥ ' . self::REPEAT_MIN_CASES . ' kasus dalam periode: ' . $repeatPeriodCaption . ')';

        $bullets = [];
        if ($pelanggarCount === 0) {
            $bullets[] = 'Tidak ada pelanggar dengan ≥ ' . self::REPEAT_MIN_CASES . ' kasus dalam periode ini.';
        }

        $out = [
            'key' => 'repeat_violator',
            'metric' => 'Repetitif pelanggaran',
            'description' => $desc,
            'action_threshold' => $threshold,
            'status' => $critical ? 'critical' : 'ok',
            'detail_bullets' => $bullets,
        ];

        if ($violatorsDetail !== []) {
            $out['violators_detail'] = $violatorsDetail;
        }

        return $out;
    }

    /**
     * @param positive-int|null $year
     * @param int<1,12>|null $month
     *
     * @return array{key: string, metric: string, description: string, action_threshold: string, status: 'critical'|'warning'|'ok'|'neutral', detail_bullets: list<string>, recency_detail?: array{latest: array{kejadian_id: int, tanggal_label: string, kategori_deviasi: string}, previous: array{kejadian_id: int, tanggal_label: string, kategori_deviasi: string}, gap_days: int, footnote: string, pelanggar_nama: string, pelanggar_sid: string, metric_explanation: string}}
     */
    private function rowRecency(?int $year, ?int $month, string $repeatPeriodCaption): array
    {
        $inMonth = $year !== null && $month !== null;
        $scopeDesc = $inMonth
            ? 'Jarak antar dua pelanggaran terbaru oleh pelanggar yang sama (SID), dalam periode chart'
            : 'Jarak antar dua pelanggaran terbaru oleh pelanggar yang sama (SID), seluruh data';

        $metricExplanation = 'Metrik ini mengukur jarak waktu antara dua pelanggaran terbaru oleh orang yang sama (satu SID). Selisih hari kecil berarti pelanggaran berulang dalam waktu singkat (semakin rapat, semakin perlu perhatian).';

        $base = DB::table('peer_pressure_peserta_edukasi as p')
            ->join('peer_pressure_kejadian_edukasi as k', 'k.id', '=', 'p.kejadian_edukasi_id')
            ->where('p.peran', 'pelanggar');

        if ($inMonth) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $base->where('k.tanggal_temuan', '>=', $start->toDateString())
                ->where('k.tanggal_temuan', '<=', $end->toDateString());
        }

        $raw = $base
            ->orderByDesc('k.tanggal_temuan')
            ->orderByDesc('k.id')
            ->select([
                'p.sid',
                'p.nama',
                'k.id as kejadian_id',
                'k.tanggal_temuan',
                'k.kategori_deviasi',
            ])
            ->get();

        /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, object>> $bySid */
        $bySid = $raw->groupBy('sid');

        $candidates = [];
        foreach ($bySid as $sid => $items) {
            if ($items->groupBy('kejadian_id')->count() < 2) {
                continue;
            }

            $rows = [];
            foreach ($items->groupBy('kejadian_id') as $kid => $grp) {
                $first = $grp->first();
                $katRaw = $first->kategori_deviasi ?? null;
                $katStr = $katRaw !== null && !($katRaw instanceof \DateTimeInterface) ? trim((string) $katRaw) : '';

                $rows[] = [
                    'id' => (int) $kid,
                    'tanggal' => $first->tanggal_temuan,
                    'kategori_deviasi' => $katStr !== '' ? $katStr : '—',
                ];
            }

            usort($rows, function (array $a, array $b): int {
                $c = strcmp($this->tanggalTemuanIso($b['tanggal']), $this->tanggalTemuanIso($a['tanggal']));

                return $c !== 0 ? $c : $b['id'] <=> $a['id'];
            });

            $latest = $rows[0];
            $prev = $rows[1];

            $d0 = Carbon::parse($latest['tanggal'])->startOfDay();
            $d1 = Carbon::parse($prev['tanggal'])->startOfDay();
            $gap = (int) abs($d0->diffInDays($d1));

            $namaRaw = $items->first() && isset($items->first()->nama) ? trim((string) $items->first()->nama) : '';
            $nama = $namaRaw !== '' ? $namaRaw : '—';

            $candidates[] = [
                'sid' => (string) $sid,
                'nama' => $nama,
                'latest' => $latest,
                'previous' => $prev,
                'gap' => $gap,
            ];
        }

        if ($candidates === []) {
            $msg = $inMonth
                ? 'Tidak ada pelanggar dengan ≥ 2 kejadian berbeda dalam ' . $repeatPeriodCaption . ' (perlu satu SID dengan minimal dua temuan).'
                : 'Tidak ada pelanggar dengan ≥ 2 kejadian berbeda di seluruh data.';

            return [
                'key' => 'recency',
                'metric' => 'Jarak waktu pelanggaran berulang',
                'description' => $scopeDesc,
                'action_threshold' => '— Data belum cukup (minimal dua kejadian berbeda per SID dalam periode)',
                'status' => 'neutral',
                'detail_bullets' => [$msg],
            ];
        }

        usort($candidates, function (array $a, array $b): int {
            if ($a['gap'] !== $b['gap']) {
                return $a['gap'] <=> $b['gap'];
            }

            return strcmp(
                $this->tanggalTemuanIso($b['latest']['tanggal']),
                $this->tanggalTemuanIso($a['latest']['tanggal'])
            );
        });

        $pick = $candidates[0];

        $gap = $pick['gap'];
        $highRisk = $gap < self::RECENCY_HIGH_RISK_DAYS;
        $threshold = $highRisk
            ? '< 7 hari = risiko tinggi (selisih ' . $gap . ' hari, SID ' . $pick['sid'] . ')'
            : '≥ 7 hari (selisih ' . $gap . ' hari — SID ' . $pick['sid'] . ')';

        $footnote = $inMonth
            ? 'Pasangan kejadian untuk SID ini: dua temuan terbaru per pelanggar dalam ' . $repeatPeriodCaption . ' (filter sama dengan chart). Ditampilkan SID dengan selisih kalender terkecil (pola terpadat).'
            : 'Pasangan kejadian untuk SID ini: dua temuan terbaru per pelanggar pada seluruh data. Ditampilkan SID dengan selisih kalender terkecil (pola terpadat).';

        return [
            'key' => 'recency',
            'metric' => 'Jarak waktu pelanggaran berulang',
            'description' => $scopeDesc,
            'action_threshold' => $threshold,
            'status' => $highRisk ? 'critical' : 'ok',
            'detail_bullets' => [],
            'recency_detail' => [
                'pelanggar_nama' => $pick['nama'],
                'pelanggar_sid' => $pick['sid'],
                'latest' => [
                    'kejadian_id' => $pick['latest']['id'],
                    'tanggal_label' => $this->formatTanggalTemuanLabel($pick['latest']['tanggal']),
                    'kategori_deviasi' => $pick['latest']['kategori_deviasi'],
                ],
                'previous' => [
                    'kejadian_id' => $pick['previous']['id'],
                    'tanggal_label' => $this->formatTanggalTemuanLabel($pick['previous']['tanggal']),
                    'kategori_deviasi' => $pick['previous']['kategori_deviasi'],
                ],
                'gap_days' => $gap,
                'footnote' => $footnote,
                'metric_explanation' => $metricExplanation,
            ],
        ];
    }

    /**
     * @return array{key: string, metric: string, description: string, action_threshold: string, status: 'critical'|'warning'|'ok'|'neutral', detail_bullets: list<string>, deviation_variety_detail?: array{distinct_count: int, categories: list<array{kategori_deviasi: string, jumlah: int}>}}
     */
    private function rowDeviationVariety(): array
    {
        $n = (int) DB::table('peer_pressure_kejadian_edukasi')
            ->whereNotNull('kategori_deviasi')
            ->where('kategori_deviasi', '!=', '')
            ->selectRaw('COUNT(DISTINCT kategori_deviasi) as c')
            ->value('c');

        $categories = DB::table('peer_pressure_kejadian_edukasi')
            ->whereNotNull('kategori_deviasi')
            ->where('kategori_deviasi', '!=', '')
            ->selectRaw('kategori_deviasi, COUNT(*) as jumlah')
            ->groupBy('kategori_deviasi')
            ->orderByDesc('jumlah')
            ->limit(12)
            ->get();

        $pattern = $n >= self::VARIETY_PATTERN_MIN;
        $threshold = $pattern
            ? '⚠️ ≥ 3 jenis = pola berulang (' . $n . ' jenis berbeda)'
            : ($n > 0
                ? 'Di bawah ambang pola (' . $n . ' jenis berbeda)'
                : '— Belum ada kategori_deviasi terisi');

        $categoryRows = [];
        foreach ($categories as $row) {
            $categoryRows[] = [
                'kategori_deviasi' => (string) $row->kategori_deviasi,
                'jumlah' => (int) $row->jumlah,
            ];
        }

        return [
            'key' => 'deviation_variety',
            'metric' => 'Keragaman jenis deviasi',
            'description' => 'Jumlah jenis deviasi berbeda yang tercatat (kategori_deviasi)',
            'action_threshold' => $threshold,
            'status' => $pattern ? 'warning' : ($n > 0 ? 'ok' : 'neutral'),
            'detail_bullets' => [],
            'deviation_variety_detail' => [
                'distinct_count' => $n,
                'categories' => $categoryRows,
            ],
        ];
    }

    /**
     * @return array{key: string, metric: string, description: string, action_threshold: string, status: 'critical'|'warning'|'ok'|'neutral', detail_bullets: list<string>, peer_correlation_detail: array{definition: string, total_unique_pairs: int, pairs_with_freq_gte_2: int, max_pair_frequency: int, top_pairs: list<array{pelanggar_sid: string, peer_sid: string, frekuensi: int}>}}
     */
    private function rowPeerCorrelation(): array
    {
        $rawPairs = DB::select(<<<'SQL'
SELECT
  TRIM(pel.sid) AS pelanggar_sid,
  TRIM(peer.sid) AS peer_sid,
  COUNT(*) AS frekuensi
FROM peer_pressure_peserta_edukasi pel
INNER JOIN peer_pressure_peserta_edukasi peer
  ON peer.kejadian_edukasi_id = pel.kejadian_edukasi_id
 AND peer.peran = 'peer'
WHERE pel.peran = 'pelanggar'
  AND TRIM(COALESCE(pel.sid, '')) <> TRIM(COALESCE(peer.sid, ''))
GROUP BY TRIM(pel.sid), TRIM(peer.sid)
SQL);

        $pairCounts = [];
        foreach ($rawPairs as $row) {
            $key = (string) ($row->pelanggar_sid ?? '').'|'.(string) ($row->peer_sid ?? '');
            $pairCounts[$key] = (int) ($row->frekuensi ?? 0);
        }

        $repeatedPairs = array_filter($pairCounts, static fn (int $c): bool => $c >= 2);
        $pasanganBerulang = count($repeatedPairs);
        $maxPair = $pairCounts !== [] ? max($pairCounts) : 0;

        arsort($repeatedPairs);
        $topRepeated = array_slice($repeatedPairs, 0, 8, true);

        $flag = $pasanganBerulang > 0;
        $threshold = $flag
            ? '⚠️ Selidiki dinamika kelompok (' . $pasanganBerulang . ' pasangan pelanggar–peer berulang)'
            : '🟢 Tidak ada pasangan berulang (frekuensi ≥ 2)';

        $topPairRows = [];
        foreach ($topRepeated as $key => $c) {
            $parts = explode('|', $key, 2);
            $topPairRows[] = [
                'pelanggar_sid' => (string) ($parts[0] ?? ''),
                'peer_sid' => (string) ($parts[1] ?? ''),
                'frekuensi' => (int) $c,
            ];
        }

        return [
            'key' => 'peer_correlation',
            'metric' => 'Korelasi pelanggar–peer',
            'description' => 'Apakah pelanggar sering muncul dengan peer yang sama?',
            'action_threshold' => $threshold,
            'status' => $flag ? 'warning' : 'ok',
            'detail_bullets' => [],
            'peer_correlation_detail' => [
                'definition' => 'Definisi pasangan: SID pelanggar + SID peer berbeda dalam satu kejadian (multi peer = beberapa pasangan per kejadian). Pasangan dengan SID pelanggar = SID peer diabaikan — satu SID hanya satu orang.',
                'total_unique_pairs' => count($pairCounts),
                'pairs_with_freq_gte_2' => $pasanganBerulang,
                'max_pair_frequency' => $maxPair,
                'top_pairs' => $topPairRows,
            ],
        ];
    }

    /**
     * @param list<array{status: string, ...}> $rows
     */
    private function buildNarrative(int $total, array $rows): string
    {
        if ($total === 0) {
            return 'Belum ada data kejadian di database untuk dievaluasi.';
        }

        $critical = count(array_filter($rows, static fn (array $r): bool => ($r['status'] ?? '') === 'critical'));
        $warning = count(array_filter($rows, static fn (array $r): bool => ($r['status'] ?? '') === 'warning'));

        if ($critical > 0) {
            return 'Ringkasan dari ' . $total . ' kejadian: ada ' . $critical . ' metrik kritis.';
        }
        if ($warning > 0) {
            return 'Ringkasan dari ' . $total . ' kejadian: ada ' . $warning . ' peringatan (variasi deviasi / pola peer).';
        }

        return 'Ringkasan dari ' . $total . ' kejadian: parameter utama sesuai ambang saat ini.';
    }

}
