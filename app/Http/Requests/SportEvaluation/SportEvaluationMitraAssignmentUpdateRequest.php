<?php

declare(strict_types=1);

namespace App\Http\Requests\SportEvaluation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SportEvaluationMitraAssignmentUpdateRequest extends FormRequest
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
        $assignmentId = (int) $this->route('id');

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('evaluasi_well_mitra_assignments', 'user_id')->ignore($assignmentId),
            ],
            'site' => ['required', 'string', 'max:100'],
            'perusahaan' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'user',
            'site' => 'site',
            'perusahaan' => 'perusahaan',
            'is_active' => 'status aktif',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
