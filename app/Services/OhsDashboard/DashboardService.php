<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard;

use App\Models\OhsDashboard\Employee;
use App\Models\OhsDashboard\Event;
use App\Models\OhsDashboard\LeaveRequest;
use App\Models\OhsDashboard\ProjectIssueTracker;
use App\Services\OhsDashboard\Support\OhsDashboardPayload;
use Carbon\Carbon;

final class DashboardService
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
    public function overview(array $payload): array
    {
        $team = OhsDashboardPayload::string($payload, 'team');
        $site = OhsDashboardPayload::string($payload, 'site');
        $year = $this->support->clampInteger(
            OhsDashboardPayload::raw($payload, 'year'),
            2000,
            2100,
            (int) $this->support->today()->year,
        );

        $windows = $this->support->dashboardWeekWindows($year);
        $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, $this->support->timezone())->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31, 0, 0, 0, $this->support->timezone())->startOfDay();
        $cutoff = $this->support->ytdCutoff($year);
        $yearStartIso = $this->support->formatISO($yearStart);
        $yearEndIso = $this->support->formatISO($yearEnd);

        $employeeCount = $this->employeeCount($team, $site);

        $leaveThisWeek = [];
        $upcomingLeave = [];
        $leaveDaysByEmp = [];

        $leavesQuery = LeaveRequest::query()
            ->where('start_date', '<=', $yearEndIso)
            ->where('end_date', '>=', $yearStartIso);
        if (! $this->support->isAllTeam($team)) {
            $leavesQuery->where('team', $team);
        }
        if (! $this->support->isAllSite($site)) {
            $leavesQuery->where('site_dedicated', $site);
        }

        foreach ($leavesQuery->limit(800)->get() as $leave) {
            $start = $leave->start_date;
            $end = $leave->end_date;
            if ($start === null || $end === null) {
                continue;
            }

            if (count($leaveThisWeek) < 40
                && $this->support->isDateRangeOverlap($start, $end, $windows['thisWeekStart'], $windows['thisWeekEnd'])) {
                $leaveThisWeek[] = $this->leaveService->enrich($leave);
            }
            if (count($upcomingLeave) < 40 && $start->gt($windows['thisWeekEnd'])) {
                $upcomingLeave[] = $this->leaveService->enrich($leave);
            }

            $leaveDaysByEmp[$leave->emp_id] = ($leaveDaysByEmp[$leave->emp_id] ?? 0)
                + $this->support->countWorkingDaysClipped($start, $end, $yearStart, $cutoff);
        }

        $eventGroups = [
            'thisWeek' => [],
            'nextWeek' => [],
            'nextTwoWeek' => [],
            'moreThanTwoWeeks' => [],
        ];

        $eventsQuery = Event::query()
            ->where('event_date', '>=', $yearStartIso)
            ->where('event_date', '<=', $yearEndIso)
            ->limit(200);
        if (! $this->support->isAllTeam($team)) {
            $eventsQuery->where('pic_team', $team);
        }
        if (! $this->support->isAllSite($site)) {
            $eventsQuery->where('pic_site_dedicated', $site);
        }

        foreach ($eventsQuery->get() as $event) {
            $row = $this->eventService->enrich($event);
            $iso = $row['EventDate'];
            if ($this->support->isISODateInRange($iso, $windows['thisWeekStart'], $windows['thisWeekEnd'])) {
                $eventGroups['thisWeek'][] = $row;
            } elseif ($this->support->isISODateInRange($iso, $windows['nextWeekStart'], $windows['nextWeekEnd'])) {
                $eventGroups['nextWeek'][] = $row;
            } elseif ($this->support->isISODateInRange($iso, $windows['nextTwoWeekStart'], $windows['nextTwoWeekEnd'])) {
                $eventGroups['nextTwoWeek'][] = $row;
            } elseif ($iso > $this->support->formatISO($windows['nextTwoWeekEnd'])) {
                $eventGroups['moreThanTwoWeeks'][] = $row;
            }
        }

        $trackers = [];
        $projectActive = 0;
        $issueActive = 0;
        $trackerQuery = ProjectIssueTracker::query()->with('subTasks')
            ->where('start_date', '<=', $yearEndIso)
            ->where('due_date', '>=', $yearStartIso);
        if (! $this->support->isAllTeam($team)) {
            $trackerQuery->where('department', $team);
        }
        if (! $this->support->isAllSite($site)) {
            $trackerQuery->where('site', $site);
        }

        foreach ($trackerQuery->limit(80)->get() as $tracker) {
            $row = $this->trackerService->enrichTracker($tracker, true);
            $trackers[] = $row;
            if ($tracker->status !== 'Closed') {
                if ($tracker->tracker_type === 'Project') {
                    $projectActive++;
                } elseif ($tracker->tracker_type === 'Issue') {
                    $issueActive++;
                }
            }
        }

        usort($trackers, function (array $a, array $b): int {
            $order = $this->support->trackerStatusSortOrder($a['EffectiveStatus']) <=> $this->support->trackerStatusSortOrder($b['EffectiveStatus']);
            if ($order !== 0) {
                return $order;
            }
            $due = strcmp((string) $a['DueDate'], (string) $b['DueDate']);

            return $due !== 0 ? $due : strcmp((string) $a['ProjectIssueName'], (string) $b['ProjectIssueName']);
        });

        $totalWorkingDays = $cutoff->lt($yearStart) ? 0 : $this->support->countWorkingDaysInclusive($yearStart, $cutoff);
        $leavePersonDays = (int) array_sum($leaveDaysByEmp);
        $limit = (int) config('ohs-dashboard.dashboard.leaderboard_limit', 200);
        $upcomingLimit = (int) config('ohs-dashboard.dashboard.upcoming_leave_limit', 30);

        arsort($leaveDaysByEmp);
        $topLeaveEmpIds = array_slice(array_keys($leaveDaysByEmp), 0, $limit);
        $employeesById = $topLeaveEmpIds === []
            ? collect()
            : Employee::query()
                ->select(['emp_id', 'emp_name', 'team', 'site_dedicated', 'position'])
                ->whereIn('emp_id', $topLeaveEmpIds)
                ->get()
                ->keyBy('emp_id');

        $leaderboard = [];
        foreach ($topLeaveEmpIds as $empId) {
            $employee = $employeesById->get($empId);
            $leaveYtd = (int) $leaveDaysByEmp[$empId];
            $effective = max(0, $totalWorkingDays - $leaveYtd);
            $leaderboard[] = [
                'EmpId' => $empId,
                'EmpName' => $employee?->emp_name ?? (string) $empId,
                'Team' => $employee?->team ?? '',
                'SiteDedicated' => $employee?->site_dedicated ?? '',
                'Position' => $employee?->position ?? '',
                'LeaveYTD' => $leaveYtd,
                'TotalWorkingDaysYTD' => $totalWorkingDays,
                'EffectiveWorkingDays' => $effective,
                'EffectiveWorkingPercent' => $this->support->workingDayPercent($effective, $totalWorkingDays),
            ];
        }

        $totalPersonWorkingDays = $employeeCount * $totalWorkingDays;
        $effectivePersonDays = max(0, $totalPersonWorkingDays - $leavePersonDays);

        return [
            'year' => $year,
            'team' => $team !== '' ? $team : 'All Teams',
            'site' => $site !== '' ? $site : 'All Sites',
            'windows' => [
                'thisWeekStart' => $this->support->formatISO($windows['thisWeekStart']),
                'thisWeekEnd' => $this->support->formatISO($windows['thisWeekEnd']),
                'nextWeekStart' => $this->support->formatISO($windows['nextWeekStart']),
                'nextWeekEnd' => $this->support->formatISO($windows['nextWeekEnd']),
                'nextTwoWeekStart' => $this->support->formatISO($windows['nextTwoWeekStart']),
                'nextTwoWeekEnd' => $this->support->formatISO($windows['nextTwoWeekEnd']),
            ],
            'kpis' => [
                'eventThisWeek' => count($eventGroups['thisWeek']),
                'upcomingEvent' => count($eventGroups['nextWeek']) + count($eventGroups['nextTwoWeek']) + count($eventGroups['moreThanTwoWeeks']),
                'leaveThisWeek' => count($leaveThisWeek),
                'upcomingLeave' => count($upcomingLeave),
                'projectActive' => $projectActive,
                'issueActive' => $issueActive,
            ],
            'eventStatus' => $eventGroups,
            'leaveStatus' => [
                'thisWeek' => $leaveThisWeek,
                'upcoming' => array_slice($upcomingLeave, 0, $upcomingLimit),
                'upcomingCount' => count($upcomingLeave),
            ],
            'leaderboard' => $leaderboard,
            'workforceEffectiveness' => [
                'employeeCount' => $employeeCount,
                'totalWorkingDaysPerEmployee' => $totalWorkingDays,
                'totalPersonWorkingDays' => $totalPersonWorkingDays,
                'leavePersonDays' => $leavePersonDays,
                'effectivePersonDays' => $effectivePersonDays,
                'effectiveWorkingPercent' => $this->support->workingDayPercent($effectivePersonDays, $totalPersonWorkingDays),
            ],
            'trackerHighlights' => $trackers,
        ];
    }

    private function employeeCount(string $team, string $site): int
    {
        $query = Employee::query();
        if (! $this->support->isAllTeam($team)) {
            $query->where('team', $team);
        }
        if (! $this->support->isAllSite($site)) {
            $query->where('site_dedicated', $site);
        }

        return (int) $query->count();
    }
}
