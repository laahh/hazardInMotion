<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedBanStatus;
use App\Enums\AutoBannedHsctSyncStatus;
use App\Enums\AutoBannedSystemStatus;
use App\Enums\AutoBannedTreatmentStatus;
use App\Enums\AutoBannedVerificationStatus;
use App\Models\AutoBannedStatusSnapshot;
use Carbon\CarbonInterface;

class AutoBannedSlaCalculator
{
    public const BANNED_SLA_DAYS = 3;

    public const UNBAN_SLA_DAYS = 3;

    /** Target unban otomatis: 24 jam setelah waktu banned (completed_at). */
    public const AUTOMATION_UNBAN_HOURS = 24;

    public const TREATMENT_DEADLINE_DAYS = 1;

    public const VERIFICATION_DEADLINE_DAYS = 1;

    /**
     * @return array{
     *     followUpLabel: string,
     *     slaBannedLabel: string,
     *     slaBannedDetail: string,
     *     slaBannedTone: string,
     *     slaUnbannedLabel: string,
     *     slaUnbannedDetail: string,
     *     slaUnbannedTone: string,
     *     remainingBannedLabel: string,
     *     remainingBannedDetail: string,
     *     banStatus: AutoBannedBanStatus
     * }
     */
    public function resolve(AutoBannedStatusSnapshot $snapshot, CarbonInterface $now): array
    {
        $followUpLabel = $this->buildFollowUpLabel($snapshot);

        if ($snapshot->system_status !== AutoBannedSystemStatus::NotPassed) {
            return [
                'followUpLabel' => $followUpLabel,
                'slaBannedLabel' => '—',
                'slaBannedDetail' => '—',
                'slaBannedTone' => 'muted',
                'slaUnbannedLabel' => '—',
                'slaUnbannedDetail' => '—',
                'slaUnbannedTone' => 'muted',
                'remainingBannedLabel' => '—',
                'remainingBannedDetail' => '—',
                'banStatus' => $snapshot->ban_status,
            ];
        }

        $slaBanned = $this->resolveBannedSla($snapshot, $now);

        $slaUnbannedLabel = '—';
        $slaUnbannedDetail = '—';
        $slaUnbannedTone = 'muted';
        $remainingBannedLabel = $slaBanned['remainingBannedLabel'];
        $remainingBannedDetail = $slaBanned['remainingBannedDetail'];
        $banStatus = $slaBanned['banStatus'];

        if ($snapshot->verification_status === AutoBannedVerificationStatus::Done
            || $snapshot->verification_status === AutoBannedVerificationStatus::DoneOverdue) {
            $unbanStart = $snapshot->unban_opened_at ?? $snapshot->verification_done_at ?? $now;
            $unbanDeadline = $unbanStart->copy()->addDays(self::UNBAN_SLA_DAYS);
            $unbanOverdue = $now->greaterThan($unbanDeadline);

            $banStatus = $unbanOverdue
                ? AutoBannedBanStatus::OverdueUnbanned
                : ($snapshot->unban_closed_at
                    ? AutoBannedBanStatus::ClosedUnbanned
                    : AutoBannedBanStatus::OpenUnbanned);

            $slaUnbannedLabel = $unbanOverdue ? 'Counting overdue…' : 'Counting open…';

            $doneDay = (int) ($snapshot->verification_done_at ?? $unbanStart)->format('j');
            $unbanDay = (int) $unbanStart->format('j');
            $unbanDelta = max(0, (int) ($snapshot->verification_done_at ?? $unbanStart)->copy()->startOfDay()->diffInDays($unbanStart->copy()->startOfDay()));
            $slaUnbannedDetail = "delta waktu Done Verifikasi - waktu unbanned ({$doneDay} - {$unbanDay} = {$unbanDelta} hari)";
            $slaUnbannedTone = $unbanOverdue ? 'danger' : 'wait';

            $remainingDays = $unbanDeadline->isFuture() ? (int) $now->diffInDays($unbanDeadline) : 0;
            $remainingBannedLabel = $unbanOverdue ? 'Overdue' : "{$remainingDays} hari";
            $remainingBannedDetail = 'hari dia harus unbanned - waktu sekarang';
        } elseif ($snapshot->treatment_status === AutoBannedTreatmentStatus::Submitted) {
            $banStatus = AutoBannedBanStatus::OnTreatmentBanned;
            $remainingBannedLabel = 'On treatment';
            $remainingBannedDetail = 'menunggu verifikasi SOD';
        }

        return [
            'followUpLabel' => $followUpLabel,
            'slaBannedLabel' => $slaBanned['slaBannedLabel'],
            'slaBannedDetail' => $slaBanned['slaBannedDetail'],
            'slaBannedTone' => $slaBanned['slaBannedTone'],
            'slaUnbannedLabel' => $slaUnbannedLabel,
            'slaUnbannedDetail' => $slaUnbannedDetail,
            'slaUnbannedTone' => $slaUnbannedTone,
            'remainingBannedLabel' => $remainingBannedLabel,
            'remainingBannedDetail' => $remainingBannedDetail,
            'banStatus' => $banStatus,
        ];
    }

