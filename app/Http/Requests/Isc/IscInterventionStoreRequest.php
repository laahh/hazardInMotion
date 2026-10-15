<?php

declare(strict_types=1);

namespace App\Http\Requests\Isc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IscInterventionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Siapa pun yang login boleh membuat intervensi (tidak lagi dibatasi
        // peran isc-pic/admin). Login tetap wajib karena pembuatnya dicatat
        // sebagai pic_user_id. Rute /isc/interventions tetap dijaga middleware
        // isc.role di routes/Isc/isc.php; rute dari peta tidak.
        return $this->user() !== null;
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
