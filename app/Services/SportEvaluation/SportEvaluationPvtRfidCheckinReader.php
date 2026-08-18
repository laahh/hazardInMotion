<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pembaca read-only check-IN lolos dari bcsid.mv_checkinout_rfid.
 * Koneksi: LANGSUNG ke RDS (PG_HOST:PG_PORT) dengan kredensial
 * PG_SSH_DATABASE / PG_SSH_USER / PG_SSH_PASSWORD — tunnel SSH/jump host
 * (pgsql_ssh) sengaja TIDAK dipakai lagi atas permintaan eksplisit (tunnel
 * di server tidak selalu aktif, langsung akses RDS lebih andal).
 */
final class SportEvaluationPvtRfidCheckinReader
{
    public const TABLE = 'bcsid.mv_checkinout_rfid';

    public const CONNECTION_TUNNEL = 'pgsql_ssh';

    public const CONNECTION_DIRECT = 'pgsql_direct';

    private const SID_CHUNK = 800;

    private const UP_CACHE_KEY = 'evaluasi_well:pvt_rfid_connection_v1';

    private const UP_CACHE_TTL = 20;

    private ?string $activeConnection = null;

    /**
     * @var list<string>
     */
    private const CHECKIN_TYPES = [
        'IN',
        'CHECKIN',
        'CHECK-IN',
        'CHECK_IN',
        'CHECK IN',
        'MASUK',
    ];

    /**
     * @var list<string>
     */
    private const CHECKOUT_TYPES = [
        'OUT',
        'CHECKOUT',
        'CHECK-OUT',
        'CHECK_OUT',
        'CHECK OUT',
        'KELUAR',
    ];

    /**
     * @var list<string>
     */
    private const PASSED_STATUSES = [
        'PASSED',
        'PASS',
        'LOLOS',
        'YA',
        'YES',
        '1',
        'TRUE',
        'T',
        'Y',
    ];

    public function isUp(): bool
    {
        return $this->connectionName() !== null;
    }

    /**
     * Nama koneksi hidup: LANGSUNG ke RDS saja (tunnel/jump host sengaja
     * dilewati — lihat catatan kelas di atas).
     */
    public function connectionName(): ?string
    {
        if ($this->activeConnection !== null) {
            return $this->activeConnection !== '' ? $this->activeConnection : null;
        }

        try {
            $cached = Cache::remember(self::UP_CACHE_KEY, self::UP_CACHE_TTL, function (): string {
                return $this->ping(self::CONNECTION_DIRECT) ? self::CONNECTION_DIRECT : '';
            });
        } catch (Throwable $e) {
            report($e);
            $cached = $this->ping(self::CONNECTION_DIRECT) ? self::CONNECTION_DIRECT : '';
        }

        $this->activeConnection = is_string($cached) ? $cached : '';

        return $this->activeConnection !== '' ? $this->activeConnection : null;
    }

    private const SOCKET_TIMEOUT_SECONDS = 3;

