<?php

declare(strict_types=1);

namespace App\Support\AutoBanned;

/**
 * Nama kolom aktual pada tabel scr_weekly_banned (scraping Tableau Weekly Banned).
 */
final class ScrWeeklyBannedColumns
{
    public const TABLE = 'scr_weekly_banned';

    public const SID = 'sid_pelapor_all_karyawan';

    public const NIK = 'nik_pelapor_all_karyawan';

    public const NAMA = 'pelapor_all_karyawan';

    public const PERUSAHAAN = 'perusahaan_pelapor_all_karyawan';

    public const SITE = 'site_dedicated_pelapor_all_karyawan';

    public const BANNED_REASON = 'Banned_Weekly_Reason';

    public const BANNED_STATUS = 'Status_Banned_Weekly';

    public const ONSITE_STATUS = 'Status_Onsite_Daily';

    public const ISO_YEAR = 'filter_iso_year';

    public const ISO_WEEK = 'filter_iso_week';
}
