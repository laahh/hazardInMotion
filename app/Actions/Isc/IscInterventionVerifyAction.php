<?php

declare(strict_types=1);

namespace App\Actions\Isc;

use App\Models\Isc\IscIntervention;
use App\Models\Isc\IscInterventionVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class IscInterventionVerifyAction
{
    /**
     * @param  array{result:string,notes?:?string}  $payload
     */
    public function execute(User $verifier, IscIntervention $intervention, array $payload): IscIntervention
    {
        if ((int) $verifier->id === (int) $intervention->pic_user_id) {
            throw new RuntimeException('PIC tidak boleh memverifikasi intervensi miliknya sendiri.');
        }
        if ($intervention->verification()->exists()) {
            throw new RuntimeException('Intervensi ini sudah diverifikasi.');
        }

        return DB::transaction(function () use ($verifier, $intervention, $payload): IscIntervention {
            IscInterventionVerification::query()->create([
                'intervention_id' => $intervention->id,
                'verifier_user_id' => $verifier->id,
                'result' => $payload['result'],
                'notes' => $payload['notes'] ?? null,
            ]);
            $intervention->status = $payload['result'] === 'verified' ? 'verified' : 'rejected';
            $intervention->save();

            $event = $intervention->event;
            if ($event && $payload['result'] === 'verified') {
                $event->status = 'closed';
                if ($event->exited_at === null) {
                    $event->exited_at = now();
                    $event->duration_seconds = $event->durationSecondsNow();
                }
                $event->save();
            }

            return $intervention->fresh(['verification', 'event']);
        });
    }
}
