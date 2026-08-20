<?php

declare(strict_types=1);

namespace App\Http\Requests\DmsMonitoring;

use Illuminate\Foundation\Http\FormRequest;

final class DmsMonitoringOverallModalRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:with_alert,without_alert'],
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

    public function page(): int
    {
        return max(1, (int) $this->input('page', 1));
    }

    public function status(): string
    {
        $status = (string) $this->input('status', 'with_alert');

        return in_array($status, ['with_alert', 'without_alert'], true)
            ? $status
            : 'with_alert';
    }
}
