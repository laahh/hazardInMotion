<?php

declare(strict_types=1);

namespace App\Http\Requests\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryTool;
use Illuminate\Foundation\Http\FormRequest;

final class PncMonitoringInventoryToolRequest extends FormRequest
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
            'category' => ['required', 'string', 'in:'.implode(',', PncMonitoringInventoryTool::CATEGORIES)],
            'asset_id' => ['nullable', 'string', 'max:100'],
            'nama_alat' => ['required', 'string', 'max:191'],
            'sub_kategori' => ['nullable', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:150'],
            'model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:150'],
            'tahun_pembuatan' => ['nullable', 'string', 'max:20'],
            'power_source' => ['nullable', 'string', 'max:50'],
            'kapasitas_rating' => ['nullable', 'string', 'max:191'],
            'site' => ['nullable', 'string', 'max:100'],
            'lokasi_detail' => ['nullable', 'string', 'max:150'],
            'status_ketersediaan' => ['nullable', 'string', 'in:'.implode(',', PncMonitoringInventoryTool::STATUSES)],
            'condition' => ['nullable', 'string', 'in:'.implode(',', PncMonitoringInventoryTool::CONDITIONS)],
            'pic' => ['nullable', 'string', 'max:150'],
            'qty_on_hand' => ['nullable', 'integer', 'min:0'],
            'calibration_required' => ['nullable', 'boolean'],
            'last_calibration_date' => ['nullable', 'date'],
            'calibration_due_date' => ['nullable', 'date'],
            'inspection_required' => ['nullable', 'boolean'],
            'last_inspection_date' => ['nullable', 'date'],
            'next_inspection_due' => ['nullable', 'date'],
            'inspection_result' => ['nullable', 'string', 'in:Pass,Fail,Conditional'],
            'pm_required' => ['nullable', 'boolean'],
            'last_pm_date' => ['nullable', 'date'],
            'next_pm_due' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesPayload(): array
    {
        $data = $this->validated();
        $data['nama_alat'] = trim((string) $data['nama_alat']);
        $data['qty_on_hand'] = (int) ($data['qty_on_hand'] ?? 1);
        $data['calibration_required'] = $this->boolean('calibration_required');
        $data['inspection_required'] = $this->boolean('inspection_required');
        $data['pm_required'] = $this->boolean('pm_required');
        $data['upsert_key'] = PncMonitoringInventoryTool::buildUpsertKey(
            $data['category'],
            $data['nama_alat'],
            $data['asset_id'] ?? null,
            $data['serial_number'] ?? null,
        );

        return $data;
    }
}
