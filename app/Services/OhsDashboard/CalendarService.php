<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard;

use App\Models\OhsDashboard\Employee;
use App\Models\OhsDashboard\Event;
use App\Models\OhsDashboard\LeaveRequest;
use App\Models\OhsDashboard\ProjectIssueTracker;
use App\Services\OhsDashboard\Support\OhsDashboardPayload;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class CalendarService
{
    public function __construct(
        private readonly OhsDashboardSupport $support,
        private readonly LeaveService $leaveService,
        private readonly EventService $eventService,
        private readonly TrackerService $trackerService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function range(array $payload): array
    {
        $viewMode = strtoupper(OhsDashboardPayload::string($payload, 'viewMode') ?: 'WEEK');
        if (! in_array($viewMode, ['WEEK', 'MONTH', 'YEAR'], true)) {
            $viewMode = 'WEEK';
        }
        $anchorRaw = OhsDashboardPayload::string($payload, 'anchorISO');
        $anchor = $anchorRaw !== '' ? $this->support->parseISO($anchorRaw) : $this->support->today();
        $team = OhsDashboardPayload::string($payload, 'team');
        $site = OhsDashboardPayload::string($payload, 'site');
        $search = mb_strtolower(OhsDashboardPayload::string($payload, 'search'));

        [$rangeStart, $rangeEnd] = $this->rangeBounds($viewMode, $anchor);
        $cols = $this->buildCalendarColumns($viewMode, $rangeStart, $rangeEnd);
        $startIso = $this->support->formatISO($rangeStart);
        $endIso = $this->support->formatISO($rangeEnd);

        $leavesQuery = LeaveRequest::query()
            ->select([
                'request_id', 'emp_id', 'emp_name', 'team', 'position', 'site_dedicated',
                'leave_type', 'start_date', 'end_date', 'backup_emp_id', 'backup_emp_name',
            ])
            ->where('start_date', '<=', $endIso)
            ->where('end_date', '>=', $startIso);
        if (! $this->support->isAllTeam($team)) {
            $leavesQuery->where('team', $team);
        }
        if (! $this->support->isAllSite($site)) {
            $leavesQuery->where('site_dedicated', $site);
        }
        $leaves = $leavesQuery->limit(400)->get();

        $itemsByEmp = [];
        foreach ($leaves as $leave) {
            $row = $this->leaveService->enrich($leave);
            $item = $this->makeItem(
                $leave->emp_id,
                'LEAVE',
                $row['LeaveType'].' → '.$row['BackupEmpName'],
                $row['StartDate'],
                $row['EndDate'],
                $row,
            );
            $itemsByEmp[$leave->emp_id][] = $item;
        }

        $eventsQuery = Event::query()
            ->where('event_date', '>=', $startIso)
            ->where('event_date', '<=', $endIso);
        if (! $this->support->isAllTeam($team)) {
            $eventsQuery->where('pic_team', $team);
        }
        if (! $this->support->isAllSite($site)) {
            $eventsQuery->where('pic_site_dedicated', $site);
        }
        foreach ($eventsQuery->limit(200)->get() as $event) {
            $row = $this->eventService->enrich($event);
            $iso = (string) $row['EventDate'];
            $item = $this->makeItem($event->pic_emp_id, 'EVENT', $event->event_name, $iso, $iso, $row);
            foreach ($this->distributeAssignmentToBackup($item, $leaves) as $distributed) {
                $itemsByEmp[$distributed['empId']][] = $distributed;
            }
        }

        $trackersQuery = ProjectIssueTracker::query()
            ->where('start_date', '<=', $endIso)
            ->where('due_date', '>=', $startIso);
        if (! $this->support->isAllTeam($team)) {
            $trackersQuery->where('department', $team);
        }
        if (! $this->support->isAllSite($site)) {
            $trackersQuery->where('site', $site);
        }
        $trackers = $trackersQuery->limit(200)->get();

        foreach ($trackers as $tracker) {
            $row = $this->trackerService->enrichTracker($tracker, false);
            $category = $tracker->tracker_type === 'Issue' ? 'ISSUE' : 'PROJECT';
            $title = $tracker->project_issue_name.' ('.(float) $tracker->current_percent_complete.'%)';
            $item = $this->makeItem(
                $tracker->project_leader_emp_id,
                $category,
                $title,
                $tracker->start_date?->format('Y-m-d') ?? '',
                $tracker->due_date?->format('Y-m-d') ?? '',
                $row,
            );
            foreach ($this->distributeAssignmentToBackup($item, $leaves) as $distributed) {
                $itemsByEmp[$distributed['empId']][] = $distributed;
            }
        }

        $hasFilter = $this->hasFilter($team, $site, $search);
        $rows = [];

        $itemEmpIds = array_keys($itemsByEmp);
        $query = Employee::query()->select(['emp_id', 'sid', 'emp_name', 'position', 'team', 'site_dedicated']);
        if (! $this->support->isAllTeam($team)) {
            $query->where('team', $team);
        }
        if (! $this->support->isAllSite($site)) {
            $query->where('site_dedicated', $site);
        }
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($itemEmpIds, $like): void {
                if ($itemEmpIds !== []) {
                    $builder->whereIn('emp_id', array_slice($itemEmpIds, 0, 300));
                }
                $builder->orWhere('emp_name', 'like', $like)
                    ->orWhere('emp_id', 'like', $like)
                    ->orWhere('sid', 'like', $like)
                    ->orWhere('position', 'like', $like)
                    ->orWhere('team', 'like', $like)
                    ->orWhere('site_dedicated', 'like', $like);
            });
        } elseif ($itemEmpIds !== []) {
            $query->whereIn('emp_id', array_slice($itemEmpIds, 0, 200));
        } else {
            $query->whereRaw('1 = 0');
        }
        $employees = $query->orderBy('emp_name')->limit(200)->get()->keyBy('emp_id');

        $roster = $hasFilter
            ? $employees
            : $employees->filter(fn (Employee $emp): bool => isset($itemsByEmp[$emp->emp_id]));

        foreach ($roster as $employee) {
            $items = $itemsByEmp[$employee->emp_id] ?? [];
            if ($search !== '' && ! $this->employeeOrItemsMatch($employee, $items, $search)) {
                continue;
            }

            $assignmentCount = 0;
            $actingCount = 0;
            foreach ($items as $item) {
                if ($item['category'] !== 'LEAVE') {
                    $assignmentCount++;
                    if (! empty($item['acting'])) {
                        $actingCount++;
                    }
                }
            }

            $rows[] = [
                'employee' => $employee->toApiArray(),
                'chip' => 'Assignment: '.$assignmentCount.' · Acting: '.$actingCount,
                'items' => $items,
            ];
            if (count($rows) >= 120) {
                break;
            }
        }

        usort($rows, function (array $a, array $b): int {
            $teamCmp = strcmp((string) $a['employee']['Team'], (string) $b['employee']['Team']);

            return $teamCmp !== 0 ? $teamCmp : strcmp((string) $a['employee']['EmpName'], (string) $b['employee']['EmpName']);
        });

        // Fix event/project/issue counts from items rather than the crude increment above
        $counts = $this->recount($rows);

        return [
            'viewMode' => $viewMode,
            'rangeStart' => $this->support->formatISO($rangeStart),
            'rangeEnd' => $this->support->formatISO($rangeEnd),
            'cols' => $cols,
            'rows' => $rows,
            'counts' => $counts,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildCalendarColumns(string $viewMode, CarbonInterface $rangeStart, CarbonInterface $rangeEnd): array
    {
        $cols = [];
        if ($viewMode === 'YEAR') {
            for ($month = 1; $month <= 12; $month++) {
                $date = Carbon::create((int) $rangeStart->year, $month, 1, 0, 0, 0, $this->support->timezone());
                $cols[] = [
                    'type' => 'MONTH',
                    'label' => $date->format('M'),
                    'key' => $date->format('Y-m'),
                    'start' => $date->format('Y-m-d'),
                    'end' => $date->copy()->endOfMonth()->format('Y-m-d'),
                ];
            }

            return $cols;
        }

        if ($viewMode === 'MONTH') {
            $cursor = $this->support->startOfWeekMonday($rangeStart);
            $monthEnd = $rangeEnd->copy();
            while ($cursor->lte($monthEnd)) {
                $weekEnd = $cursor->copy()->addDays(6);
                $cols[] = [
                    'type' => 'WEEK',
                    'label' => 'Week '.$this->support->getISOWeekNumber($cursor),
                    'key' => $this->support->formatISO($cursor),
                    'start' => $this->support->formatISO($cursor),
                    'end' => $this->support->formatISO($weekEnd),
                ];
                $cursor->addDays(7);
            }

            return $cols;
        }

        $cursor = $rangeStart->copy();
        while ($cursor->lte($rangeEnd)) {
            $cols[] = [
                'type' => 'DAY',
                'label' => $cursor->format('D d'),
                'key' => $this->support->formatISO($cursor),
                'start' => $this->support->formatISO($cursor),
                'end' => $this->support->formatISO($cursor),
            ];
            $cursor->addDay();
        }

        return $cols;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LeaveRequest>  $leaves
     * @param  array<string, mixed>  $item
     * @return list<array<string, mixed>>
     */
    public function distributeAssignmentToBackup(array $item, $leaves): array
    {
        $ownerId = (string) $item['empId'];
        $start = $this->support->parseISO((string) $item['start']);
        $end = $this->support->parseISO((string) $item['end']);
        $ownerLeaves = $leaves->filter(
            fn (LeaveRequest $leave): bool => $leave->emp_id === $ownerId
                && $this->support->isDateRangeOverlap($start, $end, $leave->start_date, $leave->end_date)
        )->sortBy('start_date');

        if ($ownerLeaves->isEmpty()) {
            return [$item];
        }

        $segments = [];
        $cursor = $start->copy();
        foreach ($ownerLeaves as $leave) {
            $leaveStart = $leave->start_date->copy()->max($start);
            $leaveEnd = $leave->end_date->copy()->min($end);
            if ($cursor->lt($leaveStart)) {
                $before = $item;
                $before['start'] = $this->support->formatISO($cursor);
                $before['end'] = $this->support->formatISO($leaveStart->copy()->subDay());
                if ($before['start'] <= $before['end']) {
                    $segments[] = $before;
                }
            }

            $during = $item;
            $during['start'] = $this->support->formatISO($leaveStart);
            $during['end'] = $this->support->formatISO($leaveEnd);
            $backupId = (string) $leave->backup_emp_id;
            if ($backupId === '' || $backupId === $ownerId) {
                $during['detail'] = trim(($during['detail'] ?? '')."\nBackup PIC tidak valid; assignment tetap pada PIC asal.");
            } else {
                $during['empId'] = $backupId;
                $during['acting'] = true;
                $during['originalOwnerName'] = $leave->emp_name;
                $during['actingEmployeeName'] = $leave->backup_emp_name;
                $during['title'] = 'ACTING for '.$leave->emp_name.' • '.$item['title'];
                $during['detail'] = trim(($during['detail'] ?? '')."\nHandover ke ".$leave->backup_emp_name."\nPeriode leave: ".$this->support->formatISO($leave->start_date).' s/d '.$this->support->formatISO($leave->end_date)."\n".($leave->note ?? ''));
            }
            $segments[] = $during;
            $cursor = $leaveEnd->copy()->addDay();
        }

        if ($cursor->lte($end)) {
            $after = $item;
            $after['start'] = $this->support->formatISO($cursor);
            $after['end'] = $this->support->formatISO($end);
            $segments[] = $after;
        }

        return $segments;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangeBounds(string $viewMode, CarbonInterface $anchor): array
    {
        if ($viewMode === 'YEAR') {
            return [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()->startOfDay()];
        }
        if ($viewMode === 'MONTH') {
            return [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()->startOfDay()];
        }

        $start = $this->support->startOfWeekMonday($anchor);

        return [$start, $start->copy()->addDays(6)];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function makeItem(string $empId, string $category, string $title, string $start, string $end, array $data): array
    {
        return [
            'empId' => $empId,
            'category' => $category,
            'title' => $title,
            'start' => $start,
            'end' => $end,
            'status' => $data['Status'] ?? $data['EffectiveStatus'] ?? '',
            'detail' => $data['Description'] ?? $data['DescriptionProject'] ?? $data['DescriptionSubTask'] ?? $data['Note'] ?? '',
            'searchText' => mb_strtolower($title.' '.$category),
            'acting' => false,
            'originalOwnerName' => '',
            'actingEmployeeName' => '',
            'data' => [
                'RequestId' => $data['RequestId'] ?? '',
                'EventId' => $data['EventId'] ?? '',
                'TrackerId' => $data['TrackerId'] ?? '',
                'SubTaskId' => $data['SubTaskId'] ?? '',
            ],
        ];
    }

    private function hasFilter(string $team, string $site, string $search): bool
    {
        return ! $this->support->isAllTeam($team) || ! $this->support->isAllSite($site) || $search !== '';
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function employeeOrItemsMatch(Employee $employee, array $items, string $search): bool
    {
        $hay = mb_strtolower(implode(' ', [
            $employee->emp_id,
            $employee->sid,
            $employee->emp_name,
            $employee->position,
            $employee->team,
            $employee->site_dedicated,
        ]));
        if (str_contains($hay, $search)) {
            return true;
        }

        foreach ($items as $item) {
            $blob = mb_strtolower(implode(' ', [
                $item['category'],
                $item['title'],
                $item['detail'],
                $item['status'],
                $item['searchText'],
                $item['originalOwnerName'],
                $item['actingEmployeeName'],
            ]));
            if (str_contains($blob, $search)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function recount(array $rows): array
    {
        $counts = ['events' => 0, 'projects' => 0, 'issues' => 0, 'leaveEmployees' => 0, 'actingTransfers' => 0];
        foreach ($rows as $row) {
            $hasLeave = false;
            foreach ($row['items'] as $item) {
                $category = (string) $item['category'];
                if ($category === 'LEAVE') {
                    $hasLeave = true;
                } elseif ($category === 'EVENT') {
                    $counts['events']++;
                } elseif (str_starts_with($category, 'PROJECT')) {
                    $counts['projects']++;
                } elseif (str_starts_with($category, 'ISSUE')) {
                    $counts['issues']++;
                }
                if (! empty($item['acting'])) {
                    $counts['actingTransfers']++;
                }
            }
            if ($hasLeave) {
                $counts['leaveEmployees']++;
            }
        }

        return $counts;
    }
}