    /**
     * @return array{
     *     slaBannedLabel: string,
     *     slaBannedDetail: string,
     *     slaBannedTone: string,
     *     remainingBannedLabel: string,
     *     remainingBannedDetail: string,
     *     banStatus: AutoBannedBanStatus
     * }
     */
    private function resolveBannedSla(AutoBannedStatusSnapshot $snapshot, CarbonInterface $now): array
    {
        if ($snapshot->ban_status === AutoBannedBanStatus::CloseBanned
            || $snapshot->hsct_sync_status === AutoBannedHsctSyncStatus::Confirmed) {
            $sentAt = $snapshot->hsct_sent_at ?? $snapshot->first_seen_at;
            $confirmedAt = $snapshot->hsct_confirmed_at ?? $now;
            $sentDay = (int) $sentAt->format('j');
            $confirmedDay = (int) $confirmedAt->format('j');
            $delta = max(0, (int) $sentAt->copy()->startOfDay()->diffInDays($confirmedAt->copy()->startOfDay()));

            return [
                'slaBannedLabel' => "Closed ({$delta} hari)",
                'slaBannedDetail' => "delta waktu banned - waktu list dikirim ({$confirmedDay} - {$sentDay} = {$delta} hari)",
                'slaBannedTone' => 'muted',
                'remainingBannedLabel' => '—',
                'remainingBannedDetail' => '—',
                'banStatus' => AutoBannedBanStatus::CloseBanned,
            ];
        }

        if ($snapshot->hsct_sent_at === null) {
            return [
                'slaBannedLabel' => '—',
                'slaBannedDetail' => 'Menunggu email HSECT terkirim',
                'slaBannedTone' => 'muted',
                'remainingBannedLabel' => '—',
                'remainingBannedDetail' => '—',
                'banStatus' => AutoBannedBanStatus::OpenBanned,
            ];
        }

        $sentAt = $snapshot->hsct_sent_at;
        $bannedDeadline = $sentAt->copy()->addDays(self::BANNED_SLA_DAYS);
        $bannedOverdue = $now->greaterThan($bannedDeadline);

        $sentDay = (int) $sentAt->format('j');
        $nowDay = (int) $now->format('j');
        $listBannedDelta = max(0, (int) $sentAt->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()));

        $slaBannedLabel = $bannedOverdue ? 'Counting overdue…' : 'Counting open…';
        $slaBannedDetail = "delta waktu banned - waktu list dikirim ({$nowDay} - {$sentDay} = {$listBannedDelta} hari)";

        $remainingDays = $bannedDeadline->isFuture() ? (int) $now->diffInDays($bannedDeadline) : 0;

        return [
            'slaBannedLabel' => $slaBannedLabel,
            'slaBannedDetail' => $slaBannedDetail,
            'slaBannedTone' => $bannedOverdue ? 'danger' : 'wait',
            'remainingBannedLabel' => $bannedOverdue ? 'Overdue' : "{$remainingDays} hari",
            'remainingBannedDetail' => 'hari dia harus unbanned - waktu sekarang',
            'banStatus' => $bannedOverdue
                ? AutoBannedBanStatus::OverdueBanned
                : AutoBannedBanStatus::OpenBanned,
        ];
    }

    private function buildFollowUpLabel(AutoBannedStatusSnapshot $snapshot): string
    {
        if ($snapshot->hsct_confirmed_at !== null) {
            return 'Banned '.$snapshot->hsct_confirmed_at->format('j F Y, h:i a');
        }

        if ($snapshot->hsct_sent_at !== null) {
            return 'List dikirim '.$snapshot->hsct_sent_at->format('j F Y, h:i a');
        }

        if ($snapshot->system_status === AutoBannedSystemStatus::NotPassed && $snapshot->banned_detected_at !== null) {
            return 'Detected '.$snapshot->banned_detected_at->format('j F Y, h:i a');
        }

        if ($snapshot->first_seen_at !== null) {
            return 'Registered '.$snapshot->first_seen_at->format('j F Y, h:i a');
        }

        return '—';
    }
}
