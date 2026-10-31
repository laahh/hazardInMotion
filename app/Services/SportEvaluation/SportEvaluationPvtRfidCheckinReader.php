<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pembaca read-only check-IN lolos dari bcsid.aaj_vw_checkinout_rfid.
 * View biasa (bukan materialized view) sehingga datanya real-time — dipakai
 * menggantikan bcsid.mv_checkinout_rfid yang ternyata refresh-nya bisa
 * tertinggal berjam-jam (lihat riwayat: HUD "Check-in RFID" di /isc/maps
 * pernah macet di angka lama karena materialized view itu tidak ter-refresh).
 * Koneksi: LANGSUNG ke RDS (PG_HOST:PG_PORT) dengan kredensial
 * PG_SSH_DATABASE / PG_SSH_USER / PG_SSH_PASSWORD — tunnel SSH/jump host
 * (pgsql_ssh) sengaja TIDAK dipakai lagi atas permintaan eksplisit (tunnel
 * di server tidak selalu aktif, langsung akses RDS lebih andal).
 */
final class SportEvaluationPvtRfidCheckinReader
{
    public const TABLE = 'bcsid.aaj_vw_checkinout_rfid';

    public const CONNECTION_TUNNEL = 'pgsql_ssh';

    public const CONNECTION_DIRECT = 'pgsql_direct';

    private const SID_CHUNK = 800;

    private const UP_CACHE_KEY = 'evaluasi_well:pvt_rfid_connection_v1';

    private const UP_CACHE_TTL = 20;

    private ?string $activeConnection = null;

    /**
     * Snapshot RFID per tanggal (semua SID) di cache. Query view dengan daftar
     * SID (UPPER(TRIM(kode_sid)) IN, ±15 ribu operator = 20 potongan) butuh
     * ±100 dtk karena view men-join m_karyawan (±6 GB) di tiap potongan;
     * satu query sehari penuh tanpa daftar SID ±6 dtk. Penyaringan SID di PHP.
     * Diisi juga di latar oleh evaluasi-well:rfid-hangatkan.
     */
    private const HARI_KUNCI = 'evaluasi_well:rfid_hari:v1:';

    private const HARI_TTL_HARI_INI = 300;

    private const HARI_TTL_KEMARIN = 1800;

    private const HARI_TTL_LAMPAU = 21600;

    private const HARI_KUNCI_TUNGGU = 90;

    /** Query terakhir gagal (error / tidak terjangkau): hasil kosongnya jangan di-cache. */
    private bool $queryGagal = false;

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
        // Tanpa cek isUp() di sini: snapshot yang sudah ter-cache tetap dipakai
        // saat OLAP sesaat tak terjangkau; snapshotHari() sendiri yang mengecek
        // koneksi sebelum query.
        if ($sids === []) {
            return [];
        }

