<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse\Equipment;

use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('equipment');

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('er_emergency_equipment', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'equipment_category_id' => ['nullable', Rule::exists('er_equipment_categories', 'id')],
            'type_model' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'site_id' => ['nullable', Rule::exists('er_sites', 'id')],
            'location_id' => ['nullable', Rule::exists('er_locations', 'id')],
            'area_id' => ['nullable', Rule::exists('er_areas', 'id')],
            'position_detail' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'department_id' => ['nullable', Rule::exists('er_departments', 'id')],
            'emergency_unit_id' => ['nullable', Rule::exists('er_emergency_units', 'id')],
            'purchased_at' => ['nullable', 'date'],
            'commissioned_at' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(array_keys(EmergencyEquipment::CONDITIONS))],
            'operational_status' => ['required', Rule::in(array_keys(EmergencyEquipment::OPERATIONAL_STATUSES))],
            'last_inspection_at' => ['nullable', 'date'],
            'next_inspection_at' => ['nullable', 'date'],
            'last_calibration_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'certificate_expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
