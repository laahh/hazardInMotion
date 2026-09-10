<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Models\ControlRoom\ControlRoomTbcValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ControlRoomTbcValidationRequest extends FormRequest
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
        $route = $this->route('tbcValidation');
        $id = $route instanceof ControlRoomTbcValidation ? (int) $route->id : (int) $route;
        $rootcause = $this->configList('tbc_rootcause_options');
        $noAlert = $this->configList('tbc_no_alert_options');

        return [
            'no_alert' => $this->optionalIn($noAlert, 50),
            'validator' => ['nullable', 'string', 'max:255'],
            'tasklist' => [
                'required',
                'string',
                'max:191',
                Rule::unique('control_room_tbc_validations', 'tasklist')->ignore($id > 0 ? $id : null),
            ],
            'to_be_concerned_hazard' => ['nullable', 'string', 'max:255'],
            'gr' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
            'nomor_gr_valid' => ['nullable', 'string', 'max:255'],
            'kategori_gr_valid_kpi' => ['nullable', 'string', 'max:255'],
            'blindspot_terlapor_bc' => ['nullable', 'string'],
            'kronologi_singkat' => ['nullable', 'string'],
            'rootcause_aktual' => $this->optionalIn($rootcause, 255),
            'detail_rootcause_aktual' => ['nullable', 'string'],
            'sid_pekerja_terlibat' => ['nullable', 'string', 'max:255'],
            'sid_pengawas_aktual' => ['nullable', 'string', 'max:255'],
            'tindakan_perbaikan_aktual' => ['nullable', 'string'],
            'no_item_pspp' => ['nullable', 'string', 'max:255'],
            'kategori_gr' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, ?string>
     */
    public function attributesPayload(): array
    {
        $validated = $this->validated();
        $out = [];
        foreach (ControlRoomTbcValidation::FILLABLE_FIELDS as $key) {
            $value = $validated[$key] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $out[$key] = $value !== '' && $value !== null ? $value : null;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tasklist.required' => 'Tasklist wajib diisi (kunci unik, id laporan SAP).',
            'tasklist.unique' => 'Tasklist sudah ada. Ubah baris yang ada atau pakai Tasklist lain.',
        ];
    }

    /**
     * @return list<string>
     */
    private function configList(string $key): array
    {
        $raw = config('control-room.'.$key, []);
        if (! is_array($raw)) {
            return [];
        }

        $options = [];
        foreach ($raw as $option) {
            $label = trim((string) $option);
            if ($label !== '') {
                $options[] = $label;
            }
        }

        return $options;
    }

    /**
     * @param  list<string>  $options
     * @return list<mixed>
     */
    private function optionalIn(array $options, int $max): array
    {
        if ($options === []) {
            return ['nullable', 'string', 'max:'.$max];
        }

        return ['nullable', 'string', 'max:'.$max, Rule::in($options)];
    }
}
