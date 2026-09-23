<?php

declare(strict_types=1);

namespace App\Http\Requests\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryToolAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PncMonitoringInventoryToolAssetRequest extends FormRequest
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
        $assetId = $this->route('inventoryToolAsset')?->asset_id;

        return [
            'tool_master_id' => ['required', 'integer', 'exists:pnc_monitoring_inventory_tool_master,tool_master_id'],
            'inventory_id' => ['nullable', 'string', 'max:50', Rule::unique('pnc_monitoring_inventory_tool_assets', 'inventory_id')->ignore($assetId, 'asset_id')],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'year_made' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'power_source' => ['nullable', 'string', 'max:50'],
            'status_availability' => ['nullable', 'string', Rule::in(PncMonitoringInventoryToolAsset::STATUSES)],
            'location_detail' => ['nullable', 'string', 'max:150'],
            'condition' => ['nullable', 'string', Rule::in(PncMonitoringInventoryToolAsset::CONDITIONS)],
            'owner_type' => ['nullable', 'string', 'max:20'],
            'owner_company_id' => ['nullable', 'integer', 'exists:pnc_monitoring_inventory_companies,company_id'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