        return array_intersect_key($this->snapshotHari($date), self::kunciSid($sids));
    }

    /**
     * Check-IN lolos pertama per SID pada tanggal, tanpa filter daftar SID.
     *
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
    public function firstPassedCheckinsForDate(string $date): array
    {
        if (! $this->isUp()) {
            return [];
        }

        $day = Carbon::parse($date, config('app.timezone'))->startOfDay();
        $start = $day->format('Y-m-d H:i:s');
        $end = $day->copy()->addDay()->format('Y-m-d H:i:s');

        $merged = [];
        foreach ($this->queryChunk($start, $end, [], true) as $row) {
            $upper = mb_strtoupper($row['kode_sid']);
            if (! isset($merged[$upper])) {
                $merged[$upper] = $row;
            }
        }

        return $merged;
    }

    /**
     * Check-OUT lolos terakhir per SID pada tanggal, tanpa filter daftar SID.
     *
     * @return array<string, array{
     *     kode_sid: string, checked_in_at: string, nama_karyawan: string,
     *     perusahaan: string, gate: string, jenis_checkinout: string, status_lolos: string
     * }>
     */
    public function lastPassedCheckoutsForDate(string $date): array
    {
        if (! $this->isUp()) {
            return [];
        }

        $day = Carbon::parse($date, config('app.timezone'))->startOfDay();
        $start = $day->format('Y-m-d H:i:s');
        $end = $day->copy()->addDay()->format('Y-m-d H:i:s');

        $merged = [];
        foreach ($this->queryChunk($start, $end, [], true, self::CHECKOUT_TYPES, 'DESC') as $row) {
            $upper = mb_strtoupper($row['kode_sid']);
            if (! isset($merged[$upper])) {
                $merged[$upper] = $row;
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
        // Tanpa cek isUp() di sini: snapshot yang sudah ter-cache tetap dipakai
        // saat OLAP sesaat tak terjangkau; snapshotHari() sendiri yang mengecek
        // koneksi sebelum query.
        if ($sids === []) {
            return [];
        }

        $kunci = self::kunciSid($sids);
        if ($kunci === []) {
            return [];
        }

        $tz = (string) config('app.timezone');
        $tanggal = [];
        $akhir = Carbon::parse($toDateInclusive, $tz)->startOfDay();
        for ($hari = Carbon::parse($fromDate, $tz)->startOfDay(); $hari->lte($akhir); $hari->addDay()) {
            $tanggal[] = $hari->toDateString();
        }

        // Sedikit SID + banyak tanggal belum ter-cache (mis. bukti 1 SID 120
        // hari): satu query rentang langsung jauh lebih murah daripada mengisi
        // snapshot tiap tanggal (±6 dtk per tanggal).
        $belum = array_filter($tanggal, static fn (string $t): bool => ! Cache::has(self::HARI_KUNCI.'in:'.$t));
        if (count($kunci) <= self::SID_CHUNK && count($belum) > 2) {
            return $this->byDayLangsung($fromDate, $toDateInclusive, array_keys($kunci));
        }

        // Banyak SID: per hari dari snapshot (tanggal lampau ter-cache
        // berjam-jam), bukan DISTINCT ON rentang × daftar SID (> 100 dtk).
        $byDay = [];
        foreach ($tanggal as $t) {
            $baris = array_intersect_key($this->snapshotHari($t), $kunci);
            if ($baris !== []) {
                $byDay[$t] = $baris;
            }
        }

        return $byDay;
    }

    /**
     * Check-IN lolos pertama per (hari, SID) langsung dari view untuk daftar
     * SID kecil — dedup di SQL (DISTINCT ON), bukan di PHP.
     *
     * @param  list<string>  $upperSids
     * @return array<string, array<string, array<string, string>>>
     */
    private function byDayLangsung(string $fromDate, string $toDateInclusive, array $upperSids): array
    {
        $tz = (string) config('app.timezone');
        $start = Carbon::parse($fromDate, $tz)->startOfDay()->format('Y-m-d H:i:s');
        $end = Carbon::parse($toDateInclusive, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');

        $byDay = [];
        foreach (array_chunk($upperSids, self::SID_CHUNK) as $chunk) {
            foreach ($this->queryChunk($start, $end, $chunk, false, firstPerDayPerSid: true) as $row) {
                $day = substr($row['checked_in_at'], 0, 10);
                $upper = mb_strtoupper($row['kode_sid']);
                if ($day !== '' && ! isset($byDay[$day][$upper])) {
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
        // Tanpa cek isUp() di sini: snapshot yang sudah ter-cache tetap dipakai
        // saat OLAP sesaat tak terjangkau; snapshotHari() sendiri yang mengecek
        // koneksi sebelum query.
        if ($sids === []) {
            return [];
        }

        return array_intersect_key($this->snapshotHari($date, true), self::kunciSid($sids));
    }

    /**
     * Semua check-IN lolos pertama (atau check-OUT lolos terakhir) per SID pada
     * satu tanggal, kunci = SID huruf besar — dari cache, diisi sekali per
     * tanggal dengan kunci (lock) supaya request bersamaan tidak ikut query.
     * Hasil query yang gagal tidak di-cache.
     *
     * @return array<string, array{
     *     kode_sid: string, checked_in_at: string, nama_karyawan: string,
     *     perusahaan: string, gate: string, jenis_checkinout: string, status_lolos: string
     * }>
     */
    public function snapshotHari(string $date, bool $checkout = false, bool $segarkan = false): array
    {
        $tanggal = Carbon::parse($date, (string) config('app.timezone'))->toDateString();
        $kunci = self::HARI_KUNCI.($checkout ? 'out:' : 'in:').$tanggal;
        if (! $segarkan && is_array($ada = Cache::get($kunci))) {
            return $ada;
        }

        try {
            return Cache::lock($kunci.':kunci', self::HARI_KUNCI_TUNGGU)->block(
                self::HARI_KUNCI_TUNGGU,
                function () use ($kunci, $tanggal, $checkout, $segarkan): array {
                    if (! $segarkan && is_array($ada = Cache::get($kunci))) {
                        return $ada;
                    }

                    $this->queryGagal = ! $this->isUp();
                    $data = $checkout ? $this->lastPassedCheckoutsForDate($tanggal) : $this->firstPassedCheckinsForDate($tanggal);
                    if (! $this->queryGagal) {
                        Cache::put($kunci, $data, $this->ttlHari($tanggal));
                    }

                    return $data;
                },
            );
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            // Proses lain masih mengisi tanggal ini: pakai yang ada, jangan ikut menumpuk query.
            $ada = Cache::get($kunci);

            return is_array($ada) ? $ada : [];
        }
    }

    private function ttlHari(string $tanggal): int
    {
        $hariIni = Carbon::now((string) config('app.timezone'))->toDateString();

        return match (true) {
            $tanggal >= $hariIni => self::HARI_TTL_HARI_INI,
            $tanggal === Carbon::parse($hariIni)->subDay()->toDateString() => self::HARI_TTL_KEMARIN,
            default => self::HARI_TTL_LAMPAU,
        };
    }

    /**
     * @param  list<string>  $sids
     * @return array<string, true> SID huruf besar
     */
    private static function kunciSid(array $sids): array
    {
        $out = [];
        foreach ($sids as $sid) {
            $sid = mb_strtoupper(trim((string) $sid));
            if ($sid !== '') {
                $out[$sid] = true;
            }
        }

        return $out;
    }

    /**
     * Tap lolos TERAKHIR per SID dalam rentang waktu [$start, $end) — check-in
     * atau check-out. Rentang boleh lintas hari (mis. sejak kemarin pagi,
     * supaya shift malam yang check-in kemarin sore tetap terbaca).
     *
     * @param  list<string>  $sids
     * @return array<string, array{
     *     kode_sid: string, checked_in_at: string, nama_karyawan: string,
     *     perusahaan: string, gate: string, jenis_checkinout: string, status_lolos: string
     * }>  kunci = SID huruf besar
     */
    public function lastPassedForSidsBetween(string $start, string $end, array $sids, bool $checkout = false): array
    {
        if (! $this->isUp() || $sids === []) {
            return [];
        }

        $upper = array_values(array_unique(array_filter(array_map(
            static fn ($s): string => mb_strtoupper(trim((string) $s)),
            $sids,
        ))));

        $merged = [];
        foreach (array_chunk($upper, self::SID_CHUNK) as $chunk) {
            foreach ($this->queryChunk($start, $end, $chunk, true, $checkout ? self::CHECKOUT_TYPES : self::CHECKIN_TYPES, 'DESC') as $row) {
                $merged[mb_strtoupper($row['kode_sid'])] ??= $row;
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
    private function queryChunk(string $start, string $end, array $upperSids, bool $firstPerSidOnly, ?array $types = null, string $direction = 'ASC', bool $firstPerDayPerSid = false): array
    {
        $types ??= self::CHECKIN_TYPES;
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $sidPlaceholders = $upperSids === [] ? '' : implode(',', array_fill(0, count($upperSids), '?'));
        $sidClause = $upperSids === [] ? '' : 'AND UPPER(TRIM(kode_sid)) IN ('.$sidPlaceholders.')';
        $typePlaceholders = implode(',', array_fill(0, count($types), '?'));
        $statusPlaceholders = implode(',', array_fill(0, count(self::PASSED_STATUSES), '?'));
        $compactTypes = array_map(
            static fn (string $t): string => str_replace([' ', '-'], '', strtoupper($t)),
            $types
        );
        $compactPlaceholders = implode(',', array_fill(0, count($compactTypes), '?'));

        if ($firstPerDayPerSid) {
            // Rentang multi-hari (mis. chart 7 hari terakhir): dedup ke SATU
            // baris per (tanggal, SID) langsung di database, bukan ambil semua
            // baris mentah lalu dibuang di PHP — lihat catatan pemanggil.
            $selectHead = 'SELECT DISTINCT ON ("date"::date, UPPER(TRIM(kode_sid)))';
            $orderBy = 'ORDER BY "date"::date, UPPER(TRIM(kode_sid)), "date" '.$direction;
        } elseif ($firstPerSidOnly) {
            $selectHead = 'SELECT DISTINCT ON (UPPER(TRIM(kode_sid)))';
            $orderBy = 'ORDER BY UPPER(TRIM(kode_sid)), "date" '.$direction;
        } else {
            $selectHead = 'SELECT';
            $orderBy = 'ORDER BY "date" '.$direction;
        }

        // Alias kolom ke nama lama (tanggal_checkinout/perusahaan/gate/
        // jenis_checkinout/status_lolos) supaya mapCheckinRow() dan semua
        // pemanggil di bawahnya tidak perlu berubah sama sekali.
        $sql = '
            '.$selectHead.'
                TRIM(kode_sid) AS kode_sid,
                "date" AS tanggal_checkinout,
                TRIM(COALESCE(nama_karyawan::text, \'\')) AS nama_karyawan,
                TRIM(COALESCE(nama_perusahaan::text, \'\')) AS perusahaan,
                TRIM(COALESCE(pintu_gate::text, \'\')) AS gate,
                TRIM(COALESCE(status_checkin_out::text, \'\')) AS jenis_checkinout,
                TRIM(COALESCE(status_passed::text, \'\')) AS status_lolos
            FROM '.self::TABLE.'
            WHERE "date" >= ?
              AND "date" < ?
              AND kode_sid IS NOT NULL
              AND TRIM(kode_sid) <> \'\'
              '.$sidClause.'
              AND (
                    UPPER(TRIM(status_checkin_out::text)) IN ('.$typePlaceholders.')
                 OR REPLACE(REPLACE(UPPER(TRIM(status_checkin_out::text)), \' \', \'\'), \'-\', \'\') IN ('.$compactPlaceholders.')
              )
              AND REPLACE(REPLACE(UPPER(TRIM(status_passed::text)), \' \', \'\'), \'-\', \'\') IN ('.$statusPlaceholders.')
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
            $this->queryGagal = true;

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
