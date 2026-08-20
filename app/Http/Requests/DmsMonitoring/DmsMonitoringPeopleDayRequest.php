<?php

declare(strict_types=1);

namespace App\Http\Requests\DmsMonitoring;

use Illuminate\Foundation\Http\FormRequest;

final class DmsMonitoringPeopleDayRequest extends FormRequest
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
            'day' => ['required', 'date_format:Y-m-d', 'after_or_equal:start', 'before_or_equal:end'],
            'site' => ['nullable', 'string', 'max:80'],
            'perusahaan' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:with_alert,without_alert,all'],
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

    public function day(): string
    {
        return (string) $this->input('day');
    }

    public function page(): int
    {
        return max(1, (int) $this->input('page', 1));
    }

    public function status(): string
    {
        $status = (string) $this->input('status', 'without_alert');

        return in_array($status, ['with_alert', 'without_alert', 'all'], true)
            ? $status
            : 'without_alert';
    }
}
