<?php

declare(strict_types=1);

namespace App\Http\Requests\PncMonitoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PncMonitoringInventoryCategoryRequest extends FormRequest
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
        $categoryId = $this->route('inventoryCategory')?->category_id;

        return [
            'code' => ['required', 'string', 'max:5', Rule::unique('pnc_monitoring_inventory_categories', 'code')->ignore($categoryId, 'category_id')],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ];
    }
}
