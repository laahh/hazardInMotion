<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Manpower;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Manpower\Attendance;
use App\Models\EmergencyResponse\Manpower\Employee;
use App\Models\EmergencyResponse\MasterData\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->filled('date') ? Carbon::parse($request->query('date')) : today();

        $employees = Employee::query()->where('is_active', true)->with(['attendance' => fn ($query) => $query->whereDate('date', $date)])->orderBy('full_name')->get();

        return view('EmergencyResponse.manpower.attendance.index', [
            'date' => $date,
            'employees' => $employees,
            'shifts' => Shift::query()->where('is_active', true)->orderBy('start_time')->get(),
        ]);
    }

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'shift_id' => ['nullable', Rule::exists('er_shifts', 'id')],
            'status' => ['required', Rule::in(array_keys(Attendance::STATUSES))],
            'notes' => ['nullable', 'string'],
        ]);
        $data['created_by'] = $request->user()->id;

        Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $data['date']],
            $data,
        );

        return back()->with('success', 'Kehadiran berhasil dicatat.');
    }

    public function checkIn(Employee $employee): RedirectResponse
    {
        Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => today()->toDateString()],
            ['status' => 'hadir', 'check_in_at' => now()],
        );

        return back()->with('success', "{$employee->full_name} berhasil check-in.");
    }

    public function checkOut(Employee $employee): RedirectResponse
    {
        $attendance = Attendance::where('employee_id', $employee->id)->where('date', today()->toDateString())->first();
        if ($attendance) {
            $attendance->update(['check_out_at' => now()]);
        }

        return back()->with('success', "{$employee->full_name} berhasil check-out.");
    }
}
