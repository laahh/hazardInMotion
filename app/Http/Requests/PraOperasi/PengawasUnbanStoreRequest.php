<?php

declare(strict_types=1);

namespace App\Http\Requests\PraOperasi;

use App\Http\Requests\AutoBanned\Concerns\ValidatesAutoBannedTreatmentEvidenceFile;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi form publik pengajuan unbanned pengawas (tanpa login). Aturan file
 * sama dengan /form/pengajuan-treatment, ditambah 'required' dan 'mimes' karena
 * form ini terbuka untuk umum. Honeypot 'website' menahan bot pengisi form.
 */
class PengawasUnbanStoreRequest extends FormRequest
{
    use ValidatesAutoBannedTreatmentEvidenceFile;

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
            'sid' => ['required', 'string', 'max:64'],
            'tanggal_kerja' => ['required', 'date_format:Y-m-d'],
            'alasan_pengajuan' => ['required', 'string', 'max:2000'],
            'no_hp' => ['required', 'string', 'max:32'],
            'evidence_file' => [
                'required',
                'file',
                'mimes:'.implode(',', (array) config('auto_banned.treatment.allowed_mimes', [])),
                ...$this->treatmentEvidenceFileRules(),
            ],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->treatmentEvidenceFileMessages(), [
            'sid.required' => 'Masukkan SID Anda terlebih dahulu.',
            'tanggal_kerja.required' => 'Pilih riwayat banned terlebih dahulu.',
            'tanggal_kerja.date_format' => 'Riwayat banned tidak valid. Cari ulang SID Anda.',
            'alasan_pengajuan.required' => 'Ceritakan singkat tindakan perbaikan yang sudah dilakukan.',
            'no_hp.required' => 'Masukkan nomor HP / WhatsApp Anda.',
            'evidence_file.mimes' => 'Format file tidak didukung. Gunakan PDF, foto (JPG/PNG), Word, atau Excel.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'no_hp' => 'nomor HP',
        ];
    }
}
