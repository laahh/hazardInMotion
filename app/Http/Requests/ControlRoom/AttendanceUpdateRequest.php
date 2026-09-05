<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Models\ControlRoom\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AttendanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in([
                Attendance::STATUS_SESUAI_JADWAL,
                Attendance::STATUS_MENGGANTIKAN,
                Attendance::STATUS_TIDAK_HADIR,
            ])],
            'replacing_source_key' => ['required_if:status,'.Attendance::STATUS_MENGGANTIKAN, 'nullable', 'string', 'max:100'],
            'absence_reason' => ['required_if:status,'.Attendance::STATUS_TIDAK_HADIR, 'nullable', 'string', 'max:2000'],
            'correction_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
