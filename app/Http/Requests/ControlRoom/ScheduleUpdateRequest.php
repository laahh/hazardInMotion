<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\SchedulePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ScheduleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var SchedulePlan $plan */
        $plan = $this->route('schedule');
        $isLocked = $plan instanceof SchedulePlan && $plan->isLocked();

        return [
            'site_code' => ['sometimes', Rule::in(array_column(ControlRoomSiteCode::cases(), 'value'))],
            'date' => ['sometimes', 'date'],
            'shift_code' => ['sometimes', Rule::in(array_column(ControlRoomShiftCode::cases(), 'value'))],
            'personnel_source_key' => ['sometimes', 'string', 'max:100'],
            'reason' => [$isLocked ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }
}
