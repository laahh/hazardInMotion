<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Status "Fatigue Test" (form Fit to Work pra-shift) per SID pada tanggal tertentu,
 * dibaca dari bcsid.clean_data_fatigue_check (Postgres, hse_automation).
 *
 * Tier Hijau/Kuning/Merah dihitung dari kesiapan_bekerja_fisik_dan_mental (skala 1-10):
 *   8-10 = Hijau, 5-7 = Kuning, 1-4 = Merah.
 * Tabel ini hanya menyimpan pemeriksaan yang SUDAH selesai (tidak ada baris
 * tanggal_pemeriksaan kosong) — status "belum" disimpulkan dari TIDAK ADANYA baris,
 * bukan dari nilai kolom.
 */
final class PraOperasiFatigueCheckReader
{
    private const SID_CHUNK = 500;

    private const TIER_HIJAU_MIN = 8;

    private const TIER_KUNING_MIN = 5;

    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $connectionSource,
    ) {}

    public function isUp(): bool
    {
        return $this->connectionSource->isUp();
    }

    public static function tierFromScore(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }
        if ($score >= self::TIER_HIJAU_MIN) {
            return 'hijau';
        }
        if ($score >= self::TIER_KUNING_MIN) {
            return 'kuning';
        }

        return 'merah';
    }

    /**
     * @param  list<string>  $sids
     * @return array<string, array{
     *     kesiapan_score: int|null, tier: string|null, hasil_sobriety_test: string,
     *     kondisi_karyawan: string, tindakan_unfit: string, jumlah_jam_tidur: string,
     *     checked_at: string
     * }>  keyed by UPPER(kode_sid)
     */
    public function statusForSidsOnDate(array $sids, string $date): array
    {
        if (! $this->isUp() || $sids === []) {
            return [];
        }

        $normalized = [];
        foreach ($sids as $sid) {
            $trimmed = trim((string) $sid);
            if ($trimmed !== '') {
                $normalized[mb_strtoupper($trimmed)] = true;
            }
        }
        $upperSids = array_keys($normalized);
        if ($upperSids === []) {
            return [];
        }

        $cacheKey = 'pra_operasi:fatigue_check:v1:'.$date.':'.md5(implode(',', $upperSids));

        return Cache::remember($cacheKey, 30, function () use ($upperSids, $date): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $merged = [];
            foreach (array_chunk($upperSids, self::SID_CHUNK) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $sql = '
                    SELECT
                        TRIM(sid) AS sid,
                        kesiapan_bekerja_fisik_dan_mental,
                        hasil_sobriety_test,
                        kondisi_karyawan,
                        tindakan_unfit,
                        jumlah_jam_tidur,
                        tanggal_pemeriksaan,
                        jam_pemeriksaan,
                        uploaded_at
                    FROM bcsid.clean_data_fatigue_check
                    WHERE tanggal_pemeriksaan = ?
                      AND sid IS NOT NULL
                      AND UPPER(TRIM(sid)) IN ('.$placeholders.')
                    ORDER BY uploaded_at ASC
                ';

                try {
                    $rows = DB::connection($connection)->select($sql, array_merge([$date], $chunk));
                } catch (Throwable $e) {
                    report($e);
                    continue;
                }

                foreach ($rows as $row) {
                    $sid = mb_strtoupper(trim((string) ($row->sid ?? '')));
                    if ($sid === '') {
                        continue;
                    }

                    $scoreRaw = trim((string) ($row->kesiapan_bekerja_fisik_dan_mental ?? ''));
                    $score = ctype_digit($scoreRaw) ? (int) $scoreRaw : null;

                    // Baris terakhir (uploaded_at ASC) menang jika ada >1 submission hari itu.
                    $merged[$sid] = [
                        'kesiapan_score' => $score,
                        'tier' => self::tierFromScore($score),
                        'hasil_sobriety_test' => trim((string) ($row->hasil_sobriety_test ?? '')),
                        'kondisi_karyawan' => trim((string) ($row->kondisi_karyawan ?? '')),
                        'tindakan_unfit' => trim((string) ($row->tindakan_unfit ?? '')),
                        'jumlah_jam_tidur' => trim((string) ($row->jumlah_jam_tidur ?? '')),
                        'checked_at' => trim((string) ($row->tanggal_pemeriksaan ?? '')).' '.trim((string) ($row->jam_pemeriksaan ?? '')),
                    ];
                }
            }

            return $merged;
        });
    }
}
