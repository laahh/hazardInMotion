<?php

declare(strict_types=1);

namespace App\Http\Requests\MonitoringSafetyEngineering;

use Illuminate\Foundation\Http\FormRequest;

class MonitoringSafetyEngineeringImportRequest extends FormRequest
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
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'period_year' => 'tahun periode',
            'excel_file' => 'file Excel',
        ];
    }
}
