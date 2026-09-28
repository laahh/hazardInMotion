<?php

declare(strict_types=1);

namespace App\Http\Requests\PraOperasi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi form publik pengajuan bukti treatment (tanpa login).
 * Honeypot 'website' — bot pengisi form otomatis biasanya ikut mengisi
 * field tersembunyi ini; manusia tidak akan melihatnya sama sekali.
 */
class RosterTreatmentEvidenceStoreRequest extends FormRequest
{
    public const MAX_UPLOAD_KB = 10240;

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xlsx', 'xls'];

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
            'master_id' => ['required', 'integer', 'exists:sid_roster_banned_master,id'],
            'submitted_by' => ['required', 'string', 'max:100'],
            'tanggal_treatment' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'evidence_file' => [
                'required',
                'file',
                'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
                'max:'.self::MAX_UPLOAD_KB,
            ],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'master_id.required' => 'Cari NIK/SID Anda terlebih dahulu untuk memilih data pelanggaran.',
            'master_id.exists' => 'Data pelanggaran tidak ditemukan. Cari ulang NIK/SID Anda.',
            'submitted_by.required' => 'Nama pengaju wajib diisi.',
            'evidence_file.required' => 'Lampirkan file bukti treatment (foto/dokumen).',
            'evidence_file.mimes' => 'Format file tidak didukung. Gunakan PDF, foto (JPG/PNG), Word, atau Excel.',
            'evidence_file.max' => 'Ukuran file maksimal 10 MB.',
        ];
    }
}
