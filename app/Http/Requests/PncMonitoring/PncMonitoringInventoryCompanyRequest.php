<?php

declare(strict_types=1);

namespace App\Http\Requests\PncMonitoring;

use Illuminate\Foundation\Http\FormRequest;

final class PncMonitoringInventoryCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['nullable', 'string', 'max:30'],
        ];
    }
}
