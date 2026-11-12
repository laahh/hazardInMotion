<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pembaca read-only tap RFID (check-IN lolos pertama / check-OUT lolos terakhir).
 *
 * Sumber data RFID: ClickHouse OBDS `obds.aaj_vw_checkinout_rfid` (koneksi
 * clickhouse_obds, lihat RfidClickHouseClient) — salinan Airbyte (sinkron tiap
 * jam) dari view Postgres OLAP bcsid.aaj_vw_checkinout_rfid. Dulu view OLAP itu
 * di-query langsung (butuh driver pgsql + join m_karyawan ±6 GB tiap query).
 * Dicek 12 Nov 2026: check-in/check-out lolos per SID identik dengan OLAP.
 *
 * PENTING: connectionName()/isUp() TETAP menunjuk ke Postgres OLAP (pgsql_direct)
 * karena ±80 pemanggil lain (PraOperasi, DMS, ISC, Fatigue) memakainya sebagai
 * "sumber koneksi" untuk query OLAP mereka sendiri. Ketersediaan RFID = rfidUp().
 */
final class SportEvaluationPvtRfidCheckinReader
{
    public const TABLE = 'obds.aaj_vw_checkinout_rfid';

    /** Sumber lama (Postgres OLAP), hanya untuk rujukan. */
    public const TABLE_OLAP = 'bcsid.aaj_vw_checkinout_rfid';

    public const CONNECTION_TUNNEL = 'pgsql_ssh';

    public const CONNECTION_DIRECT = 'pgsql_direct';

    private const SID_CHUNK = 800;

    private const UP_CACHE_KEY = 'evaluasi_well:pvt_rfid_connection_v1';

    private const UP_CACHE_TTL = 20;

    private ?string $activeConnection = null;

    public function __construct(private readonly RfidClickHouseClient $clickhouse)
    {
    }

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

    /** Postgres OLAP hidup (dipakai pemanggil lain untuk query OLAP mereka sendiri). */
    public function isUp(): bool
    {
        return $this->connectionName() !== null;
    }

    /** Sumber RFID (ClickHouse OBDS) bisa dijangkau. */
    public function rfidUp(): bool
    {
        return $this->clickhouse->up();
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
        if (! $this->rfidUp()) {
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
        if (! $this->rfidUp()) {
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

                    $this->queryGagal = ! $this->rfidUp();
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
        if (! $this->rfidUp() || $sids === []) {
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
        if (! self::waktuValid($start) || ! self::waktuValid($end)) {
            $this->queryGagal = true;

            return [];
        }

        $q = static fn (array $v): string => implode(',', array_map(
            static fn ($x): string => RfidClickHouseClient::quote((string) $x),
            $v
        ));
        $ringkas = static fn (string $kolom): string => "replaceAll(replaceAll(upperUTF8({$kolom}), ' ', ''), '-', '')";
        $compactTypes = array_map(static fn (string $t): string => str_replace([' ', '-'], '', strtoupper($t)), $types);

        $sid = 'upperUTF8(kode_sid)';
        $sidClause = $upperSids === [] ? '' : "AND {$sid} IN (".$q($upperSids).')';

        // Satu baris per SID (atau per hari+SID) langsung di ClickHouse:
        // ORDER BY ... LIMIT 1 BY = padanan DISTINCT ON di Postgres.
        if ($firstPerDayPerSid) {
            $tail = "ORDER BY toDate(waktu), {$sid}, waktu {$direction} LIMIT 1 BY toDate(waktu), {$sid}";
        } elseif ($firstPerSidOnly) {
            $tail = "ORDER BY {$sid}, waktu {$direction} LIMIT 1 BY {$sid}";
        } else {
            $tail = "ORDER BY waktu {$direction} LIMIT 1 BY {$sid}, waktu, upperUTF8(jenis_checkinout)";
        }

        // FINAL: tabel ReplacingMergeTree(id) hasil Airbyte, buang baris ganda sinkron.
        // Alias ke nama kolom lama supaya mapCheckinRow() dan pemanggil tidak berubah.
        // `date` = waktu lokal (WITA) tanpa zona, sama seperti di OLAP.
        $template = <<<'SQL'
            SELECT kode_sid, toString(waktu) AS tanggal_checkinout, nama_karyawan, perusahaan, gate, jenis_checkinout, status_lolos
            FROM (
                SELECT
                    trimBoth(assumeNotNull(kode_sid)) AS kode_sid,
                    date AS waktu,
                    trimBoth(ifNull(nama_karyawan, '')) AS nama_karyawan,
                    trimBoth(ifNull(nama_perusahaan, '')) AS perusahaan,
                    trimBoth(ifNull(pintu_gate, '')) AS gate,
                    trimBoth(ifNull(status_checkin_out, '')) AS jenis_checkinout,
                    trimBoth(ifNull(status_passed, '')) AS status_lolos
                FROM %1$s FINAL
                WHERE date >= toDateTime64(%2$s, 3)
                  AND date < toDateTime64(%3$s, 3)
                  AND kode_sid IS NOT NULL
                  AND trimBoth(kode_sid) != ''
            )
            WHERE (upperUTF8(jenis_checkinout) IN (%5$s) OR %6$s IN (%7$s))
              AND %8$s IN (%9$s)
              %4$s
            %10$s
            SQL;
        $sql = sprintf(
            $template,
            self::TABLE,
            RfidClickHouseClient::quote($start),
            RfidClickHouseClient::quote($end),
            $sidClause,
            $q($types),
            $ringkas('jenis_checkinout'),
            $q($compactTypes),
            $ringkas('status_lolos'),
            $q(self::PASSED_STATUSES),
            $tail,
        );

        try {
            $rows = $this->clickhouse->select($sql);
        } catch (Throwable $e) {
            report($e);
            $this->queryGagal = true;

            return [];
        }

        $list = [];
        foreach ($rows as $row) {
            $mapped = $this->mapCheckinRow((object) $row);
            if ($mapped !== null) {
                $list[] = $mapped;
            }
        }

        return $list;
    }

    private static function waktuValid(string $waktu): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $waktu);
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
