<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Query baca-saja ke bcsid.mv_checkinout_rfid untuk kartu aturan banned di
 * Ringkasan Roster. Agregasi berat dikerjakan Postgres supaya yang dikirim ke
 * PHP cuma satu baris per SID; melempar exception bila OLAP tidak terjangkau
 * (ditangkap pemanggil).
 *
 * Pemasangan check-in ↔ check-out di semua query ini sama: sebuah CHECK IN
 * berpasangan dengan tap berikutnya bila tap itu CHECK OUT dan jaraknya
 * < 24 jam. Dengan begitu check-out pagi milik shift malam kemarin tidak
 * tertukar ke shift hari ini.
 */
final class DmsRosterRfidReader
{
    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * Kode shift per hari per SID dari CHECK IN pertama: offset hari => 'P'|'M'
     * (offset dihitung dari $awal).
     *
     * Yang membatasi BUKAN waktu hitung di Postgres (±3 dtk untuk 180 hari),
     * melainkan waktu KIRIM hasilnya ke server aplikasi: statement_timeout
     * ikut menghitung pengiriman, dan jalur server aplikasi → RDS lambat,
     * sehingga hasil ±7,5 MB dibatalkan (SQLSTATE 57014). Karena itu:
     *   - hanya SID yang dinilai ($sids, ±3,7 rb) yang ditarik, bukan ±24 rb;
     *   - tiap SID dikirim sebagai string ringkas satu huruf per hari
     *     (P/M/o), bukan daftar "offset+kode";
     *   - jendela dipecah per N hari (reg_live.potongan_hari, default 30),
     *     jadi tiap query hanya mengirim ±130 KB.
     *
     * @param  list<string>|null  $sids  null = semua SID (hanya untuk uji/diagnosa).
     * @param  (callable(string, float, int): void)|null  $lapor  dipanggil per potongan: rentang, detik, jumlah SID.
     * @return array<string, array<int, string>>
     */
    public function kodeHarian(CarbonImmutable $awal, CarbonImmutable $hariIni, ?array $sids = null, ?callable $lapor = null): array
    {
        $potongan = max(7, (int) config('dms_roster.reg_live.potongan_hari', 30));
        $batasAkhir = $hariIni->addDay();
        $out = [];

        for ($dari = $awal; $dari->lt($batasAkhir); $dari = $dari->addDays($potongan)) {
            $sampai = $dari->addDays($potongan)->min($batasAkhir);
            $mulai = microtime(true);
            $hasil = $this->kodeHarianRentang($awal, $dari, $sampai, $sids);

            foreach ($hasil as $sid => $hari) {
                // + menjaga isi lama; rentang tidak beririsan, jadi tak ada tumpang.
                $out[$sid] = ($out[$sid] ?? []) + $hari;
            }

            if ($lapor !== null) {
                $lapor($dari->toDateString().' … '.$sampai->subDay()->toDateString(), microtime(true) - $mulai, count($hasil));
            }
        }

        return $out;
    }

