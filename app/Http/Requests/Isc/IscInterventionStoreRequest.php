<?php

declare(strict_types=1);

namespace App\Http\Requests\Isc;

use App\Models\Isc\IscIntervention;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IscInterventionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', IscIntervention::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:isc_boundary_events,id'],
            'type' => ['required', 'string', Rule::in(['himbauan', 'evakuasi', 'penghentian_aktivitas', 'dampingan', 'lainnya'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidences' => ['nullable', 'array', 'max:5'],
            'evidences.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }
}
