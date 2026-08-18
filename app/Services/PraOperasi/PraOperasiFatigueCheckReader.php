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

    /**
     * hari_ke >= ini dianggap "roster tinggi" — berbasis distribusi riil 15 Agu 2026:
     * populasi mayoritas (~95%) ada di hari_ke 1-6, turun drastis mulai hari_ke 7
     * (414 orang di hari-6 → 53 orang di hari-7).
     */
    public const ROSTER_HIGH_THRESHOLD = 7;

    /**
     * Mapping kode shift → label, dikonfirmasi EMPIRIS dari jam pemeriksaan riil
     * (bukan asumsi): shift 1 puncak pemeriksaan jam 05:00-07:00 (sebelum mulai
     * shift siang ~06:00), shift 2 puncak jam 17:00-18:00 (sebelum mulai shift
     * malam ~18:00) — pola rotasi 12 jam standar tambang.
     */
    private const SHIFT_LABELS = ['1' => 'Siang', '2' => 'Malam'];

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

    public static function shiftLabel(string $shiftCode): ?string
    {
        return self::SHIFT_LABELS[$shiftCode] ?? null;
    }

    public static function isRosterHigh(?int $hariKe): bool
    {
        return $hariKe !== null && $hariKe >= self::ROSTER_HIGH_THRESHOLD;
    }

    /**
     * @param  list<string>  $sids
     * @return array<string, array{
     *     kesiapan_score: int|null, tier: string|null, hasil_sobriety_test: string,
     *     kondisi_karyawan: string, tindakan_unfit: string, jumlah_jam_tidur: string,
     *     checked_at: string, hari_ke: int|null, shift: string|null
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

        $cacheKey = 'pra_operasi:fatigue_check:v2:'.$date.':'.md5(implode(',', $upperSids));

        return Cache::remember($cacheKey, 30, function () use ($upperSids, $date): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            // SENGAJA satu query TANPA di-chunk: bcsid.clean_data_fatigue_check
            // adalah foreign table (FDW) yang TIDAK bisa push-down filter apa pun
            // (UPPER(TRIM(sid)) IN (...), regex, bahkan tanggal_pemeriksaan = ?) —
            // dicek langsung lewat EXPLAIN, cost-nya tetap ~65-90rb terlepas dari
            // selektivitas filter (1 SID maupun 3000 SID sama saja, karena FDW-nya
            // scan penuh sisi remote). Artinya chunking di sini BUKAN mengurangi
            // beban per query, tapi malah MENGALIKAN beban itu sebanyak jumlah
            // chunk — inilah penyebab 504. Satu query untuk SEMUA SID sekaligus
            // (placeholder ratusan/ribuan tidak masalah untuk Postgres) memangkas
            // beban total sebanyak jumlah chunk yang sebelumnya dijalankan.
            $placeholders = implode(',', array_fill(0, count($upperSids), '?'));
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
                    uploaded_at,
                    hari_ke,
                    shift
                FROM bcsid.clean_data_fatigue_check
                WHERE tanggal_pemeriksaan = ?
                  AND sid IS NOT NULL
                  AND UPPER(TRIM(sid)) IN ('.$placeholders.')
                ORDER BY uploaded_at ASC
            ';

            try {
                $rows = DB::connection($connection)->select($sql, array_merge([$date], $upperSids));
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            $merged = [];
            foreach ($rows as $row) {
                $sid = mb_strtoupper(trim((string) ($row->sid ?? '')));
                if ($sid === '') {
                    continue;
                }

                $scoreRaw = trim((string) ($row->kesiapan_bekerja_fisik_dan_mental ?? ''));
                $score = ctype_digit($scoreRaw) ? (int) $scoreRaw : null;
                $hariKeRaw = trim((string) ($row->hari_ke ?? ''));

                // Baris terakhir (uploaded_at ASC) menang jika ada >1 submission hari itu.
                $merged[$sid] = [
                    'kesiapan_score' => $score,
                    'tier' => self::tierFromScore($score),
                    'hasil_sobriety_test' => trim((string) ($row->hasil_sobriety_test ?? '')),
                    'kondisi_karyawan' => trim((string) ($row->kondisi_karyawan ?? '')),
                    'tindakan_unfit' => trim((string) ($row->tindakan_unfit ?? '')),
                    'jumlah_jam_tidur' => trim((string) ($row->jumlah_jam_tidur ?? '')),
                    'checked_at' => trim((string) ($row->tanggal_pemeriksaan ?? '')).' '.trim((string) ($row->jam_pemeriksaan ?? '')),
                    'hari_ke' => ctype_digit($hariKeRaw) ? (int) $hariKeRaw : null,
                    'shift' => self::shiftLabel(trim((string) ($row->shift ?? ''))),
                ];
            }

            return $merged;
        });
    }

    /**
     * Riwayat skor kesiapan (kesiapan_bekerja_fisik_dan_mental) per SID dalam
     * window (default 30 hari, TIDAK termasuk tanggal acuan) — dipakai untuk
     * menghitung baseline personal (mean & std) di skor risiko komposit.
     *
     * @param  list<string>  $sids
     * @return array<string, list<array{date:string, score:int}>>  keyed by UPPER(kode_sid), urut tanggal ASC
     */
    public function scoreHistoryForSids(array $sids, string $untilDate, int $days = 30): array
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

        $cacheKey = 'pra_operasi:fatigue_history:v1:'.$untilDate.':'.$days.':'.md5(implode(',', $upperSids));

        return Cache::remember($cacheKey, 1800, function () use ($upperSids, $untilDate, $days): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $from = \Illuminate\Support\Carbon::parse($untilDate)->subDays($days)->toDateString();

            // Satu query TANPA chunk — lihat catatan di statusForSidsOnDate() soal
            // clean_data_fatigue_check (FDW) yang cost-nya flat ~65-90rb terlepas
            // dari jumlah SID yang difilter; chunking di sini justru mengalikan
            // beban itu, bukan menguranginya.
            $placeholders = implode(',', array_fill(0, count($upperSids), '?'));
            $sql = "
                SELECT UPPER(TRIM(sid)) AS sid, tanggal_pemeriksaan, kesiapan_bekerja_fisik_dan_mental
                FROM bcsid.clean_data_fatigue_check
                WHERE UPPER(TRIM(sid)) IN ({$placeholders})
                  AND tanggal_pemeriksaan >= ? AND tanggal_pemeriksaan <= ?
                  AND kesiapan_bekerja_fisik_dan_mental ~ '^[0-9]+$'
                ORDER BY tanggal_pemeriksaan ASC
            ";

            try {
                $rows = DB::connection($connection)->select($sql, array_merge($upperSids, [$from, $untilDate]));
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            $out = [];
            foreach ($rows as $row) {
                $sid = trim((string) ($row->sid ?? ''));
                if ($sid === '') {
                    continue;
                }
                $out[$sid][] = [
                    'date' => (string) $row->tanggal_pemeriksaan,
                    'score' => (int) $row->kesiapan_bekerja_fisik_dan_mental,
                ];
            }

            return $out;
        });
    }

    /**
     * Riwayat detail LENGKAP Fit to Work (bukan cuma skor) untuk SATU SID,
     * default 7 hari terakhir termasuk tanggal acuan — dipakai di panel detail
     * operator supaya sobriety test/kondisi/jam tidur/tindakan unfit bisa
     * ditelusuri seminggu ke belakang, bukan cuma hari ini.
     *
     * @return list<array{
     *     date:string, kesiapan_score:int|null, tier:string|null, hasil_sobriety_test:string,
     *     kondisi_karyawan:string, tindakan_unfit:string, jumlah_jam_tidur:string, checked_at:string
     * }>  urut tanggal DESC (terbaru dulu)
     */
    public function detailHistoryForSid(string $kodeSid, string $untilDate, int $days = 7): array
    {
        if (! $this->isUp()) {
            return [];
        }

        $upper = mb_strtoupper(trim($kodeSid));
        if ($upper === '') {
            return [];
        }

        $cacheKey = 'pra_operasi:fatigue_detail_history:v1:'.$untilDate.':'.$days.':'.$upper;

        return Cache::remember($cacheKey, 300, function () use ($upper, $untilDate, $days): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $from = \Illuminate\Support\Carbon::parse($untilDate)->subDays($days - 1)->toDateString();

            $sql = '
                SELECT
                    tanggal_pemeriksaan, jam_pemeriksaan, kesiapan_bekerja_fisik_dan_mental,
                    hasil_sobriety_test, kondisi_karyawan, tindakan_unfit, jumlah_jam_tidur
                FROM bcsid.clean_data_fatigue_check
                WHERE UPPER(TRIM(sid)) = ?
                  AND tanggal_pemeriksaan >= ? AND tanggal_pemeriksaan <= ?
                ORDER BY tanggal_pemeriksaan DESC, uploaded_at ASC
            ';

            try {
                $rows = DB::connection($connection)->select($sql, [$upper, $from, $untilDate]);
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            // Kalau ada >1 submission di hari yang sama, baris terakhir (uploaded_at ASC) menang.
            $byDate = [];
            foreach ($rows as $row) {
                $date = (string) ($row->tanggal_pemeriksaan ?? '');
                if ($date === '') {
                    continue;
                }
                $scoreRaw = trim((string) ($row->kesiapan_bekerja_fisik_dan_mental ?? ''));
                $score = ctype_digit($scoreRaw) ? (int) $scoreRaw : null;

                $byDate[$date] = [
                    'date' => $date,
                    'kesiapan_score' => $score,
                    'tier' => self::tierFromScore($score),
                    'hasil_sobriety_test' => trim((string) ($row->hasil_sobriety_test ?? '')),
                    'kondisi_karyawan' => trim((string) ($row->kondisi_karyawan ?? '')),
                    'tindakan_unfit' => trim((string) ($row->tindakan_unfit ?? '')),
                    'jumlah_jam_tidur' => trim((string) ($row->jumlah_jam_tidur ?? '')),
                    'checked_at' => $date.' '.trim((string) ($row->jam_pemeriksaan ?? '')),
                ];
            }

            $out = array_values($byDate);
            usort($out, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

            return $out;
        });
    }
}
