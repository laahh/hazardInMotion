<?php

declare(strict_types=1);

namespace App\Http\Requests\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringIkkRecord;
use Illuminate\Foundation\Http\FormRequest;

final class PncMonitoringIkkRecordRequest extends FormRequest
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
            'jenis' => ['nullable', 'string', 'max:100'],
            'nomor' => ['required', 'string', 'max:100'],
            'pekerjaan' => ['nullable', 'string', 'max:255'],
            'tanggal' => ['nullable', 'date'],
            'minggu' => ['nullable', 'integer', 'min:1', 'max:53'],
            'bulan' => ['nullable', 'integer', 'min:1', 'max:12'],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'site' => ['nullable', 'string', 'max:100'],
            'mine_contractor' => ['nullable', 'string', 'max:150'],
            'perusahaan' => ['nullable', 'string', 'max:150'],
            'finding_ia' => ['nullable', 'integer', 'min:0'],
            'finding_verlap' => ['nullable', 'integer', 'min:0'],
            'ia' => ['nullable', 'integer', 'min:0', 'max:1'],
            'ipk' => ['nullable', 'integer', 'min:0', 'max:1'],
            'plan_okk' => ['nullable', 'integer', 'min:0'],
            'okk_1' => ['nullable', 'integer', 'min:0'],
            'okk_2' => ['nullable', 'integer', 'min:0'],
            'okk_3' => ['nullable', 'integer', 'min:0'],
            'okk_layer_2' => ['nullable', 'integer', 'min:0'],
            'okk_layer_3' => ['nullable', 'integer', 'min:0'],
            'okk_layer_4' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesPayload(): array
    {
        $data = $this->validated();
        $nomor = trim((string) $data['nomor']);
        $tanggal = isset($data['tanggal']) ? (string) $data['tanggal'] : null;
        $minggu = isset($data['minggu']) ? (int) $data['minggu'] : null;
        $tahun = isset($data['tahun']) ? (int) $data['tahun'] : null;
        if ($tahun === null && $tanggal !== null && $tanggal !== '') {
            $tahun = (int) substr($tanggal, 0, 4);
        }
        if (! isset($data['bulan']) && $tanggal !== null && $tanggal !== '') {
            $data['bulan'] = (int) substr($tanggal, 5, 2);
        }

        $data['nomor'] = $nomor;
        $data['tahun'] = $tahun;
        $data['upsert_key'] = PncMonitoringIkkRecord::buildUpsertKey($nomor, $tanggal, $minggu, $tahun);
        $data['finding_ia'] = (int) ($data['finding_ia'] ?? 0);
        $data['finding_verlap'] = (int) ($data['finding_verlap'] ?? 0);
        $data['plan_okk'] = (int) ($data['plan_okk'] ?? 0);
        $data['okk_1'] = (int) ($data['okk_1'] ?? 0);
        $data['okk_2'] = (int) ($data['okk_2'] ?? 0);
        $data['okk_3'] = (int) ($data['okk_3'] ?? 0);
        $data['okk_layer_2'] = (int) ($data['okk_layer_2'] ?? 0);
        $data['okk_layer_3'] = (int) ($data['okk_layer_3'] ?? 0);
        $data['okk_layer_4'] = (int) ($data['okk_layer_4'] ?? 0);

        return $data;
    }
}
