<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Services\ControlRoom\Reference\PengawasRoster;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class ControlRoomPengawasSapDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sid' => strtoupper(trim((string) $this->query('sid', $this->input('sid')))),
            'date' => trim((string) $this->query('date', $this->input('date'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sid' => ['required', 'string', 'max:32', function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(PengawasRoster::class)->has((string) $value)) {
                    $fail('SID bukan pengawas yang dimonitor.');
                }
            }],
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sid.required' => 'SID wajib diisi.',
            'date.required' => 'Tanggal wajib diisi.',
        ];
    }
}
