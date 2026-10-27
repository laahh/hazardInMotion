<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Definisi tunggal "kasus banned yang masih aktif" di Master Roster Banned.
 *
 * Satu orang boleh punya beberapa baris di sid_roster_banned_master — satu
 * per kejadian banned. Sebuah baris (kasus) dianggap SELESAI bila ada log di
 * sid_roster_unban_log untuk master_id-nya dengan status yang menandakan SID
 * sudah tidak ter-banned (config dms_roster.reg_live.status_unban_selesai):
 *   SUCCESS  — unban berhasil dieksekusi
 *   SKIPPED  — "BAN SID sudah tidak ada di Work Permit (dianggap sudah ter-unban)"
 * Log FAILED / PROCESSING tidak dihitung: unban-nya belum terjadi.
 *
 * Orang dengan setidaknya satu kasus AKTIF tidak dimunculkan di kartu aturan
 * banned dan tidak dimasukkan lagi ke master. Setelah semua kasusnya selesai
 * (sudah di-unban), ia boleh di-banned lagi sebagai baris master baru.
 */
final class DmsRosterBannedAktif
{
    /**
     * Baris master yang kasusnya masih aktif (belum di-unban).
     */
    public static function query(): Builder
    {
        $selesai = self::statusSelesai();

        return DB::table('sid_roster_banned_master as m')
            ->whereNotExists(static fn (Builder $q) => $q
                ->from('sid_roster_unban_log as u')
                ->whereColumn('u.master_id', 'm.id')
                ->whereIn('u.automation_status', $selesai));
    }

    /**
     * Baris master yang menunggu dieksekusi: belum di-unban DAN belum punya
     * satu pun baris di sid_roster_banned_log — sama dengan filter "Belum
     * Banned" + "Belum Ada Log" di halaman Master Roster Banned.
     */
    public static function menungguEksekusi(): Builder
    {
        return self::query()->whereNotExists(static fn (Builder $q) => $q
            ->from('sid_roster_banned_log as bl')
            ->whereColumn('bl.master_id', 'm.id'));
    }

    /**
     * Sidik ringkas isi master + log unban — berubah begitu ada orang
     * di-banned, diubah, atau di-unban. Dipakai sebagai bagian kunci cache.
     */
    public static function versi(): string
    {
        $m = DB::table('sid_roster_banned_master')->selectRaw('COUNT(*) AS n, MAX(updated_at) AS t')->first();
        $u = DB::table('sid_roster_unban_log')->selectRaw('COUNT(*) AS n, MAX(updated_at) AS t')->first();

        return md5(($m->n ?? 0).'|'.($m->t ?? '').'|'.($u->n ?? 0).'|'.($u->t ?? ''));
    }

    /**
     * Tanggal unban selesai terakhir per SID (kasus yang sudah ditutup).
     * Dipakai supaya periode on-site yang SAMA dengan yang sudah ditindak
     * tidak dihukum dua kali: setelah di-unban, seseorang baru dihitung
     * melanggar lagi bila periode on-site barunya dimulai SESUDAH tanggal ini.
     *
     * @param  list<string>  $sids
     * @return array<string, string>  SID => Y-m-d
     */
    public static function unbanTerakhir(array $sids): array
    {
        if ($sids === []) {
            return [];
        }

        return DB::table('sid_roster_unban_log as u')
            ->join('sid_roster_banned_master as m', 'm.id', '=', 'u.master_id')
            ->whereIn('m.sid', $sids)
            ->whereIn('u.automation_status', self::statusSelesai())
            ->groupBy('m.sid')
            ->selectRaw('m.sid AS sid, DATE(MAX(COALESCE(u.completed_at, u.updated_at, u.created_at))) AS tgl')
            ->pluck('tgl', 'sid')
            ->map(static fn ($t): string => (string) $t)
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function statusSelesai(): array
    {
        /** @var list<string> $status */
        $status = config('dms_roster.reg_live.status_unban_selesai', ['SUCCESS', 'SKIPPED']);

        return $status;
    }
}
