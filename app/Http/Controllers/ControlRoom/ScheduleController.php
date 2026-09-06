<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\ControlRoom\ScheduleBulkRequest;
use App\Http\Requests\ControlRoom\ScheduleCopyRequest;
use App\Http\Requests\ControlRoom\ScheduleUpdateRequest;
use App\Models\ControlRoom\ScheduleChange;
use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\Reference\PersonnelReader;
use App\Services\ControlRoom\ScheduleBulkAssignService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ScheduleController extends Controller
{
    private const SHIFT_COLORS = ['S1' => '#0d6efd', 'S2' => '#fd7e14'];

    public function __construct(
        private readonly PersonnelReader $personnelReader,
    ) {}

    public function index(Request $request): View
    {
        $site = ControlRoomSiteCode::from($request->string('site', ControlRoomSiteCode::HeadOffice->value)->toString());

        return view('control-room.schedule.index', [
            'site' => $site,
            'sites' => ControlRoomSiteCode::cases(),
            'personnel' => $this->personnelReader->all(),
        ]);
    }

    /**
     * Feed JSON untuk FullCalendar (dipanggil otomatis oleh library setiap
     * rentang tanggal yang ditampilkan berubah — prev/next/today).
     */
    public function events(Request $request): JsonResponse
    {
        $site = ControlRoomSiteCode::from($request->string('site', ControlRoomSiteCode::HeadOffice->value)->toString());
        $start = CarbonImmutable::parse($request->string('start')->toString());
        $end = CarbonImmutable::parse($request->string('end')->toString());

        $events = SchedulePlan::query()
            ->where('site_code', $site->value)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('shift_code')
            ->get()
            ->map(function (SchedulePlan $plan): array {
                $color = self::SHIFT_COLORS[$plan->shift_code->value] ?? '#6c757d';

                return [
                    'id' => $plan->id,
                    'title' => "{$plan->shift_code->value} • {$plan->personnel_name_snapshot}",
                    'start' => $plan->date->toDateString(),
                    'allDay' => true,
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'scheduleId' => $plan->id,
                        'locked' => $plan->isLocked(),
                        'personnel' => $plan->personnel_name_snapshot,
                        'personnelSourceKey' => $plan->personnel_source_key,
                        'shift' => $plan->shift_code->value,
                        'updateUrl' => route('control-room.schedule.update', $plan),
                        'deleteUrl' => route('control-room.schedule.destroy', $plan),
                    ],
                ];
            });

        return response()->json($events);
    }

    public function storeBulk(ScheduleBulkRequest $request, ScheduleBulkAssignService $service): RedirectResponse
    {
        $result = $service->assign($request->validated(), (int) $request->user()->id);

        $redirectParams = ['site' => $request->string('site_code')];

        if ($result->hasErrors()) {
            return redirect()
                ->route('control-room.schedule.index', $redirectParams)
                ->withErrors(['assignments' => $result->errors])
                ->withInput();
        }

        return redirect()
            ->route('control-room.schedule.index', $redirectParams)
            ->with('success', "Jadwal tersimpan: {$result->created} baru, {$result->updated} diperbarui.")
            ->with('warnings', $result->warnings);
    }

    public function copy(ScheduleCopyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $sourcePlans = SchedulePlan::query()
            ->where('site_code', $data['site_code'])
            ->where('year', $data['from_year'])
            ->where('week_number', $data['from_week_number'])
            ->get();

        $weekOffset = CarbonImmutable::now()->setISODate($data['to_year'], $data['to_week_number'], 1)
            ->diffInWeeks(CarbonImmutable::now()->setISODate($data['from_year'], $data['from_week_number'], 1), false);

        foreach ($sourcePlans as $sourcePlan) {
            $newDate = CarbonImmutable::parse($sourcePlan->date)->subWeeks((int) $weekOffset);

            SchedulePlan::updateOrCreate(
                [
                    'site_code' => $sourcePlan->site_code->value,
                    'date' => $newDate->toDateString(),
                    'shift_code' => $sourcePlan->shift_code->value,
                    'personnel_source_key' => $sourcePlan->personnel_source_key,
                ],
                [
                    'year' => $data['to_year'],
                    'week_number' => $data['to_week_number'],
                    'personnel_name_snapshot' => $sourcePlan->personnel_name_snapshot,
                    'status' => SchedulePlan::STATUS_DRAFT,
                    'created_by' => $request->user()->id,
                ]
            );
        }

        return redirect()
            ->route('control-room.schedule.index', ['site' => $data['site_code']])
            ->with('success', "Minggu {$data['from_week_number']} berhasil disalin ke minggu {$data['to_week_number']} ({$sourcePlans->count()} baris) — silakan diedit.");
    }

    public function update(ScheduleUpdateRequest $request, SchedulePlan $schedule): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        if (isset($data['personnel_source_key']) && $data['personnel_source_key'] !== $schedule->personnel_source_key) {
            $personnel = $this->personnelReader->find($data['personnel_source_key']);
            $data['personnel_name_snapshot'] = $personnel?->emp_name ?? $data['personnel_source_key'];
        }

        $schedule->changeReason = $reason;
        $schedule->update($data);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jadwal diperbarui.']);
        }

        return back()->with('success', 'Jadwal diperbarui.');
    }

    public function destroy(Request $request, SchedulePlan $schedule): RedirectResponse|JsonResponse
    {
        if ($schedule->isLocked() || $schedule->date->isPast()) {
            $message = 'Hanya jadwal berstatus draft di minggu yang belum berjalan yang bisa dihapus.';

            return $request->wantsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['schedule' => $message]);
        }

        $schedule->delete();

        return $request->wantsJson()
            ? response()->json(['message' => 'Jadwal dihapus.'])
            : back()->with('success', 'Jadwal dihapus.');
    }

    public function lock(Request $request, int $week): RedirectResponse
    {
        $data = $request->validate([
            'site_code' => ['required', 'string'],
            'year' => ['required', 'integer'],
        ]);

        SchedulePlan::query()
            ->where('site_code', $data['site_code'])
            ->where('year', $data['year'])
            ->where('week_number', $week)
            ->where('status', SchedulePlan::STATUS_DRAFT)
            ->update(['status' => SchedulePlan::STATUS_LOCKED, 'locked_at' => now()]);

        return back()->with('success', "Minggu {$week} dikunci sebagai baseline.");
    }

    public function changes(Request $request): View
    {
        $changes = ScheduleChange::query()
            ->with(['schedulePlan', 'changedBy'])
            ->when($request->filled('schedule_plan_id'), fn ($q) => $q->where('schedule_plan_id', $request->integer('schedule_plan_id')))
            ->orderByDesc('changed_at')
            ->paginate(50);

        return view('control-room.schedule.changes', ['changes' => $changes]);
    }
}