    private function ping(string $connection): bool
    {
        // Cek TCP-level dulu (fsockopen, timeout pendek) SEBELUM mencoba
        // koneksi PDO penuh. PDO::ATTR_TIMEOUT (dipakai di config/database.php)
        // TIDAK selalu ditegakkan andal oleh driver pgsql untuk fase awal
        // koneksi di semua kombinasi OS/versi PHP — kalau host RDS memang
        // tidak terjangkau (mis. security group menahan paket tanpa membalas,
        // bukan menolak), percobaan PDO bisa menggantung puluhan detik sampai
        // akhirnya memicu 504 Gateway Timeout. fsockopen() menghormati
        // timeout-nya secara konsisten di level socket OS, jadi kalau host
        // tidak terjangkau, ini gagal cepat dalam hitungan detik.
        if (! $this->isHostReachable($connection)) {
            return false;
        }

        try {
            DB::connection($connection)->select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function isHostReachable(string $connection): bool
    {
        $host = config("database.connections.{$connection}.host");
        $port = config("database.connections.{$connection}.port");

        if (! is_string($host) || $host === '' || ! is_numeric($port) || (int) $port <= 0) {
            // Konfigurasi tidak lengkap/tidak biasa — biarkan PDO yang memutuskan,
            // bukan menganggap host tidak terjangkau begitu saja.
            return true;
        }

        $socket = @fsockopen($host, (int) $port, $errno, $errstr, self::SOCKET_TIMEOUT_SECONDS);
        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /**
     * Check-IN lolos pertama per SID pada tanggal (Asia/Makassar).
     *
     * @param  list<string>  $sids
     * @return array<string, array{
     *     kode_sid: string,
     *     checked_in_at: string,
     *     nama_karyawan: string,
     *     perusahaan: string,
     *     gate: string,
     *     jenis_checkinout: string,
     *     status_lolos: string
     * }>
     */
    public function firstPassedCheckinsForSids(string $date, array $sids): array
    {
        if (! $this->isUp() || $sids === []) {
            return [];
        }

        $normalized = [];
        foreach ($sids as $sid) {
            $trimmed = trim((string) $sid);
            if ($trimmed === '') {
                continue;
            }
            $normalized[mb_strtoupper($trimmed)] = $trimmed;
        }
        if ($normalized === []) {
            return [];
        }

        $day = Carbon::parse($date, config('app.timezone'))->startOfDay();
        $start = $day->format('Y-m-d H:i:s');
        $end = $day->copy()->addDay()->format('Y-m-d H:i:s');

        $merged = [];
        foreach (array_chunk(array_keys($normalized), self::SID_CHUNK) as $chunk) {
            foreach ($this->queryChunk($start, $end, $chunk, true) as $row) {
                $upper = mb_strtoupper($row['kode_sid']);
                if (! isset($merged[$upper])) {
                    $merged[$upper] = $row;
                }
            }
        }

        return $merged;
    }

    /**
     * Check-IN lolos pertama per SID per hari dalam rentang tanggal (inklusif).
     *
     * @param  list<string>  $sids
     * @return array<string, array<string, array{
     *     kode_sid: string,
     *     checked_in_at: string,
     *     nama_karyawan: string,
     *     perusahaan: string,
     *     gate: string,
     *     jenis_checkinout: string,
     *     status_lolos: string
     * }>>
     */
    public function firstPassedCheckinsByDayForSids(string $fromDate, string $toDateInclusive, array $sids): array
    {
        if (! $this->isUp() || $sids === []) {
            return [];
        }

        $normalized = [];
        foreach ($sids as $sid) {
            $trimmed = trim((string) $sid);
            if ($trimmed === '') {
                continue;
            }
            $normalized[mb_strtoupper($trimmed)] = $trimmed;
        }
        if ($normalized === []) {
            return [];
        }

        $tz = (string) config('app.timezone');
        $start = Carbon::parse($fromDate, $tz)->startOfDay()->format('Y-m-d H:i:s');
        $end = Carbon::parse($toDateInclusive, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');

        $byDay = [];
        foreach (array_chunk(array_keys($normalized), self::SID_CHUNK) as $chunk) {
            foreach ($this->queryChunk($start, $end, $chunk, false) as $row) {
                $day = substr($row['checked_in_at'], 0, 10);
                if ($day === '') {
                    continue;
                }
                $upper = mb_strtoupper($row['kode_sid']);
                if (! isset($byDay[$day][$upper])) {
                    $byDay[$day][$upper] = $row;
                }
            }
        }

        return $byDay;
    }

    /**
     * Check-OUT lolos terakhir per SID pada tanggal (Asia/Makassar). Sama persis
     * dengan firstPassedCheckinsForSids, hanya tipe event & arah urutan (ambil
     * yang TERAKHIR, bukan pertama — checkout yang relevan adalah saat pulang).
     *
     * @param  list<string>  $sids
     * @return array<string, array{
     *     kode_sid: string, checked_in_at: string, nama_karyawan: string,
     *     perusahaan: string, gate: string, jenis_checkinout: string, status_lolos: string
     * }>
     */
    public function lastPassedCheckoutsForSids(string $date, array $sids): array
    {
        if (! $this->isUp() || $sids === []) {
            return [];
        }

        $normalized = [];
        foreach ($sids as $sid) {
            $trimmed = trim((string) $sid);
            if ($trimmed === '') {
                continue;
            }
            $normalized[mb_strtoupper($trimmed)] = $trimmed;
        }
        if ($normalized === []) {
            return [];
        }

        $day = Carbon::parse($date, config('app.timezone'))->startOfDay();
        $start = $day->format('Y-m-d H:i:s');
        $end = $day->copy()->addDay()->format('Y-m-d H:i:s');

        $merged = [];
        foreach (array_chunk(array_keys($normalized), self::SID_CHUNK) as $chunk) {
            foreach ($this->queryChunk($start, $end, $chunk, true, self::CHECKOUT_TYPES, 'DESC') as $row) {
                $upper = mb_strtoupper($row['kode_sid']);
                if (! isset($merged[$upper])) {
                    $merged[$upper] = $row;
                }
            }
        }

        return $merged;
    }

    /**
     * @param  list<string>  $upperSids
     * @param  list<string>  $types  daftar sinonim jenis_checkinout (IN atau OUT)
     * @return list<array{
     *     kode_sid: string,
     *     checked_in_at: string,
     *     nama_karyawan: string,
     *     perusahaan: string,
     *     gate: string,
     *     jenis_checkinout: string,
     *     status_lolos: string
     * }>
     */
    private function queryChunk(string $start, string $end, array $upperSids, bool $firstPerSidOnly, ?array $types = null, string $direction = 'ASC'): array
    {
        $types ??= self::CHECKIN_TYPES;
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $sidPlaceholders = implode(',', array_fill(0, count($upperSids), '?'));
        $typePlaceholders = implode(',', array_fill(0, count($types), '?'));
        $statusPlaceholders = implode(',', array_fill(0, count(self::PASSED_STATUSES), '?'));
        $compactTypes = array_map(
            static fn (string $t): string => str_replace([' ', '-'], '', strtoupper($t)),
            $types
        );
        $compactPlaceholders = implode(',', array_fill(0, count($compactTypes), '?'));

        $selectHead = $firstPerSidOnly
            ? 'SELECT DISTINCT ON (UPPER(TRIM(kode_sid)))'
            : 'SELECT';
        $orderBy = $firstPerSidOnly
            ? 'ORDER BY UPPER(TRIM(kode_sid)), tanggal_checkinout '.$direction
            : 'ORDER BY tanggal_checkinout '.$direction;

        $sql = '
            '.$selectHead.'
                TRIM(kode_sid) AS kode_sid,
                tanggal_checkinout,
                TRIM(COALESCE(nama_karyawan::text, \'\')) AS nama_karyawan,
                TRIM(COALESCE(perusahaan::text, \'\')) AS perusahaan,
                TRIM(COALESCE(gate::text, \'\')) AS gate,
                TRIM(COALESCE(jenis_checkinout::text, \'\')) AS jenis_checkinout,
                TRIM(COALESCE(status_lolos::text, \'\')) AS status_lolos
            FROM '.self::TABLE.'
            WHERE tanggal_checkinout >= ?
              AND tanggal_checkinout < ?
              AND kode_sid IS NOT NULL
              AND TRIM(kode_sid) <> \'\'
              AND UPPER(TRIM(kode_sid)) IN ('.$sidPlaceholders.')
              AND (
                    UPPER(TRIM(jenis_checkinout::text)) IN ('.$typePlaceholders.')
                 OR REPLACE(REPLACE(UPPER(TRIM(jenis_checkinout::text)), \' \', \'\'), \'-\', \'\') IN ('.$compactPlaceholders.')
              )
              AND REPLACE(REPLACE(UPPER(TRIM(status_lolos::text)), \' \', \'\'), \'-\', \'\') IN ('.$statusPlaceholders.')
            '.$orderBy.'
        ';

        $bindings = array_merge(
            [$start, $end],
            $upperSids,
            $types,
            $compactTypes,
            self::PASSED_STATUSES,
        );

        try {
            $connection = $this->connectionName();
            if ($connection === null) {
                return [];
            }
            $rows = DB::connection($connection)->select($sql, $bindings);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $list = [];
        foreach ($rows as $row) {
            $mapped = $this->mapCheckinRow($row);
            if ($mapped !== null) {
                $list[] = $mapped;
            }
        }

        return $list;
    }

    /**
     * @return array{
     *     kode_sid: string,
     *     checked_in_at: string,
     *     nama_karyawan: string,
     *     perusahaan: string,
     *     gate: string,
     *     jenis_checkinout: string,
     *     status_lolos: string
     * }|null
     */
    private function mapCheckinRow(object $row): ?array
    {
        $sid = trim((string) ($row->kode_sid ?? ''));
        if ($sid === '') {
            return null;
        }

        $checkedInAt = $row->tanggal_checkinout ?? null;
        if ($checkedInAt instanceof \DateTimeInterface) {
            $checkedInAt = Carbon::instance($checkedInAt)
                ->timezone((string) config('app.timezone'))
                ->format('Y-m-d H:i:s');
        } else {
            $checkedInAt = trim((string) $checkedInAt);
            if ($checkedInAt !== '') {
                try {
                    $checkedInAt = Carbon::parse($checkedInAt, config('app.timezone'))
                        ->timezone((string) config('app.timezone'))
                        ->format('Y-m-d H:i:s');
                } catch (Throwable) {
                    // biarkan string mentah
                }
            }
        }
        if ($checkedInAt === '') {
            return null;
        }

        return [
            'kode_sid' => $sid,
            'checked_in_at' => $checkedInAt,
            'nama_karyawan' => trim((string) ($row->nama_karyawan ?? '')),
            'perusahaan' => trim((string) ($row->perusahaan ?? '')),
            'gate' => trim((string) ($row->gate ?? '')),
            'jenis_checkinout' => trim((string) ($row->jenis_checkinout ?? '')),
            'status_lolos' => trim((string) ($row->status_lolos ?? '')),
        ];
    }
}
