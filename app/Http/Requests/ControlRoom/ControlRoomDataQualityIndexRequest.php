<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\ControlRoomSiteDutyBoardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ControlRoomDataQualityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $site = strtoupper(trim((string) $this->query('site', $this->input('site', ''))));
        $this->merge([
            'site' => in_array($site, ['', 'ALL'], true) ? null : $site,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'iso_week' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}-W\d{1,2}$/i'],
            'year' => ['sometimes', 'integer', 'min:2020', 'max:2100'],
            'week' => ['sometimes', 'integer', 'min:1', 'max:53'],
            'site' => ['sometimes', 'nullable', 'string', Rule::in(ControlRoomSiteDutyBoardService::BOARD_SITE_CODES)],
        ];
    }

    public function siteFilter(): ?ControlRoomSiteCode
    {
        $value = strtoupper(trim((string) ($this->input('site') ?? '')));
        if ($value === '') {
            return null;
        }

        return ControlRoomSiteCode::tryFrom($value);
    }
}
