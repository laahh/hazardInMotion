<?php

declare(strict_types=1);

namespace App\Http\Requests\Isc;

use Illuminate\Foundation\Http\FormRequest;

final class IscInterventionEvidenceStoreRequest extends FormRequest
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
            'evidences' => ['required', 'array', 'min:1', 'max:5'],
            'evidences.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }
}
