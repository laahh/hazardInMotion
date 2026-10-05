<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SportEvaluation\SportEvaluationDashboardController;
use App\Services\OhsScoreCard\ScoreCardParameterMatrix;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
        private readonly ScoreCardParameterMatrix $scoreCard,
    ) {}

    public function index(Request $request): View
    {
        // Score Card SENGAJA TIDAK dirakit di sini. Sumbernya 22 tabel di
        // database jauh, dan merakitnya saat cache dingin memakan beberapa
        // detik -- itu akan menahan seluruh dashboard. Kartunya memanggil
        // scoreCardParameter() sendiri setelah halaman tampil.
        return view('ohs-score-card.dashboard', $this->dashboard->buildIndexData(
            $this->filtersFromRequest($request)
        ));
    }

    /** Matriks Score Card untuk satu bulan, dipakai saat filter bulan diganti. */
    public function scoreCardParameter(Request $request): JsonResponse
    {
        return response()->json($this->scoreCard->bangun($this->bulanDari($request)));
    }

    /**
     * Bulan dari query string. Nilai di luar 1-12 -- termasuk teks dan nol --
     * diperlakukan sebagai "semua bulan", bukan ditolak dengan galat, supaya
     * tautan lama tidak mematahkan dashboard.
     */
    private function bulanDari(Request $request): ?int
    {
        $raw = $request->input('bulan', $request->input('month', ''));

        if (!is_scalar($raw) || !preg_match('/^\d{1,2}$/', (string) $raw)) {
            return null;
        }

        $n = (int) $raw;

        return $n >= 1 && $n <= 12 ? $n : null;
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
