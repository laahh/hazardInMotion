<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse\Maintenance;

use App\Models\EmergencyResponse\Maintenance\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment_type' => ['nullable', 'in:equipment,safety_device'],
            'equipment_id' => ['nullable', 'required_with:equipment_type', 'uuid'],
            'work_type' => ['required', Rule::in(array_keys(WorkOrder::WORK_TYPES))],
            'description' => ['required', 'string'],
            'priority_level_id' => ['nullable', Rule::exists('er_priority_levels', 'id')],
            'vendor_id' => ['nullable', Rule::exists('er_vendors', 'id')],
            'target_start_at' => ['nullable', 'date'],
            'target_end_at' => ['nullable', 'date', 'after_or_equal:target_start_at'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'source' => ['nullable', Rule::in(array_keys(WorkOrder::SOURCES))],
            'source_inspection_finding_id' => ['nullable', 'uuid'],
            'source_incident_id' => ['nullable', 'uuid'],
        ];
    }
}
