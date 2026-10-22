<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Query baca-saja ke OLAP untuk deteksi Long Shift pengawas
 * (docs/overshift.md). Seluruh pipeline — dedup, pasangan IN→OUT, blok
 * kontinu, irisan jadwal, klasifikasi — dikerjakan Postgres, yang dikirim ke
 * PHP hanya satu baris ringkas per SID. Melempar exception bila OLAP tidak
 * terjangkau (ditangkap pemanggil).
 *
 * Setiap CTE sengaja MATERIALIZED: tanpa itu planner menggabungkan
 * blok×sesi lewat nested loop dan query melewati 30 detik (terukur); dengan
 * itu 5–12 dtk untuk 7 hari (±3.650 pengawas ber-tap), tergantung beban server.
 */
final class DmsLongShiftReader
{
    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * Baseline: seluruh pengawas aktif. Satu baris per SID (view bisa memuat
     * SID ganda — id terbesar menang).
     *
     * @return list<object{sid:string,nama:string,perusahaan:?string,jabatan:?string,departemen:?string}>
     */
    public function pengawas(): array
    {
        return $this->pilih(
            "SELECT DISTINCT ON (trim(kode_sid)) trim(kode_sid) AS sid, nama, nama_perusahaan AS perusahaan,
                    jabatan_struktural AS jabatan, departement AS departemen
             FROM {$this->view()}
             WHERE level_jabatan = ? AND status_karyawan = ? AND coalesce(trim(kode_sid), '') <> ''
             ORDER BY trim(kode_sid), id DESC",
            [$this->param('level_jabatan'), $this->param('status_karyawan')],
        );
    }

