<?php

declare(strict_types=1);

namespace App\Http\Requests\Isc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IscInterventionVerifyRequest extends FormRequest
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
            'result' => ['required', 'string', Rule::in(['verified', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
