<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Services\SportEvaluation\NutritionEvaluationService;
use Illuminate\View\View;

/**
 * Dashboard Evaluasi Nutrisi — Fase 1 (alert + KPI dari BeWell read-only).
 */
class NutritionEvaluationController extends Controller
{
    public function __construct(
        private readonly NutritionEvaluationService $nutrition,
    ) {}

    public function index(): View
    {
        $data = $this->nutrition->dashboard();

        return view('evaluasi-well.nutrition.index', $data);
    }
}
