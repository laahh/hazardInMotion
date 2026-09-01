<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Http\Controllers\Controller;
use App\Services\Isc\IscPostEventTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class IscPostEventTrackController extends Controller
{
    public function __construct(
        private readonly IscPostEventTrackService $tracks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $date = $this->date($request);
        $query = trim((string) $request->query('q', ''));
        $demo = $request->query('source', 'live') === 'demo';

        return response()->json([
            'success' => true,
            ...$this->tracks->roster($date, mb_substr($query, 0, 80), $demo),
        ]);
    }

    public function trail(Request $request): JsonResponse
    {
        $date = $this->date($request);
        $entity = $request->query('entity') === 'unit' ? 'unit' : 'person';
        $id = trim((string) $request->query('id', ''));
        if ($id === '') {
            return response()->json(['success' => false, 'message' => 'id wajib diisi.'], 422);
        }
        $demo = $request->query('source', 'live') === 'demo';

        return response()->json([
            'success' => true,
            ...$this->tracks->trail($entity, mb_substr($id, 0, 80), $date, $demo),
        ]);
    }

    private function date(Request $request): string
    {
        $raw = trim((string) $request->query('date', ''));
        $tz = (string) config('app.timezone');
        try {
            if ($raw !== '') {
                return Carbon::parse($raw, $tz)->toDateString();
            }
        } catch (\Throwable) {
        }

        return Carbon::now($tz)->toDateString();
    }
}
