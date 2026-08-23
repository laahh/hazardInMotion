<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChecklistTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('checklist_template');

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('er_checklist_templates', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', Rule::in(['emergency_equipment', 'safety_device'])],
            'equipment_category_id' => ['nullable', 'required_if:applies_to,emergency_equipment', Rule::exists('er_equipment_categories', 'id')],
            'safety_device_type_id' => ['nullable', 'required_if:applies_to,safety_device', Rule::exists('er_safety_device_types', 'id')],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_text' => ['required', 'string', 'max:500'],
            'items.*.answer_type' => ['required', Rule::in(['compliance', 'measurement', 'text'])],
            'items.*.is_required' => ['nullable', 'boolean'],
        ];
    }
}
