<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Data pendukung 8 panel "wawasan fatigue" (bukan watchlist harian):
 * trend alert, trend Fit to Work, breakdown deviasi, penyakit kritis vs alert,
 * dan ranking operator dengan alert berulang + tren-nya.
 *
 * Semua query bersifat company-wide (tidak dibatasi ke roster Operator) karena
 * DMS & Fatigue Check secara inheren hanya diisi oleh operator/pengemudi unit —
 * dan supaya query tetap ringan (mv_dms_alert & clean_data_fatigue_check sudah
 * terindeks/terbukti cepat untuk window tanggal, tapi TIDAK untuk filter
 * berbasis m_karyawan/jabatan — lihat catatan di PraOperasiOperatorRosterReader).
 *
 * PENTING soal data quality: kolom tanggal_terkonfirmasi_penyakit_kritis berisi
 * banyak nilai sentinel/placeholder lama ('1945-08-17', '2001-01-01', dst) yang
 * BUKAN tanggal konfirmasi asli — query di sini membatasi ke rentang tanggal
 * yang masuk akal (>= 18 bulan terakhir) untuk menghindari overcount.
 */
final class PraOperasiFatigueTrendReader
{
    private const TREND_DAYS = 14;

    private const CACHE_TTL_SECONDS = 3600;

    /** @var list<string> */
    private const FATIGUE_ALERT_NAMES = ['Menutup Mata', 'Menguap', 'Menunduk'];

