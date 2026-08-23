<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse\Manpower;

use App\Models\EmergencyResponse\Manpower\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('employee');

        return [
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('er_employees', 'employee_number')->ignore($ignoreId)],
            'user_id' => ['nullable', 'exists:users,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', Rule::exists('er_departments', 'id')],
            'emergency_unit_id' => ['nullable', Rule::exists('er_emergency_units', 'id')],
            'site_id' => ['nullable', Rule::exists('er_sites', 'id')],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'employment_status' => ['required', Rule::in(array_keys(Employee::EMPLOYMENT_STATUSES))],
            'skills' => ['nullable', 'string'],
            'emergency_role' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
