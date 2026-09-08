<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\Attendance;
use App\Services\ControlRoom\AttendanceFormRecorder;
use App\Services\ControlRoom\ControlRoomDutyRosterService;
use App\Services\ControlRoom\Reference\PersonnelReader;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'mode' => (string) $this->input('mode') === AttendanceFormRecorder::MODE_PENGGANTI
                ? AttendanceFormRecorder::MODE_PENGGANTI
                : AttendanceFormRecorder::MODE_SCHEDULED,
            'site' => ($site = strtoupper(trim((string) $this->input('site')))) !== '' ? $site : null,
            'replacing_plan_id' => $this->input('replacing_plan_id') ?: null,
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
            'mode' => ['required', Rule::in([AttendanceFormRecorder::MODE_SCHEDULED, AttendanceFormRecorder::MODE_PENGGANTI])],
            'site' => ['nullable', 'string', Rule::in(array_column(ControlRoomSiteCode::cases(), 'value'))],
            'replacing_plan_id' => [
                'required_if:mode,'.AttendanceFormRecorder::MODE_PENGGANTI,
                'nullable',
                'integer',
            ],
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
            'replacing_plan_id.required_if' => 'Pilih personil terjadwal yang Anda gantikan.',
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
            $dutyDate = $roster->dutyDate();
            $site = ControlRoomSiteCode::tryFrom((string) $this->input('site', ''));

            if ($this->input('mode') === AttendanceFormRecorder::MODE_PENGGANTI) {
                $plan = $roster->findPlanForDuty((int) $this->input('replacing_plan_id', 0), $dutyDate, $site);
                if ($plan === null) {
                    $validator->errors()->add('replacing_plan_id', 'Pilih personil terjadwal yang Anda gantikan hari ini.');

                    return;
                }

                if (strtoupper((string) $plan->personnel_source_key) === $sid) {
                    $validator->errors()->add('sid', 'SID ini sudah dijadwalkan. Gunakan absen biasa, bukan tombol pengganti.');
                }

                $alreadyPresent = Attendance::query()
                    ->where('schedule_plan_id', $plan->id)
                    ->where('status', Attendance::STATUS_SESUAI_JADWAL)
                    ->exists();

                if ($alreadyPresent) {
                    $validator->errors()->add(
                        'replacing_plan_id',
                        'Personil yang dipilih sudah absen sesuai jadwal, tidak perlu diganti.',
                    );
                }

                return;
            }

            if ($roster->findDuty($sid, $dutyDate, $site) === null) {
                $validator->errors()->add('sid', ControlRoomDutyRosterService::NOT_SCHEDULED_MESSAGE);
            }
        });
    }

    public function attendancePayload(): array
    {
        $data = $this->validated();

        return [
            'sid' => (string) $data['sid'],
            'tanggal' => (string) $data['tanggal'],
            'site' => isset($data['site']) && $data['site'] !== '' ? (string) $data['site'] : null,
            'mode' => (string) $data['mode'],
            'replacing_plan_id' => isset($data['replacing_plan_id']) ? (int) $data['replacing_plan_id'] : null,
        ];
    }
}
