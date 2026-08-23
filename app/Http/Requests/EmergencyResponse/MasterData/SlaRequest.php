<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SlaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('sla');

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('er_slas', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', Rule::in(['incident', 'work_order', 'inspection_followup'])],
            'response_time_minutes' => ['required', 'integer', 'min:1'],
            'resolution_time_minutes' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
