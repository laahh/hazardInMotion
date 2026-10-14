<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;

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
     * Kode shift per hari per SID dari CHECK IN pertama: offset hari => 'P'|'M'.
     *
     * @return array<string, array<int, string>>
     */
    public function kodeHarian(CarbonImmutable $awal, CarbonImmutable $hariIni): array
    {
        $rows = $this->olap->select(
            "SELECT d.kode_sid,
                    string_agg(d.o::text || CASE WHEN d.m < ? THEN 'P' ELSE 'M' END, ',') AS hari
             FROM (
                 SELECT trim(s.kode_sid) AS kode_sid,
                        s.tanggal_checkinout::date - ?::date AS o,
                        MIN(extract(hour FROM s.tanggal_checkinout) * 60
                            + extract(minute FROM s.tanggal_checkinout)) AS m
                 FROM bcsid.mv_checkinout_rfid s
                 WHERE upper(trim(coalesce(s.status_lolos, ''))) = ?
                   AND s.jenis_checkinout = 'CHECK IN'
                   AND s.tanggal_checkinout >= ?
                   AND s.tanggal_checkinout < ?
                   AND s.kode_sid IS NOT NULL
                   AND trim(s.kode_sid) <> ''
                 GROUP BY 1, 2
             ) d
             GROUP BY d.kode_sid",
            [
                (int) config('dms_roster.populasi.batas_menit_pagi', 720),
                $awal->toDateString(),
                $this->statusLolos(),
                $awal->toDateTimeString(),
                $hariIni->addDay()->toDateTimeString(),
            ],
            $this->timeout(),
        );

        $out = [];
        foreach ($rows as $row) {
            $hari = [];
            foreach (explode(',', (string) $row->hari) as $sel) {
                $hari[(int) substr($sel, 0, -1)] = substr($sel, -1);
            }
            $out[(string) $row->kode_sid] = $hari;
        }

        return $out;
    }

    /**
     * Shift lengkap terakhir per SID (check-in ≥ $awal) beserta gate-nya, dan
     * berapa shift dalam rentang itu yang melebihi $maksJam.
     *
     * @return array<string, array{masuk:string,keluar:string,gate_in:string,gate_out:string,n_lebih:int,n_shift:int}>
     */
    public function shiftTerakhir(CarbonImmutable $awal, CarbonImmutable $hariIni, int $maksJam): array
    {
        $rows = $this->olap->select(
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
                $hariIni->addDay()->toDateTimeString(),
            ],
            $this->timeout(),
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
        $rows = $this->olap->select(
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
            $this->timeout(),
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

    private function timeout(): int
    {
        return (int) config('dms_roster.reg_live.timeout_ms', 20000);
    }
}
