<?php

declare(strict_types=1);

namespace App\Http\Requests\Hsecm;

use Illuminate\Foundation\Http\FormRequest;

class HsecmTasklistRejectRequest extends FormRequest
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
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
