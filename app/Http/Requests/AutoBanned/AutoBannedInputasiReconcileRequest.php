<?php

declare(strict_types=1);

namespace App\Http\Requests\AutoBanned;

use App\Enums\AutoBannedReconcileGapType;
use App\Enums\AutoBannedReconcileUnbanLogMode;
use App\Enums\AutoBannedSidAutomationStatus;
use App\Support\AutoBanned\AutoBannedSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutoBannedInputasiReconcileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $gapType = AutoBannedReconcileGapType::tryFrom(trim((string) $this->input('gap_type', '')))
            ?? AutoBannedReconcileGapType::NoRequest;

        $banLogRule = ['required', 'integer', 'min:1'];
        $banLogTable = $gapType->isWeekly() ? 'sid_banned_log_weekly' : 'sid_banned_log';

        if ($gapType->isWeekly()) {
            if (AutoBannedSchema::hasSidBannedLogWeeklyTable()) {
                $banLogRule[] = Rule::exists($banLogTable, 'id')->where(
                    static fn ($query) => $query->whereIn(
                        'automation_status',
                        AutoBannedSidAutomationStatus::reconcileEligibleValues(),
                    ),
                );
            }
        } elseif (AutoBannedSchema::hasSidBannedLogTable()) {
            $banLogRule[] = Rule::exists($banLogTable, 'id')->where(
                static fn ($query) => $query->whereIn(
                    'automation_status',
                    AutoBannedSidAutomationStatus::reconcileEligibleValues(),
                ),
            );
        }

        return [
            'ban_log_ids' => ['required', 'array', 'min:1'],
            'ban_log_ids.*' => $banLogRule,
            'unban_log_mode' => ['required', 'string', Rule::in(array_column(AutoBannedReconcileUnbanLogMode::cases(), 'value'))],
            'gap_type' => ['required', 'string', Rule::in(array_column(AutoBannedReconcileGapType::cases(), 'value'))],
            'alasan_pengajuan' => ['nullable', 'string', 'max:2000'],
            'unban_completed_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ban_log_ids.required' => 'Pilih minimal satu riwayat banned.',
            'ban_log_ids.min' => 'Pilih minimal satu riwayat banned.',
            'ban_log_ids.*.exists' => 'Salah satu riwayat banned tidak valid atau bukan status SUCCESS/SKIPPED.',
            'unban_log_mode.required' => 'Pilih mode rekonsiliasi.',
            'unban_log_mode.in' => 'Mode rekonsiliasi tidak valid.',
            'gap_type.required' => 'Tab rekonsiliasi tidak valid.',
            'gap_type.in' => 'Tab rekonsiliasi tidak valid.',
            'unban_completed_at.date' => 'Waktu unban selesai tidak valid.',
        ];
    }
}
