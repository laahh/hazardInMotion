<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ScheduleCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_code' => ['required', Rule::in(array_column(ControlRoomSiteCode::cases(), 'value'))],
            'from_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'from_week_number' => ['required', 'integer', 'min:1', 'max:53'],
            'to_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'to_week_number' => ['required', 'integer', 'min:1', 'max:53'],
        ];
    }
}
