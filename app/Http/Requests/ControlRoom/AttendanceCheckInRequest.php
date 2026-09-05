<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * plan-OCR.md T3.3. Identitas personil dipilih manual dari dropdown
 * pencarian (ketik SID/nama) di form check-in — TIDAK lagi wajib diambil
 * dari Auth::user()->personnel_source_key. Kolom itu tetap dipakai sebagai
 * nilai default/pre-fill kalau sudah pernah diisi, tapi bukan satu-satunya
 * sumber (lihat AttendanceController::checkIn()).
 */
final class AttendanceCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Input personil dari <datalist> berbentuk "Nama (SID)" (lihat view
     * check-in.blade.php — trik ini dipakai karena <datalist> native HTML
     * tidak konsisten menampilkan label yang beda dari value di semua
     * browser). Kalau tidak ada pola "(...)" di akhir — mis. user langsung
     * mengetik SID polos — pakai apa adanya.
     */
    protected function prepareForValidation(): void
    {
        $raw = trim((string) $this->input('personnel_source_key'));

        if (preg_match('/\(([^)]+)\)\s*$/', $raw, $matches) === 1) {
            $raw = trim($matches[1]);
        }

        $this->merge(['personnel_source_key' => $raw]);
    }

    public function rules(): array
    {
        return [
            'site_code' => ['required', Rule::in(array_column(ControlRoomSiteCode::cases(), 'value'))],
            'personnel_source_key' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in([
                Attendance::STATUS_SESUAI_JADWAL,
                Attendance::STATUS_MENGGANTIKAN,
                Attendance::STATUS_TIDAK_HADIR,
            ])],
            'replacing_source_key' => ['required_if:status,'.Attendance::STATUS_MENGGANTIKAN, 'nullable', 'string', 'max:100'],
            'absence_reason' => ['required_if:status,'.Attendance::STATUS_TIDAK_HADIR, 'nullable', 'string', 'max:2000'],
        ];
    }
}
