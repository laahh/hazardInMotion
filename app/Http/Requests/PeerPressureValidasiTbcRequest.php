<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PeerPressureValidasiTbcRequest extends FormRequest
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
            'tasklist' => ['nullable', 'string'],
            'to_be_concerned_hazard' => ['nullable', 'string'],
            'gr' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
            'blindspot_terlapor_bc' => ['nullable', 'string'],
            'no_item_pspp' => ['nullable', 'string', 'max:255'],
            'kategori_gr' => ['nullable', 'string', 'max:255'],
            'sid_pekerja_terlibat' => ['nullable', 'string', 'max:255'],
            'nama_pekerja_terlibat' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesPayload(): array
    {
        $v = $this->validated();
        $out = [];
        foreach (self::FIELD_KEYS as $key) {
            $val = $v[$key] ?? null;
            if (is_string($val)) {
                $val = trim($val);
            }
            $out[$key] = $val !== '' && $val !== null ? $val : null;
        }

        return $out;
    }

    /** Urutan kolom mengikuti header sumber Validasi TBC. */
    public const FIELD_KEYS = [
        'tasklist',
        'to_be_concerned_hazard',
        'gr',
        'catatan',
        'blindspot_terlapor_bc',
        'no_item_pspp',
        'kategori_gr',
        'sid_pekerja_terlibat',
        'nama_pekerja_terlibat',
    ];
}
