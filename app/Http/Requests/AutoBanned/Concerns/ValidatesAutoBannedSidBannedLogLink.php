<?php

declare(strict_types=1);

namespace App\Http\Requests\AutoBanned\Concerns;

use App\Enums\AutoBannedSidAutomationStatus;
use App\Models\AutoBannedUnbanRequest;
use App\Support\AutoBanned\AutoBannedSchema;
use Illuminate\Validation\Rule;

trait ValidatesAutoBannedSidBannedLogLink
{
    /**
     * @return array<string, mixed>
     */
    protected function sidBannedLogIdRules(): array
    {
        if (! AutoBannedSchema::hasSidBannedLogTable()) {
            return ['sid_banned_log_id' => ['nullable', 'integer']];
        }

        $sid = strtoupper(trim((string) $this->input('sid', '')));
        $requestedScrIds = AutoBannedUnbanRequest::requestedScrDailyBannedIdsForSid($sid);

        return [
            'sid_banned_log_id' => [
                'required',
                'integer',
                Rule::exists('sid_banned_log', 'id')->where(function ($query) use ($sid, $requestedScrIds): void {
                    $query->whereRaw('UPPER(TRIM(sid)) = ?', [$sid])
                        ->where('automation_status', AutoBannedSidAutomationStatus::Success->value);

                    if ($requestedScrIds !== []) {
                        $query->where(function ($inner) use ($requestedScrIds): void {
                            $inner->whereNull('scr_daily_banned_id')
                                ->orWhereNotIn('scr_daily_banned_id', $requestedScrIds);
                        });
                    }
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function sidBannedLogIdMessages(): array
    {
        return [
            'sid_banned_log_id.required' => 'Pilih riwayat banned yang terkait.',
            'sid_banned_log_id.exists' => 'Riwayat banned tidak valid untuk SID ini atau sudah pernah diajukan.',
        ];
    }
}