    /**
     * Satu potongan [$dari, $sampai) dari kodeHarian(). Postgres merakit
     * string tepat sepanjang potongan per SID ('o' = tidak ada check-in);
     * PHP menerjemahkannya kembali ke offset dari $awal.
     *
     * @param  list<string>|null  $sids
     * @return array<string, array<int, string>>
     */
    private function kodeHarianRentang(CarbonImmutable $awal, CarbonImmutable $dari, CarbonImmutable $sampai, ?array $sids): array
    {
        $panjang = (int) $dari->diffInDays($sampai);
        $filterSid = $sids === null ? '' : 'AND trim(s.kode_sid) = ANY(?::text[])';
        $bindSid = $sids === null ? [] : [$this->arrayPg($sids)];

        $rows = $this->pilih(
            "WITH hari AS (
                 SELECT trim(s.kode_sid) AS kode_sid,
                        s.tanggal_checkinout::date - ?::date AS o,
                        CASE WHEN MIN(extract(hour FROM s.tanggal_checkinout) * 60
                                      + extract(minute FROM s.tanggal_checkinout)) < ?
                             THEN 'P' ELSE 'M' END AS k
                 FROM bcsid.mv_checkinout_rfid s
                 WHERE upper(trim(coalesce(s.status_lolos, ''))) = ?
                   AND s.jenis_checkinout = 'CHECK IN'
                   AND s.tanggal_checkinout >= ?
                   AND s.tanggal_checkinout < ?
                   AND s.kode_sid IS NOT NULL
                   AND trim(s.kode_sid) <> ''
                   {$filterSid}
                 GROUP BY 1, 2
             )
             SELECT x.kode_sid, string_agg(coalesce(h.k, 'o'), '' ORDER BY g.o) AS pola
             FROM (SELECT DISTINCT kode_sid FROM hari) x
             CROSS JOIN generate_series(0, ?) AS g(o)
             LEFT JOIN hari h ON h.kode_sid = x.kode_sid AND h.o = g.o
             GROUP BY x.kode_sid",
            [
                $dari->toDateString(),
                (int) config('dms_roster.populasi.batas_menit_pagi', 720),
                $this->statusLolos(),
                $dari->toDateTimeString(),
                $sampai->toDateTimeString(),
                ...$bindSid,
                $panjang - 1,
            ],
        );

        // Offset di dalam potongan → offset dari awal jendela.
        $geser = (int) $awal->diffInDays($dari);
        $out = [];
        foreach ($rows as $row) {
            $pola = (string) $row->pola;
            $hari = [];
            for ($i = 0, $n = strlen($pola); $i < $n; $i++) {
                if ($pola[$i] !== 'o') {
                    $hari[$geser + $i] = $pola[$i];
                }
            }
            $out[(string) $row->kode_sid] = $hari;
        }

