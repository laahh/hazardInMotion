<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use Illuminate\Foundation\Http\FormRequest;

final class ControlRoomTbcExcelImportRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file Excel Validasi TBC.',
            'file.mimes' => 'File harus .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ];
    }
}
