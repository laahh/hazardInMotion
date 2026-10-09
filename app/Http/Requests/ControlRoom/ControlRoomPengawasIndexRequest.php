<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\Reference\PengawasRoster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ControlRoomPengawasIndexRequest extends FormRequest
{
    public const ALL_SITES = 'ALL';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $site = strtoupper(trim((string) $this->query('site', $this->input('site', ''))));
        $this->merge(['site' => $site === '' ? null : $site]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sites = array_map(
            static fn (ControlRoomSiteCode $site): string => $site->value,
            app(PengawasRoster::class)->sites(),
        );

        return [
            'iso_week' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}-W\d{1,2}$/i'],
            'year' => ['sometimes', 'integer', 'min:2020', 'max:2100'],
            'week' => ['sometimes', 'integer', 'min:1', 'max:53'],
            'site' => ['sometimes', 'nullable', 'string', Rule::in([self::ALL_SITES, ...$sites])],
        ];
    }

    /**
     * Null = semua site. Default (tanpa parameter) = site pertama supaya
     * halaman tidak langsung menarik SAP ±250 pengawas sekaligus.
     */
    public function siteFilter(): ?ControlRoomSiteCode
    {
        $value = (string) ($this->input('site') ?? '');
        if ($value === self::ALL_SITES) {
            return null;
        }
        if ($value === '') {
            return app(PengawasRoster::class)->sites()[0] ?? null;
        }

        return ControlRoomSiteCode::tryFrom($value);
    }
}
