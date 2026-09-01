<?php

declare(strict_types=1);

namespace App\Actions\Isc;

use App\Events\Isc\IscHazardEntered;
use App\Models\Isc\IscBoundaryEvent;
use App\Models\Isc\IscDetectionRule;
use App\Services\Isc\IscPobSnapshotService;
use App\Services\Isc\IscSchema;
use Illuminate\Support\Carbon;
use Throwable;

final class IscDetectHazardEntryAction
{
    public const RULE_CODE = 'hazard-entry';

    public function __construct(
        private readonly IscPobSnapshotService $snapshot,
    ) {}

    /**
     * @return array{created:int,closed:int,skipped:bool,message:?string}
     */
    public function execute(bool $demo = false): array
    {
        if (! IscSchema::eventsReady()) {
            return [
                'created' => 0,
                'closed' => 0,
                'skipped' => true,
                'message' => 'Tabel isc_boundary_events belum ada. Jalankan migration setelah konfirmasi.',
            ];
        }

        $pack = $this->snapshot->snapshot(true, $demo);
        $people = is_array($pack['people'] ?? null) ? $pack['people'] : [];
        $ruleCode = $this->activeRuleCode();

        $open = IscBoundaryEvent::query()->whereIn('status', ['open', 'in_progress'])->get();
        $openByKey = [];
        foreach ($open as $event) {
            $openByKey[$event->person_key.'|'.(string) $event->hazard_boundary_id] = $event;
        }

        $stillOpen = [];
        $created = 0;
        foreach ($people as $person) {
            if (($person['presence'] ?? null) !== IscPobClassifyAction::PRESENCE_IN) {
                continue;
            }
            if (($person['safety'] ?? null) !== IscPobClassifyAction::SAFETY_UNSAFE) {
                continue;
            }
            $personKey = (string) ($person['key'] ?? '');
            $hazardId = (string) ($person['hazard_boundary_id'] ?? '');
            if ($personKey === '') {
                continue;
            }
            $pair = $personKey.'|'.$hazardId;
            $stillOpen[$pair] = true;
            if (isset($openByKey[$pair])) {
                $existing = $openByKey[$pair];
                $existing->lat = (float) $person['lat'];
                $existing->lng = (float) $person['lng'];
                $existing->save();
                continue;
            }

            $row = IscBoundaryEvent::query()->create([
                'person_key' => $personKey,
                'sid' => $person['sid'] ?? null,
                'name' => (string) ($person['name'] ?? $personKey),
                'company' => $person['company'] ?? null,
                'job_title' => $person['job_title'] ?? null,
                'lat' => (float) $person['lat'],
                'lng' => (float) $person['lng'],
                'iupk_site' => $person['iupk_site'] ?? null,
                'hazard_boundary_id' => $hazardId !== '' ? $hazardId : null,
                'hazard_name' => $person['hazard_name'] ?? null,
                'entered_at' => now(),
                'status' => 'open',
                'rule_code' => $ruleCode,
            ]);
            $created++;
            try {
                IscHazardEntered::dispatch($row);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $closed = 0;
        foreach ($open as $event) {
            $pair = $event->person_key.'|'.(string) $event->hazard_boundary_id;
            if (isset($stillOpen[$pair])) {
                continue;
            }
            $exited = now();
            $event->exited_at = $exited;
            $event->duration_seconds = (int) Carbon::parse($event->entered_at)->diffInSeconds($exited);
            if ($event->status === 'open') {
                $event->status = 'closed';
            }
            $event->save();
            $closed++;
        }

        return ['created' => $created, 'closed' => $closed, 'skipped' => false, 'message' => null];
    }

    private function activeRuleCode(): string
    {
        if (! IscSchema::rulesReady()) {
            return self::RULE_CODE;
        }
        $rule = IscDetectionRule::query()->where('is_active', true)->orderBy('id')->first();

        return $rule?->code ?: self::RULE_CODE;
    }
}
