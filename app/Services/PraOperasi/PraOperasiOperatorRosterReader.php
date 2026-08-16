<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Roster karyawan berjabatan "Operator" dari bcsid.m_karyawan ⋈ bcsid.m_jabatan
 * (hse_automation/Postgres).
 *
 * SENGAJA hanya jabatan STRUKTURAL (id_jabatan_tipe ⋈ m_jabatan_tipe.nama =
 * 'STRUKTURAL') yang mengandung kata "Operator" — bukan jabatan FUNGSIONAL.
 * Dicek langsung ke data: hanya ADA SATU jabatan "Operator" bertipe FUNGSIONAL
 * (nama generik "Operator", id 28536) sedangkan ratusan jabatan operator
 * sungguhan (mis. "Operator Excavator", "Operator Bulldozer 155", "Plant
 * Operator", dst) semuanya STRUKTURAL. Jabatan fungsional generik semacam itu
 * bukan role lapangan yang benar-benar mengoperasikan unit, jadi harus
 * dikecualikan dari roster Pra Operasi.
 *
 * CATATAN PERFORMA (root cause 504 Gateway Timeout):
 * bcsid.m_karyawan berukuran ~6 GB untuk ±68 ribu baris dan HANYA punya index
 * primary key (id) — tidak ada index di kode_sid atau id_jabatan. Query apa pun
 * yang memfilter/join tabel ini akan selalu full sequential scan. Query di
 * bawah sengaja ditulis sebagai SATU scan (`id_jabatan = ANY(array)`, bukan
 * JOIN ke m_jabatan langsung) untuk menghindari nested loop yang bisa
 * men-scan tabel ini puluhan-ratusan kali (satu kali per id jabatan yang cocok).
 *
 * Mitigasi jangka panjang yang sebenarnya ada di sisi database, bukan aplikasi:
 *   CREATE INDEX CONCURRENTLY idx_m_karyawan_id_jabatan ON bcsid.m_karyawan (id_jabatan);
 *   CREATE INDEX CONCURRENTLY idx_m_karyawan_kode_sid ON bcsid.m_karyawan (upper(btrim(kode_sid)));
 *   VACUUM (FULL, ANALYZE) bcsid.m_karyawan; -- tabel ini bloat parah (~87KB/baris rata-rata)
 * Sampai index itu ada, hasil query ini di-cache lama (bukan per-request) supaya
 * scan berat ini tidak berulang setiap kali user ganti filter tanggal.
 */
final class PraOperasiOperatorRosterReader
{
    private const CACHE_TTL_SECONDS = 6 * 3600;

    private const CACHE_KEY = 'pra_operasi:operator_roster:v3';

    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $connectionSource,
    ) {}

    public function isUp(): bool
    {
        return $this->connectionSource->isUp();
    }

    /**
     * @return list<array{kode_sid: string, nama: string, jabatan: string, perusahaan: string}>
     */
    public function operatorRoster(): array
    {
        if (! $this->isUp()) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => $this->fetchRoster());
    }

    /**
     * Paksa refresh cache (dipanggil dari Artisan command terjadwal).
     *
     * @return list<array{kode_sid: string, nama: string, jabatan: string, perusahaan: string}>
     */
    public function refresh(): array
    {
        $roster = $this->fetchRoster();
        Cache::put(self::CACHE_KEY, $roster, self::CACHE_TTL_SECONDS);

        return $roster;
    }

    /**
     * @return list<array{kode_sid: string, nama: string, jabatan: string, perusahaan: string}>
     */
    private function fetchRoster(): array
    {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return [];
        }

        try {
            $jabatanRows = DB::connection($connection)->select(
                "SELECT j.id, j.nama
                 FROM bcsid.m_jabatan j
                 JOIN bcsid.m_jabatan_tipe jt ON jt.id = j.id_jabatan_tipe
                 WHERE UPPER(j.nama) LIKE '%OPERATOR%'
                   AND UPPER(j.nama) <> 'VISITOR'
                   AND UPPER(jt.nama) = 'STRUKTURAL'"
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        if ($jabatanRows === []) {
            return [];
        }

        $jabatanNameById = [];
        $jabatanIds = [];
        foreach ($jabatanRows as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id <= 0) {
                continue;
            }
            $jabatanIds[] = $id;
            $jabatanNameById[$id] = trim((string) ($row->nama ?? ''));
        }
        if ($jabatanIds === []) {
            return [];
        }

        // Satu scan (bukan join), plus batasi ke karyawan yang belum dinonaktifkan
        // supaya roster tidak membengkak dengan riwayat karyawan lama.
        $sql = '
            SELECT k.kode_sid, k.nama, k.id_jabatan, k.id_perusahaan
            FROM bcsid.m_karyawan k
            WHERE k.id_jabatan = ANY(?)
              AND k.kode_sid IS NOT NULL
              AND TRIM(k.kode_sid) <> \'\'
              AND k.tanggal_penonaktifkan IS NULL
        ';

        try {
            $pgArray = '{'.implode(',', $jabatanIds).'}';
            $rows = DB::connection($connection)->select($sql, [$pgArray]);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $perusahaanIds = [];
        $raw = [];
        foreach ($rows as $row) {
            $sid = trim((string) ($row->kode_sid ?? ''));
            if ($sid === '') {
                continue;
            }
            $upper = mb_strtoupper($sid);
            if (isset($raw[$upper])) {
                continue;
            }
            $idPerusahaan = $row->id_perusahaan !== null ? (int) $row->id_perusahaan : null;
            if ($idPerusahaan !== null) {
                $perusahaanIds[$idPerusahaan] = true;
            }
            $raw[$upper] = [
                'kode_sid' => $sid,
                'nama' => trim((string) ($row->nama ?? '')),
                'jabatan' => $jabatanNameById[(int) ($row->id_jabatan ?? 0)] ?? '',
                'id_perusahaan' => $idPerusahaan,
            ];
        }

        $perusahaanNameById = $this->loadPerusahaanNames($connection, array_keys($perusahaanIds));

        $roster = [];
        foreach ($raw as $entry) {
            $roster[] = [
                'kode_sid' => $entry['kode_sid'],
                'nama' => $entry['nama'],
                'jabatan' => $entry['jabatan'],
                'perusahaan' => $entry['id_perusahaan'] !== null ? ($perusahaanNameById[$entry['id_perusahaan']] ?? '') : '',
            ];
        }

        return $roster;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function loadPerusahaanNames(string $connection, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        try {
            $pgArray = '{'.implode(',', $ids).'}';
            $rows = DB::connection($connection)->select(
                'SELECT id, nama FROM bcsid.m_perusahaan WHERE id = ANY(?)',
                [$pgArray]
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->id] = trim((string) ($row->nama ?? ''));
        }

        return $map;
    }
}
