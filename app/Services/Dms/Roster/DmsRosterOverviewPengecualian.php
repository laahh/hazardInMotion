<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

/**
 * Perusahaan & site yang tidak dihitung di Overview Roster
 * (config dms_roster.master.overview_dikecualikan): daftar perusahaan
 * ter-upload, KPI, dan kartu cakupan. Data roster-nya sendiri tidak disentuh.
 */
final class DmsRosterOverviewPengecualian
{
    public static function perusahaan(string $nama): bool
    {
        foreach ((array) config('dms_roster.master.overview_dikecualikan.perusahaan', []) as $pt) {
            if (DmsRosterAksesPerusahaan::samaNama((string) $pt, $nama)) {
                return true;
            }
        }

        return false;
    }

    public static function site(string $site): bool
    {
        $k = self::kunciSite($site);
        if ($k === '') {
            return false;
        }
        foreach ((array) config('dms_roster.master.overview_dikecualikan.site', []) as $s) {
            if (self::kunciSite((string) $s) === $k) {
                return true;
            }
        }

        return false;
    }

    /**
     * Karyawan aktif per perusahaan (DmsRosterKaryawanAktif::perusahaan())
     * tanpa perusahaan & site yang dikecualikan; jumlah dihitung ulang.
     *
     * @param  list<array{nama:string,jumlah:int,site:list<array{nama:string,jumlah:int}>}>  $aktif
     * @return list<array{nama:string,jumlah:int,site:list<array{nama:string,jumlah:int}>}>
     */
    public static function saringAktif(array $aktif): array
    {
        $out = [];
        foreach ($aktif as $pt) {
            if (self::perusahaan($pt['nama'])) {
                continue;
            }
            $site = array_values(array_filter($pt['site'], static fn (array $s): bool => ! self::site($s['nama'])));
            if ($site === []) {
                continue;
            }
            $out[] = ['nama' => $pt['nama'], 'jumlah' => array_sum(array_column($site, 'jumlah')), 'site' => $site];
        }

        return $out;
    }

    /**
     * @param  list<array{perusahaan:string,site:string}>  $upload  DmsRosterMasterService::uploadPerSite()
     * @return list<array{perusahaan:string,site:string}>
     */
    public static function saringUpload(array $upload): array
    {
        return array_values(array_filter($upload, static fn (array $u): bool => ! self::perusahaan($u['perusahaan']) && ! self::site($u['site'])));
    }

    /**
     * SID aktif per perusahaan per site (DmsRosterKaryawanAktif::sidPerPerusahaan()).
     *
     * @param  array<string, array<string, list<string>>>|null  $sid
     * @return array<string, array<string, list<string>>>|null
     */
    public static function saringSid(?array $sid): ?array
    {
        if ($sid === null) {
            return null;
        }
        $out = [];
        foreach ($sid as $pt => $perSite) {
            if (self::perusahaan((string) $pt)) {
                continue;
            }
            $out[$pt] = array_filter($perSite, static fn (string $s): bool => ! self::site($s), ARRAY_FILTER_USE_KEY);
        }

        return $out;
    }

    private static function kunciSite(string $site): string
    {
        return mb_strtoupper(preg_replace('/[^\p{L}\p{N}]+/u', '', $site) ?? $site);
    }
}
