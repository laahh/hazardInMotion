<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ControlRoomDashboardSapDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sid' => strtoupper(trim((string) $this->query('sid', $this->input('sid')))),
            'date' => trim((string) $this->query('date', $this->input('date'))),
            'shift' => strtoupper(trim((string) $this->query('shift', $this->input('shift')))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sid' => ['required', 'string', 'max:32'],
            'date' => ['required', 'date'],
            'shift' => ['required', Rule::enum(ControlRoomShiftCode::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sid.required' => 'SID wajib diisi.',
            'date.required' => 'Tanggal wajib diisi.',
            'shift.required' => 'Shift wajib diisi.',
        ];
    }
}
