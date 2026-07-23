<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Halaman aktivitas — desain layout dulu (tanpa query DB).
 */
class SportActivitiesController extends Controller
{
    public function index(): View
    {
        return view('evaluasi-well.activities.index');
    }

    public function data(): JsonResponse
    {
        return response()->json([
            'draw' => 0,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ]);
    }
}
