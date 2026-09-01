<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Actions\Isc\IscPostEventReportAction;
use App\Http\Controllers\Controller;
use App\Services\Isc\IscSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

final class IscPostEventController extends Controller
{
    public function __construct(
        private readonly IscPostEventReportAction $report,
    ) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $demo = ! IscSchema::eventsReady() || $request->query('source') === 'demo';

        return view('isc.post-event.index', [
            'report' => $this->report->execute($from, $to, $demo),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function json(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $demo = ! IscSchema::eventsReady() || $request->query('source') === 'demo';

        return response()->json($this->report->execute($from, $to, $demo));
    }

    /**
     * @return array{0:string,1:string}
     */
    private function range(Request $request): array
    {
        $tz = (string) config('app.timezone');
        $to = (string) ($request->query('to') ?: Carbon::now($tz)->toDateString());
        $from = (string) ($request->query('from') ?: Carbon::parse($to, $tz)->subDays(6)->toDateString());

        return [$from, $to];
    }
}
