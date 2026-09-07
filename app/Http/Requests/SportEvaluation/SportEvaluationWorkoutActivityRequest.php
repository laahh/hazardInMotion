<?php

declare(strict_types=1);

namespace App\Http\Requests\SportEvaluation;

use Illuminate\Foundation\Http\FormRequest;

final class SportEvaluationWorkoutActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $from = trim((string) $this->input('from', ''));
        $to = trim((string) $this->input('to', ''));

        if ($from === '') {
            $this->merge(['from' => now()->subDays(29)->format('Y-m-d')]);
        }
        if ($to === '') {
            $this->merge(['to' => now()->format('Y-m-d')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'site' => ['nullable', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'division' => ['nullable', 'string', 'max:150'],
            'activity_type' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.date' => 'Tanggal awal tidak valid.',
            'to.date' => 'Tanggal akhir tidak valid.',
            'to.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
        ];
    }
}
