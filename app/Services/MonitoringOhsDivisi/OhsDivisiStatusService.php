<?php

declare(strict_types=1);

namespace App\Services\MonitoringOhsDivisi;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Status RFID terkini tiap karyawan departemen OHS Divisi:
 *   onsite  check-in terakhir belum disusul check-out (dianggap masih di site)
 *   keluar  check-out terakhir sesudah check-in terakhir
 *   belum   tidak ada check-in dalam jendela waktu
 *
 * Jendela = sejak awal hari kemarin s/d sekarang, supaya shift malam yang
 * check-in kemarin sore tetap terbaca onsite hari ini. Lama di site:
 * onsite = sekarang − check-in; keluar = check-out − check-in.
 */
final class OhsDivisiStatusService
{
    public const ONSITE = 'onsite';

    public const KELUAR = 'keluar';

    public const BELUM = 'belum';

    private const URUTAN = [self::ONSITE => 0, self::KELUAR => 1, self::BELUM => 2];

    public function __construct(
        private readonly OhsDivisiKaryawanReader $karyawan,
        private readonly SportEvaluationPvtRfidCheckinReader $rfid,
    ) {}

    /**
     * @return array{tersedia:bool,karyawan_terbaca:bool,diambil:string,dari:string,ringkas:array<string,int>,baris:list<array<string,mixed>>}
     */
    public function status(): array
    {
        return Cache::remember('monitoring_ohs_divisi:status:v1', 60, function (): array {
            $kini = CarbonImmutable::now((string) config('app.timezone'));
            $dari = $kini->subDay()->startOfDay();
            $karyawan = $this->karyawan->karyawan();
            $sids = array_keys($karyawan ?? []);

            $tersedia = $this->rfid->isUp();
            $mulai = $dari->format('Y-m-d H:i:s');
            $akhir = $kini->addMinute()->format('Y-m-d H:i:s');
            $masuk = $tersedia ? $this->rfid->lastPassedForSidsBetween($mulai, $akhir, $sids) : [];
            $keluar = $tersedia ? $this->rfid->lastPassedForSidsBetween($mulai, $akhir, $sids, true) : [];

            return [
                'tersedia' => $tersedia,
                'karyawan_terbaca' => $karyawan !== null,
                'diambil' => $kini->toDateTimeString(),
                'dari' => $dari->toDateTimeString(),
            ] + self::susun($karyawan ?? [], $masuk, $keluar, $kini);
        });
    }

    /**
     * Gabungkan karyawan dengan tap terakhirnya. Murni.
     *
     * @param  array<string, array{sid:string,nama:string,departemen:string,perusahaan:string,jabatan:string}>  $karyawan
     * @param  array<string, array<string, mixed>>  $masuk  tap check-in terakhir per SID (huruf besar)
     * @param  array<string, array<string, mixed>>  $keluar  tap check-out terakhir per SID
     * @return array{ringkas: array<string, int>, baris: list<array<string, mixed>>}
     */
    public static function susun(array $karyawan, array $masuk, array $keluar, CarbonImmutable $kini): array
    {
        $baris = [];
        $ringkas = [self::ONSITE => 0, self::KELUAR => 0, self::BELUM => 0];
        foreach ($karyawan as $sid => $k) {
            $in = $masuk[$sid] ?? null;
            $out = $keluar[$sid] ?? null;
            $waktuIn = $in === null ? null : CarbonImmutable::parse((string) $in['checked_in_at']);
            $waktuOut = $out === null ? null : CarbonImmutable::parse((string) $out['checked_in_at']);

            if ($waktuIn === null) {
                $status = self::BELUM;
                $menit = null;
            } elseif ($waktuOut !== null && $waktuOut->gt($waktuIn)) {
                $status = self::KELUAR;
                $menit = (int) $waktuIn->diffInMinutes($waktuOut, true);
            } else {
                // Check-in tanpa check-out sesudahnya = masih di site.
                $status = self::ONSITE;
                $menit = (int) $waktuIn->diffInMinutes($kini, true);
                $out = null;
                $waktuOut = null;
            }
            $ringkas[$status]++;

            $baris[] = $k + [
                'status' => $status,
                'masuk' => $waktuIn?->format('Y-m-d H:i'),
                'gate_masuk' => $in === null ? null : ((string) ($in['gate'] ?? '') ?: null),
                'keluar' => $waktuOut?->format('Y-m-d H:i'),
                'gate_keluar' => $out === null ? null : ((string) ($out['gate'] ?? '') ?: null),
                'durasi_menit' => $menit,
            ];
        }

        // Onsite dulu (paling lama di site di atas), lalu sudah keluar, lalu belum check-in.
        usort($baris, static fn (array $a, array $b): int => [self::URUTAN[$a['status']], -($a['durasi_menit'] ?? 0), $a['nama']]
            <=> [self::URUTAN[$b['status']], -($b['durasi_menit'] ?? 0), $b['nama']]);

        return ['ringkas' => $ringkas, 'baris' => $baris];
    }
}
