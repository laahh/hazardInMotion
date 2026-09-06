<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\ControlRoom\ScheduleBulkRequest;
use App\Http\Requests\ControlRoom\ScheduleCopyRequest;
use App\Http\Requests\ControlRoom\ScheduleDestroyWeekRequest;
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
    private const SHIFT_COLORS = [
        'S1' => ['bg' => '#DBEAFE', 'border' => '#93C5FD', 'text' => '#1E3A8A'],
        'S2' => ['bg' => '#FFEDD5', 'border' => '#FDBA74', 'text' => '#9A3412'],
    ];

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
                $colors = self::SHIFT_COLORS[$plan->shift_code->value] ?? ['bg' => '#E5E7EB', 'border' => '#9CA3AF', 'text' => '#111827'];

                return [
                    'id' => $plan->id,
                    'title' => "{$plan->shift_code->value} • {$plan->personnel_name_snapshot}",
                    'start' => $plan->date->toDateString(),
                    'allDay' => true,
                    'backgroundColor' => $colors['bg'],
                    'borderColor' => $colors['border'],
                    'textColor' => $colors['text'],
                    'classNames' => ['ocr-sched-event', 'ocr-sched-event--'.$plan->shift_code->value],
                    'extendedProps' => [
                        'scheduleId' => $plan->id,
                        'locked' => $plan->isLocked(),
                        'personnel' => $plan->personnel_name_snapshot,
                        'personnelSourceKey' => $plan->personnel_source_key,
                        'shift' => $plan->shift_code->value,
                        'accent' => $colors['text'],
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

    public function destroyWeek(ScheduleDestroyWeekRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $query = SchedulePlan::query()
            ->where('site_code', $data['site_code'])
            ->where('year', $data['year'])
            ->where('week_number', $data['week_number']);

        $count = (int) $query->count();

        if ($count === 0) {
            return redirect()
                ->route('control-room.schedule.index', ['site' => $data['site_code']])
                ->withErrors(['week' => "Tidak ada jadwal di minggu {$data['week_number']} tahun {$data['year']} untuk site ini."]);
        }

        $query->each(function (SchedulePlan $plan): void {
            $plan->delete();
        });

        return redirect()
            ->route('control-room.schedule.index', ['site' => $data['site_code']])
            ->with('success', "Minggu {$data['week_number']} tahun {$data['year']} dihapus ({$count} baris). Absen terkait tidak dihapus.");
    }

    public function update(ScheduleUpdateRequest $request, int $schedule): RedirectResponse|JsonResponse
    {
        $plan = SchedulePlan::query()->find($schedule);

        if (! $plan instanceof SchedulePlan) {
            return $this->missingScheduleResponse($request);
        }

        $data = $request->validated();
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        if (isset($data['personnel_source_key']) && $data['personnel_source_key'] !== $plan->personnel_source_key) {
            $personnel = $this->personnelReader->find($data['personnel_source_key']);
            $data['personnel_name_snapshot'] = $personnel?->emp_name ?? $data['personnel_source_key'];
        }

        $plan->changeReason = $reason;
        $plan->update($data);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jadwal diperbarui.']);
        }

        return back()->with('success', 'Jadwal diperbarui.');
    }

    public function destroy(Request $request, int $schedule): RedirectResponse|JsonResponse
    {
        $plan = SchedulePlan::query()->find($schedule);

        if (! $plan instanceof SchedulePlan) {
            return $this->missingScheduleResponse($request);
        }

        if ($plan->isLocked()) {
            $message = 'Jadwal terkunci tidak bisa dihapus per slot. Gunakan Hapus Minggu di alat bawah.';

            return $request->wantsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['schedule' => $message]);
        }

        $plan->delete();

        return $request->wantsJson()
            ? response()->json(['message' => 'Jadwal dihapus.'])
            : back()->with('success', 'Jadwal dihapus.');
    }

    public function lock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_code' => ['required', 'string'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'week_number' => ['required', 'integer', 'min:1', 'max:53'],
        ]);

        $updated = SchedulePlan::query()
            ->where('site_code', $data['site_code'])
            ->where('year', $data['year'])
            ->where('week_number', $data['week_number'])
            ->where('status', SchedulePlan::STATUS_DRAFT)
            ->update(['status' => SchedulePlan::STATUS_LOCKED, 'locked_at' => now()]);

        return back()->with('success', "Minggu {$data['week_number']} dikunci sebagai baseline ({$updated} baris).");
    }

    private function missingScheduleResponse(Request $request): RedirectResponse|JsonResponse
    {
        $message = 'Jadwal tidak ditemukan. Mungkin sudah dihapus — kalender akan diperbarui.';

        return $request->wantsJson()
            ? response()->json(['message' => $message], 404)
            : redirect()
                ->route('control-room.schedule.index', array_filter([
                    'site' => $request->input('site_code') ?: $request->input('site'),
                ]))
                ->withErrors(['schedule' => $message]);
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
