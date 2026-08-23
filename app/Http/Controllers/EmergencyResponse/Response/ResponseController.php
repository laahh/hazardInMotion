<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Response;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\Incident\Incident;
use App\Models\EmergencyResponse\Incident\IncidentEquipmentUsage;
use App\Models\EmergencyResponse\Incident\ResponsePersonnel;
use App\Models\EmergencyResponse\Incident\ResponseUnit;
use App\Models\EmergencyResponse\MasterData\EmergencyUnit;
use App\Models\EmergencyResponse\SafetyDevice\SafetyDevice;
use App\Models\User;
use App\Services\EmergencyResponse\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResponseController extends Controller
{
    public function index(): View
    {
        $activeIncidents = Incident::query()
            ->with(['incidentType', 'site', 'responseUnits.emergencyUnit', 'responseUnits.personnel'])
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('reported_at')
            ->get();

        return view('EmergencyResponse.response.index', ['activeIncidents' => $activeIncidents]);
    }

    public function dispatchUnit(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate(['emergency_unit_id' => ['required', Rule::exists('er_emergency_units', 'id')]]);

        $incident->responseUnits()->create([
            ...$data,
            'status' => 'dispatched',
            'departed_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        if ($incident->status === 'open') {
            $incident->update(['status' => 'in_progress', 'confirmed_at' => $incident->confirmed_at ?? now()]);
            $incident->recordStatusChange('in_progress', 'Unit dikerahkan.', $request->user()->id);
        }
        if (! $incident->dispatched_at) {
            $incident->update(['dispatched_at' => now()]);
        }

        $unit = EmergencyUnit::find($data['emergency_unit_id']);
        $incident->addTimelineEntry('dispatch', "Unit \"{$unit->name}\" dikerahkan.", $request->user()->id);

        return back()->with('success', 'Unit berhasil dikerahkan.');
    }

    public function updateUnitStatus(Request $request, Incident $incident, ResponseUnit $unit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ResponseUnit::STATUSES))]]);

        $timestampField = match ($data['status']) {
            'arrived' => 'arrived_at',
            'returned' => 'returned_at',
            default => null,
        };

        $unit->update([
            'status' => $data['status'],
            ...($timestampField ? [$timestampField => now()] : []),
            'updated_by' => $request->user()->id,
        ]);

        if ($data['status'] === 'arrived' && ! $incident->arrived_at) {
            $incident->update(['arrived_at' => now()]);
        }

        $incident->addTimelineEntry('dispatch', "Unit \"{$unit->emergencyUnit->name}\" status: {$unit->statusLabel()}.", $request->user()->id);

        return back()->with('success', 'Status unit diperbarui.');
    }

    public function storePersonnel(Request $request, Incident $incident, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'response_unit_id' => ['nullable', Rule::exists('er_response_units', 'id')],
            'user_id' => ['required', 'exists:users,id'],
            'role_in_response' => ['nullable', 'string', 'max:255'],
        ]);

        ResponsePersonnel::create([...$data, 'incident_id' => $incident->id]);

        $user = User::find($data['user_id']);
        $incident->addTimelineEntry('dispatch', "{$user->name} ditugaskan sebagai personel respons.", $request->user()->id);

        $notifications->notifyUser(
            $user,
            'assignment',
            'Anda Ditugaskan sebagai Personel Respons',
            "Anda ditugaskan sebagai personel respons untuk insiden {$incident->incident_number}".($data['role_in_response'] ?? '' ? " ({$data['role_in_response']})" : '').'.',
            route('emergency-response.incident.show', $incident),
        );

        return back()->with('success', 'Personel berhasil ditugaskan.');
    }

    public function destroyPersonnel(Incident $incident, ResponsePersonnel $personnel): RedirectResponse
    {
        $personnel->delete();

        return back()->with('success', 'Personel dihapus dari daftar respons.');
    }

    public function storeEquipmentUsage(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'equipment_type' => ['required', 'in:equipment,safety_device'],
            'equipment_id' => ['required', 'uuid'],
            'response_unit_id' => ['nullable', Rule::exists('er_response_units', 'id')],
            'quantity_used' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $equipmentClass = $data['equipment_type'] === 'equipment' ? EmergencyEquipment::class : SafetyDevice::class;
        $equipment = $equipmentClass::findOrFail($data['equipment_id']);

        IncidentEquipmentUsage::create([
            'incident_id' => $incident->id,
            'response_unit_id' => $data['response_unit_id'] ?? null,
            'equipmentable_type' => $equipmentClass,
            'equipmentable_id' => $equipment->id,
            'quantity_used' => $data['quantity_used'] ?? 1,
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $incident->addTimelineEntry('dispatch', "Equipment \"{$equipment->name}\" ({$equipment->code}) digunakan dalam respons.", $request->user()->id);

        return back()->with('success', 'Penggunaan equipment dicatat.');
    }

    public function destroyEquipmentUsage(Incident $incident, IncidentEquipmentUsage $usage): RedirectResponse
    {
        $usage->delete();

        return back()->with('success', 'Catatan penggunaan equipment dihapus.');
    }
}
