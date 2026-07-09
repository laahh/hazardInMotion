<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoBanned;

use App\Http\Controllers\Controller;
use App\Http\Controllers\AutoBanned\Concerns\ProvidesAutoBannedLayout;
use App\Enums\AutoBannedReconcileGapType;
use App\Enums\AutoBannedReconcileUnbanLogMode;
use App\Http\Requests\AutoBanned\AutoBannedInputasiReconcileRequest;
use App\Services\AutoBanned\AutoBannedLogReconcileService;
use App\Services\AutoBanned\AutoBannedPipelineGapService;
use App\Services\AutoBanned\AutoBannedReconcileCrossScopeService;
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
        private readonly AutoBannedReconcileCrossScopeService $crossScopeService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->pipelineGapService->resolveFilters($request);
        $gapType = $this->pipelineGapService->resolveGapType($filters);
        $tableAvailable = $this->pipelineGapService->bannedLogTableAvailable($gapType);
        $gapRows = $tableAvailable
            ? $this->crossScopeService->attachCrossScopeTickets(
                $this->pipelineGapService->gapBanLogs($filters),
                $gapType,
            )
            : collect();

        $gapExplanations = ($tableAvailable && $gapRows->isEmpty() && ($filters['sid'] ?? '') !== '')
            ? $this->pipelineGapService->explainGapExclusionsForSid($filters, $gapType)
            : collect();

        return view('AutoBanned.inputasi.reconcile', [
            'navActive' => 'inputasi',
            'navItems' => $this->autoBannedNavItems(),
            'filters' => $filters,
            'gapType' => $gapType,
            'filterOptions' => $this->pipelineGapService->filterOptions($filters),
            'gapRows' => $gapRows,
            'gapExplanations' => $gapExplanations,
            'tableAvailable' => $tableAvailable,
            'defaultAlasan' => AutoBannedLogReconcileService::DEFAULT_ALASAN,
            'defaultMinDaysOld' => AutoBannedPipelineGapService::DEFAULT_MIN_DAYS_OLD,
            'unbanLogModes' => $gapType->allowedUnbanLogModes(),
            'gapTypes' => AutoBannedReconcileGapType::cases(),
        ]);
    }

    public function store(AutoBannedInputasiReconcileRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $gapType = AutoBannedReconcileGapType::tryFrom((string) ($validated['gap_type'] ?? ''))
            ?? AutoBannedReconcileGapType::NoRequest;

        $unbanCompletedAt = isset($validated['unban_completed_at']) && $validated['unban_completed_at'] !== ''
            ? Carbon::parse((string) $validated['unban_completed_at'])
            : null;

        $unbanLogMode = AutoBannedReconcileUnbanLogMode::from((string) $validated['unban_log_mode']);

        $result = $this->logReconcileService->reconcileBanLogs(
            banLogIds: array_map('intval', $validated['ban_log_ids']),
            actor: $user,
            gapType: $gapType,
            alasanPengajuan: (string) ($validated['alasan_pengajuan'] ?? ''),
            unbanCompletedAt: $unbanCompletedAt,
            unbanLogMode: $unbanLogMode,
        );

        $redirect = redirect()
            ->route('auto-banned.inputasi.reconcile.index', array_filter([
                'gap_type' => $gapType->value,
                'min_days_old' => $request->input('min_days_old'),
                'site' => $request->input('site'),
                'sid' => $request->input('sid'),
                'q' => $request->input('q'),
            ], static fn ($value) => $value !== null && $value !== ''));

        if ($result['processed'] > 0) {
            $scopeLabel = $gapType->isWeekly() ? 'weekly' : 'daily';
            $message = match ($unbanLogMode) {
                AutoBannedReconcileUnbanLogMode::BelumSukses => $result['processed'].' riwayat '.$scopeLabel.' berhasil direkonsiliasi (request unban Disetujui saja, tanpa log unban SUCCESS).',
                AutoBannedReconcileUnbanLogMode::UnbanLogOnly => $result['processed'].' riwayat '.$scopeLabel.' berhasil direkonsiliasi (log unban SUCCESS — request sudah ada).',
                default => $result['processed'].' riwayat '.$scopeLabel.' berhasil direkonsiliasi (request unban Disetujui + log unban SUCCESS).',
            };
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
