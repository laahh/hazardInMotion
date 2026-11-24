<?php

declare(strict_types=1);

namespace App\Actions\PeerPressure;

use App\Services\PeerPressure\PeerPressurePelaksanaanBaselineService;
use Carbon\Carbon;

/**
 * Daftar item baseline peer pressure (BeRecord + TBC + fatigue, tujuh kontraktor) dengan status comply per baris (paginasi).
 * Menyertakan agregat penyebab tidak comply untuk rekomendasi perbaikan.
 * Sumber data (item + ringkasan) sama persis dengan {@see GetPeerPressureDashboardKpiStatsAction} /
 * {@see PeerPressurePelaksanaanBaselineService::compute()}, sehingga total & comply pada tabel selalu selaras dengan kartu KPI.
 */
final class GetPeerPressureDashboardComplianceBreakdownAction
{
    private const MIN_YEAR = 2025;

    private const MAX_YEAR = 2026;

    public function __construct(
        private readonly PeerPressurePelaksanaanBaselineService $pelaksanaanBaseline,
    ) {}

    /** @var array<int, string> */
    private const MONTHS_ID = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * @return array{
     *   period_scope: 'all'|'month',
     *   period_caption: string,
     *   peer_pressure_compliance_pct: float,
     *   peer_pressure_compliance_total: int,
     *   peer_pressure_compliance_comply: int,
     *   recommendations: list<array{code: string, judul: string, jumlah: int, rekomendasi: string, daftar_kejadian: list<array{id: int|null, tanggal_temuan: string|null, kategori_deviasi: string|null, bucket_label: string}>}>,
     *   pagination: array{current_page: int, per_page: int, total: int, last_page: int, from: int|null, to: int|null},
     *   rows: list<array<string, mixed>>
     * }
     */
    public function __invoke(?int $year = null, ?int $month = null, int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = min(50, max(5, $perPage));

        if ($year !== null && $month !== null) {
            $y = max(self::MIN_YEAR, min(self::MAX_YEAR, $year));
            $m = max(1, min(12, $month));
            $start = Carbon::create($y, $m, 1)->startOfDay();

            return [
                'period_scope' => 'month',
                'period_caption' => (self::MONTHS_ID[$m] ?? (string) $m).' '.$y,
                ...$this->buildPaginated($page, $perPage, $y, $m),
            ];
        }

        return [
            'period_scope' => 'all',
            'period_caption' => 'Seluruh data',
            ...$this->buildPaginated($page, $perPage, null, null),
        ];
    }

