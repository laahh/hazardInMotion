<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ControlRoomScheduleExcelImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_code' => ['required', Rule::in(array_column(ControlRoomSiteCode::cases(), 'value'))],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'week_number' => ['required', 'integer', 'min:1', 'max:53'],
            'file' => ['required', 'file', 'max:2048', 'mimes:xlsx,xls'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file Excel jadwal.',
            'file.mimes' => 'File harus .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 2 MB.',
        ];
    }
}
