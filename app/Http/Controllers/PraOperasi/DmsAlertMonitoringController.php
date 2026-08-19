<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Services\Dms\DmsDashboardOverviewService;
use App\Services\DmsMonitoring\DmsAlertMonitoringService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /pra-operasi/dashboard — dashboard WowDash CRM untuk monitoring alert DMS.
 * Data dari bcsid.mv_dms_alert via DmsDashboardOverviewService.
 */
final class DmsAlertMonitoringController extends Controller
{
    public function __construct(
        private readonly DmsAlertMonitoringService $service,
        private readonly DmsDashboardOverviewService $overview,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->readDateFilters($request);
        $payload = $this->overview->dashboard($filters['start'], $filters['end']);
        return view('pra-operasi.dms-alert-monitoring', $payload);
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

    /**
     * @return array{start:string, end:string}
     */
    private function readDateFilters(Request $request): array
    {
        $read = static fn (mixed $v): string => is_string($v) ? mb_substr(trim($v), 0, 10) : '';
        $tz = (string) config('app.timezone');
        $today = now($tz)->toDateString();

        $end = $read($request->query('end', ''));
        if ($end === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1) {
            $end = $today;
        }

        $start = $read($request->query('start', ''));
        if ($start === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1) {
            $start = now($tz)->subDays(6)->toDateString();
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return ['start' => $start, 'end' => $end];
    }
}
