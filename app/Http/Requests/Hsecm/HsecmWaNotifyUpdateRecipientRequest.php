<?php

declare(strict_types=1);

namespace App\Http\Requests\Hsecm;

use Illuminate\Foundation\Http\FormRequest;

class HsecmWaNotifyUpdateRecipientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $site = $this->input('site');
        if (is_string($site) && trim($site) === '') {
            $this->merge(['site' => null]);
        }

        $this->merge([
            'nama' => trim((string) $this->input('nama', '')),
            'email' => trim((string) $this->input('email', '')),
            'perusahaan' => trim((string) $this->input('perusahaan', '')),
            'role' => trim((string) $this->input('role', '')),
            'no' => trim((string) $this->input('no', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'site' => ['nullable', 'string', 'max:100'],
            'perusahaan' => ['nullable', 'string', 'max:190'],
            'role' => ['nullable', 'string', 'max:150'],
            'no' => ['nullable', 'string', 'max:30'],
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
            'nama.required' => 'Nama penerima wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ];
    }
}
