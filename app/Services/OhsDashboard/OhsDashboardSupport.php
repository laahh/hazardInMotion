<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard;

use App\Exceptions\OhsDashboard\OhsDashboardException;
use App\Models\OhsDashboard\Employee;
use App\Models\OhsDashboard\Holiday;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class OhsDashboardSupport
{
    /** @var array<string, string>|null */
    private ?array $holidayMap = null;

    public function timezone(): string
    {
        return (string) config('ohs-dashboard.timezone', 'Asia/Jakarta');
    }

    public function now(): Carbon
    {
        return Carbon::now($this->timezone());
    }

    public function today(): Carbon
    {
        return $this->now()->copy()->startOfDay();
    }

    public function todayISO(): string
    {
        return $this->formatISO($this->today());
    }

    public function formatISO(CarbonInterface $date): string
    {
        return $date->copy()->timezone($this->timezone())->format('Y-m-d');
    }

    public function parseISO(string $value, bool $startOfDay = true): Carbon
    {
        $trimmed = trim($value);
        if ($trimmed === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            throw new OhsDashboardException('Format tanggal harus YYYY-MM-DD.');
        }

        $date = Carbon::createFromFormat('Y-m-d', $trimmed, $this->timezone());
        if ($date === false) {
            throw new OhsDashboardException('Tanggal tidak valid.');
        }

        return $startOfDay ? $date->startOfDay() : $date;
    }

    public function optionalISO(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $this->parseISO($value);
    }

    public function formatDateTime(?CarbonInterface $date): string
    {
        if ($date === null) {
            return '';
        }

        return $date->copy()->timezone($this->timezone())->format('Y-m-d H:i:s');
    }

    public function startOfWeekMonday(CarbonInterface $date): Carbon
    {
        return $date->copy()->timezone($this->timezone())->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    public function getISOWeekNumber(CarbonInterface $date): int
    {
        return (int) $date->copy()->timezone($this->timezone())->isoWeek();
    }

    public function isDateRangeOverlap(CarbonInterface $startA, CarbonInterface $endA, CarbonInterface $startB, CarbonInterface $endB): bool
    {
        return $this->formatISO($startA) <= $this->formatISO($endB)
            && $this->formatISO($endA) >= $this->formatISO($startB);
    }

    public function isISODateInRange(string $iso, CarbonInterface $start, CarbonInterface $end): bool
    {
        return $iso >= $this->formatISO($start) && $iso <= $this->formatISO($end);
    }

    /**
     * @return array<string, string>
     */
    public function holidayMap(): array
    {
        if ($this->holidayMap === null) {
            $this->holidayMap = Holiday::query()
                ->get(['date', 'name'])
                ->mapWithKeys(function (Holiday $holiday): array {
                    $date = $holiday->date instanceof CarbonInterface
                        ? $this->formatISO($holiday->date)
                        : (string) $holiday->date;

                    return [$date => $holiday->name !== '' ? $holiday->name : 'Holiday'];
                })
                ->all();
        }

        return $this->holidayMap;
    }

    public function countWorkingDaysInclusive(CarbonInterface $start, CarbonInterface $end): int
    {
        $cursor = $start->copy()->timezone($this->timezone())->startOfDay();
        $last = $end->copy()->timezone($this->timezone())->startOfDay();
        if ($cursor->gt($last)) {
            return 0;
        }

        $holidays = $this->holidayMap();
        $count = 0;

        while ($cursor->lte($last)) {
            $iso = $this->formatISO($cursor);
            $day = (int) $cursor->dayOfWeek;
            if ($day !== Carbon::SATURDAY && $day !== Carbon::SUNDAY && ! isset($holidays[$iso])) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    public function countWorkingDaysClipped(
        CarbonInterface $start,
        CarbonInterface $end,
        CarbonInterface $rangeStart,
        CarbonInterface $rangeEnd,
    ): int {
        $clipStart = $start->copy()->max($rangeStart);
        $clipEnd = $end->copy()->min($rangeEnd);
        if ($clipStart->gt($clipEnd)) {
            return 0;
        }

        return $this->countWorkingDaysInclusive($clipStart, $clipEnd);
    }

    public function workingDayPercent(int $effective, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($effective / $total) * 1000) / 10;
    }

    public function ytdCutoff(int $year): Carbon
    {
        $currentYear = (int) $this->today()->year;

        if ($year < $currentYear) {
            return Carbon::create($year, 12, 31, 0, 0, 0, $this->timezone())->startOfDay();
        }

        if ($year > $currentYear) {
            return Carbon::create($year, 1, 1, 0, 0, 0, $this->timezone())->subDay()->startOfDay();
        }

        return $this->today();
    }

    /**
     * @return array{reference: Carbon, thisWeekStart: Carbon, thisWeekEnd: Carbon, nextWeekStart: Carbon, nextWeekEnd: Carbon, nextTwoWeekStart: Carbon, nextTwoWeekEnd: Carbon}
     */
    public function dashboardWeekWindows(int $year): array
    {
        $currentYear = (int) $this->today()->year;

        if ($year === $currentYear) {
            $reference = $this->today();
        } elseif ($year < $currentYear) {
            $reference = Carbon::create($year, 12, 31, 0, 0, 0, $this->timezone())->startOfDay();
        } else {
            $reference = Carbon::create($year, 1, 1, 0, 0, 0, $this->timezone())->subDay()->startOfDay();
        }

        $thisWeekStart = $this->startOfWeekMonday($reference);
        $thisWeekEnd = $thisWeekStart->copy()->addDays(6);
        $nextWeekStart = $thisWeekStart->copy()->addDays(7);
        $nextWeekEnd = $thisWeekStart->copy()->addDays(13);
        $nextTwoWeekStart = $thisWeekStart->copy()->addDays(14);
        $nextTwoWeekEnd = $thisWeekStart->copy()->addDays(20);

        return [
            'reference' => $reference,
            'thisWeekStart' => $thisWeekStart,
            'thisWeekEnd' => $thisWeekEnd,
            'nextWeekStart' => $nextWeekStart,
            'nextWeekEnd' => $nextWeekEnd,
            'nextTwoWeekStart' => $nextTwoWeekStart,
            'nextTwoWeekEnd' => $nextTwoWeekEnd,
        ];
    }

    public function deriveTrackerStatus(float $percentComplete, CarbonInterface $dueDate, ?CarbonInterface $today = null): string
    {
        $today ??= $this->today();

        if ($percentComplete >= 100) {
            return 'Closed';
        }

        if ($this->formatISO($dueDate) < $this->formatISO($today)) {
            return 'Overdue';
        }

        return 'On Going';
    }

    public function normalizePercentComplete(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $raw = str_replace(',', '.', trim((string) $value));
        if (! is_numeric($raw)) {
            throw new OhsDashboardException('% Complete harus berupa angka 0–100.');
        }

        $percent = round((float) $raw, 2);
        if ($percent < 0 || $percent > 100) {
            throw new OhsDashboardException('% Complete harus antara 0 dan 100.');
        }

        return $percent;
    }

    public function validatePercentComplete(mixed $value): float
    {
        if ($value === null || trim((string) $value) === '') {
            throw new OhsDashboardException('% Complete wajib diisi (0–100).');
        }

        return $this->normalizePercentComplete($value);
    }

    public function normalizeTrackerType(string $type): string
    {
        $normalized = trim($type);
        if (! in_array($normalized, ['Project', 'Issue'], true)) {
            throw new OhsDashboardException('TrackerType hanya boleh Project atau Issue.');
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $subTasks
     * @return array{percent: float, status: string, weekly: string, remarks: string}
     */
    public function calculateTrackerAggregate(array $subTasks, CarbonInterface $parentDue, ?CarbonInterface $today = null): array
    {
        $today ??= $this->today();
        $count = count($subTasks);
        if ($count === 0) {
            return [
                'percent' => 0.0,
                'status' => $this->deriveTrackerStatus(0, $parentDue, $today),
                'weekly' => '',
                'remarks' => '',
            ];
        }

        $sum = 0.0;
        $closed = 0;
        $hasOverdue = false;
        $latest = null;
        $latestName = '';
        $latestWeekly = '';
        $latestRemarks = '';
        $latestUpdated = null;

        foreach ($subTasks as $task) {
            $percent = (float) ($task['current_percent_complete'] ?? $task['percent'] ?? 0);
            $sum += $percent;
            $status = (string) ($task['status'] ?? '');
            if ($status === 'Closed') {
                $closed++;
            }
            if ($status === 'Overdue') {
                $hasOverdue = true;
            }

            $updated = $task['last_updated'] ?? null;
            $updatedTs = $updated instanceof CarbonInterface ? $updated->timestamp : (is_string($updated) && $updated !== '' ? strtotime($updated) : 0);
            if ($latestUpdated === null || $updatedTs >= $latestUpdated) {
                $latestUpdated = $updatedTs;
                $latestName = (string) ($task['sub_task_name'] ?? $task['name'] ?? '');
                $latestWeekly = (string) ($task['current_progress_report_weekly'] ?? $task['weekly'] ?? '');
                $latestRemarks = (string) ($task['current_remarks'] ?? $task['remarks'] ?? '');
                $latest = $task;
            }
        }

        $percent = round($sum / $count, 2);
        $parentDuePassed = $this->formatISO($parentDue) < $this->formatISO($today);

        if ($closed === $count) {
            $status = 'Closed';
        } elseif ($hasOverdue || $parentDuePassed) {
            $status = 'Overdue';
        } else {
            $status = 'On Going';
        }

        $weekly = $closed.'/'.$count.' sub task closed. Latest - '.$latestName.': '.$latestWeekly;
        $overdueCount = 0;
        foreach ($subTasks as $task) {
            if (($task['status'] ?? '') === 'Overdue') {
                $overdueCount++;
            }
        }

        $remarks = $latestRemarks;
        if ($remarks === '') {
            $remarks = $overdueCount > 0
                ? $overdueCount.' sub task overdue.'
                : 'Progress dihitung dari rata-rata seluruh sub task.';
        }

        unset($latest);

        return [
            'percent' => $percent,
            'status' => $status,
            'weekly' => $weekly,
            'remarks' => $remarks,
        ];
    }

    public function isAllFilter(?string $value, string $allLabel): bool
    {
        $value = trim((string) $value);

        return $value === '' || strcasecmp($value, $allLabel) === 0;
    }

    public function isAllTeam(?string $value): bool
    {
        return $this->isAllFilter($value, 'All Teams')
            || $this->isAllFilter($value, 'All Departments');
    }

    public function isAllSite(?string $value): bool
    {
        return $this->isAllFilter($value, 'All Sites');
    }

    public function isAllType(?string $value): bool
    {
        return $this->isAllFilter($value, 'All Types');
    }

    public function isAllStatus(?string $value): bool
    {
        return $this->isAllFilter($value, 'All Status');
    }

    public function clampInteger(mixed $value, int $min, int $max, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $int = (int) $value;

        return max($min, min($max, $int));
    }

    public function toBoolean(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtoupper(trim((string) $value));

        return in_array($normalized, ['1', 'TRUE', 'YES', 'YA', 'ON'], true);
    }

    /**
     * @return list<string>
     */
    public function normalizeScheduleDays(mixed $value): array
    {
        $allowed = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
        $raw = is_array($value) ? implode(',', $value) : (string) $value;
        $parts = preg_split('/[\s,;]+/', strtoupper($raw)) ?: [];
        $days = [];

        foreach ($parts as $part) {
            if (in_array($part, $allowed, true) && ! in_array($part, $days, true)) {
                $days[] = $part;
            }
        }

        return $days;
    }

    /**
     * @return list<string>
     */
    public function parseEmailList(string $raw): array
    {
        $parts = preg_split('/[,;\n\r]+/', $raw) ?: [];
        $emails = [];

        foreach ($parts as $part) {
            $email = strtolower(trim($part));
            if ($email === '') {
                continue;
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new OhsDashboardException('Format email tidak valid: '.$email);
            }
            if (! in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    public function jsWeekdayCode(CarbonInterface $date): string
    {
        return ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'][(int) $date->dayOfWeek];
    }

    /**
     * @return array{shouldRun: bool, key: string, reason: string}
     */
    public function getPortalSchedulerDecision(
        bool $enabled,
        array $scheduleDays,
        int $sendHour,
        int $sendMinute,
        string $lastScheduledKey,
        ?CarbonInterface $now = null,
    ): array {
        $now ??= $this->now();
        $key = $this->formatISO($now).' '.str_pad((string) $sendHour, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $sendMinute, 2, '0', STR_PAD_LEFT);
        $window = (int) config('ohs-dashboard.scheduler.window_minutes', 75);

        if (! $enabled) {
            return ['shouldRun' => false, 'key' => $key, 'reason' => 'Scheduler disabled.'];
        }

        if (! in_array($this->jsWeekdayCode($now), $scheduleDays, true)) {
            return ['shouldRun' => false, 'key' => $key, 'reason' => 'Hari ini tidak termasuk schedule_days.'];
        }

        $target = $now->copy()->startOfDay()->setTime($sendHour, $sendMinute, 0);
        $minutes = (int) floor(($now->getTimestamp() - $target->getTimestamp()) / 60);
        if ($minutes < 0 || $minutes >= $window) {
            return ['shouldRun' => false, 'key' => $key, 'reason' => 'Di luar window 75 menit.'];
        }

        if ($lastScheduledKey === $key) {
            return ['shouldRun' => false, 'key' => $key, 'reason' => 'Sudah dijalankan untuk slot ini.'];
        }

        return ['shouldRun' => true, 'key' => $key, 'reason' => ''];
    }

    /**
     * @return array{shouldRun: bool, key: string, reason: string}
     */
    public function getOverdueReminderDecision(string $lastKey, ?CarbonInterface $now = null): array
    {
        $now ??= $this->now();
        $todayISO = $this->formatISO($now);
        $hour = (int) config('ohs-dashboard.scheduler.overdue_hour', 8);
        $minute = (int) config('ohs-dashboard.scheduler.overdue_minute', 0);
        $window = (int) config('ohs-dashboard.scheduler.window_minutes', 75);
        $target = $now->copy()->startOfDay()->setTime($hour, $minute, 0);
        $minutes = (int) floor(($now->getTimestamp() - $target->getTimestamp()) / 60);

        if ($minutes < 0 || $minutes >= $window) {
            return ['shouldRun' => false, 'key' => $todayISO, 'reason' => 'Di luar window 08:00 + 75 menit.'];
        }

        if ($lastKey === $todayISO) {
            return ['shouldRun' => false, 'key' => $todayISO, 'reason' => 'Sudah dijalankan hari ini.'];
        }

        return ['shouldRun' => true, 'key' => $todayISO, 'reason' => ''];
    }

    public function employeeMap(): array
    {
        return Employee::query()
            ->get(['emp_id', 'sid', 'emp_name', 'position', 'team', 'site_dedicated', 'company', 'photo_url'])
            ->keyBy('emp_id')
            ->all();
    }

    public function requireEmployee(string $empId, string $label = 'Karyawan'): Employee
    {
        $empId = trim($empId);
        if ($empId === '') {
            throw new OhsDashboardException($label.' wajib dipilih.');
        }

        $employee = Employee::query()->find($empId);
        if (! $employee instanceof Employee) {
            throw new OhsDashboardException($label.' tidak ditemukan.');
        }

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotEmployee(Employee $employee, string $prefix = ''): array
    {
        if ($prefix === '') {
            return [
                'emp_id' => $employee->emp_id,
                'emp_name' => $employee->emp_name,
                'team' => $employee->team,
                'position' => $employee->position,
                'site_dedicated' => $employee->site_dedicated,
            ];
        }

        return [
            $prefix.'_emp_id' => $employee->emp_id,
            $prefix.'_name' => $employee->emp_name,
            $prefix.'_team' => $employee->team,
            $prefix.'_position' => $employee->position,
            $prefix.'_site_dedicated' => $employee->site_dedicated,
        ];
    }

    public function fillIfEmpty(?string $current, ?string $fallback): string
    {
        $current = trim((string) $current);

        return $current !== '' ? $current : trim((string) $fallback);
    }

    public function trackerStatusSortOrder(string $status): int
    {
        return match ($status) {
            'Overdue' => 0,
            'On Going' => 1,
            'Closed' => 2,
            default => 9,
        };
    }
}
