<?php

declare(strict_types=1);

namespace App\Http\Requests\MonitoringSafetyEngineering;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Validasi form assign PIC → perusahaan & site (dipakai untuk create maupun update).
 */
final class MonitoringSafetyEngineeringPicAssignmentRequest extends FormRequest
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
            'sid' => array_values(array_filter([
                'nullable',
                'string',
                'max:50',
                // Tabel bisa belum termigrasi; jangan bikin validasi meledak karenanya.
                Schema::hasTable('monitoring_safety_engineering_pic_assignments')
                    ? Rule::unique('monitoring_safety_engineering_pic_assignments', 'sid')
                        ->ignore($this->assignmentId())
                    : null,
            ])),
            'jabatan' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*.perusahaan' => ['required', 'string', 'max:255'],
            'scopes.*.sites' => ['required', 'array', 'min:1'],
            'scopes.*.sites.*' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nama' => 'nama PIC',
            'sid' => 'SID',
            'user_id' => 'akun user',
            'scopes' => 'perusahaan & site',
            'scopes.*.perusahaan' => 'perusahaan',
            'scopes.*.sites' => 'site',
            'scopes.*.sites.*' => 'site',
            'is_active' => 'status aktif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scopes.required' => 'Minimal satu perusahaan beserta site-nya harus diisi.',
            'scopes.*.sites.required' => 'Pilih minimal satu site untuk perusahaan ini.',
            'sid.unique' => 'SID ini sudah dipakai assignment lain.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $sid = trim((string) $this->input('sid', ''));

        $this->merge([
            'nama' => trim((string) $this->input('nama', '')),
            'sid' => $sid === '' ? null : strtoupper($sid),
            'user_id' => (int) $this->input('user_id', 0) > 0 ? (int) $this->input('user_id') : null,
            'is_active' => $this->boolean('is_active'),
            'scopes' => $this->normalizedScopesInput(),
        ]);
    }

    private function assignmentId(): ?int
    {
        $id = (int) $this->route('id');

        return $id > 0 ? $id : null;
    }

    /**
     * Gabungkan baris dengan perusahaan yang sama dan buang site duplikat.
     *
     * @return list<array{perusahaan: string, sites: list<string>}>
     */
    private function normalizedScopesInput(): array
    {
        $scopes = $this->input('scopes', []);
        if (! is_array($scopes)) {
            return [];
        }

        $byCompany = [];
        foreach ($scopes as $row) {
            if (! is_array($row)) {
                continue;
            }

            $company = trim((string) ($row['perusahaan'] ?? ''));
            $sites = $row['sites'] ?? [];
            if (! is_array($sites)) {
                $sites = [$sites];
            }

            $cleanSites = [];
            foreach ($sites as $site) {
                $trimmed = trim((string) $site);
                if ($trimmed !== '') {
                    $cleanSites[$trimmed] = $trimmed;
                }
            }

            if ($company === '' && $cleanSites === []) {
                continue;
            }

            $key = strtoupper($company);
            if (isset($byCompany[$key])) {
                $byCompany[$key]['sites'] = array_merge($byCompany[$key]['sites'], $cleanSites);

                continue;
            }

            $byCompany[$key] = [
                'perusahaan' => $company,
                'sites' => $cleanSites,
            ];
        }

        $rows = [];
        foreach ($byCompany as $row) {
            $rows[] = [
                'perusahaan' => $row['perusahaan'],
                'sites' => array_values($row['sites']),
            ];
        }

        return $rows;
    }
}
