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
 *
 * Site tambahan HO (kantor, di luar IUPK sehingga tidak dikenal
 * IscSiteNormalizer): check-in yang belum punya site dan gate-nya bertuliskan
 * "HO" (mis. "HO - POS DEPAN HO") diberi site_code HO.
 */
final class OhsDivisiPobService
{
    public const SITE_HO = 'HO';

    public const LABEL_HO = 'Head Office';

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
            if ($dep === null) {
                continue;
            }
            if (($row['site_code'] ?? null) === null && self::gateHo((string) ($row['gate'] ?? ''))) {
                $row['site_code'] = self::SITE_HO;
                $row['site_label'] = self::LABEL_HO;
            }
            $checkins[] = $row + ['department' => $dep];
        }

        $perSite = [];
        foreach (IscSiteNormalizer::SITES as $kode => $_label) {
            $perSite[$kode] = 0;
        }
        $perSite[self::SITE_HO] = 0;
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
            array_values(array_filter($summary['checkin_by_site'] ?? [], static fn (array $s): bool => $s['code'] !== self::SITE_HO)),
        );
        $summary['checkin_by_site'][] = ['code' => self::SITE_HO, 'label' => self::LABEL_HO, 'count' => $perSite[self::SITE_HO]];

        $reconcile = $data['reconcile'] ?? [];
        $reconcile['rfid_count'] = count($checkins);
        // Sama dengan IscPobSnapshotService::applyGapRfidTanpaGps(): check-in − GPS aktif.
        $reconcile['gap_rfid_minus_besigma_count'] = max(0, count($checkins) - (int) ($reconcile['current_count'] ?? 0));

        return ['checkins' => $checkins, 'summary' => $summary, 'reconcile' => $reconcile] + $data;
    }

    /** Gate kantor HO: kata "HO" berdiri sendiri ("HO - POS DEPAN HO"), bukan bagian kata lain. */
    public static function gateHo(string $gate): bool
    {
        return preg_match('/(^|[^A-Z0-9])HO([^A-Z0-9]|$)/', mb_strtoupper($gate)) === 1;
    }
}
