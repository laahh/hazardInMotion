<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SportEvaluation\SportEvaluationDashboardController;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Dashboard OHS Score Card.
 *
 * View resources/views/ohs-score-card/dashboard.blade.php butuh payload SSR
 * yang sama dengan dashboard BeWell, jadi datanya diambil dari
 * SportEvaluationDashboardController::buildIndexData() daripada diduplikasi.
 */
final class OhsScoreCardDashboardController extends Controller
{
    private const FILTER_MAX_LENGTH = 180;

    public function __construct(
        private readonly SportEvaluationDashboardController $dashboard,
    ) {}

    public function index(Request $request): View
    {
        return view('ohs-score-card.dashboard', $this->dashboard->buildIndexData(
            $this->filtersFromRequest($request)
        ));
    }

    /**
     * Filter dashboard dari query string. Key digandakan (perusahaan/company,
     * division_group/division/divisi) mengikuti kontrak buildIndexData().
     *
     * @return array<string, string>
     */
    private function filtersFromRequest(Request $request): array
    {
        $site = $this->clamp($request->input('site', ''));
        $perusahaan = $this->clamp($request->input('perusahaan', $request->input('company', '')));
        $division = $this->clamp(
            $request->input('division_group', $request->input('division', $request->input('divisi', '')))
        );

        return [
            'site' => $site,
            'perusahaan' => $perusahaan,
            'company' => $perusahaan,
            'division_group' => $division,
            'division' => $division,
            'divisi' => $division,
        ];
    }

    private function clamp(mixed $value): string
    {
        return mb_substr(trim((string) $value), 0, self::FILTER_MAX_LENGTH);
    }
}
