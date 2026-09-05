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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ScheduleController extends Controller
{
    public function __construct(
        private readonly PersonnelReader $personnelReader,
    ) {}

    public function index(Request $request): View
    {
        $site = ControlRoomSiteCode::from($request->string('site', ControlRoomSiteCode::HeadOffice->value)->toString());
        $year = (int) $request->integer('year', (int) now()->isoFormat('GGGG'));
        $week = (int) $request->integer('week', (int) now()->isoWeek());

        $plans = SchedulePlan::query()
            ->where('site_code', $site->value)
            ->where('year', $year)
            ->where('week_number', $week)
            ->orderBy('date')
            ->orderBy('shift_code')
            ->get()
            ->groupBy(fn (SchedulePlan $plan): string => $plan->date->toDateString().'|'.$plan->shift_code->value);

        $personnel = $this->personnelReader->all($site);

        return view('control-room.schedule.index', [
            'site' => $site,
            'year' => $year,
            'week' => $week,
            'sites' => ControlRoomSiteCode::cases(),
            'plans' => $plans,
            'personnel' => $personnel,
        ]);
    }

    public function storeBulk(ScheduleBulkRequest $request, ScheduleBulkAssignService $service): RedirectResponse
    {
        $result = $service->assign($request->validated(), (int) $request->user()->id);

        if ($result->hasErrors()) {
            return back()->withErrors(['assignments' => $result->errors])->withInput();
        }

        return redirect()
            ->route('control-room.schedule.index', [
                'site' => $request->string('site_code'),
                'year' => $request->integer('year'),
                'week' => $request->integer('week_number'),
            ])
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
            ->route('control-room.schedule.index', [
                'site' => $data['site_code'],
                'year' => $data['to_year'],
                'week' => $data['to_week_number'],
            ])
            ->with('success', "Minggu {$data['from_week_number']} berhasil disalin ke minggu {$data['to_week_number']} ({$sourcePlans->count()} baris) — silakan diedit.");
    }

    public function update(ScheduleUpdateRequest $request, SchedulePlan $schedule): RedirectResponse
    {
        $data = $request->validated();
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        $schedule->changeReason = $reason;
        $schedule->update($data);

        return back()->with('success', 'Jadwal diperbarui.');
    }

    public function destroy(SchedulePlan $schedule): RedirectResponse
    {
        if ($schedule->isLocked() || $schedule->date->isPast()) {
            return back()->withErrors(['schedule' => 'Hanya jadwal berstatus draft di minggu yang belum berjalan yang bisa dihapus.']);
        }

        $schedule->delete();

        return back()->with('success', 'Jadwal dihapus.');
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
