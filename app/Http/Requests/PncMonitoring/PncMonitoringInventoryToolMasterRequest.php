<?php

declare(strict_types=1);

namespace App\Http\Requests\PncMonitoring;

use Illuminate\Foundation\Http\FormRequest;

final class PncMonitoringInventoryToolMasterRequest extends FormRequest
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
            'category_id' => ['required', 'integer', 'exists:pnc_monitoring_inventory_categories,category_id'],
            'standard_name' => ['required', 'string', 'max:200'],
            'sub_category' => ['nullable', 'string', 'max:100'],
            'main_function' => ['nullable', 'string'],
            'criticality' => ['nullable', 'string', 'max:20'],
            'risk_class' => ['nullable', 'string', 'max:20'],
            'is_regulated' => ['nullable', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],

            'functions' => ['nullable', 'array'],
            'functions.*.description' => ['nullable', 'string'],

            'inspection_methods' => ['nullable', 'array'],
            'inspection_methods.*.method_name' => ['nullable', 'string', 'max:150'],

            'safety_features' => ['nullable', 'array'],
            'safety_features.*.feature_name' => ['nullable', 'string', 'max:150'],
            'safety_features.*.description' => ['nullable', 'string'],

            'standards' => ['nullable', 'array'],
            'standards.*.standard_name' => ['nullable', 'string', 'max:150'],

            'checklist_items' => ['nullable', 'array'],
            'checklist_items.*.komponen_diperiksa' => ['nullable', 'string', 'max:200'],
            'checklist_items.*.kriteria_pemeriksaan' => ['nullable', 'string'],

            'usage_rules' => ['nullable', 'array'],
            'usage_rules.*.rule_type' => ['nullable', 'string', 'in:do,dont'],
            'usage_rules.*.description' => ['nullable', 'string'],

            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_name' => ['nullable', 'string', 'max:150'],
            'attributes.*.attribute_value' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function corePayload(): array
    {
        $data = $this->validated();

        return [
            'category_id' => (int) $data['category_id'],
            'standard_name' => trim((string) $data['standard_name']),
            'sub_category' => $this->blankToNull($data['sub_category'] ?? null),
            'main_function' => $this->blankToNull($data['main_function'] ?? null),
            'criticality' => $this->blankToNull($data['criticality'] ?? null),
            'risk_class' => $this->blankToNull($data['risk_class'] ?? null),
            'is_regulated' => (bool) ($data['is_regulated'] ?? false),
            'image_url' => $this->blankToNull($data['image_url'] ?? null),
        ];
    }

    /**
     * @return list<array{description:string}>
     */
    public function functionsPayload(): array
    {
        $rows = [];
        foreach ((array) $this->input('functions', []) as $index => $row) {
            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $rows[] = ['sequence' => count($rows) + 1, 'description' => $description];
        }

        return $rows;
    }

    /**
     * @return list<array{method_name:string}>
     */
    public function inspectionMethodsPayload(): array
    {
        return $this->nonEmptyRows('inspection_methods', ['method_name']);
    }

    /**
     * @return list<array{feature_name:string,description:?string}>
     */
    public function safetyFeaturesPayload(): array
    {
        return $this->nonEmptyRows('safety_features', ['feature_name', 'description']);
    }

    /**
     * @return list<array{standard_name:string}>
     */
    public function standardsPayload(): array
    {
        return $this->nonEmptyRows('standards', ['standard_name']);
    }

    /**
     * @return list<array{sequence:int,komponen_diperiksa:string,kriteria_pemeriksaan:?string}>
     */
    public function checklistItemsPayload(): array
    {
        $rows = [];
        foreach ((array) $this->input('checklist_items', []) as $row) {
            $komponen = trim((string) ($row['komponen_diperiksa'] ?? ''));
            if ($komponen === '') {
                continue;
            }
            $rows[] = [
                'sequence' => count($rows) + 1,
                'komponen_diperiksa' => $komponen,
                'kriteria_pemeriksaan' => trim((string) ($row['kriteria_pemeriksaan'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{sequence:int,rule_type:string,description:string}>
     */
    public function usageRulesPayload(): array
    {
        $rows = [];
        foreach ((array) $this->input('usage_rules', []) as $row) {
            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $rows[] = [
                'sequence' => count($rows) + 1,
                'rule_type' => in_array($row['rule_type'] ?? 'do', ['do', 'dont'], true) ? $row['rule_type'] : 'do',
                'description' => $description,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{attribute_name:string,attribute_value:?string}>
     */
    public function attributesPayload(): array
    {
        $rows = [];
        foreach ((array) $this->input('attributes', []) as $row) {
            $name = trim((string) ($row['attribute_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'attribute_name' => $name,
                'attribute_value' => $this->blankToNull($row['attribute_value'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    private function nonEmptyRows(string $key, array $fields): array
    {
        $rows = [];
        foreach ((array) $this->input($key, []) as $row) {
            $primary = trim((string) ($row[$fields[0]] ?? ''));
            if ($primary === '') {
                continue;
            }
            $entry = [];
            foreach ($fields as $field) {
                $entry[$field] = $field === $fields[0] ? $primary : $this->blankToNull($row[$field] ?? null);
            }
            $rows[] = $entry;
        }

        return $rows;
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