    /** @var list<string> */
    private const TINDAKAN_UNFIT_NEUTRAL = ['TIDAK', 'TIDAK ADA', 'FIT', 'NA', 'N/A', '-', '_', '0', '00:00:00', 'TIDAK UNFIT', ''];

    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $connectionSource,
    ) {}

    public function isUp(): bool
    {
        return $this->connectionSource->isUp();
    }

    /**
     * Panel #1 — Trend Alert Fatigue (True/False/Null) + jumlah operator berbeda per hari.
     *
     * @return array{categories:list<string>, true_count:list<int>, false_count:list<int>, null_count:list<int>, operator_count:list<int>}
     */
    public function alertTrend(string $untilDate): array
    {
        return $this->remember('alert_trend', $untilDate, function () use ($untilDate): array {
            [$start, $end] = $this->windowBounds($untilDate);
            $connection = $this->connectionSource->connectionName();
            $empty = ['categories' => [], 'true_count' => [], 'false_count' => [], 'null_count' => [], 'operator_count' => []];
            if ($connection === null) {
                return $empty;
            }

            $namePlaceholders = implode(',', array_fill(0, count(self::FATIGUE_ALERT_NAMES), '?'));
            $sql = '
                SELECT date(waktu_deteksi) AS tgl,
                    count(*) FILTER (WHERE l1_model_status = true) AS true_count,
                    count(*) FILTER (WHERE l1_model_status = false) AS false_count,
                    count(*) FILTER (WHERE sudah_direview_l1 = false) AS null_count,
                    count(DISTINCT kode_sid) AS operator_count
                FROM bcsid.mv_dms_alert
                WHERE nama_pelanggaran IN ('.$namePlaceholders.')
                  AND waktu_deteksi >= ? AND waktu_deteksi < ?
                GROUP BY date(waktu_deteksi)
                ORDER BY tgl
            ';

            try {
                $rows = DB::connection($connection)->select($sql, array_merge(self::FATIGUE_ALERT_NAMES, [$start, $end]));
            } catch (Throwable $e) {
                report($e);

                return $empty;
            }

            return $this->fillDailySeries($rows, $start, $end, [
                'true_count' => 'true_count', 'false_count' => 'false_count',
                'null_count' => 'null_count', 'operator_count' => 'operator_count',
            ]);
        });
    }

    /**
     * Panel #3 — Trend status scoring Fit to Work (Hijau/Kuning/Merah) per hari.
     *
     * @return array{categories:list<string>, hijau:list<int>, kuning:list<int>, merah:list<int>}
     */
    public function fitToWorkTrend(string $untilDate): array
    {
        return $this->remember('ftw_trend', $untilDate, function () use ($untilDate): array {
            [$start, $end] = $this->windowBounds($untilDate);
            $connection = $this->connectionSource->connectionName();
            $empty = ['categories' => [], 'hijau' => [], 'kuning' => [], 'merah' => []];
            if ($connection === null) {
                return $empty;
            }

            $sql = "
                SELECT tanggal_pemeriksaan AS tgl,
                    count(*) FILTER (WHERE kesiapan_bekerja_fisik_dan_mental ~ '^[0-9]+$' AND kesiapan_bekerja_fisik_dan_mental::int >= 8) AS hijau,
                    count(*) FILTER (WHERE kesiapan_bekerja_fisik_dan_mental ~ '^[0-9]+$' AND kesiapan_bekerja_fisik_dan_mental::int BETWEEN 5 AND 7) AS kuning,
                    count(*) FILTER (WHERE kesiapan_bekerja_fisik_dan_mental ~ '^[0-9]+$' AND kesiapan_bekerja_fisik_dan_mental::int BETWEEN 1 AND 4) AS merah
                FROM bcsid.clean_data_fatigue_check
                WHERE tanggal_pemeriksaan >= ? AND tanggal_pemeriksaan <= ?
                GROUP BY tanggal_pemeriksaan
                ORDER BY tanggal_pemeriksaan
            ";

            try {
                $rows = DB::connection($connection)->select($sql, [substr($start, 0, 10), substr($end, 0, 10)]);
            } catch (Throwable $e) {
                report($e);

                return $empty;
            }

            return $this->fillDailySeries($rows, $start, $end, [
                'hijau' => 'hijau', 'kuning' => 'kuning', 'merah' => 'merah',
            ], dateColumnIsString: true);
        });
    }

    /**
     * Panel #4 — Breakdown deviasi Fit to Work (14 hari terakhir).
     *
     * @return array{sobriety_unfit:int, kurang_tidur:int, sakit:int, ada_tindakan_unfit:int, total:int}
     */
    public function deviationBreakdown(string $untilDate): array
    {
        return $this->remember('deviation_breakdown', $untilDate, function () use ($untilDate): array {
            [$start, $end] = $this->windowBounds($untilDate);
            $connection = $this->connectionSource->connectionName();
            $empty = ['sobriety_unfit' => 0, 'kurang_tidur' => 0, 'sakit' => 0, 'ada_tindakan_unfit' => 0, 'total' => 0];
            if ($connection === null) {
                return $empty;
            }

            $neutralPlaceholders = implode(',', array_fill(0, count(self::TINDAKAN_UNFIT_NEUTRAL), '?'));
            $sql = "
                SELECT
                    count(*) FILTER (WHERE UPPER(TRIM(hasil_sobriety_test)) = 'UNFIT') AS sobriety_unfit,
                    count(*) FILTER (WHERE jumlah_jam_tidur ~ '^[0-9.]+$' AND jumlah_jam_tidur::numeric < 6) AS kurang_tidur,
                    count(*) FILTER (WHERE kondisi_karyawan = 'Sakit') AS sakit,
                    count(*) FILTER (WHERE tindakan_unfit IS NOT NULL AND UPPER(TRIM(tindakan_unfit)) NOT IN ({$neutralPlaceholders})) AS ada_tindakan_unfit,
                    count(*) AS total
                FROM bcsid.clean_data_fatigue_check
                WHERE tanggal_pemeriksaan >= ? AND tanggal_pemeriksaan <= ?
            ";

            try {
                $row = DB::connection($connection)->selectOne(
                    $sql,
                    array_merge(self::TINDAKAN_UNFIT_NEUTRAL, [substr($start, 0, 10), substr($end, 0, 10)])
                );
            } catch (Throwable $e) {
                report($e);

                return $empty;
            }

            return [
                'sobriety_unfit' => (int) ($row->sobriety_unfit ?? 0),
                'kurang_tidur' => (int) ($row->kurang_tidur ?? 0),
                'sakit' => (int) ($row->sakit ?? 0),
                'ada_tindakan_unfit' => (int) ($row->ada_tindakan_unfit ?? 0),
                'total' => (int) ($row->total ?? 0),
            ];
        });
    }

    /**
     * Panel #5 — Penyakit kritis terkonfirmasi (18 bulan terakhir, dibersihkan
     * dari tanggal sentinel lama) vs yang juga kena alert fatigue DMS (14 hari).
     *
     * @return array{total_penyakit_kritis:int, ada_alert_fatigue:int}
     */
    public function criticalIllnessVsAlert(string $untilDate): array
    {
        return $this->remember('critical_illness', $untilDate, function () use ($untilDate): array {
            [$start, $end] = $this->windowBounds($untilDate);
            $connection = $this->connectionSource->connectionName();
            $empty = ['total_penyakit_kritis' => 0, 'ada_alert_fatigue' => 0];
            if ($connection === null) {
                return $empty;
            }

            $illnessFrom = Carbon::parse($untilDate)->subMonths(18)->toDateString();
            $namePlaceholders = implode(',', array_fill(0, count(self::FATIGUE_ALERT_NAMES), '?'));

            $sql = "
                WITH sakit_kritis AS (
                    SELECT DISTINCT UPPER(TRIM(sid)) AS sid FROM bcsid.clean_data_fatigue_check
                    WHERE tanggal_terkonfirmasi_penyakit_kritis ~ '^\\d{4}-\\d{2}-\\d{2}$'
                      AND tanggal_terkonfirmasi_penyakit_kritis::date >= ?
                      AND tanggal_terkonfirmasi_penyakit_kritis::date <= ?
                )
                SELECT
                    (SELECT count(*) FROM sakit_kritis) AS total_penyakit_kritis,
                    (SELECT count(DISTINCT a.kode_sid) FROM bcsid.mv_dms_alert a
                        JOIN sakit_kritis sk ON UPPER(TRIM(a.kode_sid)) = sk.sid
                        WHERE a.nama_pelanggaran IN ({$namePlaceholders})
                          AND a.waktu_deteksi >= ? AND a.waktu_deteksi < ?) AS ada_alert_fatigue
            ";

            try {
                $row = DB::connection($connection)->selectOne(
                    $sql,
                    array_merge([$illnessFrom, $untilDate], self::FATIGUE_ALERT_NAMES, [$start, $end])
                );
            } catch (Throwable $e) {
                report($e);

                return $empty;
            }

            return [
                'total_penyakit_kritis' => (int) ($row->total_penyakit_kritis ?? 0),
                'ada_alert_fatigue' => (int) ($row->ada_alert_fatigue ?? 0),
            ];
        });
    }

    /**
     * Panel #7 + #8 — Ranking operator dengan alert fatigue TRUE (terverifikasi)
     * paling sering berulang, dilengkapi status Fit to Work terkininya (#7) dan
     * arah tren 7 hari terakhir vs 7 hari sebelumnya (#8, bukan prediksi
     * black-box — murni perbandingan tren historis, dilabeli transparan).
     *
     * @return list<array{
     *     kode_sid:string, nama:string, unit:string, true_alert_count:int,
     *     ftw_tier:string|null, ftw_score:int|null, trend:string, trend_ratio:float|null
     * }>
     */
    public function topRepeatOperators(string $untilDate, int $limit = 10): array
    {
        return $this->remember('top_repeat.'.$limit, $untilDate, function () use ($untilDate, $limit): array {
            [$start, $end] = $this->windowBounds($untilDate);
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $namePlaceholders = implode(',', array_fill(0, count(self::FATIGUE_ALERT_NAMES), '?'));
            $sql = '
                SELECT kode_sid, nama_driver_dms, unit, count(*) AS true_alert_count
                FROM bcsid.mv_dms_alert
                WHERE nama_pelanggaran IN ('.$namePlaceholders.')
                  AND l1_model_status = true
                  AND waktu_deteksi >= ? AND waktu_deteksi < ?
                GROUP BY kode_sid, nama_driver_dms, unit
                ORDER BY true_alert_count DESC
                LIMIT ?
            ';

            try {
                $rows = DB::connection($connection)->select($sql, array_merge(self::FATIGUE_ALERT_NAMES, [$start, $end, $limit]));
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            if ($rows === []) {
                return [];
            }

            $sids = array_map(static fn ($r): string => trim((string) $r->kode_sid), $rows);
            $ftwBySid = $this->latestFatigueTierForSids($connection, $sids, $untilDate);
            $trendBySid = $this->trendForSids($connection, $sids, $untilDate);

            $out = [];
            foreach ($rows as $row) {
                $sid = trim((string) $row->kode_sid);
                $upper = mb_strtoupper($sid);
                $ftw = $ftwBySid[$upper] ?? null;
                $trend = $trendBySid[$upper] ?? ['trend' => 'stabil', 'ratio' => null];

                $out[] = [
                    'kode_sid' => $sid,
                    'nama' => trim((string) ($row->nama_driver_dms ?? '')) ?: '-',
                    'unit' => trim((string) ($row->unit ?? '')) ?: '-',
                    'true_alert_count' => (int) $row->true_alert_count,
                    'ftw_tier' => $ftw['tier'] ?? null,
                    'ftw_score' => $ftw['score'] ?? null,
                    'trend' => $trend['trend'],
                    'trend_ratio' => $trend['ratio'],
                ];
            }

            return $out;
        });
    }

    /**
     * @param  list<string>  $sids
     * @return array<string, array{tier: string|null, score: int|null}>
     */
    private function latestFatigueTierForSids(string $connection, array $sids, string $untilDate): array
    {
        $upperSids = array_values(array_unique(array_map('mb_strtoupper', $sids)));
        if ($upperSids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($upperSids), '?'));
        $from = Carbon::parse($untilDate)->subDays(30)->toDateString();

        $sql = "
            SELECT DISTINCT ON (UPPER(TRIM(sid))) sid, kesiapan_bekerja_fisik_dan_mental
            FROM bcsid.clean_data_fatigue_check
            WHERE UPPER(TRIM(sid)) IN ({$placeholders})
              AND tanggal_pemeriksaan >= ? AND tanggal_pemeriksaan <= ?
            ORDER BY UPPER(TRIM(sid)), tanggal_pemeriksaan DESC
        ";

        try {
            $rows = DB::connection($connection)->select($sql, array_merge($upperSids, [$from, $untilDate]));
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $sid = mb_strtoupper(trim((string) ($row->sid ?? '')));
            if ($sid === '') {
                continue;
            }
            $scoreRaw = trim((string) ($row->kesiapan_bekerja_fisik_dan_mental ?? ''));
            $score = ctype_digit($scoreRaw) ? (int) $scoreRaw : null;
            $out[$sid] = ['tier' => PraOperasiFatigueCheckReader::tierFromScore($score), 'score' => $score];
        }

        return $out;
    }

    /**
     * Bandingkan jumlah alert TRUE 7 hari terakhir vs 7 hari sebelumnya per SID.
     *
     * @param  list<string>  $sids
     * @return array<string, array{trend:string, ratio: float|null}>
     */
    private function trendForSids(string $connection, array $sids, string $untilDate): array
    {
        $upperSids = array_values(array_unique(array_map('mb_strtoupper', $sids)));
        if ($upperSids === []) {
            return [];
        }

        $tz = (string) config('app.timezone');
        $end = Carbon::parse($untilDate, $tz)->startOfDay()->addDay();
        $midpoint = $end->copy()->subDays(7);
        $farStart = $end->copy()->subDays(14);

        $placeholders = implode(',', array_fill(0, count($upperSids), '?'));
        $namePlaceholders = implode(',', array_fill(0, count(self::FATIGUE_ALERT_NAMES), '?'));

        $sql = '
            SELECT UPPER(TRIM(kode_sid)) AS sid,
                count(*) FILTER (WHERE waktu_deteksi >= ? AND waktu_deteksi < ?) AS minggu_lalu,
                count(*) FILTER (WHERE waktu_deteksi >= ? AND waktu_deteksi < ?) AS minggu_ini
            FROM bcsid.mv_dms_alert
            WHERE nama_pelanggaran IN ('.$namePlaceholders.')
              AND l1_model_status = true
              AND waktu_deteksi >= ? AND waktu_deteksi < ?
              AND UPPER(TRIM(kode_sid)) IN ('.$placeholders.')
            GROUP BY UPPER(TRIM(kode_sid))
        ';

        $bindings = array_merge(
            [$farStart->format('Y-m-d H:i:s'), $midpoint->format('Y-m-d H:i:s')],
            [$midpoint->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')],
            self::FATIGUE_ALERT_NAMES,
            [$farStart->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')],
            $upperSids,
        );

        try {
            $rows = DB::connection($connection)->select($sql, $bindings);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $sid = (string) ($row->sid ?? '');
            if ($sid === '') {
                continue;
            }
            $lalu = (int) $row->minggu_lalu;
            $ini = (int) $row->minggu_ini;
            $ratio = $lalu > 0 ? round($ini / $lalu, 2) : ($ini > 0 ? null : 1.0);

            $trend = 'stabil';
            if ($ratio !== null) {
                if ($ratio >= 1.2) {
                    $trend = 'meningkat';
                } elseif ($ratio <= 0.8) {
                    $trend = 'menurun';
                }
            } elseif ($ini > 0) {
                $trend = 'meningkat';
            }

            $out[$sid] = ['trend' => $trend, 'ratio' => $ratio];
        }

        return $out;
    }

    /**
     * @return array{0:string,1:string} [start, end) dalam Y-m-d H:i:s, Asia/Makassar.
     */
    private function windowBounds(string $untilDate): array
    {
        $tz = (string) config('app.timezone');
        $end = Carbon::parse($untilDate, $tz)->startOfDay()->addDay();
        $start = $end->copy()->subDays(self::TREND_DAYS);

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    /**
     * Isi hari yang tidak punya baris hasil query dengan 0, supaya sumbu-x chart
     * tetap rapat (bukan cuma tanggal yang ada datanya).
     *
     * @param  array<string, string>  $columns  alias output => nama kolom SQL
     * @return array<string, list<int>|list<string>>
     */
    private function fillDailySeries(array $rows, string $start, string $end, array $columns, bool $dateColumnIsString = false): array
    {
        $tz = (string) config('app.timezone');
        $byDate = [];
        foreach ($rows as $row) {
            $tgl = $row->tgl ?? null;
            $key = $dateColumnIsString
                ? (string) $tgl
                : ($tgl instanceof \DateTimeInterface ? Carbon::instance($tgl)->toDateString() : (string) $tgl);
            $byDate[$key] = $row;
        }

        $categories = [];
        $series = [];
        foreach (array_keys($columns) as $alias) {
            $series[$alias] = [];
        }

        $cursor = Carbon::parse($start, $tz)->startOfDay();
        $endDay = Carbon::parse($end, $tz)->startOfDay();
        while ($cursor->lt($endDay)) {
            $dateKey = $cursor->toDateString();
            $categories[] = $cursor->translatedFormat('d M');
            $row = $byDate[$dateKey] ?? null;
            foreach ($columns as $alias => $col) {
                $series[$alias][] = $row !== null ? (int) ($row->{$col} ?? 0) : 0;
            }
            $cursor->addDay();
        }

        return array_merge(['categories' => $categories], $series);
    }

    private function remember(string $key, string $untilDate, \Closure $callback): array
    {
        if (! $this->isUp()) {
            return [];
        }

        return Cache::remember('pra_operasi:trend:'.$key.':'.$untilDate, self::CACHE_TTL_SECONDS, $callback);
    }
}
