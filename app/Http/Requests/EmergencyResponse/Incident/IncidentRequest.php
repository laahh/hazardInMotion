<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse\Incident;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occurred_at' => ['required', 'date'],
            'incident_type_id' => ['nullable', Rule::exists('er_incident_types', 'id')],
            'severity_level_id' => ['nullable', Rule::exists('er_severity_levels', 'id')],
            'priority_level_id' => ['nullable', Rule::exists('er_priority_levels', 'id')],
            'site_id' => ['nullable', Rule::exists('er_sites', 'id')],
            'location_detail' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['required', 'string'],
            'victim_count' => ['nullable', 'integer', 'min:0'],
            'potential_hazards' => ['nullable', 'string'],
            'assistance_needed' => ['nullable', 'string'],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'reporter_phone' => ['nullable', 'string', 'max:50'],
            'reporter_department' => ['nullable', 'string', 'max:255'],
        ];
    }
}
