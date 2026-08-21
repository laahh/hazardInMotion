<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard;

use App\Exceptions\OhsDashboard\OhsDashboardException;
use App\Models\OhsDashboard\Event;
use App\Models\OhsDashboard\EventActionItem;
use App\Models\OhsDashboard\EventAttendance;
use App\Models\OhsDashboard\EventMinute;
use App\Services\OhsDashboard\Support\OhsDashboardId;
use App\Services\OhsDashboard\Support\OhsDashboardPayload;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class EventService
{
    public function __construct(private readonly OhsDashboardSupport $support) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{eventId: string}
     */
    public function create(array $payload): array
    {
        $name = OhsDashboardPayload::string($payload, 'EventName');
        $description = OhsDashboardPayload::string($payload, 'Description');
        $where = OhsDashboardPayload::string($payload, 'Where');
        $picEmpId = OhsDashboardPayload::string($payload, 'PICEmpId');
        $eventDateRaw = OhsDashboardPayload::string($payload, 'EventDate');

        if ($name === '' || $description === '' || $where === '' || $eventDateRaw === '') {
            throw new OhsDashboardException('EventName, Description, Where, PIC, dan EventDate wajib diisi.');
        }

        $eventDate = $this->support->parseISO($eventDateRaw);
        if ($eventDate->lt($this->support->today())) {
            throw new OhsDashboardException('Tanggal event tidak boleh sebelum hari ini.');
        }

        $pic = $this->support->requireEmployee($picEmpId, 'PIC');
        $eventId = OhsDashboardId::event();

        Event::query()->create([
            'event_id' => $eventId,
            'timestamp' => $this->support->now(),
            'event_name' => $name,
            'description' => $description,
            'where' => $where,
            'readiness_update' => null,
            'readiness_updated_at' => null,
            'pic_emp_id' => $pic->emp_id,
            'pic_name' => $pic->emp_name,
            'pic_team' => $pic->team,
            'pic_position' => $pic->position,
            'pic_site_dedicated' => $pic->site_dedicated,
            'event_date' => $this->support->formatISO($eventDate),
        ]);

        return ['eventId' => $eventId];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{eventId: string}
     */
    public function update(array $payload): array
    {
        $event = $this->requireEvent(OhsDashboardPayload::string($payload, 'EventId'));
        $name = OhsDashboardPayload::string($payload, 'EventName');
        $description = OhsDashboardPayload::string($payload, 'Description');
        $where = OhsDashboardPayload::string($payload, 'Where');
        $picEmpId = OhsDashboardPayload::string($payload, 'PICEmpId');
        $eventDateRaw = OhsDashboardPayload::string($payload, 'EventDate');

        if ($name === '' || $description === '' || $where === '' || $eventDateRaw === '') {
            throw new OhsDashboardException('EventName, Description, Where, PIC, dan EventDate wajib diisi.');
        }

        $pic = $this->support->requireEmployee($picEmpId, 'PIC');
        $eventDate = $this->support->parseISO($eventDateRaw);

        $event->fill([
            'event_name' => $name,
            'description' => $description,
            'where' => $where,
            'pic_emp_id' => $pic->emp_id,
            'pic_name' => $pic->emp_name,
            'pic_team' => $pic->team,
            'pic_position' => $pic->position,
            'pic_site_dedicated' => $pic->site_dedicated,
            'event_date' => $this->support->formatISO($eventDate),
        ]);
        $event->save();

        return ['eventId' => $event->event_id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateReadiness(array $payload): array
    {
        $event = $this->requireEvent(OhsDashboardPayload::string($payload, 'EventId'));
        $update = OhsDashboardPayload::string($payload, 'ReadinessUpdate');
        if ($update === '') {
            throw new OhsDashboardException('ReadinessUpdate wajib diisi.');
        }

        $now = $this->support->now();
        $event->readiness_update = $update;
        $event->readiness_updated_at = $now;
        $event->save();

        return [
            'eventId' => $event->event_id,
            'readinessUpdate' => $update,
            'readinessUpdatedAt' => $this->support->formatDateTime($now),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function makerData(array $payload): array
    {
        $team = OhsDashboardPayload::string($payload, 'team');
        $site = OhsDashboardPayload::string($payload, 'site');
        $windows = $this->support->dashboardWeekWindows((int) $this->support->today()->year);
        $today = $this->support->today();

        $query = Event::query()->orderBy('event_date');
        if (! $this->support->isAllTeam($team)) {
            $query->where('pic_team', $team);
        }
        if (! $this->support->isAllSite($site)) {
            $query->where('pic_site_dedicated', $site);
        }

        $events = [];
        $counts = [
            'This Week' => 0,
            'Next Week' => 0,
            'Next 2 Week' => 0,
            'More Than 2 Weeks Ahead' => 0,
            'Previous Event' => 0,
        ];

        foreach ($query->get() as $event) {
            $row = $this->enrich($event);
            [$status, $order] = $this->scheduleStatus($event->event_date, $today, $windows);
            $row['ScheduleStatus'] = $status;
            $row['ScheduleOrder'] = $order;
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $events[] = $row;
        }

        usort($events, function (array $a, array $b): int {
            $prevA = $a['ScheduleStatus'] === 'Previous Event';
            $prevB = $b['ScheduleStatus'] === 'Previous Event';
            if ($prevA !== $prevB) {
                return $prevA <=> $prevB;
            }
            if ($prevA) {
                return strcmp((string) $b['EventDate'], (string) $a['EventDate']);
            }
            if ($a['ScheduleOrder'] !== $b['ScheduleOrder']) {
                return $a['ScheduleOrder'] <=> $b['ScheduleOrder'];
            }

            return strcmp((string) $a['EventDate'], (string) $b['EventDate']);
        });

        return [
            'events' => $events,
            'counts' => $counts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkinInfo(string $eventId): array
    {
        $event = $this->requireEvent($eventId, 'Event tidak ditemukan atau QR sudah tidak berlaku.');
        $attendances = EventAttendance::query()
            ->where('event_id', $event->event_id)
            ->orderBy('check_in_at')
            ->get();

        return [
            'event' => [
                'EventId' => $event->event_id,
                'EventName' => $event->event_name,
                'Description' => $event->description,
                'Where' => $event->where,
                'EventDate' => $event->event_date?->format('Y-m-d'),
            ],
            'checkedInEmpIds' => $attendances->pluck('emp_id')->all(),
            'attendanceCount' => $attendances->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkin(array $payload): array
    {
        $event = $this->requireEvent(OhsDashboardPayload::string($payload, 'EventId'));
        $employee = $this->support->requireEmployee(OhsDashboardPayload::string($payload, 'EmpId'));

        $existing = EventAttendance::query()
            ->where('event_id', $event->event_id)
            ->where('emp_id', $employee->emp_id)
            ->first();

        if ($existing instanceof EventAttendance) {
            return [
                'alreadyCheckedIn' => true,
                'empName' => $existing->emp_name,
                'checkInAt' => $this->support->formatDateTime($existing->check_in_at),
            ];
        }

        $now = $this->support->now();
        EventAttendance::query()->create([
            'attendance_id' => OhsDashboardId::attendance(),
            'timestamp' => $now,
            'event_id' => $event->event_id,
            'emp_id' => $employee->emp_id,
            'emp_name' => $employee->emp_name,
            'team' => $employee->team,
            'position' => $employee->position,
            'site_dedicated' => $employee->site_dedicated,
            'check_in_at' => $now,
        ]);

        return [
            'alreadyCheckedIn' => false,
            'empName' => $employee->emp_name,
            'checkInAt' => $this->support->formatDateTime($now),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attendanceSummary(string $eventId): array
    {
        $event = $this->requireEvent($eventId);
        $rows = EventAttendance::query()
            ->where('event_id', $event->event_id)
            ->orderBy('check_in_at')
            ->get();

        return [
            'event' => $this->enrich($event),
            'attendanceCount' => $rows->count(),
            'attendance' => $rows->map(fn (EventAttendance $row): array => [
                'AttendanceId' => $row->attendance_id,
                'EventId' => $row->event_id,
                'EmpId' => $row->emp_id,
                'EmpName' => $row->emp_name,
                'Team' => $row->team ?? '',
                'Position' => $row->position ?? '',
                'SiteDedicated' => $row->site_dedicated ?? '',
                'CheckInAt' => $this->support->formatDateTime($row->check_in_at),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function minutes(string $eventId): array
    {
        $event = $this->requireEvent($eventId);
        $minute = EventMinute::query()->find($event->event_id);
        $items = EventActionItem::query()
            ->where('event_id', $event->event_id)
            ->orderBy('timestamp')
            ->get();

        return [
            'eventId' => $event->event_id,
            'summary' => $minute?->summary ?? '',
            'updatedAt' => $this->support->formatDateTime($minute?->updated_at),
            'updatedByEmpId' => $minute?->updated_by_emp_id ?? '',
            'updatedByName' => $minute?->updated_by_name ?? '',
            'actionItems' => $items->map(fn (EventActionItem $item): array => [
                'ActionItemId' => $item->action_item_id,
                'EventId' => $item->event_id,
                'Task' => $item->task,
                'PICEmpId' => $item->pic_emp_id ?? '',
                'PICName' => $item->pic_name ?? '',
                'DueDate' => $item->due_date?->format('Y-m-d') ?? '',
                'Status' => $item->status,
                'Timestamp' => $this->support->formatDateTime($item->timestamp),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function saveMinutes(array $payload): array
    {
        $event = $this->requireEvent(OhsDashboardPayload::string($payload, 'EventId'));
        $summary = OhsDashboardPayload::string($payload, 'Summary');
        $updatedByEmpId = OhsDashboardPayload::string($payload, 'UpdatedByEmpId');
        $updatedByName = '';
        if ($updatedByEmpId !== '') {
            $updatedByName = $this->support->requireEmployee($updatedByEmpId, 'Updated By')->emp_name;
        }

        $now = $this->support->now();
        $existing = EventMinute::query()->find($event->event_id);
        EventMinute::query()->updateOrCreate(
            ['event_id' => $event->event_id],
            [
                'timestamp' => $existing?->timestamp ?? $now,
                'summary' => $summary,
                'updated_at' => $now,
                'updated_by_emp_id' => $updatedByEmpId !== '' ? $updatedByEmpId : null,
                'updated_by_name' => $updatedByName !== '' ? $updatedByName : null,
            ],
        );

        return $this->minutes($event->event_id);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function addActionItem(array $payload): array
    {
        $event = $this->requireEvent(OhsDashboardPayload::string($payload, 'EventId'));
        $task = OhsDashboardPayload::string($payload, 'Task');
        if ($task === '') {
            throw new OhsDashboardException('Task wajib diisi.');
        }

        $picEmpId = OhsDashboardPayload::nullableString($payload, 'PICEmpId');
        $picName = null;
        if ($picEmpId) {
            $picName = $this->support->requireEmployee($picEmpId, 'PIC')->emp_name;
        }
        $due = OhsDashboardPayload::nullableString($payload, 'DueDate');

        EventActionItem::query()->create([
            'action_item_id' => OhsDashboardId::actionItem(),
            'timestamp' => $this->support->now(),
            'event_id' => $event->event_id,
            'task' => $task,
            'pic_emp_id' => $picEmpId,
            'pic_name' => $picName,
            'due_date' => $due,
            'status' => 'Open',
        ]);

        return $this->minutes($event->event_id);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateActionItemStatus(array $payload): array
    {
        $eventId = OhsDashboardPayload::string($payload, 'EventId');
        $actionItemId = OhsDashboardPayload::string($payload, 'ActionItemId');
        $status = OhsDashboardPayload::string($payload, 'Status');
        if (! in_array($status, ['Open', 'Done'], true)) {
            throw new OhsDashboardException('Status action item hanya Open atau Done.');
        }

        $item = EventActionItem::query()
            ->where('action_item_id', $actionItemId)
            ->where('event_id', $eventId)
            ->first();
        if (! $item instanceof EventActionItem) {
            throw new OhsDashboardException('Action item tidak ditemukan.');
        }

        $item->status = $status;
        $item->save();

        return $this->minutes($eventId);
    }

    public function requireEvent(string $eventId, string $message = 'Event tidak ditemukan.'): Event
    {
        $eventId = trim($eventId);
        if ($eventId === '') {
            throw new OhsDashboardException($message);
        }

        $event = Event::query()->find($eventId);
        if (! $event instanceof Event) {
            throw new OhsDashboardException($message);
        }

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    public function enrich(Event $event): array
    {
        $pic = $event->pic;

        return [
            'EventId' => $event->event_id,
            'Timestamp' => $this->support->formatDateTime($event->timestamp),
            'EventName' => $event->event_name,
            'Description' => $event->description,
            'Where' => $event->where,
            'ReadinessUpdate' => $event->readiness_update ?? '',
            'ReadinessUpdatedAt' => $this->support->formatDateTime($event->readiness_updated_at),
            'PICEmpId' => $event->pic_emp_id,
            'PICName' => $this->support->fillIfEmpty($event->pic_name, $pic?->emp_name),
            'PICTeam' => $this->support->fillIfEmpty($event->pic_team, $pic?->team),
            'PICPosition' => $this->support->fillIfEmpty($event->pic_position, $pic?->position),
            'PICSiteDedicated' => $this->support->fillIfEmpty($event->pic_site_dedicated, $pic?->site_dedicated),
            'EventDate' => $event->event_date?->format('Y-m-d'),
        ];
    }

    /**
     * @param  array<string, CarbonInterface>  $windows
     * @return array{0: string, 1: int}
     */
    private function scheduleStatus(CarbonInterface $eventDate, CarbonInterface $today, array $windows): array
    {
        $iso = $this->support->formatISO($eventDate);
        if ($iso < $this->support->formatISO($today)) {
            return ['Previous Event', 5];
        }
        if ($this->support->isISODateInRange($iso, $windows['thisWeekStart'], $windows['thisWeekEnd'])) {
            return ['This Week', 1];
        }
        if ($this->support->isISODateInRange($iso, $windows['nextWeekStart'], $windows['nextWeekEnd'])) {
            return ['Next Week', 2];
        }
        if ($this->support->isISODateInRange($iso, $windows['nextTwoWeekStart'], $windows['nextTwoWeekEnd'])) {
            return ['Next 2 Week', 3];
        }

        return ['More Than 2 Weeks Ahead', 4];
    }
}
