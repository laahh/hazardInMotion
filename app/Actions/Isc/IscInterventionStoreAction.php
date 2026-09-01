<?php

declare(strict_types=1);

namespace App\Actions\Isc;

use App\Models\Isc\IscBoundaryEvent;
use App\Models\Isc\IscIntervention;
use App\Models\Isc\IscInterventionEvidence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class IscInterventionStoreAction
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<UploadedFile>  $files
     */
    public function execute(User $pic, array $payload, array $files = []): IscIntervention
    {
        return DB::transaction(function () use ($pic, $payload, $files): IscIntervention {
            $event = IscBoundaryEvent::query()->findOrFail((int) $payload['event_id']);
            $intervention = IscIntervention::query()->create([
                'event_id' => $event->id,
                'pic_user_id' => $pic->id,
                'type' => (string) $payload['type'],
                'notes' => $payload['notes'] ?? null,
                'status' => 'submitted',
            ]);
            if ($event->status === 'open') {
                $event->status = 'in_progress';
                $event->save();
            }
            $this->storeFiles($intervention, $pic, $files);

            return $intervention->load(['evidences', 'event']);
        });
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function storeFiles(IscIntervention $intervention, User $uploader, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $file->store('isc-evidence/'.$intervention->id, 'public');
            IscInterventionEvidence::query()->create([
                'intervention_id' => $intervention->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'uploaded_by' => $uploader->id,
            ]);
        }
    }
}
