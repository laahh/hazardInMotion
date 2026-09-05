<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * plan-OCR.md T3.1 — bisa satu baris (dari modal kalender) atau banyak baris
 * sekaligus dalam satu submit. `year`/`week_number` TIDAK diminta di sini —
 * dihitung otomatis oleh ScheduleBulkAssignService dari tanggal tiap baris,
 * supaya tidak ada risiko keduanya tidak sinkron dengan tanggal asli.
 */
final class ScheduleBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Input personil dari <datalist> berbentuk "Nama (SID)" (pola yang sama
     * dengan check-in.blade.php) — ekstrak SID-nya sebelum divalidasi.
     */
    protected function prepareForValidation(): void
    {
        $assignments = (array) $this->input('assignments', []);

        foreach ($assignments as $index => $assignment) {
            $raw = trim((string) ($assignment['personnel_source_key'] ?? ''));

            if (preg_match('/\(([^)]+)\)\s*$/', $raw, $matches) === 1) {
                $raw = trim($matches[1]);
            }

            $assignments[$index]['personnel_source_key'] = $raw;
        }

        $this->merge(['assignments' => $assignments]);
    }

    public function rules(): array
    {
        return [
            'site_code' => ['required', Rule::in(array_column(ControlRoomSiteCode::cases(), 'value'))],
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
