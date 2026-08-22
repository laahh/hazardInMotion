<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard;

use App\Exceptions\OhsDashboard\OhsDashboardException;
use App\Models\OhsDashboard\Employee;
use App\Models\OhsDashboard\LeaveRequest;
use App\Models\OhsDashboard\LeaveType;
use App\Services\OhsDashboard\Support\OhsDashboardId;
use App\Services\OhsDashboard\Support\OhsDashboardPayload;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class LeaveService
{
    public function __construct(private readonly OhsDashboardSupport $support) {}

    /**
     * Total leave days (working days only; excludes Sat/Sun/holidays, clipped to today) per employee, year-to-date.
     *
     * @param  list<string>  $empIds
     * @return array<string, int>
     */
    public function leaveDaysByEmpForYear(array $empIds, int $year): array
    {
        if ($empIds === []) {
            return [];
        }

        $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, $this->support->timezone())->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31, 0, 0, 0, $this->support->timezone())->startOfDay();
        $cutoff = $this->support->ytdCutoff($year);

        $days = [];
        $leaves = LeaveRequest::query()
            ->whereIn('emp_id', $empIds)
            ->where('start_date', '<=', $this->support->formatISO($yearEnd))
            ->where('end_date', '>=', $this->support->formatISO($yearStart))
            ->get(['emp_id', 'start_date', 'end_date']);

        foreach ($leaves as $leave) {
            if ($leave->start_date === null || $leave->end_date === null) {
                continue;
            }
            $days[$leave->emp_id] = ($days[$leave->emp_id] ?? 0)
                + $this->support->countWorkingDaysClipped($leave->start_date, $leave->end_date, $yearStart, $cutoff);
        }

        return $days;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkOverlap(array $payload): array
    {
        $empId = OhsDashboardPayload::string($payload, 'EmpId');
        $backupEmpId = OhsDashboardPayload::string($payload, 'BackupEmpId');
        $startRaw = OhsDashboardPayload::string($payload, 'StartDate');
        $endRaw = OhsDashboardPayload::string($payload, 'EndDate');
        $excludeId = OhsDashboardPayload::nullableString($payload, 'ExcludeRequestId');

        if ($empId === '' || $startRaw === '' || $endRaw === '') {
            return [
                'hasOverlap' => false,
                'overlaps' => [],
                'hasBackupConflict' => false,
                'backupOverlaps' => [],
                'message' => '',
            ];
        }

        $start = $this->support->parseISO($startRaw);
        $end = $this->support->parseISO($endRaw);
        if ($end->lt($start)) {
            throw new OhsDashboardException('EndDate harus sama atau setelah StartDate.');
        }

        $overlaps = $this->findLeaveOverlaps($empId, $start, $end, $excludeId);
        $backupOverlaps = $backupEmpId !== ''
            ? $this->findLeaveOverlaps($backupEmpId, $start, $end, $excludeId)
            : [];

        return [
            'hasOverlap' => $overlaps !== [],
            'overlaps' => array_map(fn (LeaveRequest $row): array => $this->overlapSummary($row), $overlaps),
            'hasBackupConflict' => $backupOverlaps !== [],
            'backupOverlaps' => array_map(fn (LeaveRequest $row): array => $this->overlapSummary($row), $backupOverlaps),
            'message' => $this->overlapMessage($overlaps, $backupOverlaps),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{requestId: string}
     */
    public function create(array $payload): array
    {
        $empId = OhsDashboardPayload::string($payload, 'EmpId');
        $backupEmpId = OhsDashboardPayload::string($payload, 'BackupEmpId');
        $leaveType = OhsDashboardPayload::string($payload, 'LeaveType');
        $startRaw = OhsDashboardPayload::string($payload, 'StartDate');
        $endRaw = OhsDashboardPayload::string($payload, 'EndDate');
        $timeFrom = OhsDashboardPayload::nullableString($payload, 'TimeFrom');
        $timeTo = OhsDashboardPayload::nullableString($payload, 'TimeTo');
        $note = OhsDashboardPayload::nullableString($payload, 'Note');

        $employee = $this->support->requireEmployee($empId, 'Karyawan');
        if ($backupEmpId === '') {
            throw new OhsDashboardException('Backup / Acting PIC wajib dipilih.');
        }
        if ($backupEmpId === $empId) {
            throw new OhsDashboardException('Backup / Acting PIC tidak boleh sama dengan karyawan yang cuti.');
        }
        $backup = $this->support->requireEmployee($backupEmpId, 'Backup / Acting PIC');

        if ($leaveType === '') {
            throw new OhsDashboardException('Leave Type wajib dipilih.');
        }
        $typeExists = LeaveType::query()->where('leave_type', $leaveType)->exists();
        if (! $typeExists) {
            throw new OhsDashboardException('Leave Type tidak valid.');
        }

        $start = $this->support->parseISO($startRaw);
        $end = $this->support->parseISO($endRaw);
        if ($end->lt($start)) {
            throw new OhsDashboardException('EndDate harus sama atau setelah StartDate.');
        }

        $overlaps = $this->findLeaveOverlaps($empId, $start, $end);
        $backupOverlaps = $this->findLeaveOverlaps($backupEmpId, $start, $end);
        if ($overlaps !== [] || $backupOverlaps !== []) {
            throw new OhsDashboardException($this->overlapMessage($overlaps, $backupOverlaps));
        }

        $requestId = OhsDashboardId::leave();

        DB::transaction(function () use ($requestId, $employee, $backup, $leaveType, $start, $end, $timeFrom, $timeTo, $note): void {
            LeaveRequest::query()->create([
                'request_id' => $requestId,
                'timestamp' => $this->support->now(),
                'emp_id' => $employee->emp_id,
                'emp_name' => $employee->emp_name,
                'team' => $employee->team,
                'position' => $employee->position,
                'site_dedicated' => $employee->site_dedicated,
                'leave_type' => $leaveType,
                'start_date' => $this->support->formatISO($start),
                'end_date' => $this->support->formatISO($end),
                'start_time' => $timeFrom,
                'end_time' => $timeTo,
                'note' => $note,
                'backup_emp_id' => $backup->emp_id,
                'backup_emp_name' => $backup->emp_name,
                'backup_team' => $backup->team,
                'backup_position' => $backup->position,
                'backup_site_dedicated' => $backup->site_dedicated,
            ]);
        });

        return ['requestId' => $requestId];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function list(array $payload): array
    {
        $team = OhsDashboardPayload::string($payload, 'team');
        $site = OhsDashboardPayload::string($payload, 'site');
        $search = mb_strtolower(OhsDashboardPayload::string($payload, 'search'));
        $year = $this->support->clampInteger(
            OhsDashboardPayload::raw($payload, 'year'),
            2000,
            2100,
            (int) $this->support->today()->year,
        );

        $yearStart = $this->support->formatISO(
            $this->support->today()->copy()->setYear($year)->startOfYear()
        );
        $yearEnd = $this->support->formatISO(
            $this->support->today()->copy()->setYear($year)->endOfYear()->startOfDay()
        );

        $query = LeaveRequest::query()
            ->where('start_date', '<=', $yearEnd)
            ->where('end_date', '>=', $yearStart)
            ->orderByDesc('start_date')
            ->orderByDesc('end_date')
            ->limit(400);

        if (! $this->support->isAllTeam($team)) {
            $query->where('team', $team);
        }
        if (! $this->support->isAllSite($site)) {
            $query->where('site_dedicated', $site);
        }

        $today = $this->support->today();
        $requests = [];
        $counts = ['total' => 0, 'onLeave' => 0, 'upcoming' => 0, 'completed' => 0];

        foreach ($query->get() as $row) {
            $enriched = $this->enrichWithStatus($row, $today);
            if ($search !== '' && ! $this->leaveMatchesSearch($enriched, $search)) {
                continue;
            }
            $counts['total']++;
            match ($enriched['Status']) {
                'On Leave' => $counts['onLeave']++,
                'Upcoming' => $counts['upcoming']++,
                default => $counts['completed']++,
            };
            $requests[] = $enriched;
        }

        return [
            'year' => $year,
            'counts' => $counts,
            'requests' => $requests,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $requestId): array
    {
        return $this->enrichWithStatus($this->requireLeave($requestId), $this->support->today());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{requestId: string}
     */
    public function update(array $payload): array
    {
        $leave = $this->requireLeave(OhsDashboardPayload::string($payload, 'RequestId'));
        $empId = OhsDashboardPayload::string($payload, 'EmpId') ?: $leave->emp_id;
        $backupEmpId = OhsDashboardPayload::string($payload, 'BackupEmpId') ?: $leave->backup_emp_id;
        $leaveType = OhsDashboardPayload::string($payload, 'LeaveType') ?: $leave->leave_type;
        $startRaw = OhsDashboardPayload::string($payload, 'StartDate') ?: ($leave->start_date?->format('Y-m-d') ?? '');
        $endRaw = OhsDashboardPayload::string($payload, 'EndDate') ?: ($leave->end_date?->format('Y-m-d') ?? '');
        $timeFrom = OhsDashboardPayload::nullableString($payload, 'TimeFrom');
        $timeTo = OhsDashboardPayload::nullableString($payload, 'TimeTo');
        $note = OhsDashboardPayload::nullableString($payload, 'Note');

        $employee = $this->support->requireEmployee($empId, 'Karyawan');
        if ($backupEmpId === '') {
            throw new OhsDashboardException('Backup / Acting PIC wajib dipilih.');
        }
        if ($backupEmpId === $empId) {
            throw new OhsDashboardException('Backup / Acting PIC tidak boleh sama dengan karyawan yang cuti.');
        }
        $backup = $this->support->requireEmployee($backupEmpId, 'Backup / Acting PIC');

        if ($leaveType === '') {
            throw new OhsDashboardException('Leave Type wajib dipilih.');
        }
        $typeExists = LeaveType::query()->where('leave_type', $leaveType)->exists();
        if (! $typeExists) {
            throw new OhsDashboardException('Leave Type tidak valid.');
        }

        $start = $this->support->parseISO($startRaw);
        $end = $this->support->parseISO($endRaw);
        if ($end->lt($start)) {
            throw new OhsDashboardException('EndDate harus sama atau setelah StartDate.');
        }

        $overlaps = $this->findLeaveOverlaps($empId, $start, $end, $leave->request_id);
        $backupOverlaps = $this->findLeaveOverlaps($backupEmpId, $start, $end, $leave->request_id);
        if ($overlaps !== [] || $backupOverlaps !== []) {
            throw new OhsDashboardException($this->overlapMessage($overlaps, $backupOverlaps));
        }

        $leave->fill([
            'emp_id' => $employee->emp_id,
            'emp_name' => $employee->emp_name,
            'team' => $employee->team,
            'position' => $employee->position,
            'site_dedicated' => $employee->site_dedicated,
            'leave_type' => $leaveType,
            'start_date' => $this->support->formatISO($start),
            'end_date' => $this->support->formatISO($end),
            'start_time' => $timeFrom,
            'end_time' => $timeTo,
            'note' => $note,
            'backup_emp_id' => $backup->emp_id,
            'backup_emp_name' => $backup->emp_name,
            'backup_team' => $backup->team,
            'backup_position' => $backup->position,
            'backup_site_dedicated' => $backup->site_dedicated,
        ]);
        $leave->save();

        return ['requestId' => $leave->request_id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{requestId: string, deleted: bool}
     */
    public function delete(array $payload): array
    {
        $leave = $this->requireLeave(OhsDashboardPayload::string($payload, 'RequestId'));
        $requestId = $leave->request_id;
        $leave->delete();

        return ['requestId' => $requestId, 'deleted' => true];
    }

    public function requireLeave(string $requestId): LeaveRequest
    {
        $requestId = trim($requestId);
        if ($requestId === '') {
            throw new OhsDashboardException('RequestId wajib diisi.');
        }

        $leave = LeaveRequest::query()->find($requestId);
        if (! $leave instanceof LeaveRequest) {
            throw new OhsDashboardException('Leave request tidak ditemukan.');
        }

        return $leave;
    }

    /**
     * @return array<string, mixed>
     */
    public function history(string $empId, ?int $year): array
    {
        $employee = $this->support->requireEmployee($empId);
        $year ??= (int) $this->support->today()->year;
        $today = $this->support->today();
        $yearStart = $today->copy()->setYear($year)->startOfYear();
        $yearEnd = $yearStart->copy()->endOfYear()->startOfDay();
        $cutoff = $this->support->ytdCutoff($year);

        $records = LeaveRequest::query()
            ->where('emp_id', $employee->emp_id)
            ->orderByDesc('start_date')
            ->orderByDesc('end_date')
            ->get();

        $apiRecords = [];
        $totalLeaveDaysAllHistory = 0;
        $leaveDaysYtd = 0;

        foreach ($records as $row) {
            $enriched = $this->enrich($row, $employee);
            $start = $this->support->parseISO($enriched['StartDate']);
            $end = $this->support->parseISO($enriched['EndDate']);
            $leaveDays = $this->support->countWorkingDaysInclusive($start, $end);
            $totalLeaveDaysAllHistory += $leaveDays;

            if ($this->support->isDateRangeOverlap($start, $end, $yearStart, $yearEnd)) {
                $leaveDaysYtd += $this->support->countWorkingDaysClipped($start, $end, $yearStart, $cutoff);
            }

            $status = 'Completed';
            if ($start->gt($today)) {
                $status = 'Upcoming';
            } elseif ($start->lte($today) && $end->gte($today)) {
                $status = 'On Leave';
            }

            $enriched['LeaveDays'] = $leaveDays;
            $enriched['WorkingDays'] = $leaveDays;
            $enriched['Status'] = $status;
            $apiRecords[] = $enriched;
        }

        $ytdWorkingDays = $this->support->countWorkingDaysInclusive($yearStart, $cutoff);
        $effective = max(0, $ytdWorkingDays - $leaveDaysYtd);

        return [
            'employee' => $employee->toApiArray(),
            'records' => $apiRecords,
            'totalRequests' => count($apiRecords),
            'totalWorkingDays' => $totalLeaveDaysAllHistory,
            'totalLeaveDaysAllHistory' => $totalLeaveDaysAllHistory,
            'leaveDaysYTD' => $leaveDaysYtd,
            'ytdWorkingDays' => $ytdWorkingDays,
            'effectiveWorkingDays' => $effective,
            'effectiveWorkingPercent' => $this->support->workingDayPercent($effective, $ytdWorkingDays),
            'currentYear' => $year,
        ];
    }

    /**
     * @return list<LeaveRequest>
     */
    public function findLeaveOverlaps(string $empId, CarbonInterface $start, CarbonInterface $end, ?string $excludeId = null): array
    {
        $query = LeaveRequest::query()->where('emp_id', $empId);
        if ($excludeId) {
            $query->where('request_id', '!=', $excludeId);
        }

        $startIso = $this->support->formatISO($start);
        $endIso = $this->support->formatISO($end);

        return $query
            ->where('start_date', '<=', $endIso)
            ->where('end_date', '>=', $startIso)
            ->orderBy('start_date')
            ->limit(50)
            ->get()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function enrich(LeaveRequest $row, ?Employee $employee = null): array
    {
        if ($employee === null && $row->relationLoaded('employee')) {
            $employee = $row->employee;
        }

        return [
            'RequestId' => $row->request_id,
            'Timestamp' => $this->support->formatDateTime($row->timestamp),
            'EmpId' => $row->emp_id,
            'EmpName' => $this->support->fillIfEmpty($row->emp_name, $employee?->emp_name),
            'Team' => $this->support->fillIfEmpty($row->team, $employee?->team),
            'Position' => $this->support->fillIfEmpty($row->position, $employee?->position),
            'SiteDedicated' => $this->support->fillIfEmpty($row->site_dedicated, $employee?->site_dedicated),
            'LeaveType' => $row->leave_type,
            'StartDate' => $row->start_date?->format('Y-m-d') ?? (string) $row->start_date,
            'EndDate' => $row->end_date?->format('Y-m-d') ?? (string) $row->end_date,
            'TimeFrom' => $row->start_time ? substr((string) $row->start_time, 0, 5) : '',
            'TimeTo' => $row->end_time ? substr((string) $row->end_time, 0, 5) : '',
            'Note' => $row->note ?? '',
            'BackupEmpId' => $row->backup_emp_id,
            'BackupEmpName' => $row->backup_emp_name,
            'BackupTeam' => $row->backup_team ?? '',
            'BackupPosition' => $row->backup_position ?? '',
            'BackupSiteDedicated' => $row->backup_site_dedicated ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function overlapSummary(LeaveRequest $row): array
    {
        return [
            'RequestId' => $row->request_id,
            'LeaveType' => $row->leave_type,
            'StartDate' => $row->start_date?->format('Y-m-d'),
            'EndDate' => $row->end_date?->format('Y-m-d'),
            'EmpName' => $row->emp_name,
        ];
    }

    /**
     * @param  list<LeaveRequest>  $overlaps
     * @param  list<LeaveRequest>  $backupOverlaps
     */
    private function overlapMessage(array $overlaps, array $backupOverlaps): string
    {
        $parts = [];

        if ($overlaps !== []) {
            $first = $overlaps[0];
            $extra = count($overlaps) - 1;
            $message = 'Tanggal beririsan dengan '.$first->leave_type.' ('.$first->start_date?->format('Y-m-d').' sampai '.$first->end_date?->format('Y-m-d').')';
            if ($extra > 0) {
                $message .= ' dan '.$extra.' request lain';
            }
            $parts[] = $message.'.';
        }

        if ($backupOverlaps !== []) {
            $first = $backupOverlaps[0];
            $parts[] = 'Backup / Acting PIC '.$first->emp_name.' juga sedang on leave ('.$first->start_date?->format('Y-m-d').' sampai '.$first->end_date?->format('Y-m-d').'). Pilih backup lain.';
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichWithStatus(LeaveRequest $row, CarbonInterface $today): array
    {
        $enriched = $this->enrich($row);
        $start = $this->support->parseISO($enriched['StartDate']);
        $end = $this->support->parseISO($enriched['EndDate']);
        $status = 'Completed';
        if ($start->gt($today)) {
            $status = 'Upcoming';
        } elseif ($start->lte($today) && $end->gte($today)) {
            $status = 'On Leave';
        }

        $enriched['LeaveDays'] = $this->support->countWorkingDaysInclusive($start, $end);
        $enriched['Status'] = $status;

        return $enriched;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function leaveMatchesSearch(array $row, string $search): bool
    {
        $haystack = mb_strtolower(implode(' ', [
            $row['RequestId'] ?? '',
            $row['EmpId'] ?? '',
            $row['EmpName'] ?? '',
            $row['Team'] ?? '',
            $row['LeaveType'] ?? '',
            $row['BackupEmpName'] ?? '',
            $row['Note'] ?? '',
            $row['Status'] ?? '',
        ]));

        return str_contains($haystack, $search);
    }
}
