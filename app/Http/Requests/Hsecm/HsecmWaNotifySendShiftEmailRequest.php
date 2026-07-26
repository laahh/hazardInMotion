<?php

declare(strict_types=1);

namespace App\Http\Requests\Hsecm;

use App\Services\Hsecm\HsecmWaRecipientRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HsecmWaNotifySendShiftEmailRequest extends FormRequest
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
        $maxIndex = max(count(app(HsecmWaRecipientRepository::class)->all()) - 1, 0);

        return [
            'mode' => ['required', Rule::in(['midshift', 'endshift'])],
            'shift' => ['required', Rule::in(['night', 'day'])],
            'dry_run' => ['sometimes', 'boolean'],
            'email' => ['nullable', 'email', 'max:255'],
            'indexes' => ['nullable', 'array'],
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
            'mode.required' => 'Pilih mode midshift atau endshift.',
            'shift.required' => 'Pilih shift night atau day.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dry_run' => $this->boolean('dry_run'),
        ]);
    }
}
