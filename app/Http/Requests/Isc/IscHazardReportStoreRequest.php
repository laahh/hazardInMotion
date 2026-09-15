<?php

declare(strict_types=1);

namespace App\Http\Requests\Isc;

use App\Models\Isc\IscIntervention;
use Illuminate\Foundation\Http\FormRequest;

final class IscHazardReportStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        // Task dummy (id >= 9000) atau flag demo: izinkan uji form tanpa role PIC.
        $eventId = (int) $this->input('event_id', 0);
        if ($this->boolean('demo') || $eventId >= 9000) {
            return true;
        }

        return $user->can('create', IscIntervention::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'integer'],
            'demo' => ['nullable', 'boolean'],
            'username' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:255'],
            'perusahaan' => ['nullable', 'string', 'max:255'],
            'pic_sid' => ['nullable', 'string', 'max:64'],
            'pic_npk' => ['nullable', 'string', 'max:64'],
            'pic_nama' => ['nullable', 'string', 'max:255'],
            'pic_jabatan' => ['nullable', 'string', 'max:255'],
            'tools_pengamatan' => ['nullable', 'string', 'max:100'],
            'site' => ['nullable', 'string', 'max:64'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'detail_lokasi' => ['nullable', 'string', 'max:255'],
            'keterangan_lokasi' => ['nullable', 'string', 'max:2000'],
            'sid_pelapor' => ['required', 'string', 'max:64'],
            'npk_pelapor' => ['nullable', 'string', 'max:64'],
            'nama_pelapor' => ['nullable', 'string', 'max:255'],
            'jabatan_pelapor' => ['nullable', 'string', 'max:255'],
            'area_pja_bc' => ['nullable', 'string', 'max:255'],
            'area_pja_mitra' => ['nullable', 'string', 'max:255'],
            'foto' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'is_observasi_area_kritis' => ['nullable', 'boolean'],
            'ketidaksesuaian' => ['nullable', 'string', 'max:255'],
            'sub_ketidaksesuaian' => ['nullable', 'string', 'max:255'],
            'quick_action' => ['nullable', 'string', 'max:255'],
            'deskripsi_temuan' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_observasi_area_kritis')) {
            $this->merge([
                'is_observasi_area_kritis' => filter_var(
                    $this->input('is_observasi_area_kritis'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ) ?? false,
            ]);
        }

        if ($this->filled('sid_pelapor')) {
            $this->merge(['sid_pelapor' => strtoupper(trim((string) $this->input('sid_pelapor')))]);
        }
        if ($this->filled('pic_sid')) {
            $this->merge(['pic_sid' => strtoupper(trim((string) $this->input('pic_sid')))]);
        }
        if (! $this->filled('username')) {
            $this->merge(['username' => null]);
        }
        if (! $this->filled('password')) {
            $this->merge(['password' => null]);
        }
    }
}