    /**
     * Hasil deteksi per SID pengawas yang punya tap di jendela. Shift yang
     * dinilai: mulai >= $dari dan sudah selesai (mulai + 12 jam <= $sekarang).
     * Event ditarik 2 hari sebelum $dari supaya blok yang sudah berjalan sejak
     * sebelum jendela tetap utuh.
     *
     * long_shift, shift & anomali dikirim sebagai JSON dan di-decode pemanggil.
     *
     * @param  list<string>  $sids  SID pengawas dari pengawas().
     * @return list<object{sid:string,n_shift:int,n_long:int,long_shift:?string,shift:?string,n_anomali:int,anomali:?string}>
     */
    public function deteksi(CarbonImmutable $dari, CarbonImmutable $sekarang, array $sids): array
    {
        if ($sids === []) {
            return [];
        }

        // Angka dari config di-cast ke int dan timestamp dari Carbon berformat
        // tetap, jadi aman disisipkan langsung; hanya daftar SID yang di-bind.
        // (Named parameter berulang tidak andal di PDO pgsql.)
        $dup = (int) $this->param('duplikat_menit', 2);
        $pasangan = (int) $this->param('pasangan_maks_jam', 36);
        $jeda = (int) $this->param('jeda_maks_menit', 60);
        $jamMulai = (int) $this->param('shift_mulai_jam', 7);
        $minShift = (int) $this->param('min_shift_menit', 360);
        $minLanjut = (int) $this->param('min_lanjut_menit', 30);
        $tarikDari = $dari->subDays(2)->toDateTimeString();
        $mulai = $dari->toDateTimeString();
        $kini = $sekarang->toDateTimeString();

        $sql = <<<SQL
            WITH peg AS MATERIALIZED (
                -- SID baseline dikirim pemanggil: view pengawas butuh 3–12 dtk, jangan dibaca dua kali.
                SELECT DISTINCT unnest(CAST(? AS text[])) AS sid
            ),
            ev0 AS MATERIALIZED (
                SELECT trim(s.kode_sid) AS sid, s.tanggal_checkinout AS t,
                       CASE WHEN s.jenis_checkinout = 'CHECK IN' THEN 'IN' ELSE 'OUT' END AS tipe,
                       s.gate, s.id_checkinout AS id
                FROM bcsid.mv_checkinout_rfid s
                WHERE s.tanggal_checkinout >= TIMESTAMP '{$tarikDari}'
                  AND s.tanggal_checkinout < TIMESTAMP '{$kini}'
                  AND upper(trim(coalesce(s.status_lolos, ''))) = 'PASSED'
                  AND s.jenis_checkinout IN ('CHECK IN', 'CHECK OUT')
                  AND trim(s.kode_sid) IN (SELECT sid FROM peg)
            ),
            -- 1. SORT & DEDUP: buang scan sejenis berdekatan.
            ev AS MATERIALIZED (
                SELECT * FROM (
                    SELECT ev0.*, lag(tipe) OVER w AS p_tipe, lag(t) OVER w AS p_t
                    FROM ev0 WINDOW w AS (PARTITION BY sid ORDER BY t, id)
                ) x
                WHERE NOT coalesce(p_tipe = tipe AND t - p_t <= make_interval(mins => {$dup}), false)
            ),
            pr AS MATERIALIZED (
                SELECT ev.*, lag(tipe) OVER w AS b_tipe, lead(tipe) OVER w AS n_tipe, lead(t) OVER w AS n_t, lead(gate) OVER w AS n_gate
                FROM ev WINDOW w AS (PARTITION BY sid ORDER BY t, id)
            ),
            -- 2. PAIR EVENTS: IN yang langsung diikuti OUT, durasi wajar.
            ses AS MATERIALIZED (
                SELECT sid, t AS t_in, n_t AS t_out, gate AS g_in, n_gate AS g_out
                FROM pr WHERE tipe = 'IN' AND n_tipe = 'OUT' AND n_t - t <= make_interval(hours => {$pasangan})
            ),
            anom AS MATERIALIZED (
                SELECT sid, t, gate,
                       CASE WHEN tipe = 'OUT' THEN 'OUT tanpa IN'
                            WHEN n_tipe = 'IN' THEN 'IN tanpa OUT'
                            WHEN n_tipe IS NULL THEN 'IN tanpa OUT (tidak ada tap sesudahnya)'
                            ELSE 'Pasangan IN–OUT melebihi {$pasangan} jam' END AS jenis
                FROM pr
                WHERE t >= TIMESTAMP '{$mulai}'
                  AND ((tipe = 'IN' AND (n_tipe = 'IN'
                                         -- IN terakhir yang masih baru = masih di dalam area, bukan anomali.
                                         OR (n_tipe IS NULL AND t < TIMESTAMP '{$kini}' - make_interval(hours => {$pasangan}))
                                         OR (n_tipe = 'OUT' AND n_t - t > make_interval(hours => {$pasangan}))))
                    OR (tipe = 'OUT' AND b_tipe = 'OUT'))
            ),
            -- 3. BUILD CONTINUOUS BLOCK: sambung sesi bila jeda OUT→IN kecil.
            blk AS MATERIALIZED (
                SELECT *, sum(baru) OVER (PARTITION BY sid ORDER BY t_in) AS bid FROM (
                    SELECT ses.*, CASE WHEN t_in - lag(t_out) OVER (PARTITION BY sid ORDER BY t_in)
                                            <= make_interval(mins => {$jeda}) THEN 0 ELSE 1 END AS baru
                    FROM ses
                ) x
            ),
            blk2 AS MATERIALIZED (
                SELECT blk.*, min(t_in) OVER b AS mulai, max(t_out) OVER b AS selesai, count(*) OVER b AS n_sesi,
                       first_value(g_in) OVER (PARTITION BY sid, bid ORDER BY t_in) AS gate_masuk,
                       first_value(g_out) OVER (PARTITION BY sid, bid ORDER BY t_out DESC) AS gate_keluar
                FROM blk WINDOW b AS (PARTITION BY sid, bid)
            ),
            -- 4–5. MATCH SCHEDULE & MEASURE NEXT SHIFT: irisan tiap sesi dengan
            -- setiap kandidat shift 12 jam yang menyentuh blok, dan 12 jam sesudahnya.
            nilai AS MATERIALIZED (
                SELECT x.sid, x.bid, g AS s, min(x.mulai) AS mulai, min(x.selesai) AS selesai, min(x.n_sesi) AS n_sesi,
                       min(x.gate_masuk) AS gate_masuk, min(x.gate_keluar) AS gate_keluar,
                       sum(greatest(0, extract(epoch FROM least(x.t_out, g + interval '12 hours') - greatest(x.t_in, g)) / 60))::int AS m_shift,
                       sum(greatest(0, extract(epoch FROM least(x.t_out, g + interval '24 hours')
                                                    - greatest(x.t_in, g + interval '12 hours')) / 60))::int AS m_lanjut
                FROM blk2 x
                CROSS JOIN LATERAL generate_series(
                    date_trunc('day', x.mulai) - interval '1 day' + make_interval(hours => {$jamMulai}),
                    x.selesai, interval '12 hours') g
                GROUP BY 1, 2, 3
            ),
            -- Shift terjadwal = shift PALING AWAL yang terisi minimal min_shift.
            jadwal AS MATERIALIZED (
                SELECT DISTINCT ON (sid, bid) * FROM nilai WHERE m_shift >= {$minShift} ORDER BY sid, bid, s
            ),
            hasil AS MATERIALIZED (
                SELECT * FROM jadwal
                WHERE s >= TIMESTAMP '{$mulai}' AND s + interval '12 hours' <= TIMESTAMP '{$kini}'
            ),
            -- 6. CLASSIFY.
            per_sid AS MATERIALIZED (
                SELECT sid, count(*) AS n_shift, count(*) FILTER (WHERE m_lanjut >= {$minLanjut}) AS n_long,
                       json_agg(json_build_object(
                           'shift', to_char(s, 'YYYY-MM-DD HH24:MI'), 'm_shift', m_shift, 'm_lanjut', m_lanjut,
                           'mulai', to_char(mulai, 'YYYY-MM-DD HH24:MI'), 'selesai', to_char(selesai, 'YYYY-MM-DD HH24:MI'),
                           'n_sesi', n_sesi, 'gate_masuk', gate_masuk, 'gate_keluar', gate_keluar
                       ) ORDER BY s DESC) FILTER (WHERE m_lanjut >= {$minLanjut}) AS long_shift,
                       -- Semua shift yang dinilai, ringkas [mulai, menit di shift, menit lanjut] — grafik per orang.
                       json_agg(json_build_array(to_char(s, 'YYYY-MM-DD HH24:MI'), m_shift, m_lanjut) ORDER BY s) AS shift
                FROM hasil GROUP BY sid
            ),
            per_anom AS MATERIALIZED (
                SELECT sid, count(*) AS n_anomali,
                       to_json((array_agg(json_build_object('waktu', to_char(t, 'YYYY-MM-DD HH24:MI'), 'jenis', jenis, 'gate', gate)
                                          ORDER BY t DESC))[1:10]) AS anomali
                FROM anom GROUP BY sid
            ),
            ada_tap AS MATERIALIZED (SELECT DISTINCT sid FROM ev0 WHERE t >= TIMESTAMP '{$mulai}')
            SELECT t.sid, coalesce(p.n_shift, 0) AS n_shift, coalesce(p.n_long, 0) AS n_long, p.long_shift, p.shift,
                   coalesce(a.n_anomali, 0) AS n_anomali, a.anomali
            FROM ada_tap t
            LEFT JOIN per_sid p ON p.sid = t.sid
            LEFT JOIN per_anom a ON a.sid = t.sid
            SQL;

        return $this->pilih($sql, [DmsRosterRfidReader::arrayPg($sids)]);
    }