    /**
     * @return array{
     *   peer_pressure_compliance_pct: float,
     *   peer_pressure_compliance_total: int,
     *   peer_pressure_compliance_comply: int,
     *   recommendations: list<array{code: string, judul: string, jumlah: int, rekomendasi: string, daftar_kejadian: list<array{id: int|null, tanggal_temuan: string|null, kategori_deviasi: string|null, bucket_label: string}>}>,
     *   pagination: array{current_page: int, per_page: int, total: int, last_page: int, from: int|null, to: int|null},
     *   rows: list<array<string, mixed>>
     * }
     */
    private function buildPaginated(int $page, int $perPage, ?int $year = null, ?int $month = null): array
    {
        $items = $this->pelaksanaanBaseline->baselineRowsForPeriod($year, $month);

        $out = [];
        $comply = 0;
        $total = count($items);
        /** @var array<string, int> $nonComplyByCode */
        $nonComplyByCode = [];
        /** @var array<string, list<array{id: int|null, tanggal_temuan: string|null, kategori_deviasi: string|null, bucket_label: string}>> $nonComplyRowsByCode */
        $nonComplyRowsByCode = [];

        foreach ($items as $item) {
            $done = (bool) ($item['sudah_peer_pressure'] ?? false);
            if ($done) {
                $comply++;
            }

            $ev = self::evaluateBaselineItem($item);

            $row = [
                'id' => $item['kejadian_edukasi_id'] ?? null,
                'tanggal_temuan' => $item['tanggal_temuan'] ?? null,
                'kategori_deviasi' => $item['referensi'] ?? null,
                'bucket' => $item['jenis'] ?? null,
                'bucket_label' => $item['jenis_label'] ?? null,
                'status_pelaksanaan_edukasi' => $item['status_pelaksanaan_edukasi'] ?? null,
                'id_berecord' => ($item['jenis'] ?? null) === 'berecord' ? ($item['referensi'] ?? null) : null,
                'comply' => $done,
                'alasan' => $ev['alasan'],
                'reason_code' => $ev['reason_code'],
            ];

            if (! $done) {
                $rc = $ev['reason_code'];
                $nonComplyByCode[$rc] = ($nonComplyByCode[$rc] ?? 0) + 1;
                if (! isset($nonComplyRowsByCode[$rc])) {
                    $nonComplyRowsByCode[$rc] = [];
                }
                $nonComplyRowsByCode[$rc][] = [
                    'id' => $row['id'],
                    'tanggal_temuan' => $row['tanggal_temuan'],
                    'kategori_deviasi' => $row['kategori_deviasi'],
                    'bucket_label' => $row['bucket_label'],
                ];
            }

            $out[] = $row;
        }

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;
        if ($page > $lastPage) {
            $page = $lastPage;
        }
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($out, $offset, $perPage);
        $from = $total === 0 ? null : $offset + 1;
        $to = $total === 0 ? null : min($offset + count($slice), $total);

        $pel = $this->pelaksanaanBaseline->compute($year, $month);

        return [
            'peer_pressure_compliance_pct' => $pel['pct_selesai'],
            'peer_pressure_compliance_total' => $pel['baseline_total'],
            'peer_pressure_compliance_comply' => $pel['selesai'],
            'recommendations' => self::buildRecommendations($nonComplyByCode, $total - $comply, $nonComplyRowsByCode),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, $lastPage),
                'from' => $from,
                'to' => $to,
            ],
            'rows' => $slice,
        ];
    }

    /**
     * Evaluasi comply satu item baseline. Sejalan dengan {@see PeerPressurePelaksanaanBaselineService::compute()}:
     * item sudah dianggap baseline (BE punya id BeRecord, TBC punya tasklist valid, fatigue tercatat), jadi satu-satunya
     * syarat comply yang tersisa adalah status pelaksanaan edukasi selesai.
     *
     * @param  array<string, mixed>  $item
     * @return array{alasan: string, reason_code: string}
     */
    private static function evaluateBaselineItem(array $item): array
    {
        if ((bool) ($item['sudah_peer_pressure'] ?? false)) {
            return [
                'alasan' => 'Pelaksanaan selesai.',
                'reason_code' => 'ok',
            ];
        }

        return match ($item['jenis'] ?? '') {
            'berecord' => [
                'alasan' => 'Pelaksanaan belum selesai (status harus mengandung CLOSED atau SELESAI).',
                'reason_code' => 'be_belum_selesai',
            ],
            default => [
                'alasan' => 'Pelaksanaan belum selesai (status harus mengandung CLOSED atau SELESAI).',
                'reason_code' => 'fb_belum_selesai',
            ],
        };
    }

    /**
     * @param  array<string, int>  $nonComplyByCode
     * @param  array<string, list<array{id: int|null, tanggal_temuan: string|null, kategori_deviasi: string|null, bucket_label: string}>>  $nonComplyRowsByCode
     * @return list<array{code: string, judul: string, jumlah: int, rekomendasi: string, daftar_kejadian: list<array{id: int|null, tanggal_temuan: string|null, kategori_deviasi: string|null, bucket_label: string}>}>
     */
    private static function buildRecommendations(array $nonComplyByCode, int $nonComplyTotal, array $nonComplyRowsByCode): array
    {
        if ($nonComplyTotal <= 0) {
            return [
                [
                    'code' => 'all_ok',
                    'judul' => 'Semua item baseline terlacak memenuhi comply',
                    'jumlah' => 0,
                    'rekomendasi' => 'Pertahankan disiplin penutupan pelaksanaan edukasi sesuai aturan baseline (BeRecord + TBC + fatigue).',
                    'daftar_kejadian' => [],
                ],
            ];
        }

        /** @var list<array{code: string, judul: string, jumlah: int, rekomendasi: string, daftar_kejadian: list<array<string, mixed>>}> $items */
        $items = [];
        $order = ['be_belum_selesai', 'fb_belum_selesai', 'unknown'];

        $copy = $nonComplyByCode;
        foreach ($order as $code) {
            $n = (int) ($copy[$code] ?? 0);
            if ($n <= 0) {
                continue;
            }
            unset($copy[$code]);
            $meta = self::recommendationMeta($code);
            $items[] = [
                'code' => $code,
                'judul' => $meta['judul'],
                'jumlah' => $n,
                'rekomendasi' => $meta['rekomendasi'],
                'daftar_kejadian' => array_values($nonComplyRowsByCode[$code] ?? []),
            ];
        }

        foreach ($copy as $code => $n) {
            if ($n <= 0 || $code === 'ok') {
                continue;
            }
            $meta = self::recommendationMeta($code);
            $items[] = [
                'code' => $code,
                'judul' => $meta['judul'],
                'jumlah' => (int) $n,
                'rekomendasi' => $meta['rekomendasi'],
                'daftar_kejadian' => array_values($nonComplyRowsByCode[$code] ?? []),
            ];
        }

        return $items;
    }

    /**
     * @return array{judul: string, rekomendasi: string}
     */
    private static function recommendationMeta(string $code): array
    {
        return match ($code) {
            'be_belum_selesai' => [
                'judul' => 'Perlu penutupan pelaksanaan (jalur BeRecord)',
                'rekomendasi' => 'Uraian per item di bawah menyebutkan tanggal temuan dan referensi; tutup pelaksanaan dengan status yang mengandung CLOSED atau SELESAI.',
            ],
            'fb_belum_selesai' => [
                'judul' => 'Perlu penutupan pelaksanaan (Fatigue / TBC)',
                'rekomendasi' => 'Kategori ini tidak memakai BeRecord. Uraian per item: update status ke CLOSED/SELESAI setelah edukasi rampung.',
            ],
            default => [
                'judul' => 'Penyebab lain ('.$code.')',
                'rekomendasi' => 'Uraian per item terkait:',
            ],
        };
    }
}
