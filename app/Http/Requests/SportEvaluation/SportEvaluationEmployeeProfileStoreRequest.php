<?php

declare(strict_types=1);

namespace App\Http\Requests\SportEvaluation;

use App\Services\SportEvaluation\SportEvaluationEmployeeProfileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SportEvaluationEmployeeProfileStoreRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:255'],
            'kode_sid' => ['required', 'string', 'max:64'],
            'status_karyawan' => ['required', 'string', 'max:50'],
            'nik' => ['nullable', 'string', 'max:64'],
            'site' => ['nullable', 'string', 'max:100'],
            'usia' => ['nullable', 'integer', 'min:0', 'max:120'],
            'divisi' => ['nullable', 'string', 'max:255'],
            'departement' => ['nullable', 'string', 'max:255'],
            'dept_dic' => ['nullable', 'string', 'max:255'],
            'kategori' => ['nullable', 'string', 'max:100'],
            'masa_kerja' => ['nullable', 'string', 'max:100'],
            'id_perusahaan' => ['nullable', 'integer', 'min:0'],
            'nama_perusahaan' => ['nullable', 'string', 'max:255'],
            'level_jabatan' => ['nullable', 'string', 'max:100'],
            'kategori_karyawan' => ['nullable', 'string', 'max:100'],
            'jabatan_fungsional' => ['nullable', 'string', 'max:255'],
            'jabatan_struktural' => ['nullable', 'string', 'max:255'],
            'membership_tier' => ['nullable', 'string', 'max:100'],
            'foto' => ['nullable', 'string', 'max:500'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama wajib diisi.',
            'kode_sid.required' => 'Kode SID wajib diisi.',
            'status_karyawan.required' => 'Status karyawan wajib diisi.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var SportEvaluationEmployeeProfileService $service */
            $service = app(SportEvaluationEmployeeProfileService::class);

            $kodeSid = trim((string) $this->input('kode_sid', ''));
            if ($kodeSid !== '' && $service->isKodeSidTaken($kodeSid)) {
                $validator->errors()->add('kode_sid', 'Kode SID sudah digunakan.');
            }

            $nik = trim((string) $this->input('nik', ''));
            if ($nik !== '' && $service->isNikTaken($nik)) {
                $validator->errors()->add('nik', 'NIK sudah digunakan.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status_karyawan');
        if (is_string($status) && $status !== '') {
            $this->merge([
                'status_karyawan' => strtoupper(trim($status)),
            ]);
        }
    }
}
