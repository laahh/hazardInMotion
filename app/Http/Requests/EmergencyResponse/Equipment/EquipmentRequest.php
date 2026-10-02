<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse\Equipment;

use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `code` tidak ada di sini: UUID peralatan digenerate otomatis oleh model
     * dari site + kategori + nama + urutan.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'equipment_category_id' => ['nullable', Rule::exists('er_equipment_categories', 'id')],
            'registration_number' => ['nullable', 'string', 'max:191'],
            'classification' => ['nullable', 'string', 'max:191'],
            'equipment_detail' => ['nullable', 'string'],
            'type_model' => ['nullable', 'string', 'max:191'],
            'brand' => ['nullable', 'string', 'max:191'],
            'serial_number' => ['nullable', 'string', 'max:191'],

            'site_id' => ['nullable', Rule::exists('er_sites', 'id')],
            'company_id' => ['nullable', Rule::exists('companies', 'id')],
            'location_name' => ['nullable', 'string', 'max:191'],
            'area_name' => ['nullable', 'string', 'max:191'],
            'position_detail' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'department_id' => ['nullable', Rule::exists('er_departments', 'id')],
            'emergency_unit_id' => ['nullable', Rule::exists('er_emergency_units', 'id')],
            'purchased_at' => ['nullable', 'date'],
            'commissioned_at' => ['nullable', 'date'],

            'condition' => ['required', Rule::in(array_keys(EmergencyEquipment::CONDITIONS))],
            'operational_status' => ['required', Rule::in(array_keys(EmergencyEquipment::OPERATIONAL_STATUSES))],
            'equipment_remarks' => ['nullable', 'string'],
            'damage_remarks' => ['nullable', 'string'],
            'position_status' => ['nullable', Rule::in(array_keys(EmergencyEquipment::POSITION_STATUSES))],

            'ba_progress' => ['nullable', Rule::in(array_keys(EmergencyEquipment::BA_PROGRESSES))],
            'ba_remarks' => ['nullable', 'string'],
            'item_status' => ['nullable', Rule::in(array_keys(EmergencyEquipment::ITEM_STATUSES))],
            'ba_closed_at' => ['nullable', 'date'],

            'last_inspection_at' => ['nullable', 'date'],
            'next_inspection_at' => ['nullable', 'date'],
            'last_calibration_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'certificate_number' => ['nullable', 'string', 'max:191'],
            'certificate_expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama Peralatan',
            'equipment_category_id' => 'Kategori Peralatan',
            'registration_number' => 'No Registrasi',
            'classification' => 'Klasifikasi Alat',
            'equipment_detail' => 'Detail Peralatan',
            'company_id' => 'Perusahaan',
            'condition' => 'Kondisi Peralatan',
            'equipment_remarks' => 'Keterangan Alat',
            'damage_remarks' => 'Keterangan Kerusakan',
            'position_status' => 'Status Posisi Barang',
            'ba_progress' => 'Progress BA',
            'ba_remarks' => 'Keterangan BA',
            'item_status' => 'Status Barang',
            'ba_closed_at' => 'Tanggal Close BA',
        ];
    }
}
