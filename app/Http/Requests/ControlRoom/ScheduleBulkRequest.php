<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * plan-OCR.md T3.1 — satu submit untuk satu minggu penuh (grid personil x
 * 7 hari x 2 shift).
 */
final class ScheduleBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_code' => ['required', Rule::in(array_column(ControlRoomSiteCode::cases(), 'value'))],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'week_number' => ['required', 'integer', 'min:1', 'max:53'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.date' => ['required', 'date'],
            'assignments.*.shift_code' => ['required', Rule::in(array_column(ControlRoomShiftCode::cases(), 'value'))],
            'assignments.*.personnel_source_key' => ['required', 'string', 'max:100'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator): void {
            $seen = [];

            foreach ((array) $this->input('assignments', []) as $index => $assignment) {
                $key = ($assignment['date'] ?? '').'|'.($assignment['shift_code'] ?? '').'|'.($assignment['personnel_source_key'] ?? '');

                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "assignments.{$index}.personnel_source_key",
                        'Personil tidak boleh dobel di tanggal dan shift yang sama.'
                    );
                }

                $seen[$key] = true;
            }
        });
    }
}
