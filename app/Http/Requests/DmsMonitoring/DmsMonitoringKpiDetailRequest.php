<?php

declare(strict_types=1);

namespace App\Http\Requests\DmsMonitoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DmsMonitoringKpiDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'site' => ['nullable', 'string', 'max:80'],
            'perusahaan' => ['nullable', 'string', 'max:80'],
            'level' => ['required', 'string', Rule::in(['sites', 'companies', 'rows'])],
            'parent_site' => ['nullable', 'string', 'max:80'],
            'parent_company' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{start:string, end:string, site:string, perusahaan:string}
     */
    public function filters(): array
    {
        return [
            'start' => (string) $this->input('start'),
            'end' => (string) $this->input('end'),
            'site' => mb_substr(trim((string) $this->input('site', '')), 0, 80),
            'perusahaan' => mb_substr(trim((string) $this->input('perusahaan', '')), 0, 80),
        ];
    }

    public function metricKey(): string
    {
        return (string) $this->route('metric');
    }

    public function level(): string
    {
        return (string) $this->input('level', 'sites');
    }

    public function parentSite(): ?string
    {
        $value = trim((string) $this->input('parent_site', ''));

        return $value === '' ? null : mb_substr($value, 0, 80);
    }

    public function parentCompany(): ?string
    {
        $value = trim((string) $this->input('parent_company', ''));

        return $value === '' ? null : mb_substr($value, 0, 80);
    }

    public function page(): int
    {
        return max(1, (int) $this->input('page', 1));
    }
}
