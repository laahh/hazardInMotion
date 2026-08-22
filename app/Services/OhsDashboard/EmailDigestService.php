<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard;

use App\Exceptions\OhsDashboard\OhsDashboardException;
use App\Mail\OhsDashboard\PortalDigestMail;
use App\Models\OhsDashboard\EmailSchedulerSetting;
use App\Services\OhsDashboard\Support\OhsDashboardPayload;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class EmailDigestService
{
    public function __construct(
        private readonly OhsDashboardSupport $support,
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $row = EmailSchedulerSetting::instance();
        $portalUrl = $this->resolvedPortalUrl($row);

        return [
            'Enabled' => (bool) $row->enabled,
            'Frequency' => $row->frequency,
            'ScheduleDays' => $row->schedule_days,
            'SendHour' => (int) $row->send_hour,
            'SendMinute' => (int) $row->send_minute,
            'Recipients' => $row->recipients,
            'Cc' => $row->cc,
            'Bcc' => $row->bcc,
            'PortalUrl' => $portalUrl,
            'OverviewTeam' => $row->overview_team,
            'OverviewSite' => $row->overview_site,
            'IncludeLeaveSummary' => (bool) $row->include_leave_summary,
            'IncludeTrackerSummary' => (bool) $row->include_tracker_summary,
            'IncludeLeaderboard' => (bool) $row->include_leaderboard,
            'SubjectPrefix' => $row->subject_prefix,
            'EventReminderDays' => $row->event_reminder_days,
            'IncludePreviousDays' => (int) $row->include_previous_days,
            'LastScheduledKey' => $row->last_scheduled_key,
            'LastRunAt' => $this->support->formatDateTime($row->last_run_at),
            'LastRunStatus' => $row->last_run_status,
            'LastEmailCount' => (int) $row->last_email_count,
            'UpdatedAt' => $this->support->formatDateTime($row->updated_at),
            'UpdatedBy' => $row->updated_by,
            'OverdueReminderLastKey' => $row->overdue_reminder_last_key,
            'OverdueReminderLastRunAt' => $this->support->formatDateTime($row->overdue_reminder_last_run_at),
            'OverdueReminderLastCount' => (int) $row->overdue_reminder_last_count,
            'TimeZone' => $this->support->timezone(),
            'CronNote' => 'Laravel Scheduler: ohs-dashboard:digest, ohs-dashboard:overdue-reminder setiap menit (window 75 menit, timezone Asia/Jakarta). Data karyawan real-time dari database HSE, tidak perlu sinkronisasi. OS cron: * * * * * php artisan schedule:run',
            'MailQuotaRemaining' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function save(array $payload): array
    {
        $row = EmailSchedulerSetting::instance();
        $enabled = $this->support->toBoolean(OhsDashboardPayload::raw($payload, 'Enabled'), false);
        $days = $this->support->normalizeScheduleDays(OhsDashboardPayload::raw($payload, 'ScheduleDays') ?? $row->schedule_days);
        $hour = $this->support->clampInteger(OhsDashboardPayload::raw($payload, 'SendHour'), 0, 23, (int) $row->send_hour);
        $minuteRaw = (int) (OhsDashboardPayload::raw($payload, 'SendMinute') ?? $row->send_minute);
        if (! in_array($minuteRaw, [0, 15, 30, 45], true)) {
            throw new OhsDashboardException('SendMinute hanya boleh 0, 15, 30, atau 45.');
        }
        $recipients = OhsDashboardPayload::string($payload, 'Recipients');
        $portalUrl = OhsDashboardPayload::string($payload, 'PortalUrl');

        if ($enabled) {
            if ($this->support->parseEmailList($recipients) === []) {
                throw new OhsDashboardException('Recipients wajib diisi jika scheduler aktif.');
            }
            if ($days === []) {
                throw new OhsDashboardException('Minimal satu hari harus dipilih jika scheduler aktif.');
            }
            if ($portalUrl === '' || ! preg_match('#^https?://#i', $portalUrl)) {
                throw new OhsDashboardException('PortalUrl wajib diisi dengan URL http(s):// jika scheduler aktif.');
            }
        } elseif ($recipients !== '') {
            $this->support->parseEmailList($recipients);
        }

        $cc = OhsDashboardPayload::string($payload, 'Cc');
        $bcc = OhsDashboardPayload::string($payload, 'Bcc');
        if ($cc !== '') {
            $this->support->parseEmailList($cc);
        }
        if ($bcc !== '') {
            $this->support->parseEmailList($bcc);
        }

        $row->fill([
            'enabled' => $enabled,
            'frequency' => 'SELECTED_DAYS',
            'schedule_days' => implode(',', $days),
            'send_hour' => $hour,
            'send_minute' => $minuteRaw,
            'recipients' => $recipients,
            'cc' => $cc,
            'bcc' => $bcc,
            'portal_url' => $portalUrl,
            'overview_team' => OhsDashboardPayload::string($payload, 'OverviewTeam') ?: 'All Teams',
            'overview_site' => OhsDashboardPayload::string($payload, 'OverviewSite') ?: 'All Sites',
            'include_leave_summary' => $this->support->toBoolean(OhsDashboardPayload::raw($payload, 'IncludeLeaveSummary'), true),
            'include_tracker_summary' => $this->support->toBoolean(OhsDashboardPayload::raw($payload, 'IncludeTrackerSummary'), true),
            'include_leaderboard' => $this->support->toBoolean(OhsDashboardPayload::raw($payload, 'IncludeLeaderboard'), true),
            'subject_prefix' => OhsDashboardPayload::string($payload, 'SubjectPrefix') ?: '[OHS Portal]',
            'event_reminder_days' => OhsDashboardPayload::string($payload, 'EventReminderDays') ?: '0,1,3,7',
            'include_previous_days' => $this->support->clampInteger(OhsDashboardPayload::raw($payload, 'IncludePreviousDays'), 0, 365, 7),
            'updated_at' => $this->support->now(),
            'updated_by' => OhsDashboardPayload::string($payload, 'UpdatedBy'),
        ]);
        $row->save();

        return $this->settings();
    }

    /**
     * @return array{sent: bool, message: string, count: int}
     */
    public function sendNow(bool $isTest = false, bool $respectSchedule = false): array
    {
        $row = EmailSchedulerSetting::instance();
        $now = $this->support->now();

        if ($respectSchedule) {
            $decision = $this->support->getPortalSchedulerDecision(
                (bool) $row->enabled,
                $this->support->normalizeScheduleDays($row->schedule_days),
                (int) $row->send_hour,
                (int) $row->send_minute,
                (string) $row->last_scheduled_key,
                $now,
            );
            if (! $decision['shouldRun']) {
                return ['sent' => false, 'message' => $decision['reason'], 'count' => 0];
            }
        }

        try {
            $overview = $this->dashboardService->overview([
                'team' => $row->overview_team,
                'site' => $row->overview_site,
                'year' => (int) $now->year,
            ]);
            $limit = (int) config('ohs-dashboard.dashboard.email_table_limit', 100);
            $leaderLimit = (int) config('ohs-dashboard.dashboard.email_leaderboard_limit', 10);
            $portalUrl = $this->resolvedPortalUrl($row);
            $dateLabel = $now->translatedFormat('d M Y') ?: $now->format('d M Y');
            $prefix = $row->subject_prefix !== '' ? $row->subject_prefix : '[OHS Portal]';
            $subject = $isTest
                ? $prefix.' TEST - Overview Dashboard - '.$dateLabel
                : $prefix.' Overview Dashboard - '.$dateLabel;

            $to = $this->support->parseEmailList((string) $row->recipients);
            if ($to === []) {
                throw new OhsDashboardException('Recipients kosong.');
            }

            $mailable = new PortalDigestMail(
                overview: $overview,
                portalUrl: $portalUrl,
                isTest: $isTest,
                includeLeave: (bool) $row->include_leave_summary,
                includeTracker: (bool) $row->include_tracker_summary,
                includeLeaderboard: (bool) $row->include_leaderboard,
                tableLimit: $limit,
                leaderboardLimit: $leaderLimit,
                subjectLine: $subject,
            );

            $pending = Mail::to($to)->mailer(config('mail.default'));
            $cc = $row->cc !== '' ? $this->support->parseEmailList((string) $row->cc) : [];
            $bcc = $row->bcc !== '' ? $this->support->parseEmailList((string) $row->bcc) : [];
            if ($cc !== []) {
                $pending->cc($cc);
            }
            if ($bcc !== []) {
                $pending->bcc($bcc);
            }
            $pending->send($mailable);

            $row->last_run_at = $now;
            $row->last_run_status = $isTest ? 'Test email terkirim.' : 'Digest terkirim.';
            $row->last_email_count = count($to);
            if ($respectSchedule) {
                $row->last_scheduled_key = $this->support->getPortalSchedulerDecision(
                    true,
                    $this->support->normalizeScheduleDays($row->schedule_days),
                    (int) $row->send_hour,
                    (int) $row->send_minute,
                    '',
                    $now,
                )['key'];
            }
            $row->save();

            return ['sent' => true, 'message' => $row->last_run_status, 'count' => count($to)];
        } catch (Throwable $e) {
            report($e);
            $row->last_run_at = $now;
            $row->last_run_status = 'Gagal: '.$e->getMessage();
            $row->save();

            throw new OhsDashboardException('Gagal mengirim email: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{sent: bool, message: string, count: int}
     */
    public function runScheduled(): array
    {
        return $this->sendNow(false, true);
    }

    private function resolvedPortalUrl(EmailSchedulerSetting $row): string
    {
        $url = trim((string) $row->portal_url);
        if ($url !== '') {
            return $url;
        }

        return (string) config('ohs-dashboard.portal_url');
    }
}
