<?php

declare(strict_types=1);

namespace App\Http\Requests\DmsMonitoring;

use Illuminate\Foundation\Http\FormRequest;

final class DmsMonitoringPersonAlertDetailRequest extends FormRequest
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
            'sid' => ['required', 'string', 'max:40'],
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

    public function sid(): string
    {
        return mb_strtoupper(mb_substr(trim((string) $this->input('sid')), 0, 40));
    }
}
