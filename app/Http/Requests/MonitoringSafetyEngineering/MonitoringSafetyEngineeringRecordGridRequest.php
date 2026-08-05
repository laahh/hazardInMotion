<?php

declare(strict_types=1);

namespace App\Http\Requests\MonitoringSafetyEngineering;

use Illuminate\Foundation\Http\FormRequest;

class MonitoringSafetyEngineeringRecordGridRequest extends FormRequest
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
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.id' => ['nullable', 'integer', 'min:1'],
            'rows.*.row_no' => ['nullable', 'integer', 'min:0'],
            'rows.*.site' => ['nullable', 'string', 'max:50'],
            'rows.*.perusahaan' => ['nullable', 'string', 'max:100'],
            'rows.*.aktivitas' => ['nullable', 'string', 'max:255'],
            'rows.*.sumber_rekayasa' => ['nullable', 'string', 'max:80'],
            'rows.*.pelaksana_rekayasa' => ['nullable', 'string', 'max:50'],
            'rows.*.pengendalian_rekayasa' => ['nullable', 'string'],
            'rows.*.tanggal_ideation' => ['nullable', 'string'],
            'rows.*.kajian_teknis_due_date' => ['nullable', 'string'],
            'rows.*.kajian_teknis_status' => ['nullable', 'string', 'max:20'],
            'rows.*.pengadaan_due_date' => ['nullable', 'string'],
            'rows.*.pengadaan_status' => ['nullable', 'string', 'max:20'],
            'rows.*.uji_coba_due_date' => ['nullable', 'string'],
            'rows.*.uji_coba_status' => ['nullable', 'string', 'max:20'],
            'rows.*.standardisasi_due_date' => ['nullable', 'string'],
            'rows.*.standardisasi_status' => ['nullable', 'string', 'max:20'],
            'rows.*.replikasi_due_date' => ['nullable', 'string'],
            'rows.*.replikasi_total_populasi' => ['nullable', 'integer', 'min:0'],
            'rows.*.replikasi_satuan' => ['nullable', 'string', 'max:50'],
            'rows.*.replikasi_target_komitmen' => ['nullable', 'integer', 'min:0'],
            'rows.*.replikasi_diusulkan_pjo' => ['nullable', 'string', 'max:255'],
            'rows.*.replikasi_ditinjau' => ['nullable', 'string', 'max:255'],
            'rows.*.replikasi_disetujui' => ['nullable', 'string', 'max:255'],
            'rows.*.replikasi_aktual' => ['nullable', 'integer', 'min:0'],
            'rows.*.deteksi_deviasi' => ['nullable', 'string', 'max:80'],
            'rows.*.intervensi_deviasi' => ['nullable', 'string', 'max:80'],
            'rows.*.prediksi_penurunan_tangga_risiko' => ['nullable', 'integer', 'min:0', 'max:255'],
            'rows.*.terkait_hazard' => ['nullable', 'string', 'max:10'],
            'rows.*.terkait_insiden' => ['nullable', 'string', 'max:10'],
            'rows.*.efektivitas_rekayasa' => ['nullable', 'string', 'max:80'],
            'rows.*.brief_analysis_challenge' => ['nullable', 'string'],
            'rows.*.next_to_do' => ['nullable', 'string'],
            'rows.*.potensi_peningkatan_efektivitas' => ['nullable', 'string', 'max:10'],
            'rows.*.pengendalian_peningkatan_efektivitas' => ['nullable', 'string'],
            'rows.*.total_risiko_signifikan' => ['nullable', 'integer', 'min:0'],
            'rows.*.link_list_risiko_signifikan' => ['nullable', 'string', 'max:500'],
            'rows.*.jumlah_risiko_signifikan_tercover_rekayasa' => ['nullable', 'integer', 'min:0'],
            'rows.*.link_risiko_signifikan_tercover_rekayasa' => ['nullable', 'string', 'max:500'],
            'rows.*.period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
