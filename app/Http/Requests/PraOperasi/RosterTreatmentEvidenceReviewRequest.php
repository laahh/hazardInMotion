<?php

declare(strict_types=1);

namespace App\Http\Requests\PraOperasi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RosterTreatmentEvidenceReviewRequest extends FormRequest
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
        return [
            'action' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Aksi wajib dipilih.',
            'action.in' => 'Aksi tidak valid.',
            'rejection_reason.required_if' => 'Alasan penolakan wajib diisi.',
        ];
    }

    public function isApprove(): bool
    {
        return (string) $this->input('action') === 'approve';
    }
}
