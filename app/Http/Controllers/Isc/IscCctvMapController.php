<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Http\Controllers\Controller;
use App\Services\Isc\IscCctvExcelService;
use App\Services\Isc\IscCctvMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IscCctvMapController extends Controller
{
    public function __construct(
        private readonly IscCctvMapService $cctv,
        private readonly IscCctvExcelService $excel,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $demo = $request->query('source') === 'demo';

        return response()->json([
            'success' => true,
            ...$this->cctv->payload($demo),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $demo = $request->query('source') === 'demo';
        $site = $request->query('site');

        return $this->excel->download($demo, is_string($site) ? $site : null);
    }
}
