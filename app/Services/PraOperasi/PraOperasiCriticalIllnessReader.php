<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Status riwayat penyakit kritis terkonfirmasi per SID, + apakah sudah ada
 * Fatigue Test follow-up SETELAH tanggal konfirmasi tersebut.
 *
 * Kolom tanggal_terkonfirmasi_penyakit_kritis berisi banyak nilai sentinel
 * lama ('1945-08-17', '2001-01-01', dst) — query di bawah membatasi ke
 * tanggal yang masuk akal (>= 2020-01-01) supaya tidak overcount.
 */
final class PraOperasiCriticalIllnessReader
{
    private const SENTINEL_FLOOR = '2020-01-01';

    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $connectionSource,
    ) {}

    public function isUp(): bool
    {
        return $this->connectionSource->isUp();
    }

    /**
     * @param  list<string>  $sids
     * @return array<string, array{has_critical_illness:bool, confirmed_date:string|null, followed_up:bool}>  keyed by UPPER(kode_sid)
     */
    public function statusForSids(array $sids, string $untilDate): array
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

        $cacheKey = 'pra_operasi:critical_illness:v1:'.$untilDate.':'.md5(implode(',', $upperSids));

        return Cache::remember($cacheKey, 1800, function () use ($upperSids, $untilDate): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            // Satu query TANPA chunk untuk KEDUA sub-query di bawah —
            // bcsid.clean_data_fatigue_check adalah foreign table (FDW) yang
            // dicek langsung via EXPLAIN: filter apa pun (UPPER(TRIM(sid))
            // IN (...), regex, perbandingan tanggal) TIDAK push-down ke sisi
            // remote, jadi cost-nya flat ~65-90rb terlepas dari jumlah SID.
            // Chunking di sini mengalikan beban itu per chunk — akar penyebab
            // 504 Gateway Timeout di /pra-operasi.
            $confirmedBySid = [];
            $placeholders = implode(',', array_fill(0, count($upperSids), '?'));
            $sql = "
                SELECT UPPER(TRIM(sid)) AS sid, max(tanggal_terkonfirmasi_penyakit_kritis) AS tgl_konfirmasi
                FROM bcsid.clean_data_fatigue_check
                WHERE UPPER(TRIM(sid)) IN ({$placeholders})
                  AND tanggal_terkonfirmasi_penyakit_kritis ~ '^\\d{4}-\\d{2}-\\d{2}$'
                  AND tanggal_terkonfirmasi_penyakit_kritis::date >= ?
                  AND tanggal_terkonfirmasi_penyakit_kritis::date <= ?
                GROUP BY UPPER(TRIM(sid))
            ";

            try {
                $rows = DB::connection($connection)->select($sql, array_merge($upperSids, [self::SENTINEL_FLOOR, $untilDate]));
            } catch (Throwable $e) {
                report($e);
                $rows = [];
            }

            foreach ($rows as $row) {
                $sid = trim((string) ($row->sid ?? ''));
                if ($sid === '') {
                    continue;
                }
                $confirmedBySid[$sid] = (string) $row->tgl_konfirmasi;
            }

            if ($confirmedBySid === []) {
                return [];
            }

            // Follow-up: ada baris Fatigue Test dengan tanggal_pemeriksaan SETELAH tanggal konfirmasi.
            $followUpBySid = [];
            $confirmedSids = array_keys($confirmedBySid);
            $followUpPlaceholders = implode(',', array_fill(0, count($confirmedSids), '?'));
            $followUpSql = "
                SELECT UPPER(TRIM(sid)) AS sid, max(tanggal_pemeriksaan) AS tgl_terakhir
                FROM bcsid.clean_data_fatigue_check
                WHERE UPPER(TRIM(sid)) IN ({$followUpPlaceholders})
                  AND tanggal_pemeriksaan <= ?
                GROUP BY UPPER(TRIM(sid))
            ";

            try {
                $followUpRows = DB::connection($connection)->select($followUpSql, array_merge($confirmedSids, [$untilDate]));
            } catch (Throwable $e) {
                report($e);
                $followUpRows = [];
            }

            foreach ($followUpRows as $row) {
                $sid = trim((string) ($row->sid ?? ''));
                if ($sid === '') {
                    continue;
                }
                $followUpBySid[$sid] = (string) $row->tgl_terakhir;
            }

            $out = [];
            foreach ($confirmedBySid as $sid => $confirmedDate) {
                $lastCheck = $followUpBySid[$sid] ?? null;
                $followedUp = $lastCheck !== null && $lastCheck > $confirmedDate;
                $out[$sid] = [
                    'has_critical_illness' => true,
                    'confirmed_date' => $confirmedDate,
                    'followed_up' => $followedUp,
                ];
            }

            return $out;
        });
    }
}
