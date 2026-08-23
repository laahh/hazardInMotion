<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Manpower;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Manpower\Attendance;
use App\Models\EmergencyResponse\Manpower\Employee;
use App\Models\EmergencyResponse\Manpower\EmployeeCertification;
use App\Models\EmergencyResponse\Manpower\EmployeeTraining;
use Illuminate\View\View;

class ManpowerController extends Controller
{
    public function index(): View
    {
        $onDutyToday = Attendance::query()->whereDate('date', today())->where('status', 'hadir')->with('employee')->get();

        $expiringTrainings = EmployeeTraining::query()
            ->with(['employee', 'training'])
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [today(), today()->addDays(30)])
            ->orderBy('expires_at')
            ->limit(10)
            ->get();

        $expiringCertifications = EmployeeCertification::query()
            ->with(['employee', 'certification'])
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [today(), today()->addDays(30)])
            ->orderBy('expires_at')
            ->limit(10)
            ->get();

        return view('EmergencyResponse.manpower.index', [
            'totalEmployees' => Employee::query()->where('is_active', true)->count(),
            'onDutyToday' => $onDutyToday,
            'expiringTrainings' => $expiringTrainings,
            'expiringCertifications' => $expiringCertifications,
        ]);
    }
}
