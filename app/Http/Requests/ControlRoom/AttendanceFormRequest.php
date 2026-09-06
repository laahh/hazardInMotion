<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use Illuminate\Foundation\Http\FormRequest;

final class AttendanceFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sid' => strtoupper(trim((string) $this->input('sid'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sid' => ['required', 'string', 'max:100'],
            'tanggal' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subDays(31)->toDateString()],
            'bukti' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sid.required' => 'SID wajib diisi.',
            'tanggal.required' => 'Tanggal wajib dipilih.',
            'tanggal.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'tanggal.after_or_equal' => 'Tanggal terlalu lama. Pilih maksimal 31 hari ke belakang.',
            'bukti.required' => 'Unggah bukti kehadiran (foto atau PDF).',
            'bukti.file' => 'Bukti harus berupa file.',
            'bukti.max' => 'Ukuran bukti maksimal 5 MB.',
            'bukti.mimes' => 'Bukti harus JPG, PNG, WEBP, atau PDF.',
        ];
    }
}
