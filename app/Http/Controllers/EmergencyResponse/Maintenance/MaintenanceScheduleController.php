<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\Maintenance\MaintenanceSchedule;
use App\Models\EmergencyResponse\MasterData\MaintenanceType;
use App\Models\EmergencyResponse\SafetyDevice\SafetyDevice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $schedules = MaintenanceSchedule::query()
            ->with(['target', 'maintenanceType', 'assignedTechnician'])
            ->orderBy('next_due_date')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.maintenance.schedule.index', [
            'schedules' => $schedules,
            'maintenanceTypes' => MaintenanceType::query()->where('is_active', true)->orderBy('name')->get(),
            'equipmentList' => EmergencyEquipment::query()->orderBy('name')->get(),
            'safetyDeviceList' => SafetyDevice::query()->orderBy('name')->get(),
            'technicians' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        MaintenanceSchedule::create($data);

        return redirect()->route('emergency-response.maintenance.schedules.index')->with('success', 'Jadwal maintenance berhasil ditambahkan.');
    }

    public function update(Request $request, MaintenanceSchedule $schedule): RedirectResponse
    {
        $data = $this->validated($request);
        $data['updated_by'] = $request->user()->id;

        $schedule->update($data);

        return redirect()->route('emergency-response.maintenance.schedules.index')->with('success', 'Jadwal maintenance berhasil diperbarui.');
    }

    public function destroy(MaintenanceSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return redirect()->route('emergency-response.maintenance.schedules.index')->with('success', 'Jadwal maintenance berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'target_type' => ['required', 'in:equipment,safety_device'],
            'target_id' => ['required', 'uuid'],
            'maintenance_type_id' => ['required', Rule::exists('er_maintenance_types', 'id')],
            'frequency_days' => ['required', 'integer', 'min:1'],
            'next_due_date' => ['required', 'date'],
            'assigned_technician_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['target_type'] = $data['target_type'] === 'equipment' ? EmergencyEquipment::class : SafetyDevice::class;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
