<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('location');

        return [
            'site_id' => ['required', 'uuid', Rule::exists('er_sites', 'id')],
            'code' => ['required', 'string', 'max:50', Rule::unique('er_locations', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
