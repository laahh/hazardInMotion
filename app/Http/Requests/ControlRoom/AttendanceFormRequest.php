<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Services\ControlRoom\ControlRoomDutyRosterService;
use App\Services\ControlRoom\Reference\PersonnelReader;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

final class AttendanceFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sid' => strtoupper(trim((string) $this->input('sid'))),
            'tanggal' => app(ControlRoomDutyRosterService::class)->dutyDate()->toDateString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sid' => ['required', 'string', 'max:100'],
            'tanggal' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subDays(31)->toDateString()],
            'bukti' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sid.required' => 'SID wajib diisi.',
            'tanggal.required' => 'Tanggal wajib diisi dari jadwal jaga.',
            'tanggal.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'tanggal.after_or_equal' => 'Tanggal terlalu lama. Pilih maksimal 31 hari ke belakang.',
            'bukti.required' => 'Unggah atau ambil foto bukti kehadiran.',
            'bukti.file' => 'Bukti harus berupa file.',
            'bukti.max' => 'Ukuran bukti maksimal 5 MB.',
            'bukti.mimes' => 'Bukti harus JPG, PNG, WEBP, atau PDF.',
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator): void {
            $sid = (string) $this->input('sid', '');
            if ($sid === '' || $validator->errors()->has('sid')) {
                return;
            }

            if (! app(PersonnelReader::class)->existsAndActive($sid)) {
                $validator->errors()->add('sid', 'SID tidak ditemukan atau personil tidak aktif.');

                return;
            }

            $roster = app(ControlRoomDutyRosterService::class);
            if ($roster->findDuty($sid, $roster->dutyDate()) === null) {
                $validator->errors()->add('sid', ControlRoomDutyRosterService::NOT_SCHEDULED_MESSAGE);
            }
        });
    }
}