        return $out;
    }

    /**
     * Literal array Postgres untuk parameter ANY(?::text[]). Setiap elemen
     * dikutip & di-escape, jadi aman untuk SID apa pun.
     *
     * @param  list<string>  $nilai
     */
    private function arrayPg(array $nilai): string
    {
        return '{'.implode(',', array_map(
            static fn (string $v): string => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $v).'"',
            $nilai,
        )).'}';
    }

    /**
     * Shift lengkap terakhir per SID (check-in ≥ $awal) beserta gate-nya, dan
     * berapa shift dalam rentang itu yang melebihi $maksJam.
     *
     * @param  list<string>|null  $sids  Batasi ke SID yang dinilai (lihat kodeHarian()).
     * @return array<string, array{masuk:string,keluar:string,gate_in:string,gate_out:string,n_lebih:int,n_shift:int}>
     */
    public function shiftTerakhir(CarbonImmutable $awal, CarbonImmutable $hariIni, int $maksJam, ?array $sids = null): array
    {
        $filterSid = $sids === null ? '' : 'AND trim(s.kode_sid) = ANY(?::text[])';
        $bindSid = $sids === null ? [] : [$this->arrayPg($sids)];

        $rows = $this->pilih(
            "SELECT p.kode_sid,
                    (array_agg(p.t ORDER BY p.t DESC))[1]  AS masuk,
                    (array_agg(p.nt ORDER BY p.t DESC))[1] AS keluar,
                    (array_agg(p.g ORDER BY p.t DESC))[1]  AS gate_in,
                    (array_agg(p.ng ORDER BY p.t DESC))[1] AS gate_out,
                    SUM(CASE WHEN p.nt - p.t > make_interval(hours => ?) THEN 1 ELSE 0 END) AS n_lebih,
                    COUNT(*) AS n_shift
             FROM (
                 SELECT trim(s.kode_sid) AS kode_sid,
                        s.tanggal_checkinout AS t, s.gate AS g, s.jenis_checkinout AS j,
                        lead(s.tanggal_checkinout) OVER (PARTITION BY s.kode_sid ORDER BY s.tanggal_checkinout) AS nt,
                        lead(s.jenis_checkinout)  OVER (PARTITION BY s.kode_sid ORDER BY s.tanggal_checkinout) AS nj,
                        lead(s.gate)              OVER (PARTITION BY s.kode_sid ORDER BY s.tanggal_checkinout) AS ng
                 FROM bcsid.mv_checkinout_rfid s
                 WHERE upper(trim(coalesce(s.status_lolos, ''))) = ?
                   AND s.jenis_checkinout IN ('CHECK IN', 'CHECK OUT')
                   AND s.tanggal_checkinout >= ?
                   -- Sehari lebih supaya check-out shift malam hari ini ikut terbaca.
                   AND s.tanggal_checkinout < ?
                   AND s.kode_sid IS NOT NULL
                   AND trim(s.kode_sid) <> ''
                   {$filterSid}
             ) p
             WHERE p.j = 'CHECK IN'
               AND p.nj = 'CHECK OUT'
               AND p.nt - p.t < interval '24 hours'
               AND p.t < ?
             GROUP BY p.kode_sid",
            [
                $maksJam,
                $this->statusLolos(),
                $awal->toDateTimeString(),
                $hariIni->addDays(2)->toDateTimeString(),
                ...$bindSid,
                $hariIni->addDay()->toDateTimeString(),
            ],
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->kode_sid] = [
                'masuk' => (string) $row->masuk,
                'keluar' => (string) $row->keluar,
                'gate_in' => trim((string) ($row->gate_in ?? '')) ?: '—',
                'gate_out' => trim((string) ($row->gate_out ?? '')) ?: '—',
                'n_lebih' => (int) $row->n_lebih,
                'n_shift' => (int) $row->n_shift,
            ];
        }

        return $out;
    }

    /**
     * Seluruh tap satu SID (±2 tap/hari). kode_sid dibandingkan apa adanya
     * supaya indeksnya terpakai (±80 ms).
     *
     * @return list<array{waktu:string,jenis:string,gate:string,lolos:bool}>
     */
    public function tapKaryawan(string $sid, CarbonImmutable $awal, CarbonImmutable $hariIni): array
    {
        $rows = $this->pilih(
            "SELECT s.tanggal_checkinout AS waktu, s.jenis_checkinout AS jenis, s.gate,
                    upper(trim(coalesce(s.status_lolos, ''))) = ? AS lolos
             FROM bcsid.mv_checkinout_rfid s
             WHERE s.kode_sid = ?
               AND s.tanggal_checkinout >= ?
               AND s.tanggal_checkinout < ?
             ORDER BY s.tanggal_checkinout",
            [
                $this->statusLolos(),
                $sid,
                $awal->toDateTimeString(),
                $hariIni->addDays(2)->toDateTimeString(),
            ],
        );

        return array_map(static fn (object $r): array => [
            'waktu' => (string) $r->waktu,
            'jenis' => strtoupper(trim((string) $r->jenis)),
            'gate' => trim((string) ($r->gate ?? '')) ?: '—',
            'lolos' => (bool) $r->lolos,
        ], $rows);
    }

    private function statusLolos(): string
    {
        return strtoupper((string) config('dms_roster.populasi.status_lolos_scan', 'PASSED'));
    }

    /**
     * select() ke OLAP dengan setelan sesi untuk agregasi besar, dicoba ulang
     * SEKALI bila dihentikan statement_timeout (SQLSTATE 57014) — biasanya
     * karena server sesaat sibuk atau materialized view sedang di-refresh.
     * Galat lain (koneksi, sintaks) langsung diteruskan.
     *
     * @param  list<mixed>  $bindings
     * @return list<object>
     */
    private function pilih(string $sql, array $bindings): array
    {
        $setelan = [
            // work_mem bawaan 4 MB membuat GROUP BY puluhan ribu SID tumpah ke disk.
            'work_mem' => (string) config('dms_roster.reg_live.work_mem', '64MB'),
            'jit' => 'off',
        ];

        try {
            return $this->olap->select($sql, $bindings, $this->timeout(), $setelan);
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '57014') {
                throw $e;
            }
            Log::info('DmsRoster RFID: query timeout, dicoba ulang sekali.');

            return $this->olap->select($sql, $bindings, $this->timeout(), $setelan);
        }
    }

    private function timeout(): int
    {
        return (int) config('dms_roster.reg_live.timeout_ms', 20000);
    }
}
