<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Services\DmsMonitoring\DmsAlertMonitoringService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /pra-operasi/dashboard — monitoring alert DMS L1/L2 (bukan Fatigue Test
 * seperti dashboard utama Pra Operasi). Lihat DmsAlertMonitoringService untuk
 * metodologi setiap panel.
 */
final class DmsAlertMonitoringController extends Controller
{
    public function __construct(
        private readonly DmsAlertMonitoringService $service,
    ) {}

    public function index(Request $request): View
    {
        return view('pra-operasi.dms-alert-monitoring', $this->service->dashboard($request));
    }

    /**
     * Buat sampel QA baru untuk periode ini (ukuran sampel dari rumus Slovin).
     */
    public function generateQaSample(Request $request): RedirectResponse
    {
        $start = (string) $request->input('start', '');
        $end = (string) $request->input('end', '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1) {
            return back()->with('error', 'Periode tidak valid.');
        }

        $result = $this->service->generateQaSample($start, $end);

        return back()->with('status', "Sampel QA dibuat: {$result['generated']} baru (target {$result['target_sample_size']} dari populasi {$result['population']} alert yang di-dismiss L1).");
    }

    /**
     * Simpan verdict audit ulang satu sampel QA.
     */
    public function submitQaVerdict(Request $request): JsonResponse
    {
        $sampleId = (int) $request->input('sample_id');
        $verdict = (string) $request->input('verdict', '');
        $catatan = $request->filled('catatan') ? mb_substr((string) $request->input('catatan'), 0, 1000) : null;

        if ($sampleId <= 0 || $verdict === '') {
            return response()->json(['message' => 'Data tidak lengkap.'], 422);
        }

        $ok = $this->service->submitQaVerdict($sampleId, $verdict, $catatan, $request->user()?->id);

        if (! $ok) {
            return response()->json(['message' => 'Gagal menyimpan verdict.'], 422);
        }

        return response()->json(['message' => 'Tersimpan.']);
    }
}
