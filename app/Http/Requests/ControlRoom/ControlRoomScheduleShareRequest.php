<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ControlRoomScheduleShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site' => ['sometimes', 'nullable', 'string', Rule::in(array_column(ControlRoomSiteCode::cases(), 'value'))],
            'year' => ['sometimes', 'integer', 'min:2020', 'max:2100'],
            'week' => ['sometimes', 'integer', 'min:1', 'max:53'],
            'week_number' => ['sometimes', 'integer', 'min:1', 'max:53'],
            'scope' => ['sometimes', 'string', Rule::in(['site', 'all'])],
        ];
    }

    public function siteFilter(): ControlRoomSiteCode
    {
        return ControlRoomSiteCode::fromRequest((string) $this->input('site', ''));
    }

    public function allSites(): bool
    {
        return $this->string('scope', 'site')->toString() === 'all';
    }

    public function weekNumber(): int
    {
        if ($this->filled('week')) {
            return (int) $this->integer('week');
        }

        return (int) $this->integer('week_number', (int) now()->isoWeek());
    }
}
