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

        $employees = $this->filteredEmployees($team, $site);
        $empIds = $employees->pluck('emp_id')->all();

        $leaveThisWeek = [];
        $upcomingLeave = [];
        $leaveDaysByEmp = [];

        if ($empIds !== []) {
            $leaves = LeaveRequest::query()
                ->whereIn('emp_id', $empIds)
                ->whereDate('start_date', '<=', $this->support->formatISO($yearEnd))
                ->whereDate('end_date', '>=', $this->support->formatISO($yearStart))
                ->get();

            foreach ($leaves as $leave) {
                $row = $this->leaveService->enrich($leave);
                $start = $this->support->parseISO($row['StartDate']);
                $end = $this->support->parseISO($row['EndDate']);

                if ($this->support->isDateRangeOverlap($start, $end, $windows['thisWeekStart'], $windows['thisWeekEnd'])) {
                    $leaveThisWeek[] = $row;
                }
                if ($start->gt($windows['thisWeekEnd'])) {
                    $upcomingLeave[] = $row;
                }

                $leaveDaysByEmp[$leave->emp_id] = ($leaveDaysByEmp[$leave->emp_id] ?? 0)
                    + $this->support->countWorkingDaysClipped($start, $end, $yearStart, $cutoff);
            }
        }

        $eventGroups = [
            'thisWeek' => [],
            'nextWeek' => [],
            'nextTwoWeek' => [],
            'moreThanTwoWeeks' => [],
        ];

        $eventsQuery = Event::query()
            ->whereYear('event_date', $year);
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
            ->whereDate('start_date', '<=', $this->support->formatISO($yearEnd))
            ->whereDate('due_date', '>=', $this->support->formatISO($yearStart));
        if (! $this->support->isAllTeam($team)) {
            $trackerQuery->where('department', $team);
        }
        if (! $this->support->isAllSite($site)) {
            $trackerQuery->where('site', $site);
        }

        foreach ($trackerQuery->get() as $tracker) {
            $this->trackerService->refreshEffectiveStatus($tracker);
            $row = $this->trackerService->enrichTracker($tracker);
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
        $leaderboard = [];
        $leavePersonDays = 0;
        foreach ($employees as $employee) {
            $leaveYtd = (int) ($leaveDaysByEmp[$employee->emp_id] ?? 0);
            $effective = max(0, $totalWorkingDays - $leaveYtd);
            $leavePersonDays += $leaveYtd;
            $leaderboard[] = [
                'EmpId' => $employee->emp_id,
                'EmpName' => $employee->emp_name,
                'Team' => $employee->team ?? '',
                'SiteDedicated' => $employee->site_dedicated ?? '',
                'Position' => $employee->position ?? '',
                'LeaveYTD' => $leaveYtd,
                'TotalWorkingDaysYTD' => $totalWorkingDays,
                'EffectiveWorkingDays' => $effective,
                'EffectiveWorkingPercent' => $this->support->workingDayPercent($effective, $totalWorkingDays),
            ];
        }

        usort($leaderboard, function (array $a, array $b): int {
            $leave = $b['LeaveYTD'] <=> $a['LeaveYTD'];

            return $leave !== 0 ? $leave : strcmp((string) $a['EmpName'], (string) $b['EmpName']);
        });

        $employeeCount = $employees->count();
        $totalPersonWorkingDays = $employeeCount * $totalWorkingDays;
        $effectivePersonDays = max(0, $totalPersonWorkingDays - $leavePersonDays);
        $limit = (int) config('ohs-dashboard.dashboard.leaderboard_limit', 200);
        $upcomingLimit = (int) config('ohs-dashboard.dashboard.upcoming_leave_limit', 30);

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
            'leaderboard' => array_slice($leaderboard, 0, $limit),
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

    /**
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function filteredEmployees(string $team, string $site)
    {
        $query = Employee::query()->select(['emp_id', 'emp_name', 'team', 'site_dedicated', 'position']);
        if (! $this->support->isAllTeam($team)) {
            $query->where('team', $team);
        }
        if (! $this->support->isAllSite($site)) {
            $query->where('site_dedicated', $site);
        }

        return $query->orderBy('emp_name')->get();
    }
}
