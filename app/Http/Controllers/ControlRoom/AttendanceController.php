<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\ControlRoom\AttendanceCheckInRequest;
use App\Http\Requests\ControlRoom\AttendanceFormRequest;
use App\Http\Requests\ControlRoom\AttendanceUpdateRequest;
use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\AttendanceFormRecorder;
use App\Services\ControlRoom\Reference\PersonnelReader;
use App\Services\ControlRoom\Reference\ShiftResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

final class AttendanceController extends Controller
{
    public function __construct(
        private readonly ShiftResolver $shiftResolver,
        private readonly PersonnelReader $personnelReader,
        private readonly AttendanceFormRecorder $attendanceFormRecorder,
    ) {}

    public function showForm(): View
    {
        return view('control-room.attendance.form', [
            'defaultTanggal' => now()->toDateString(),
            'lookupUrl' => route('control-room.attendance.personnel'),
        ]);
    }

    public function lookupPersonnel(Request $request): JsonResponse
    {
        $sid = strtoupper(trim((string) $request->query('sid', '')));
        if (strlen($sid) < 2) {
            return response()->json(['found' => false]);
        }

        $personnel = $this->personnelReader->find($sid);
        if ($personnel === null) {
            return response()->json(['found' => false]);
        }

        $name = trim((string) $personnel->emp_name);
        if ($name !== '') {
            $name = mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        }

        return response()->json([
            'found' => true,
            'sid' => (string) $personnel->sid,
            'name' => $name,
            'site' => (string) $personnel->site_dedicated,
        ]);
    }

    public function storeForm(AttendanceFormRequest $request): RedirectResponse
    {
        $bukti = $request->file('bukti');
        if (! $bukti instanceof UploadedFile) {
            return back()->withErrors(['bukti' => 'Unggah bukti kehadiran (foto atau PDF).'])->withInput();
        }

        $attendance = $this->attendanceFormRecorder->record(
            $request->safe()->only(['sid', 'tanggal']),
            $bukti,
        );

        return redirect()
            ->route('control-room.attendance.form')
            ->with('success', "Absensi tercatat untuk {$attendance->personnel_name_snapshot} ({$attendance->personnel_source_key}).");
    }

    public function showCheckIn(Request $request): View
    {
        $site = ControlRoomSiteCode::from($request->string('site', ControlRoomSiteCode::HeadOffice->value)->toString());

        return view('control-room.attendance.check-in', [
            'site' => $site,
            'sites' => ControlRoomSiteCode::cases(),
            'personnel' => $this->personnelReader->all(),
            'defaultPersonnelSourceKey' => $request->user()->personnel_source_key,
        ]);
    }

    public function index(Request $request): View
    {
        $site = ControlRoomSiteCode::from($request->string('site', ControlRoomSiteCode::HeadOffice->value)->toString());
        $year = (int) $request->integer('year', (int) now()->isoFormat('GGGG'));
        $week = (int) $request->integer('week', (int) now()->isoWeek());

        $weekStart = now()->setISODate($year, $week, 1)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

        $attendances = Attendance::query()
            ->where('site_code', $site->value)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->orderBy('date')
            ->orderBy('shift_code')
            ->paginate(100);

        return view('control-room.attendance.index', [
            'site' => $site,
            'year' => $year,
            'week' => $week,
            'sites' => ControlRoomSiteCode::cases(),
            'attendances' => $attendances,
        ]);
    }

    public function show(Attendance $attendance): View
    {
        $attendance->loadMissing('schedulePlan');

        $replacedPersonnel = null;
        if ($attendance->replacing_source_key !== null) {
            $replacedPersonnel = SchedulePlan::query()
                ->where('personnel_source_key', $attendance->replacing_source_key)
                ->where('date', $attendance->date)
                ->where('shift_code', $attendance->shift_code->value)
                ->first();
        }

        return view('control-room.attendance.show', [
            'attendance' => $attendance,
            'replacedPersonnel' => $replacedPersonnel,
        ]);
    }

    public function checkIn(AttendanceCheckInRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $personnelSourceKey = $data['personnel_source_key'];

        $personnel = $this->personnelReader->find($personnelSourceKey);
        if ($personnel === null) {
            return back()->withErrors(['personnel_source_key' => 'Personil tidak ditemukan/tidak aktif di sumber data personil.'])->withInput();
        }

        $now = now();

        // "hanya dalam rentang shift ±2 jam" — cek jam sekarang terhadap
        // jendela shift yang terdeteksi dari waktu saat ini.
        $shift = $this->shiftResolver->resolve($now);
        $effectiveDate = $this->shiftResolver->effectiveDate($now);

        if (! $this->shiftResolver->isWithinShiftWindow($now, $shift)) {
            return back()->withErrors(['checked_in_at' => 'Absen hanya bisa dilakukan dalam rentang ±2 jam dari jam shift.'])->withInput();
        }

        $schedulePlan = SchedulePlan::query()
            ->where('site_code', $data['site_code'])
            ->where('date', $effectiveDate->toDateString())
            ->where('shift_code', $shift->value)
            ->where('personnel_source_key', $personnelSourceKey)
            ->first();

        Attendance::query()->updateOrCreate(
            [
                'site_code' => $data['site_code'],
                'date' => $effectiveDate->toDateString(),
                'shift_code' => $shift->value,
                'personnel_source_key' => $personnelSourceKey,
            ],
            [
                'schedule_plan_id' => $schedulePlan?->id,
                'personnel_name_snapshot' => $personnel->emp_name,
                'status' => $data['status'],
                'replacing_source_key' => $data['replacing_source_key'] ?? null,
                'absence_reason' => $data['absence_reason'] ?? null,
                'checked_in_at' => $now,
            ]
        );

        return redirect()
            ->route('control-room.attendance.index', ['site' => $data['site_code']])
            ->with('success', "Absen tercatat untuk {$personnel->emp_name}.");
    }

    public function update(AttendanceUpdateRequest $request, Attendance $attendance): RedirectResponse
    {
        $data = $request->validated();
        $correctionReason = $data['correction_reason'];
        unset($data['correction_reason']);

        $data['corrected_by'] = $request->user()->id;
        $data['correction_reason'] = $correctionReason;

        $attendance->update($data);

        return back()->with('success', 'Absen dikoreksi.');
    }
}
