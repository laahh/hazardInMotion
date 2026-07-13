<?php

declare(strict_types=1);

namespace App\Http\Requests\AutoBanned;

use App\Enums\AutoBannedManualBanScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AutoBannedManualBanInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sid' => strtoupper(trim((string) $this->input('sid', ''))),
            'nik' => trim((string) $this->input('nik', '')),
            'nama' => trim((string) $this->input('nama', '')),
            'perusahaan' => trim((string) $this->input('perusahaan', '')),
            'site_dedicated' => trim((string) $this->input('site_dedicated', '')),
            'banned_status' => trim((string) $this->input('banned_status', '')),
            'banned_reason' => trim((string) $this->input('banned_reason', '')),
            'status_onsite' => trim((string) $this->input('status_onsite', 'ONSITE')),
            'filter_shift' => trim((string) $this->input('filter_shift', 'Shift 1')),
            'iso_year' => trim((string) $this->input('iso_year', '')),
            'iso_week' => ltrim(trim((string) $this->input('iso_week', '')), 'Ww'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $scope = AutoBannedManualBanScope::tryFrom((string) $this->input('ban_scope', ''));

        $rules = [
            'ban_scope' => ['required', 'string', Rule::in(array_column(AutoBannedManualBanScope::cases(), 'value'))],
            'sid' => ['required', 'string', 'max:32'],
            'nik' => ['nullable', 'string', 'max:64'],
            'nama' => ['required', 'string', 'max:191'],
            'perusahaan' => ['nullable', 'string', 'max:191'],
            'site_dedicated' => ['nullable', 'string', 'max:64'],
            'filter_shift' => ['required', 'string', 'max:32'],
            'banned_status' => ['required', 'string', 'max:64'],
            'banned_reason' => ['required', 'string', 'max:2000'],
            'status_onsite' => ['nullable', 'string', 'max:32'],
            'banned_at' => ['nullable', 'date'],
        ];

        if ($scope === AutoBannedManualBanScope::Daily) {
            $rules['filter_date'] = ['required', 'date'];
            $rules['iso_year'] = ['nullable'];
            $rules['iso_week'] = ['nullable'];
        }

        if ($scope === AutoBannedManualBanScope::Weekly) {
            $rules['filter_date'] = ['nullable', 'date'];
            $rules['iso_year'] = ['required', 'digits:4'];
            $rules['iso_week'] = ['required', 'regex:/^\d{1,2}$/'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $scope = AutoBannedManualBanScope::tryFrom((string) $this->input('ban_scope', ''));
            if ($scope !== AutoBannedManualBanScope::Weekly) {
                return;
            }

            $week = (int) $this->input('iso_week');
            if ($week < 1 || $week > 53) {
                $validator->errors()->add('iso_week', 'ISO week harus antara 1–53.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ban_scope.required' => 'Pilih tipe banned Daily atau Weekly.',
            'sid.required' => 'SID karyawan wajib diisi.',
            'nama.required' => 'Nama karyawan wajib diisi.',
            'filter_date.required' => 'Tanggal filter (Daily) wajib diisi.',
            'iso_year.required' => 'ISO year (Weekly) wajib diisi.',
            'iso_week.required' => 'ISO week (Weekly) wajib diisi.',
            'banned_status.required' => 'Status banned wajib diisi.',
            'banned_reason.required' => 'Alasan banned wajib diisi.',
            'filter_shift.required' => 'Shift wajib diisi.',
        ];
    }
}
