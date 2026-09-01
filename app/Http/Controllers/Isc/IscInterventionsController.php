<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Actions\Isc\IscInterventionStoreAction;
use App\Actions\Isc\IscInterventionVerifyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Isc\IscInterventionEvidenceStoreRequest;
use App\Http\Requests\Isc\IscInterventionStoreRequest;
use App\Http\Requests\Isc\IscInterventionVerifyRequest;
use App\Models\Isc\IscBoundaryEvent;
use App\Models\Isc\IscIntervention;
use App\Services\Isc\IscPobDemoDataset;
use App\Services\Isc\IscSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

final class IscInterventionsController extends Controller
{
    public function __construct(
        private readonly IscInterventionStoreAction $storeAction,
        private readonly IscInterventionVerifyAction $verifyAction,
        private readonly IscPobDemoDataset $demo,
    ) {}

    public function index(Request $request): View
    {
        $ready = IscSchema::eventsReady();
        $status = (string) $request->query('status', '');
        $eventId = $request->integer('event') ?: null;
        $demo = ! $ready || $request->query('source') === 'demo';

        if ($demo && ! $ready) {
            $events = collect($this->demo->events());
            if ($status !== '') {
                $events = $events->where('status', $status)->values();
            } elseif ($eventId === null) {
                $events = $events->whereIn('status', ['open', 'in_progress'])->values();
            }
            if ($eventId !== null) {
                $events = $events->where('id', $eventId)->values();
            }

            return view('isc.interventions.index', [
                'ready' => false,
                'demo' => true,
                'events' => $events,
                'status' => $status,
                'eventId' => $eventId,
                'canCreate' => false,
            ]);
        }

        $query = IscBoundaryEvent::query()
            ->with(['latestIntervention.pic', 'latestIntervention.verification'])
            ->orderByDesc('entered_at');
        if ($status !== '' && in_array($status, ['open', 'in_progress', 'closed'], true)) {
            $query->where('status', $status);
        } elseif ($eventId === null) {
            $query->whereIn('status', ['open', 'in_progress']);
        }
        if ($eventId !== null) {
            $query->where('id', $eventId);
        }

        return view('isc.interventions.index', [
            'ready' => true,
            'demo' => false,
            'events' => $query->paginate(30)->withQueryString(),
            'status' => $status,
            'eventId' => $eventId,
            'canCreate' => $request->user()?->can('create', IscIntervention::class) ?? false,
        ]);
    }

    public function show(Request $request, string $event): View
    {
        $ready = IscSchema::eventsReady();
        if (! $ready) {
            $row = collect($this->demo->events())->firstWhere('id', (int) $event);
            abort_if($row === null, 404);

            return view('isc.interventions.show', [
                'demo' => true,
                'event' => $row,
                'canCreate' => false,
            ]);
        }

        $model = IscBoundaryEvent::query()
            ->with(['interventions.pic', 'interventions.evidences', 'interventions.verification.verifier'])
            ->findOrFail((int) $event);

        return view('isc.interventions.show', [
            'demo' => false,
            'event' => $model,
            'canCreate' => $request->user()?->can('create', IscIntervention::class) ?? false,
        ]);
    }

    public function store(IscInterventionStoreRequest $request): RedirectResponse
    {
        $files = $request->file('evidences', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }
        $intervention = $this->storeAction->execute($request->user(), $request->validated(), array_values($files));

        return redirect()->route('isc.interventions.show', $intervention->event_id)->with('success', 'Intervensi disimpan.');
    }

    public function storeEvidence(IscInterventionEvidenceStoreRequest $request, IscIntervention $intervention): RedirectResponse
    {
        $this->authorize('uploadEvidence', $intervention);
        $files = $request->file('evidences', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }
        $this->storeAction->storeFiles($intervention, $request->user(), array_values($files));

        return redirect()->route('isc.interventions.show', $intervention->event_id)->with('success', 'Evidence diunggah.');
    }

    public function verify(IscInterventionVerifyRequest $request, IscIntervention $intervention): RedirectResponse
    {
        $this->authorize('verify', $intervention);
        try {
            $this->verifyAction->execute($request->user(), $intervention, $request->validated());
        } catch (RuntimeException $e) {
            return redirect()->route('isc.interventions.show', $intervention->event_id)->with('error', $e->getMessage());
        }

        return redirect()->route('isc.interventions.show', $intervention->event_id)->with('success', 'Verifikasi tersimpan.');
    }
}
