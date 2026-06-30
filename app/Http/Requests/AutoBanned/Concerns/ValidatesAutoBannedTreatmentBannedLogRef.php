<?php

declare(strict_types=1);

namespace App\Http\Requests\AutoBanned\Concerns;

use App\Services\AutoBanned\AutoBannedTreatmentService;

trait ValidatesAutoBannedTreatmentBannedLogRef
{
    /**
     * @return array<string, mixed>
     */
    protected function bannedLogRefRules(): array
    {
        return [
            'banned_log_ref' => [
                'required',
                'string',
                'regex:/^(daily|weekly)-\d+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $sid = strtoupper(trim((string) $this->input('sid', '')));
                    $bannedLogRef = trim((string) $value);

                    if ($sid === '' || $bannedLogRef === '') {
                        return;
                    }

                    /** @var AutoBannedTreatmentService $treatmentService */
                    $treatmentService = app(AutoBannedTreatmentService::class);

                    if (! $treatmentService->isValidTreatmentBannedLogRef($sid, $bannedLogRef)) {
                        $fail('Riwayat banned tidak valid untuk SID ini atau sudah pernah diajukan.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function bannedLogRefMessages(): array
    {
        return [
            'banned_log_ref.required' => 'Pilih riwayat banned yang terkait.',
            'banned_log_ref.regex' => 'Riwayat banned tidak valid.',
        ];
    }
}
