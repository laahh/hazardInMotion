<?php

declare(strict_types=1);

namespace App\Http\Requests\AutoBanned;

use App\Http\Requests\AutoBanned\Concerns\ValidatesAutoBannedSidBannedLogLink;
use App\Http\Requests\AutoBanned\Concerns\ValidatesAutoBannedTreatmentEvidenceFile;
use Illuminate\Foundation\Http\FormRequest;

class AutoBannedPublicStoreTreatmentEvidenceRequest extends FormRequest
{
    use ValidatesAutoBannedSidBannedLogLink;
    use ValidatesAutoBannedTreatmentEvidenceFile;

    public function authorize(): bool
    {
        if (! (bool) config('auto_banned.treatment.public_form_enabled', true)) {
            return false;
        }

        return trim((string) $this->input('website', '')) === '';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'sid' => ['required', 'string', 'max:64'],
            'week' => ['required', 'string', 'max:8'],
            'year' => ['required', 'string', 'max:8'],
            'alasan_pengajuan' => ['required', 'string', 'max:2000'],
            'no_hp' => ['required', 'string', 'max:32'],
            'evidence_file' => $this->treatmentEvidenceFileRules(),
            'website' => ['nullable', 'string', 'max:0'],
        ], $this->sidBannedLogIdRules());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->treatmentEvidenceFileMessages(), $this->sidBannedLogIdMessages(), [
            'sid.required' => 'Masukkan SID Anda terlebih dahulu.',
            'alasan_pengajuan.required' => 'Ceritakan singkat tindakan perbaikan yang sudah dilakukan.',
            'no_hp.required' => 'Masukkan nomor HP / WhatsApp Anda.',
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
