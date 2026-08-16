<?php

declare(strict_types=1);

namespace App\Services\Dms;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Data mentah untuk /dms/fatigue-baseline-static, dari bcsid.mv_dms_alert
 * (hse_automation/Postgres) — TIDAK melalui bcsid.m_karyawan (lihat catatan
 * performa di PraOperasiOperatorRosterReader): mv_dms_alert sudah punya
 * kode_sid, nama_driver_dms, unit, site, perusahaan langsung per baris alert,
 * jadi tidak perlu join ke tabel karyawan yang besar sama sekali.
 *
 * "DMS001" (nama_driver_dms = "Petugas Maintenance (Bukan Operator)") sengaja
 * dikecualikan — itu placeholder sistem untuk kejadian yang tidak tertaut ke
 * operator sungguhan, dicek langsung ke data produksi.
 */
final class FatigueBaselineDataReader
{
    private const SID_CHUNK = 500;

    /** @var list<string> */
    private const FATIGUE_ALERT_NAMES = ['Menutup Mata', 'Menguap', 'Menunduk'];

    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $connectionSource,
    ) {}

    public function isUp(): bool
    {
        return $this->connectionSource->isUp();
    }

    /**
     * Kandidat watchlist: SID dengan alert fatigue terkonfirmasi nyata
     * terbanyak dalam window, dan minimal $minAlertDays hari punya alert
     * (supaya cukup data untuk baseline, bukan cuma satu insiden liar).
     *
     * @return list<string>  UPPER(kode_sid), urut total alert terbanyak dulu
     */
    public function topFatigueSids(string $untilDate, int $lookbackDays, int $minAlertDays, int $limit): array
    {
        if (! $this->isUp()) {
            return [];
        }

        $cacheKey = 'dms:fatigue_baseline:top_sids:v1:'.$untilDate.':'.$lookbackDays.':'.$minAlertDays.':'.$limit;

        return Cache::remember($cacheKey, 900, function () use ($untilDate, $lookbackDays, $minAlertDays, $limit): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $tz = (string) config('app.timezone');
            $end = Carbon::parse($untilDate, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');
            $start = Carbon::parse($untilDate, $tz)->startOfDay()->subDays($lookbackDays)->format('Y-m-d H:i:s');
            $namePlaceholders = implode(',', array_fill(0, count(self::FATIGUE_ALERT_NAMES), '?'));

            $sql = '
                SELECT sid, count(*) AS hari_ada_alert, sum(cnt) AS total_alert
                FROM (
                    SELECT UPPER(TRIM(kode_sid)) AS sid, date(waktu_deteksi) AS d, count(*) AS cnt
                    FROM bcsid.mv_dms_alert
                    WHERE nama_pelanggaran IN ('.$namePlaceholders.')
                      AND sudah_direview_l1 = true AND l1_model_status = true
                      AND waktu_deteksi >= ? AND waktu_deteksi < ?
                      AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> \'\'
                      AND (nama_driver_dms IS NULL OR nama_driver_dms NOT ILIKE \'%Bukan Operator%\')
                    GROUP BY 1, 2
                ) daily
                GROUP BY sid
                HAVING count(*) >= ?
                ORDER BY total_alert DESC
                LIMIT ?
            ';

            try {
                $rows = DB::connection($connection)->select(
                    $sql,
                    array_merge(self::FATIGUE_ALERT_NAMES, [$start, $end, $minAlertDays, $limit])
                );
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_values(array_filter(array_map(
                static fn ($row): string => trim((string) ($row->sid ?? '')),
                $rows
            ), static fn (string $s): bool => $s !== ''));
        });
    }

    /**
     * Jumlah alert fatigue terkonfirmasi nyata PER HARI untuk sekumpulan SID —
     * hanya baris yang benar-benar punya alert (pemanggil yang mengisi tanggal
     * kosong dengan 0, karena baseline/tren butuh deret rapat tanpa lubang).
     *
     * @param  list<string>  $sids
     * @return array<string, array<string, int>>  UPPER(kode_sid) => [tanggal Y-m-d => jumlah]
     */
    public function dailyCountsForSids(array $sids, string $untilDate, int $lookbackDays): array
    {
        if (! $this->isUp() || $sids === []) {
            return [];
        }

        $upperSids = array_values(array_unique(array_filter(array_map(
            static fn (string $s): string => mb_strtoupper(trim($s)),
            $sids
        ), static fn (string $s): bool => $s !== '')));
        if ($upperSids === []) {
            return [];
        }

        $cacheKey = 'dms:fatigue_baseline:daily_counts:v1:'.$untilDate.':'.$lookbackDays.':'.md5(implode(',', $upperSids));

        return Cache::remember($cacheKey, 900, function () use ($upperSids, $untilDate, $lookbackDays): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $tz = (string) config('app.timezone');
            $end = Carbon::parse($untilDate, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');
            $start = Carbon::parse($untilDate, $tz)->startOfDay()->subDays($lookbackDays)->format('Y-m-d H:i:s');
            $namePlaceholders = implode(',', array_fill(0, count(self::FATIGUE_ALERT_NAMES), '?'));

            $out = [];
            foreach (array_chunk($upperSids, self::SID_CHUNK) as $chunk) {
                $sidPlaceholders = implode(',', array_fill(0, count($chunk), '?'));
                $sql = '
                    SELECT UPPER(TRIM(kode_sid)) AS sid, date(waktu_deteksi) AS d, count(*) AS cnt
                    FROM bcsid.mv_dms_alert
                    WHERE nama_pelanggaran IN ('.$namePlaceholders.')
                      AND sudah_direview_l1 = true AND l1_model_status = true
                      AND waktu_deteksi >= ? AND waktu_deteksi < ?
                      AND UPPER(TRIM(kode_sid)) IN ('.$sidPlaceholders.')
                    GROUP BY 1, 2
                ';

                $bindings = array_merge(self::FATIGUE_ALERT_NAMES, [$start, $end], $chunk);

                try {
                    $rows = DB::connection($connection)->select($sql, $bindings);
                } catch (Throwable $e) {
                    report($e);
                    continue;
                }

                foreach ($rows as $row) {
                    $sid = trim((string) ($row->sid ?? ''));
                    $date = (string) ($row->d ?? '');
                    if ($sid === '' || $date === '') {
                        continue;
                    }
                    $out[$sid][$date] = (int) ($row->cnt ?? 0);
                }
            }

            return $out;
        });
    }

    /**
     * Info tampilan terbaru (nama, unit, site, perusahaan) per SID — diambil
     * dari baris alert PALING BARU, bukan dari m_karyawan.
     *
     * @param  list<string>  $sids
     * @return array<string, array{nama:string, unit:string, site:string, perusahaan:string}>
     */
    public function latestMetaForSids(array $sids): array
    {
        if (! $this->isUp() || $sids === []) {
            return [];
        }

        $upperSids = array_values(array_unique(array_filter(array_map(
            static fn (string $s): string => mb_strtoupper(trim($s)),
            $sids
        ), static fn (string $s): bool => $s !== '')));
        if ($upperSids === []) {
            return [];
        }

        $cacheKey = 'dms:fatigue_baseline:meta:v1:'.md5(implode(',', $upperSids));

        return Cache::remember($cacheKey, 900, function () use ($upperSids): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $out = [];
            foreach (array_chunk($upperSids, self::SID_CHUNK) as $chunk) {
                $sidPlaceholders = implode(',', array_fill(0, count($chunk), '?'));
                $sql = '
                    SELECT DISTINCT ON (UPPER(TRIM(kode_sid)))
                        UPPER(TRIM(kode_sid)) AS sid,
                        TRIM(COALESCE(nama_driver_dms::text, \'\')) AS nama,
                        TRIM(COALESCE(unit::text, \'\')) AS unit,
                        TRIM(COALESCE(site::text, \'\')) AS site,
                        TRIM(COALESCE(perusahaan::text, \'\')) AS perusahaan
                    FROM bcsid.mv_dms_alert
                    WHERE UPPER(TRIM(kode_sid)) IN ('.$sidPlaceholders.')
                    ORDER BY UPPER(TRIM(kode_sid)), waktu_deteksi DESC
                ';

                try {
                    $rows = DB::connection($connection)->select($sql, $chunk);
                } catch (Throwable $e) {
                    report($e);
                    continue;
                }

                foreach ($rows as $row) {
                    $sid = trim((string) ($row->sid ?? ''));
                    if ($sid === '') {
                        continue;
                    }
                    $out[$sid] = [
                        'nama' => trim((string) ($row->nama ?? '')),
                        'unit' => trim((string) ($row->unit ?? '')),
                        'site' => trim((string) ($row->site ?? '')),
                        'perusahaan' => trim((string) ($row->perusahaan ?? '')),
                    ];
                }
            }

            return $out;
        });
    }
}
