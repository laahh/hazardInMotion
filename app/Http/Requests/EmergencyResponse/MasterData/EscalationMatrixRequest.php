<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EscalationMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', Rule::in(['incident', 'work_order'])],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
            'delay_minutes' => ['required', 'integer', 'min:1'],
            'notify_role_id' => ['nullable', Rule::exists('roles', 'id')],
            'channel' => ['required', Rule::in(['in_app', 'email', 'both'])],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
