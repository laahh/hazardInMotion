<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard;

use App\Mail\OhsDashboard\OverdueReminderMail;
use App\Models\OhsDashboard\EmailSchedulerSetting;
use App\Models\OhsDashboard\ProjectIssueSubTask;
use App\Models\OhsDashboard\ProjectIssueTracker;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class OverdueReminderService
{
    public function __construct(
        private readonly OhsDashboardSupport $support,
        private readonly TrackerService $trackerService,
    ) {}

    /**
     * @return array{sent: bool, message: string, count: int}
     */
    public function sendNow(bool $respectSchedule = false): array
    {
        $row = EmailSchedulerSetting::instance();
        $now = $this->support->now();
        $todayISO = $this->support->todayISO();

        if ($respectSchedule) {
            $decision = $this->support->getOverdueReminderDecision((string) $row->overdue_reminder_last_key, $now);
            if (! $decision['shouldRun']) {
                return ['sent' => false, 'message' => $decision['reason'], 'count' => 0];
            }
        }

        $items = $this->collectItems();
        $count = count($items);

        try {
            if ($count > 0) {
                $dateLabel = $now->format('d M Y');
                $subject = '[OHS Portal] Reminder Due Date Project & Issue Tracker - '.$dateLabel.' ('.$count.' item)';
                $recipients = config('ohs-dashboard.overdue_reminder_recipients', []);
                Mail::to($recipients)->send(new OverdueReminderMail($items, $subject));
            }

            $row->overdue_reminder_last_key = $todayISO;
            $row->overdue_reminder_last_run_at = $now;
            $row->overdue_reminder_last_count = $count;
            $row->save();

            return [
                'sent' => $count > 0,
                'message' => $count > 0 ? 'Reminder terkirim ('.$count.' item).' : 'Tidak ada item due date H-3 s/d H-0. Email tidak dikirim.',
                'count' => $count,
            ];
        } catch (Throwable $e) {
            report($e);
            $row->overdue_reminder_last_key = $todayISO;
            $row->overdue_reminder_last_run_at = $now;
            $row->overdue_reminder_last_count = 0;
            $row->save();

            throw $e;
        }
    }

    public function runScheduled(): array
    {
        return $this->sendNow(true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function collectItems(): array
    {
        $today = $this->support->today();
        $windowDays = (int) config('ohs-dashboard.scheduler.overdue_window_days', 3);
        $until = $today->copy()->addDays($windowDays);
        $todayIso = $this->support->formatISO($today);
        $untilIso = $this->support->formatISO($until);
        $items = [];

        $trackers = ProjectIssueTracker::query()->with('subTasks')->get();
        foreach ($trackers as $tracker) {
            $this->trackerService->refreshEffectiveStatus($tracker);
            if ($tracker->subTasks->isNotEmpty()) {
                foreach ($tracker->subTasks as $task) {
                    if ($this->isDueWindow($task->status, $task->due_date?->format('Y-m-d'), $todayIso, $untilIso)) {
                        $items[] = $this->rowFromSubTask($tracker, $task, $todayIso);
                    }
                }
                continue;
            }

            if ($this->isDueWindow($tracker->status, $tracker->due_date?->format('Y-m-d'), $todayIso, $untilIso)) {
                $items[] = $this->rowFromParent($tracker, $todayIso);
            }
        }

        return $items;
    }

    private function isDueWindow(string $status, ?string $due, string $todayIso, string $untilIso): bool
    {
        return $status === 'On Going' && $due !== null && $due >= $todayIso && $due <= $untilIso;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFromParent(ProjectIssueTracker $tracker, string $todayIso): array
    {
        $due = $tracker->due_date?->format('Y-m-d') ?? '';

        return [
            'sisaHari' => $this->sisaHari($due, $todayIso),
            'tipe' => $tracker->tracker_type,
            'projectIssue' => $tracker->project_issue_name,
            'item' => $tracker->project_issue_name,
            'pic' => $tracker->project_leader_name.' • '.$tracker->project_leader_team.' • '.$tracker->project_leader_site_dedicated,
            'dueDate' => $due,
            'percent' => (float) $tracker->current_percent_complete,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFromSubTask(ProjectIssueTracker $tracker, ProjectIssueSubTask $task, string $todayIso): array
    {
        $due = $task->due_date?->format('Y-m-d') ?? '';

        return [
            'sisaHari' => $this->sisaHari($due, $todayIso),
            'tipe' => $tracker->tracker_type,
            'projectIssue' => $tracker->project_issue_name,
            'item' => $task->sub_task_name,
            'pic' => $task->pic_name.' • '.$task->pic_team.' • '.$task->pic_site_dedicated,
            'dueDate' => $due,
            'percent' => (float) $task->current_percent_complete,
        ];
    }

    private function sisaHari(string $due, string $todayIso): string
    {
        $days = (int) $this->support->parseISO($todayIso)->diffInDays($this->support->parseISO($due), false);

        return $days === 0 ? 'Hari ini' : 'H-'.$days;
    }
}
