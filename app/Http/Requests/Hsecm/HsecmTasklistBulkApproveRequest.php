<?php

declare(strict_types=1);

namespace App\Http\Requests\Hsecm;

use Illuminate\Foundation\Http\FormRequest;

class HsecmTasklistBulkApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal satu item untuk di-ACC.',
        ];
    }
}
