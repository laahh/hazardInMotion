<?php

declare(strict_types=1);

namespace App\Services\MonitoringOhsDivisi;

use App\Services\Isc\IscPobSnapshotService;
use App\Services\Isc\IscSiteNormalizer;

/**
 * Snapshot POB untuk Monitoring OHS Divisi: snapshot ISC yang sama
 * (IscPobSnapshotService), tetapi bagian RFID hanya karyawan departemen OHS
 * Divisi (OhsDivisiKaryawanReader). Data GPS/Besigma tidak disaring.
 *
 * Yang disaring: daftar check-in, summary.checkin_total & checkin_by_site,
 * reconcile.rfid_count, dan selisih "check-in RFID tanpa GPS".
 */
final class OhsDivisiPobService
{
    public function __construct(
        private readonly IscPobSnapshotService $snapshot,
        private readonly OhsDivisiKaryawanReader $karyawan,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(bool $fresh, bool $demo): array
    {
        $data = $this->snapshot->snapshot($fresh, $demo);
        $sid = $this->karyawan->sidDepartemen();

        return self::saring($data, $sid ?? []) + [
            'departemen' => OhsDivisiKaryawanReader::departemen(),
            // Daftar departemen tidak terbaca → angka RFID 0, bukan seluruh karyawan.
            'departemen_terbaca' => $sid !== null,
        ];
    }

    /**
     * Saring bagian RFID snapshot ke SID yang diizinkan. Murni.
     *
     * @param  array<string, mixed>  $data  hasil IscPobSnapshotService::snapshot()
     * @param  array<string, string>  $sidDepartemen  SID (huruf besar) => departemen
     * @return array<string, mixed>
     */
    public static function saring(array $data, array $sidDepartemen): array
    {
        $checkins = [];
        foreach ($data['checkins'] ?? [] as $row) {
            $dep = $sidDepartemen[mb_strtoupper(trim((string) ($row['sid'] ?? '')))] ?? null;
            if ($dep !== null) {
                $checkins[] = $row + ['department' => $dep];
            }
        }

        $perSite = [];
        foreach (IscSiteNormalizer::SITES as $kode => $_label) {
            $perSite[$kode] = 0;
        }
        foreach ($checkins as $row) {
            $kode = (string) ($row['site_code'] ?? '');
            if (isset($perSite[$kode])) {
                $perSite[$kode]++;
            }
        }

        $summary = $data['summary'] ?? [];
        $summary['checkin_total'] = count($checkins);
        $summary['checkin_by_site'] = array_map(
            static fn (array $s): array => ['count' => $perSite[$s['code']] ?? 0] + $s,
            $summary['checkin_by_site'] ?? [],
        );

        $reconcile = $data['reconcile'] ?? [];
        $reconcile['rfid_count'] = count($checkins);
        // Sama dengan IscPobSnapshotService::applyGapRfidTanpaGps(): check-in − GPS aktif.
        $reconcile['gap_rfid_minus_besigma_count'] = max(0, count($checkins) - (int) ($reconcile['current_count'] ?? 0));

        return ['checkins' => $checkins, 'summary' => $summary, 'reconcile' => $reconcile] + $data;
    }
}
