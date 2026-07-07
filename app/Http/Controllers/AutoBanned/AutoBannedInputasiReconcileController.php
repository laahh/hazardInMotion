<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoBanned;

use App\Http\Controllers\Controller;
use App\Http\Controllers\AutoBanned\Concerns\ProvidesAutoBannedLayout;
use App\Http\Requests\AutoBanned\AutoBannedInputasiReconcileRequest;
use App\Services\AutoBanned\AutoBannedLogReconcileService;
use App\Services\AutoBanned\AutoBannedPipelineGapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AutoBannedInputasiReconcileController extends Controller
{
    use ProvidesAutoBannedLayout;

    public function __construct(
        private readonly AutoBannedPipelineGapService $pipelineGapService,
        private readonly AutoBannedLogReconcileService $logReconcileService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->pipelineGapService->resolveFilters($request);
        $tableAvailable = $this->pipelineGapService->bannedLogTableAvailable();
        $gapRows = $tableAvailable
            ? $this->pipelineGapService->gapBanLogs($filters)
            : collect();

        return view('AutoBanned.inputasi.reconcile', [
            'navActive' => 'inputasi',
            'navItems' => $this->autoBannedNavItems(),
            'filters' => $filters,
            'filterOptions' => $this->pipelineGapService->filterOptions($filters),
            'gapRows' => $gapRows,
            'tableAvailable' => $tableAvailable,
            'defaultAlasan' => AutoBannedLogReconcileService::DEFAULT_ALASAN,
            'defaultMinDaysOld' => AutoBannedPipelineGapService::DEFAULT_MIN_DAYS_OLD,
        ]);
    }

    public function store(AutoBannedInputasiReconcileRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $unbanCompletedAt = isset($validated['unban_completed_at']) && $validated['unban_completed_at'] !== ''
            ? Carbon::parse((string) $validated['unban_completed_at'])
            : null;

        $result = $this->logReconcileService->reconcileBanLogs(
            banLogIds: array_map('intval', $validated['ban_log_ids']),
            actor: $user,
            alasanPengajuan: (string) ($validated['alasan_pengajuan'] ?? ''),
            unbanCompletedAt: $unbanCompletedAt,
        );

        $redirect = redirect()
            ->route('auto-banned.inputasi.reconcile.index', array_filter([
                'min_days_old' => $request->input('min_days_old'),
                'site' => $request->input('site'),
                'sid' => $request->input('sid'),
                'q' => $request->input('q'),
            ], static fn ($value) => $value !== null && $value !== ''));

        if ($result['processed'] > 0) {
            $message = $result['processed'].' riwayat berhasil direkonsiliasi (request approved + log unban SUCCESS).';
            if ($result['skipped'] > 0) {
                $message .= ' '.$result['skipped'].' dilewati.';
            }

            return $redirect->with('success', $message);
        }

        $firstError = collect($result['errors'])->first() ?? 'Tidak ada data yang diproses.';

        return $redirect
            ->with('error', 'Rekonsiliasi gagal: '.$firstError)
            ->withInput();
    }
}
