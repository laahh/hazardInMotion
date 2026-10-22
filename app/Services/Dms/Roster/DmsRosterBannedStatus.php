<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\SidRosterBannedLog;
use App\Models\SidRosterTreatmentEvidence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Status penindakan banned per SID untuk tabel Scorecard Ringkasan Roster:
 * apakah orang itu sudah di-banned (sid_roster_banned_log), sudah treatment
 * (sid_roster_treatment_evidence / sid_roster_unban_log), atau belum tercatat.
 *
 * Yang dinilai adalah KASUS TERAKHIR (baris master dengan id terbesar) per
 * SID, dan log banned TERAKHIR per kasus (retry bisa membuat beberapa baris).
 * Tanggal banned = completed_at log itu, atau started_at / created_at bila kosong.
 * Urutan penentu:
 *   1. ada unban log selesai (DmsRosterBannedAktif::statusSelesai) → sudah treatment
 *   2. bukti treatment terakhir APPROVED                             → treatment disetujui, menunggu unban
 *   3. log banned terakhir SUCCESS atau FAILED                       → sudah banned
 *      (FAILED dianggap berhasil atas permintaan user)
 *   4. log SKIPPED / PROCESSING                                      → belum ter-banned
 *   5. belum ada log                                                 → menunggu eksekusi
 */
final class DmsRosterBannedStatus
{
    public const TIDAK_TERCATAT = 'NONE';

    /** @var array<string, array{0:string, 1:string}> kode => [label, warna badge] */
    public const LABEL = [
        'UNBANNED' => ['Sudah treatment', 'success'],
        'TREATMENT_OK' => ['Treatment disetujui', 'info'],
        'BANNED' => ['Sudah banned', 'danger'],
        'DILEWATI' => ['Banned dilewati', 'warning'],
        'PROSES' => ['Sedang diproses', 'info'],
        'ANTRE' => ['Menunggu eksekusi', 'secondary'],
        self::TIDAK_TERCATAT => ['Tidak tercatat', 'neutral'],
    ];

    /**
     * @param  list<string>  $sids
     * @return array<string, array{kode:string,label:string,warna:string,sub:string,tanggal_banned:string}>  SID tanpa kasus tidak ada di hasil.
     */
    public static function untukSid(array $sids): array
    {
        $sids = array_values(array_unique(array_filter(array_map('strval', $sids), static fn (string $s): bool => $s !== '')));
        if ($sids === []) {
            return [];
        }

        $selesai = DmsRosterBannedAktif::statusSelesai();
        $tanda = implode(',', array_fill(0, count($selesai), '?'));
        $out = [];

        foreach (array_chunk($sids, 1000) as $potong) {
            $rows = DB::table('sid_roster_banned_master as m')
                ->whereIn('m.sid', $potong)
                ->whereRaw('m.id = (SELECT MAX(m2.id) FROM sid_roster_banned_master m2 WHERE m2.sid = m.sid)')
                ->leftJoin('sid_roster_banned_log as bl', 'bl.id', '=', DB::raw('(SELECT MAX(l.id) FROM sid_roster_banned_log l WHERE l.master_id = m.id)'))
                ->select(['m.sid', 'm.tanggal_pelanggaran', 'bl.automation_status', 'bl.error_message'])
                // Tanggal banned: selesai, kalau tidak ada (mis. FAILED) mulai, lalu dicatat.
                ->selectRaw('COALESCE(bl.completed_at, bl.started_at, bl.created_at) AS banned_at')
                ->selectRaw("(SELECT MAX(COALESCE(u.completed_at, u.updated_at)) FROM sid_roster_unban_log u
                              WHERE u.master_id = m.id AND u.automation_status IN ({$tanda})) AS unban_at", $selesai)
                ->selectRaw('(SELECT e.approval_status FROM sid_roster_treatment_evidence e
                              WHERE e.master_id = m.id ORDER BY e.id DESC LIMIT 1) AS treatment')
                ->get();

            foreach ($rows as $r) {
                $out[(string) $r->sid] = self::nilai($r);
            }
        }

        return $out;
    }

    /**
     * @return array{kode:string,label:string,warna:string,sub:string}
     */
    public static function tidakTercatat(): array
    {
        return self::bentuk(self::TIDAK_TERCATAT, 'belum masuk Master Roster Banned');
    }

    /**
     * @return array{kode:string,label:string,warna:string,sub:string}
     */
    private static function nilai(object $r): array
    {
        $tgl = static fn ($t): string => $t ? Carbon::parse($t)->format('d/m/Y') : '';
        $treatment = (string) ($r->treatment ?? '');
        $bannedTgl = $r->banned_at !== null ? 'banned '.$tgl($r->banned_at) : '';
        $denganBanned = static fn (string $teks): string => $bannedTgl === '' ? $teks : $bannedTgl.' · '.$teks;

        if ($r->unban_at !== null) {
            return self::bentuk('UNBANNED', $denganBanned('unbanned '.$tgl($r->unban_at)), $tgl($r->banned_at));
        }
        if ($treatment === SidRosterTreatmentEvidence::STATUS_APPROVED) {
            return self::bentuk('TREATMENT_OK', $denganBanned('menunggu unban'), $tgl($r->banned_at));
        }

        return match ((string) ($r->automation_status ?? '')) {
            // FAILED sengaja dianggap sudah banned (keputusan user 2026-10-22).
            SidRosterBannedLog::STATUS_SUCCESS, SidRosterBannedLog::STATUS_FAILED => self::bentuk('BANNED', $denganBanned(match ($treatment) {
                SidRosterTreatmentEvidence::STATUS_PENDING => 'bukti treatment menunggu review',
                SidRosterTreatmentEvidence::STATUS_REJECTED => 'bukti treatment ditolak',
                default => 'belum treatment',
            }), $tgl($r->banned_at)),
            SidRosterBannedLog::STATUS_SKIPPED => self::bentuk('DILEWATI', mb_strimwidth((string) $r->error_message, 0, 80, '…')),
            SidRosterBannedLog::STATUS_PROCESSING => self::bentuk('PROSES', 'automation sedang berjalan'),
            default => self::bentuk('ANTRE', 'tercatat '.$tgl($r->tanggal_pelanggaran).', belum ada log banned'),
        };
    }

    /**
     * @return array{kode:string,label:string,warna:string,sub:string,tanggal_banned:string}
     */
    private static function bentuk(string $kode, string $sub, string $tanggalBanned = ''): array
    {
        [$label, $warna] = self::LABEL[$kode];

        return ['kode' => $kode, 'label' => $label, 'warna' => $warna, 'sub' => $sub, 'tanggal_banned' => $tanggalBanned];
    }
}
