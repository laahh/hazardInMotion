<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse;

use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form inspeksi publik (tanpa login).
 * Honeypot 'website' — bot pengisi form otomatis biasanya ikut mengisi field
 * tersembunyi ini; manusia tidak akan melihatnya sama sekali.
 */
class PublicInspectionStoreRequest extends FormRequest
{
    public const MAX_PHOTO_KB = 5120;

    public function authorize(): bool
    {
        return trim((string) $this->input('website', '')) === '';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in(['equipment', 'safety_device'])],
            'target_id' => ['required', 'uuid'],
            'checklist_template_id' => ['required', 'uuid', Rule::exists('er_checklist_templates', 'id')],

            'inspector_name' => ['required', 'string', 'max:100'],
            'inspector_nik' => ['nullable', 'string', 'max:30'],
            'inspector_sid' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'inspector_phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,13}$/'],

            'condition_result' => ['nullable', Rule::in(array_keys(EmergencyEquipment::CONDITIONS))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'signature_data' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.checklist_template_item_id' => ['required', 'uuid'],
            'items.*.answer_value' => ['nullable', 'string', 'max:500'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.photo' => ['nullable', 'image', 'max:'.self::MAX_PHOTO_KB],

            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_id.required' => 'Cari dan pilih peralatan yang akan diinspeksi terlebih dahulu.',
            'checklist_template_id.required' => 'Checklist belum termuat. Cari ulang peralatannya.',
            'checklist_template_id.exists' => 'Template checklist tidak ditemukan. Cari ulang peralatannya.',
            'inspector_name.required' => 'Nama inspector wajib diisi.',
            'inspector_sid.regex' => 'SID hanya boleh huruf dan angka, mis. P8GTB.',
            'inspector_sid.max' => 'SID maksimal 20 karakter.',
            'inspector_phone.required' => 'Nomor WhatsApp wajib diisi.',
            'inspector_phone.regex' => 'Format nomor WhatsApp tidak valid. Contoh: 08123456789.',
            'items.required' => 'Checklist belum diisi.',
            'items.*.photo.image' => 'Lampiran harus berupa foto (JPG/PNG).',
            'items.*.photo.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }
}
