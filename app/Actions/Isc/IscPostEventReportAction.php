<?php

declare(strict_types=1);

namespace App\Actions\Isc;

use App\Models\Isc\IscBoundaryEvent;
use App\Services\Isc\IscPobDemoDataset;
use App\Services\Isc\IscSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class IscPostEventReportAction
{
    public function __construct(
        private readonly IscPobDemoDataset $demo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $fromDate, string $toDateInclusive, bool $demo = false): array
    {
        if ($demo || ! IscSchema::eventsReady()) {
            $report = $this->demo->postEventReport($fromDate, $toDateInclusive);
            $report['ready'] = true;
            $report['demo'] = true;

            return $report;
        }

        $tz = (string) config('app.timezone');
        $from = Carbon::parse($fromDate, $tz)->startOfDay();
        $to = Carbon::parse($toDateInclusive, $tz)->endOfDay();
        $events = IscBoundaryEvent::query()->with(['interventions'])->whereBetween('entered_at', [$from, $to])->get();
        $durationSum = (int) $events->sum(fn (IscBoundaryEvent $e): int => $e->durationSecondsNow());
        $verified = $events->filter(
            fn (IscBoundaryEvent $e): bool => $e->interventions->contains(fn ($i): bool => $i->status === 'verified'),
        )->count();

        return [
            'ready' => true,
            'demo' => false,
            'from' => $fromDate,
            'to' => $toDateInclusive,
            'totals' => [
                'events' => $events->count(),
                'duration_seconds' => $durationSum,
                'open' => $events->where('status', 'open')->count(),
                'in_progress' => $events->where('status', 'in_progress')->count(),
                'closed' => $events->where('status', 'closed')->count(),
                'verified' => $verified,
                'repeat_people' => $this->repeatKeys($events)->count(),
            ],
            'by_status' => $this->groupCount($events, 'status'),
            'by_site' => $this->groupCount($events, 'iupk_site'),
            'by_company' => $this->groupCount($events, 'company'),
            'repeat_offenders' => $this->repeatKeys($events)->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, IscBoundaryEvent>  $events
     * @return list<array{key:string,count:int}>
     */
    private function groupCount(Collection $events, string $column): array
    {
        return $events
            ->groupBy(fn (IscBoundaryEvent $e): string => (string) ($e->{$column} ?: '—'))
            ->map(fn (Collection $group, string $key): array => ['key' => $key, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, IscBoundaryEvent>  $events
     */
    private function repeatKeys(Collection $events): Collection
    {
        return $events
            ->groupBy('person_key')
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(function (Collection $group): array {
                /** @var IscBoundaryEvent $first */
                $first = $group->first();

                return [
                    'person_key' => $first->person_key,
                    'name' => $first->name,
                    'sid' => $first->sid,
                    'company' => $first->company,
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->values();
    }
}
