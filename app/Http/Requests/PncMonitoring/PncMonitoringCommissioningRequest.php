<?php

declare(strict_types=1);

namespace App\Http\Requests\PncMonitoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PncMonitoringCommissioningRequest extends FormRequest
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
        $commissioning = $this->route('commissioning');
        $ignoreId = is_object($commissioning) ? $commissioning->id : null;

        return [
            'site' => ['nullable', 'string', 'max:100'],
            'no_register_spip' => [
                'required',
                'string',
                'max:100',
                Rule::unique('pnc_monitoring_commissionings', 'no_register_spip')->ignore($ignoreId),
            ],
            'detail_jenis_spip' => ['nullable', 'string', 'max:150'],
            'keterangan_sko' => ['nullable', 'string', 'max:255'],
            'nama_pengawas_teknis' => ['nullable', 'string', 'max:150'],
            'permohonan_dokumen_1' => ['nullable', 'date'],
            'week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'pemilik_spip' => ['nullable', 'string', 'max:150'],
            'pengelola_spip' => ['nullable', 'string', 'max:150'],
            'temuan_komisioning' => ['nullable', 'integer', 'min:0'],
            'status_komisioning' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'alasan_reject' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'performance_sko' => ['nullable', 'numeric'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesPayload(): array
    {
        $data = $this->validated();
        $data['no_register_spip'] = trim((string) $data['no_register_spip']);
        $data['temuan_komisioning'] = (int) ($data['temuan_komisioning'] ?? 0);
        if (! isset($data['tahun']) && ! empty($data['permohonan_dokumen_1'])) {
            $data['tahun'] = (int) substr((string) $data['permohonan_dokumen_1'], 0, 4);
        }

        return $data;
    }
}
