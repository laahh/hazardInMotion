<?php

declare(strict_types=1);

namespace App\Http\Requests\Hsecm;

use Illuminate\Foundation\Http\FormRequest;

class HsecmTasklistSubmitRequest extends FormRequest
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
            'submitted_by_name' => ['required', 'string', 'max:150'],
            'remediation_notes' => ['required', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer', 'min:1'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp,doc,docx,xls,xlsx'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal satu item.',
            'remediation_notes.required' => 'Catatan perbaikan wajib diisi.',
            'submitted_by_name.required' => 'Nama pengirim wajib diisi.',
        ];
    }
}
