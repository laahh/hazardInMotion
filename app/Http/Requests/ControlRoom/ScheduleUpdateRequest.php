<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\Reference\PersonnelReader;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ScheduleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Input personil dari <datalist> berbentuk "Nama (SID)" (pola yang sama
     * dengan check-in.blade.php/ScheduleBulkRequest) — ekstrak SID-nya
     * sebelum divalidasi.
     */
    protected function prepareForValidation(): void
    {
        $raw = trim((string) $this->input('personnel_source_key', ''));

        if ($raw === '') {
            return;
        }

        if (preg_match('/\(([^)]+)\)\s*$/', $raw, $matches) === 1) {
            $raw = trim($matches[1]);
        }

        $this->merge(['personnel_source_key' => strtoupper($raw)]);
    }

    public function rules(): array
    {
        $planId = (int) $this->route('schedule');
        $plan = $planId > 0 ? SchedulePlan::query()->find($planId) : null;
        $isLocked = $plan instanceof SchedulePlan && $plan->isLocked();

        return [
            'site_code' => ['sometimes', Rule::in(array_column(ControlRoomSiteCode::cases(), 'value'))],
            'date' => ['sometimes', 'date'],
            'shift_code' => ['sometimes', Rule::in(array_column(ControlRoomShiftCode::cases(), 'value'))],
            'personnel_source_key' => ['sometimes', 'string', 'max:100'],
            'reason' => [$isLocked ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator): void {
            if ($this->filled('personnel_source_key')) {
                $reader = app(PersonnelReader::class);
                if (! $reader->existsAndActive((string) $this->input('personnel_source_key'))) {
                    $validator->errors()->add('personnel_source_key', 'Personil tidak ditemukan/tidak aktif di sumber data personil.');
                }
            }

            $this->rejectDuplicateSlot($validator);
        });
    }

    private function rejectDuplicateSlot(ValidatorContract $validator): void
    {
        $planId = (int) $this->route('schedule');
        $plan = $planId > 0 ? SchedulePlan::query()->find($planId) : null;
        if (! $plan instanceof SchedulePlan) {
            return;
        }

        $sid = strtoupper(trim((string) ($this->input('personnel_source_key') ?: $plan->personnel_source_key)));
        $shift = (string) ($this->input('shift_code') ?: $plan->shift_code->value);
        $date = (string) ($this->input('date') ?: $plan->date->toDateString());
        $site = (string) ($this->input('site_code') ?: $plan->site_code->value);

        $clash = SchedulePlan::query()
            ->where('site_code', $site)
            ->whereDate('date', $date)
            ->where('shift_code', $shift)
            ->whereRaw('upper(personnel_source_key) = ?', [$sid])
            ->where('id', '!=', $plan->id)
            ->exists();

        if ($clash) {
            $validator->errors()->add(
                'personnel_source_key',
                'Personil ini sudah dijadwalkan di tanggal dan shift yang sama.'
            );
        }
    }
}
