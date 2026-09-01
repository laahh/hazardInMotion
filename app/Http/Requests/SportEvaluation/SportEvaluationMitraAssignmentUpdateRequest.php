<?php

declare(strict_types=1);

namespace App\Http\Requests\SportEvaluation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SportEvaluationMitraAssignmentUpdateRequest extends FormRequest
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
        $assignmentId = (int) $this->route('id');

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('evaluasi_well_mitra_assignments', 'user_id')->ignore($assignmentId),
            ],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*.perusahaan' => ['required', 'string', 'max:255'],
            'scopes.*.sites' => ['required', 'array', 'min:1'],
            'scopes.*.sites.*' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'user',
            'scopes' => 'perusahaan & site',
            'scopes.*.perusahaan' => 'perusahaan',
            'scopes.*.sites' => 'site',
            'scopes.*.sites.*' => 'site',
            'is_active' => 'status aktif',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'scopes' => $this->normalizedScopesInput(),
        ]);
    }

    /**
     * @return list<array{perusahaan: string, sites: list<string>}>
     */
    private function normalizedScopesInput(): array
    {
        $scopes = $this->input('scopes', []);
        if (! is_array($scopes)) {
            return [];
        }

        $rows = [];
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
                if ($trimmed === '') {
                    continue;
                }
                $cleanSites[$trimmed] = $trimmed;
            }
            if ($company === '' && $cleanSites === []) {
                continue;
            }
            $rows[] = [
                'perusahaan' => $company,
                'sites' => array_values($cleanSites),
            ];
        }

        return $rows;
    }
}
