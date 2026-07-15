<?php

declare(strict_types=1);

namespace App\Http\Requests\Hsecm;

use Illuminate\Foundation\Http\FormRequest;

class HsecmWaNotifySendEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxIndex = max(count(config('hsecm.wa_recipients', [])) - 1, 0);

        return [
            'indexes' => ['required', 'array', 'min:1'],
            'indexes.*' => ['integer', 'min:0', 'max:'.$maxIndex],
            'week' => ['nullable', 'string', 'max:50'],
            'year' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'indexes.required' => 'Pilih minimal satu penerima email.',
            'indexes.min' => 'Pilih minimal satu penerima email.',
        ];
    }
}
