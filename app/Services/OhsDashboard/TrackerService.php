<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard;

use App\Exceptions\OhsDashboard\OhsDashboardException;
use App\Models\OhsDashboard\Employee;
use App\Models\OhsDashboard\ProjectIssueSubTask;
use App\Models\OhsDashboard\ProjectIssueSubTaskUpdateLog;
use App\Models\OhsDashboard\ProjectIssueTracker;
use App\Models\OhsDashboard\ProjectIssueUpdateLog;
use App\Services\OhsDashboard\Support\OhsDashboardId;
use App\Services\OhsDashboard\Support\OhsDashboardPayload;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class TrackerService
{
    public function __construct(private readonly OhsDashboardSupport $support) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $type = $this->support->normalizeTrackerType(OhsDashboardPayload::string($payload, 'TrackerType'));
        $name = OhsDashboardPayload::string($payload, 'ProjectIssueName');
        $department = OhsDashboardPayload::string($payload, 'Department');
        $site = OhsDashboardPayload::string($payload, 'Site');
        $leaderId = OhsDashboardPayload::string($payload, 'ProjectLeaderEmpId');
        $description = OhsDashboardPayload::string($payload, 'DescriptionProject');
        $background = OhsDashboardPayload::string($payload, 'BackgroundProject');
        $impact = OhsDashboardPayload::string($payload, 'ImpactProject');
        $startRaw = OhsDashboardPayload::string($payload, 'StartDate');
        $dueRaw = OhsDashboardPayload::string($payload, 'DueDate');
        $success = OhsDashboardPayload::string($payload, 'SuccessIndicator');

        $this->assertRequired([
            'ProjectIssueName' => $name,
            'Department' => $department,
            'Site' => $site,
            'DescriptionProject' => $description,
            'BackgroundProject' => $background,
            'ImpactProject' => $impact,
            'SuccessIndicator' => $success,
        ]);

        $leader = $this->support->requireEmployee($leaderId, 'Project Leader');
        $start = $this->support->parseISO($startRaw);
        $due = $this->support->parseISO($dueRaw);
        if ($due->lt($start)) {
            throw new OhsDashboardException('DueDate harus sama atau setelah StartDate.');
        }

        $subTaskPayloads = $this->normalizeIncomingSubTasks(OhsDashboardPayload::array($payload, 'SubTasks'));
        $percentRaw = OhsDashboardPayload::raw($payload, 'PercentComplete', 'current_percent_complete');
        $parentPercent = $percentRaw === null || $percentRaw === '' ? 0.0 : $this->support->normalizePercentComplete($percentRaw);

        $trackerId = $type === 'Project' ? OhsDashboardId::project() : OhsDashboardId::issue();
        $now = $this->support->now();

        return DB::transaction(function () use (
            $trackerId, $type, $name, $department, $site, $leader, $description, $background, $impact,
            $start, $due, $success, $subTaskPayloads, $parentPercent, $now
        ): array {
            $status = $this->support->deriveTrackerStatus($parentPercent, $due);
            $tracker = ProjectIssueTracker::query()->create([
                'tracker_id' => $trackerId,
                'timestamp' => $now,
                'tracker_type' => $type,
                'project_issue_name' => $name,
                'department' => $department !== '' ? $department : ($leader->team ?? ''),
                'site' => $site,
                'project_leader_emp_id' => $leader->emp_id,
                'project_leader_name' => $leader->emp_name,
                'project_leader_team' => $leader->team,
                'project_leader_position' => $leader->position,
                'project_leader_site_dedicated' => $leader->site_dedicated,
                'description_project' => $description,
                'background_project' => $background,
                'impact_project' => $impact,
                'start_date' => $this->support->formatISO($start),
                'due_date' => $this->support->formatISO($due),
                'success_indicator' => $success,
                'current_percent_complete' => $parentPercent,
                'current_progress_report_weekly' => 'Tracker dibuat.',
                'current_remarks' => 'Belum ada catatan tambahan.',
                'status' => $status,
                'last_updated' => $now,
            ]);

            if ($subTaskPayloads === []) {
                $this->insertParentLog($tracker, $parentPercent, 'Tracker dibuat.', 'Belum ada catatan tambahan.', $status, $leader, $now);
            } else {
                foreach ($subTaskPayloads as $row) {
                    $this->createSubTask($tracker, $row, $leader, $now, true);
                }
                $this->syncTrackerAggregate($tracker);
            }

            $count = ProjectIssueSubTask::query()->where('tracker_id', $trackerId)->count();

            return [
                'trackerId' => $trackerId,
                'status' => ProjectIssueTracker::query()->find($trackerId)?->status ?? $status,
                'subTaskCount' => $count,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateDetails(array $payload): array
    {
        $tracker = $this->requireTracker(OhsDashboardPayload::string($payload, 'TrackerId'));
        $type = $this->support->normalizeTrackerType(OhsDashboardPayload::string($payload, 'TrackerType', 'tracker_type') ?: $tracker->tracker_type);
        $name = OhsDashboardPayload::string($payload, 'ProjectIssueName') ?: $tracker->project_issue_name;
        $department = OhsDashboardPayload::string($payload, 'Department') ?: $tracker->department;
        $site = OhsDashboardPayload::string($payload, 'Site') ?: $tracker->site;
        $leaderId = OhsDashboardPayload::string($payload, 'ProjectLeaderEmpId') ?: $tracker->project_leader_emp_id;
        $description = OhsDashboardPayload::string($payload, 'DescriptionProject') ?: $tracker->description_project;
        $background = OhsDashboardPayload::string($payload, 'BackgroundProject') ?: $tracker->background_project;
        $impact = OhsDashboardPayload::string($payload, 'ImpactProject') ?: $tracker->impact_project;
        $startRaw = OhsDashboardPayload::string($payload, 'StartDate') ?: $tracker->start_date?->format('Y-m-d');
        $dueRaw = OhsDashboardPayload::string($payload, 'DueDate') ?: $tracker->due_date?->format('Y-m-d');
        $success = OhsDashboardPayload::string($payload, 'SuccessIndicator') ?: $tracker->success_indicator;

        $leader = $this->support->requireEmployee($leaderId, 'Project Leader');
        $start = $this->support->parseISO((string) $startRaw);
        $due = $this->support->parseISO((string) $dueRaw);
        if ($due->lt($start)) {
            throw new OhsDashboardException('DueDate harus sama atau setelah StartDate.');
        }

        $existing = ProjectIssueSubTask::query()->where('tracker_id', $tracker->tracker_id)->get()->keyBy('sub_task_id');
        $incoming = $this->normalizeIncomingSubTasks(OhsDashboardPayload::array($payload, 'SubTasks'), false);
        $incomingIds = [];
        foreach ($incoming as $row) {
            $id = (string) ($row['SubTaskId'] ?? $row['sub_task_id'] ?? '');
            if ($id !== '') {
                $incomingIds[] = $id;
            }
        }

        foreach ($existing as $id => $task) {
            if (! in_array((string) $id, $incomingIds, true)) {
                throw new OhsDashboardException('Sub task yang sudah ada tidak boleh dihapus. Sertakan semua SubTaskId existing.');
            }
        }

        $newCount = 0;
        $now = $this->support->now();

        DB::transaction(function () use ($tracker, $type, $name, $department, $site, $leader, $description, $background, $impact, $start, $due, $success, $incoming, $existing, $now, &$newCount): void {
            $tracker->fill([
                'tracker_type' => $type,
                'project_issue_name' => $name,
                'department' => $department,
                'site' => $site,
                'project_leader_emp_id' => $leader->emp_id,
                'project_leader_name' => $leader->emp_name,
                'project_leader_team' => $leader->team,
                'project_leader_position' => $leader->position,
                'project_leader_site_dedicated' => $leader->site_dedicated,
                'description_project' => $description,
                'background_project' => $background,
                'impact_project' => $impact,
                'start_date' => $this->support->formatISO($start),
                'due_date' => $this->support->formatISO($due),
                'success_indicator' => $success,
            ]);
            $tracker->save();

            foreach ($incoming as $row) {
                $id = (string) ($row['SubTaskId'] ?? $row['sub_task_id'] ?? '');
                if ($id !== '' && $existing->has($id)) {
                    $this->updateExistingSubTaskStatic($existing->get($id), $row, $tracker);
                    continue;
                }
                $this->createSubTask($tracker, $row, $leader, $now, true);
                $newCount++;
            }

            if ($existing->isNotEmpty() || $newCount > 0) {
                $this->syncTrackerAggregate($tracker->fresh());
            } else {
                $tracker->status = $this->support->deriveTrackerStatus((float) $tracker->current_percent_complete, $due);
                $tracker->save();
            }
        });

        return [
            'trackerId' => $tracker->tracker_id,
            'subTaskCount' => ProjectIssueSubTask::query()->where('tracker_id', $tracker->tracker_id)->count(),
            'newSubTaskCount' => $newCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateParentProgress(array $payload): array
    {
        $tracker = $this->requireTracker(OhsDashboardPayload::string($payload, 'TrackerId'));
        if (ProjectIssueSubTask::query()->where('tracker_id', $tracker->tracker_id)->exists()) {
            throw new OhsDashboardException('Progress parent dengan sub task harus diupdate melalui sub task.');
        }

        $percent = $this->support->validatePercentComplete(OhsDashboardPayload::raw($payload, 'PercentComplete'));
        $weekly = OhsDashboardPayload::string($payload, 'ProgressReportWeekly');
        $remarks = OhsDashboardPayload::string($payload, 'Remarks');
        $updatedBy = $this->support->requireEmployee(OhsDashboardPayload::string($payload, 'UpdatedByEmpId'), 'Updated By');
        if ($weekly === '' || $remarks === '') {
            throw new OhsDashboardException('Progress Report Weekly dan Keterangan wajib diisi.');
        }

        $now = $this->support->now();
        $status = $this->support->deriveTrackerStatus($percent, $tracker->due_date);

        DB::transaction(function () use ($tracker, $percent, $weekly, $remarks, $status, $updatedBy, $now): void {
            $tracker->current_percent_complete = $percent;
            $tracker->current_progress_report_weekly = $weekly;
            $tracker->current_remarks = $remarks;
            $tracker->status = $status;
            $tracker->last_updated = $now;
            $tracker->save();
            $this->insertParentLog($tracker, $percent, $weekly, $remarks, $status, $updatedBy, $now);
        });

        return [
            'trackerId' => $tracker->tracker_id,
            'percentComplete' => $percent,
            'status' => $status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateSubTaskProgress(array $payload): array
    {
        $subTask = ProjectIssueSubTask::query()->find(OhsDashboardPayload::string($payload, 'SubTaskId'));
        if (! $subTask instanceof ProjectIssueSubTask) {
            throw new OhsDashboardException('Sub task tidak ditemukan.');
        }

        $percent = $this->support->validatePercentComplete(OhsDashboardPayload::raw($payload, 'PercentComplete'));
        $weekly = OhsDashboardPayload::string($payload, 'ProgressReportWeekly');
        $remarks = OhsDashboardPayload::string($payload, 'Remarks');
        $updatedBy = $this->support->requireEmployee(OhsDashboardPayload::string($payload, 'UpdatedByEmpId'), 'Updated By');
        if ($weekly === '' || $remarks === '') {
            throw new OhsDashboardException('Progress Report Weekly dan Keterangan wajib diisi.');
        }

        $now = $this->support->now();
        $status = $this->support->deriveTrackerStatus($percent, $subTask->due_date);

        DB::transaction(function () use ($subTask, $percent, $weekly, $remarks, $status, $updatedBy, $now): void {
            $subTask->current_percent_complete = $percent;
            $subTask->current_progress_report_weekly = $weekly;
            $subTask->current_remarks = $remarks;
            $subTask->status = $status;
            $subTask->last_updated = $now;
            $subTask->save();

            ProjectIssueSubTaskUpdateLog::query()->create([
                'update_id' => OhsDashboardId::subTaskUpdateLog(),
                'timestamp' => $now,
                'tracker_id' => $subTask->tracker_id,
                'sub_task_id' => $subTask->sub_task_id,
                'percent_complete' => $percent,
                'progress_report_weekly' => $weekly,
                'remarks' => $remarks,
                'status' => $status,
                'updated_by_emp_id' => $updatedBy->emp_id,
                'updated_by_name' => $updatedBy->emp_name,
                'updated_by_team' => $updatedBy->team,
                'updated_by_position' => $updatedBy->position,
                'updated_by_site_dedicated' => $updatedBy->site_dedicated,
            ]);

            $this->syncTrackerAggregate(ProjectIssueTracker::query()->find($subTask->tracker_id));
        });

        $fresh = $subTask->fresh();

        return [
            'trackerId' => $subTask->tracker_id,
            'subTaskId' => $subTask->sub_task_id,
            'percentComplete' => $percent,
            'status' => $fresh?->status ?? $status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function data(array $payload): array
    {
        $type = OhsDashboardPayload::string($payload, 'type');
        $statusFilter = OhsDashboardPayload::string($payload, 'status');
        $department = OhsDashboardPayload::string($payload, 'department') ?: OhsDashboardPayload::string($payload, 'team');
        $site = OhsDashboardPayload::string($payload, 'site');
        $search = mb_strtolower(OhsDashboardPayload::string($payload, 'search'));

        $trackers = ProjectIssueTracker::query()->with('subTasks')->get();
        $filtered = [];

        foreach ($trackers as $tracker) {
            $this->refreshEffectiveStatus($tracker);
            if (! $this->support->isAllType($type) && $tracker->tracker_type !== $type) {
                continue;
            }
            if (! $this->matchesDepartmentSite($tracker, $department, $site)) {
                continue;
            }
            if ($search !== '' && ! $this->matchesSearch($tracker, $search)) {
                continue;
            }
            $filtered[] = $tracker;
        }

        $counts = ['total' => 0, 'onGoing' => 0, 'overdue' => 0, 'closed' => 0];
        foreach ($filtered as $tracker) {
            $counts['total']++;
            match ($tracker->status) {
                'On Going' => $counts['onGoing']++,
                'Overdue' => $counts['overdue']++,
                'Closed' => $counts['closed']++,
                default => null,
            };
        }

        if (! $this->support->isAllStatus($statusFilter)) {
            $filtered = array_values(array_filter(
                $filtered,
                fn (ProjectIssueTracker $tracker): bool => $tracker->status === $statusFilter
            ));
        }

        usort($filtered, function (ProjectIssueTracker $a, ProjectIssueTracker $b): int {
            $order = $this->support->trackerStatusSortOrder($a->status) <=> $this->support->trackerStatusSortOrder($b->status);
            if ($order !== 0) {
                return $order;
            }
            $due = strcmp($a->due_date?->format('Y-m-d') ?? '', $b->due_date?->format('Y-m-d') ?? '');
            if ($due !== 0) {
                return $due;
            }

            return strcmp($a->project_issue_name, $b->project_issue_name);
        });

        return [
            'counts' => $counts,
            'trackers' => array_map(fn (ProjectIssueTracker $tracker): array => $this->enrichTracker($tracker), $filtered),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function parentLog(string $trackerId): array
    {
        $tracker = $this->requireTracker($trackerId);
        $this->refreshEffectiveStatus($tracker);
        $logs = ProjectIssueUpdateLog::query()
            ->where('tracker_id', $tracker->tracker_id)
            ->orderByDesc('timestamp')
            ->get();

        return [
            'tracker' => $this->enrichTracker($tracker, false),
            'logs' => $logs->map(fn (ProjectIssueUpdateLog $log): array => $this->enrichParentLog($log))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function subTaskLog(string $subTaskId): array
    {
        $subTask = ProjectIssueSubTask::query()->find($subTaskId);
        if (! $subTask instanceof ProjectIssueSubTask) {
            throw new OhsDashboardException('Sub task tidak ditemukan.');
        }
        $tracker = $this->requireTracker($subTask->tracker_id);
        $this->refreshEffectiveStatus($tracker);
        $logs = ProjectIssueSubTaskUpdateLog::query()
            ->where('sub_task_id', $subTask->sub_task_id)
            ->orderByDesc('timestamp')
            ->get();

        return [
            'tracker' => $this->enrichTracker($tracker, false),
            'subTask' => $this->enrichSubTask($subTask),
            'logs' => $logs->map(fn (ProjectIssueSubTaskUpdateLog $log): array => $this->enrichSubTaskLog($log))->all(),
        ];
    }

    public function syncTrackerAggregate(?ProjectIssueTracker $tracker): void
    {
        if (! $tracker instanceof ProjectIssueTracker) {
            return;
        }

        $subTasks = ProjectIssueSubTask::query()->where('tracker_id', $tracker->tracker_id)->get();
        if ($subTasks->isEmpty()) {
            $tracker->status = $this->support->deriveTrackerStatus((float) $tracker->current_percent_complete, $tracker->due_date);
            $tracker->save();

            return;
        }

        foreach ($subTasks as $task) {
            $task->status = $this->support->deriveTrackerStatus((float) $task->current_percent_complete, $task->due_date);
            $task->save();
        }

        $aggregate = $this->support->calculateTrackerAggregate(
            $subTasks->map(fn (ProjectIssueSubTask $task): array => [
                'current_percent_complete' => (float) $task->current_percent_complete,
                'status' => $task->status,
                'sub_task_name' => $task->sub_task_name,
                'current_progress_report_weekly' => $task->current_progress_report_weekly,
                'current_remarks' => $task->current_remarks,
                'last_updated' => $task->last_updated,
            ])->all(),
            $tracker->due_date,
        );

        $tracker->current_percent_complete = $aggregate['percent'];
        $tracker->status = $aggregate['status'];
        $tracker->current_progress_report_weekly = $aggregate['weekly'];
        $tracker->current_remarks = $aggregate['remarks'];
        $tracker->last_updated = $this->support->now();
        $tracker->save();
    }

    public function refreshEffectiveStatus(ProjectIssueTracker $tracker): void
    {
        $subTasks = $tracker->relationLoaded('subTasks')
            ? $tracker->subTasks
            : ProjectIssueSubTask::query()->where('tracker_id', $tracker->tracker_id)->get();

        foreach ($subTasks as $task) {
            $task->status = $this->support->deriveTrackerStatus((float) $task->current_percent_complete, $task->due_date);
        }

        if ($subTasks->isNotEmpty()) {
            $aggregate = $this->support->calculateTrackerAggregate(
                $subTasks->map(fn (ProjectIssueSubTask $task): array => [
                    'current_percent_complete' => (float) $task->current_percent_complete,
                    'status' => $task->status,
                    'sub_task_name' => $task->sub_task_name,
                    'current_progress_report_weekly' => $task->current_progress_report_weekly,
                    'current_remarks' => $task->current_remarks,
                    'last_updated' => $task->last_updated,
                ])->all(),
                $tracker->due_date,
            );
            $tracker->current_percent_complete = $aggregate['percent'];
            $tracker->status = $aggregate['status'];
            $tracker->current_progress_report_weekly = $aggregate['weekly'];
            $tracker->current_remarks = $aggregate['remarks'];
        } else {
            $tracker->status = $this->support->deriveTrackerStatus((float) $tracker->current_percent_complete, $tracker->due_date);
        }
    }

    public function requireTracker(string $trackerId): ProjectIssueTracker
    {
        $trackerId = trim($trackerId);
        if ($trackerId === '') {
            throw new OhsDashboardException('TrackerId wajib diisi.');
        }

        $tracker = ProjectIssueTracker::query()->with('subTasks')->find($trackerId);
        if (! $tracker instanceof ProjectIssueTracker) {
            throw new OhsDashboardException('Tracker tidak ditemukan.');
        }

        return $tracker;
    }

    /**
     * @return array<string, mixed>
     */
    public function enrichTracker(ProjectIssueTracker $tracker, bool $withSubTasks = true): array
    {
        $leader = $tracker->leader;
        $row = [
            'TrackerId' => $tracker->tracker_id,
            'Timestamp' => $this->support->formatDateTime($tracker->timestamp),
            'TrackerType' => $tracker->tracker_type,
            'ProjectIssueName' => $tracker->project_issue_name,
            'Department' => $tracker->department,
            'Site' => $tracker->site,
            'ProjectLeaderEmpId' => $tracker->project_leader_emp_id,
            'ProjectLeaderName' => $this->support->fillIfEmpty($tracker->project_leader_name, $leader?->emp_name),
            'ProjectLeaderTeam' => $this->support->fillIfEmpty($tracker->project_leader_team, $leader?->team),
            'ProjectLeaderPosition' => $this->support->fillIfEmpty($tracker->project_leader_position, $leader?->position),
            'ProjectLeaderSiteDedicated' => $this->support->fillIfEmpty($tracker->project_leader_site_dedicated, $leader?->site_dedicated),
            'DescriptionProject' => $tracker->description_project,
            'BackgroundProject' => $tracker->background_project,
            'ImpactProject' => $tracker->impact_project,
            'StartDate' => $tracker->start_date?->format('Y-m-d'),
            'DueDate' => $tracker->due_date?->format('Y-m-d'),
            'SuccessIndicator' => $tracker->success_indicator,
            'CurrentPercentComplete' => (float) $tracker->current_percent_complete,
            'CurrentProgressReportWeekly' => $tracker->current_progress_report_weekly ?? '',
            'CurrentRemarks' => $tracker->current_remarks ?? '',
            'Status' => $tracker->status,
            'EffectiveStatus' => $tracker->status,
            'LastUpdated' => $this->support->formatDateTime($tracker->last_updated),
            'HasSubTasks' => $tracker->subTasks->isNotEmpty(),
        ];

        if ($withSubTasks) {
            $subTasks = $tracker->subTasks->all();
            usort($subTasks, function (ProjectIssueSubTask $a, ProjectIssueSubTask $b): int {
                $order = $this->support->trackerStatusSortOrder($a->status) <=> $this->support->trackerStatusSortOrder($b->status);
                if ($order !== 0) {
                    return $order;
                }
                $due = strcmp($a->due_date?->format('Y-m-d') ?? '', $b->due_date?->format('Y-m-d') ?? '');

                return $due !== 0 ? $due : strcmp($a->sub_task_name, $b->sub_task_name);
            });
            $row['SubTasks'] = array_map(fn (ProjectIssueSubTask $task): array => $this->enrichSubTask($task), $subTasks);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function enrichSubTask(ProjectIssueSubTask $task): array
    {
        $pic = $task->pic;

        return [
            'SubTaskId' => $task->sub_task_id,
            'TrackerId' => $task->tracker_id,
            'Timestamp' => $this->support->formatDateTime($task->timestamp),
            'SubTaskName' => $task->sub_task_name,
            'Department' => $task->department ?? '',
            'Site' => $task->site,
            'PICEmpId' => $task->pic_emp_id,
            'PICName' => $this->support->fillIfEmpty($task->pic_name, $pic?->emp_name),
            'PICTeam' => $this->support->fillIfEmpty($task->pic_team, $pic?->team),
            'PICPosition' => $this->support->fillIfEmpty($task->pic_position, $pic?->position),
            'PICSiteDedicated' => $this->support->fillIfEmpty($task->pic_site_dedicated, $pic?->site_dedicated),
            'DescriptionSubTask' => $task->description_sub_task,
            'StartDate' => $task->start_date?->format('Y-m-d'),
            'DueDate' => $task->due_date?->format('Y-m-d'),
            'SuccessIndicator' => $task->success_indicator,
            'CurrentPercentComplete' => (float) $task->current_percent_complete,
            'CurrentProgressReportWeekly' => $task->current_progress_report_weekly,
            'CurrentRemarks' => $task->current_remarks,
            'Status' => $task->status,
            'EffectiveStatus' => $task->status,
            'LastUpdated' => $this->support->formatDateTime($task->last_updated),
        ];
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function assertRequired(array $fields): void
    {
        foreach ($fields as $label => $value) {
            if (trim($value) === '') {
                throw new OhsDashboardException($label.' wajib diisi.');
            }
        }
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeIncomingSubTasks(array $rows, bool $requireComplete = true): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $filled = false;
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    $filled = true;
                    break;
                }
            }
            if (! $filled) {
                continue;
            }
            if ($requireComplete) {
                $this->assertSubTaskComplete($row);
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertSubTaskComplete(array $row): void
    {
        $name = OhsDashboardPayload::string($row, 'SubTaskName');
        $pic = OhsDashboardPayload::string($row, 'PICEmpId');
        $site = OhsDashboardPayload::string($row, 'Site');
        $description = OhsDashboardPayload::string($row, 'DescriptionSubTask');
        $success = OhsDashboardPayload::string($row, 'SuccessIndicator');
        $start = OhsDashboardPayload::string($row, 'StartDate');
        $due = OhsDashboardPayload::string($row, 'DueDate');
        $weekly = OhsDashboardPayload::string($row, 'InitialProgressReportWeekly')
            ?: OhsDashboardPayload::string($row, 'ProgressReportWeekly');
        $remarks = OhsDashboardPayload::string($row, 'InitialRemarks')
            ?: OhsDashboardPayload::string($row, 'Remarks');

        if ($name === '' || $pic === '' || $site === '' || $description === '' || $success === '' || $start === '' || $due === '' || $weekly === '' || $remarks === '') {
            throw new OhsDashboardException('Sub task terisi wajib lengkap termasuk Initial Progress Report Weekly dan Initial Keterangan.');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createSubTask(ProjectIssueTracker $tracker, array $row, Employee $updatedBy, CarbonInterface $now, bool $writeLog): void
    {
        $pic = $this->support->requireEmployee(OhsDashboardPayload::string($row, 'PICEmpId'), 'PIC Sub Task');
        $start = $this->support->parseISO(OhsDashboardPayload::string($row, 'StartDate'));
        $due = $this->support->parseISO(OhsDashboardPayload::string($row, 'DueDate'));
        $this->assertSubTaskTimeline($tracker, $start, $due);

        $percentRaw = OhsDashboardPayload::raw($row, 'PercentComplete');
        $percent = $percentRaw === null || $percentRaw === '' ? 0.0 : $this->support->normalizePercentComplete($percentRaw);
        $weekly = OhsDashboardPayload::string($row, 'InitialProgressReportWeekly')
            ?: OhsDashboardPayload::string($row, 'ProgressReportWeekly')
            ?: 'Tracker dibuat.';
        $remarks = OhsDashboardPayload::string($row, 'InitialRemarks')
            ?: OhsDashboardPayload::string($row, 'Remarks')
            ?: 'Belum ada catatan tambahan.';
        $status = $this->support->deriveTrackerStatus($percent, $due);
        $subTaskId = OhsDashboardId::subTask();

        ProjectIssueSubTask::query()->create([
            'sub_task_id' => $subTaskId,
            'tracker_id' => $tracker->tracker_id,
            'timestamp' => $now,
            'sub_task_name' => OhsDashboardPayload::string($row, 'SubTaskName'),
            'department' => OhsDashboardPayload::string($row, 'Department') ?: ($pic->team ?? ''),
            'site' => OhsDashboardPayload::string($row, 'Site'),
            'pic_emp_id' => $pic->emp_id,
            'pic_name' => $pic->emp_name,
            'pic_team' => $pic->team,
            'pic_position' => $pic->position,
            'pic_site_dedicated' => $pic->site_dedicated,
            'description_sub_task' => OhsDashboardPayload::string($row, 'DescriptionSubTask'),
            'start_date' => $this->support->formatISO($start),
            'due_date' => $this->support->formatISO($due),
            'success_indicator' => OhsDashboardPayload::string($row, 'SuccessIndicator'),
            'current_percent_complete' => $percent,
            'current_progress_report_weekly' => $weekly,
            'current_remarks' => $remarks,
            'status' => $status,
            'last_updated' => $now,
        ]);

        if ($writeLog) {
            ProjectIssueSubTaskUpdateLog::query()->create([
                'update_id' => OhsDashboardId::subTaskUpdateLog(),
                'timestamp' => $now,
                'tracker_id' => $tracker->tracker_id,
                'sub_task_id' => $subTaskId,
                'percent_complete' => $percent,
                'progress_report_weekly' => $weekly,
                'remarks' => $remarks,
                'status' => $status,
                'updated_by_emp_id' => $updatedBy->emp_id,
                'updated_by_name' => $updatedBy->emp_name,
                'updated_by_team' => $updatedBy->team,
                'updated_by_position' => $updatedBy->position,
                'updated_by_site_dedicated' => $updatedBy->site_dedicated,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function updateExistingSubTaskStatic(ProjectIssueSubTask $task, array $row, ProjectIssueTracker $tracker): void
    {
        $picId = OhsDashboardPayload::string($row, 'PICEmpId') ?: $task->pic_emp_id;
        $pic = $this->support->requireEmployee($picId, 'PIC Sub Task');
        $start = $this->support->parseISO(OhsDashboardPayload::string($row, 'StartDate') ?: $task->start_date?->format('Y-m-d'));
        $due = $this->support->parseISO(OhsDashboardPayload::string($row, 'DueDate') ?: $task->due_date?->format('Y-m-d'));
        $this->assertSubTaskTimeline($tracker, $start, $due);

        $task->fill([
            'sub_task_name' => OhsDashboardPayload::string($row, 'SubTaskName') ?: $task->sub_task_name,
            'department' => OhsDashboardPayload::string($row, 'Department') ?: ($pic->team ?? $task->department),
            'site' => OhsDashboardPayload::string($row, 'Site') ?: $task->site,
            'pic_emp_id' => $pic->emp_id,
            'pic_name' => $pic->emp_name,
            'pic_team' => $pic->team,
            'pic_position' => $pic->position,
            'pic_site_dedicated' => $pic->site_dedicated,
            'description_sub_task' => OhsDashboardPayload::string($row, 'DescriptionSubTask') ?: $task->description_sub_task,
            'start_date' => $this->support->formatISO($start),
            'due_date' => $this->support->formatISO($due),
            'success_indicator' => OhsDashboardPayload::string($row, 'SuccessIndicator') ?: $task->success_indicator,
        ]);
        $task->status = $this->support->deriveTrackerStatus((float) $task->current_percent_complete, $due);
        $task->save();
    }

    private function assertSubTaskTimeline(ProjectIssueTracker $tracker, CarbonInterface $start, CarbonInterface $due): void
    {
        if ($due->lt($start)) {
            throw new OhsDashboardException('DueDate sub task harus sama atau setelah StartDate.');
        }
        if ($this->support->formatISO($start) < $this->support->formatISO($tracker->start_date)
            || $this->support->formatISO($due) > $this->support->formatISO($tracker->due_date)) {
            throw new OhsDashboardException('Timeline sub task harus berada dalam StartDate–DueDate parent.');
        }
    }

    private function insertParentLog(
        ProjectIssueTracker $tracker,
        float $percent,
        string $weekly,
        string $remarks,
        string $status,
        Employee $updatedBy,
        CarbonInterface $now,
    ): void {
        ProjectIssueUpdateLog::query()->create([
            'update_id' => OhsDashboardId::updateLog(),
            'timestamp' => $now,
            'tracker_id' => $tracker->tracker_id,
            'percent_complete' => $percent,
            'progress_report_weekly' => $weekly,
            'remarks' => $remarks,
            'status' => $status,
            'updated_by_emp_id' => $updatedBy->emp_id,
            'updated_by_name' => $updatedBy->emp_name,
            'updated_by_team' => $updatedBy->team,
            'updated_by_position' => $updatedBy->position,
            'updated_by_site_dedicated' => $updatedBy->site_dedicated,
        ]);
    }

    private function matchesDepartmentSite(ProjectIssueTracker $tracker, string $department, string $site): bool
    {
        $deptOk = $this->support->isAllTeam($department)
            || $tracker->department === $department
            || $tracker->subTasks->contains(fn (ProjectIssueSubTask $task): bool => $task->department === $department || $task->pic_team === $department);
        $siteOk = $this->support->isAllSite($site)
            || $tracker->site === $site
            || $tracker->subTasks->contains(fn (ProjectIssueSubTask $task): bool => $task->site === $site || $task->pic_site_dedicated === $site);

        return $deptOk && $siteOk;
    }

    private function matchesSearch(ProjectIssueTracker $tracker, string $search): bool
    {
        $haystack = mb_strtolower(implode(' ', [
            $tracker->tracker_id,
            $tracker->project_issue_name,
            $tracker->project_leader_name,
            $tracker->department,
            $tracker->site,
            $tracker->description_project,
            $tracker->success_indicator,
            $tracker->current_progress_report_weekly,
            $tracker->current_remarks,
        ]));
        if (str_contains($haystack, $search)) {
            return true;
        }

        foreach ($tracker->subTasks as $task) {
            $sub = mb_strtolower(implode(' ', [
                $task->sub_task_id,
                $task->sub_task_name,
                $task->pic_name,
                $task->department,
                $task->site,
                $task->description_sub_task,
                $task->success_indicator,
                $task->current_progress_report_weekly,
                $task->current_remarks,
            ]));
            if (str_contains($sub, $search)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichParentLog(ProjectIssueUpdateLog $log): array
    {
        return [
            'UpdateId' => $log->update_id,
            'Timestamp' => $this->support->formatDateTime($log->timestamp),
            'TrackerId' => $log->tracker_id,
            'PercentComplete' => (float) $log->percent_complete,
            'ProgressReportWeekly' => $log->progress_report_weekly,
            'Remarks' => $log->remarks,
            'Status' => $log->status,
            'UpdatedByEmpId' => $log->updated_by_emp_id,
            'UpdatedByName' => $log->updated_by_name,
            'UpdatedByTeam' => $log->updated_by_team ?? '',
            'UpdatedByPosition' => $log->updated_by_position ?? '',
            'UpdatedBySiteDedicated' => $log->updated_by_site_dedicated ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichSubTaskLog(ProjectIssueSubTaskUpdateLog $log): array
    {
        return [
            'UpdateId' => $log->update_id,
            'Timestamp' => $this->support->formatDateTime($log->timestamp),
            'TrackerId' => $log->tracker_id,
            'SubTaskId' => $log->sub_task_id,
            'PercentComplete' => (float) $log->percent_complete,
            'ProgressReportWeekly' => $log->progress_report_weekly,
            'Remarks' => $log->remarks,
            'Status' => $log->status,
            'UpdatedByEmpId' => $log->updated_by_emp_id,
            'UpdatedByName' => $log->updated_by_name,
            'UpdatedByTeam' => $log->updated_by_team ?? '',
            'UpdatedByPosition' => $log->updated_by_position ?? '',
            'UpdatedBySiteDedicated' => $log->updated_by_site_dedicated ?? '',
        ];
    }
}