    /**
     * select() dengan setelan sesi untuk agregasi, dicoba ulang SEKALI bila
     * kena statement_timeout (57014): waktu query terukur 5–12 dtk tergantung
     * beban server, mis. saat materialized view sedang di-refresh.
     *
     * @param  list<mixed>  $bindings
     * @return list<object>
     */
    private function pilih(string $sql, array $bindings): array
    {
        $setelan = ['work_mem' => '64MB', 'jit' => 'off'];

        try {
            return $this->olap->select($sql, $bindings, $this->timeout(), $setelan);
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '57014') {
                throw $e;
            }
            Log::info('DmsLongShift: query timeout, dicoba ulang sekali.');

            return $this->olap->select($sql, $bindings, $this->timeout(), $setelan);
        }
    }

    private function view(): string
    {
        $view = (string) $this->param('view_karyawan', 'bcsid.bep_sid_karyawan');

        // Nama view disisipkan langsung ke SQL — hanya izinkan skema.nama polos.
        if (preg_match('/^[a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*$/i', $view) !== 1) {
            throw new \InvalidArgumentException('dms_roster.long_shift.view_karyawan tidak valid.');
        }

        return $view;
    }

    private function param(string $kunci, mixed $default = null): mixed
    {
        return config('dms_roster.long_shift.'.$kunci, $default);
    }

    private function timeout(): int
    {
        return (int) $this->param('timeout_ms', 20000);
    }
}
