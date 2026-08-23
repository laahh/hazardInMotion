<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse\SafetyDevice;

use App\Models\EmergencyResponse\SafetyDevice\SafetyDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SafetyDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('safety_device');

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('er_safety_devices', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'safety_device_type_id' => ['nullable', Rule::exists('er_safety_device_types', 'id')],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'site_id' => ['nullable', Rule::exists('er_sites', 'id')],
            'location_id' => ['nullable', Rule::exists('er_locations', 'id')],
            'area_id' => ['nullable', Rule::exists('er_areas', 'id')],
            'position_detail' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'department_id' => ['nullable', Rule::exists('er_departments', 'id')],
            'installed_at' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(array_keys(SafetyDevice::CONDITIONS))],
            'operational_status' => ['required', Rule::in(array_keys(SafetyDevice::OPERATIONAL_STATUSES))],
            'last_inspection_at' => ['nullable', 'date'],
            'next_inspection_at' => ['nullable', 'date'],
            'last_calibration_at' => ['nullable', 'date'],
            'next_calibration_at' => ['nullable', 'date'],
            'certificate_expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
