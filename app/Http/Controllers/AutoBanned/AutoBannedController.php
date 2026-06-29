<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoBanned;

use App\Http\Controllers\Controller;
use App\Http\Controllers\AutoBanned\Concerns\ProvidesAutoBannedLayout;
use App\Models\AutoBannedHsctCampaign;
use App\Models\AutoBannedStatusSnapshot;
use App\Services\AutoBanned\AutoBannedDailyBannedEmailService;
use App\Services\AutoBanned\AutoBannedDailyDashboardService;
use App\Services\AutoBanned\AutoBannedHsctEmailService;
use App\Services\AutoBanned\AutoBannedMonitoringOverviewService;
use App\Services\AutoBanned\AutoBannedScrapPollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutoBannedController extends Controller
{
    use ProvidesAutoBannedLayout;

    public function __construct(
        private readonly AutoBannedMonitoringOverviewService $overviewService,
        private readonly AutoBannedDailyDashboardService $dailyDashboardService,
        private readonly AutoBannedScrapPollService $pollService,
        private readonly AutoBannedHsctEmailService $hsctEmailService,
        private readonly AutoBannedDailyBannedEmailService $dailyBannedEmailService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->overviewService->resolveFilters($request);
        $overview = $this->overviewService->buildOverview($filters);

        return view('AutoBanned.overview.index', [
            'navActive' => 'overview',
            'navItems' => $this->autoBannedNavItems(),
            'filters' => $overview['filters'],
            'filterOptions' => $overview['filterOptions'],
            'summaryStats' => $overview['summaryStats'],
            'banned' => $overview['banned'],
            'unban' => $overview['unban'],
        ]);
    }

    public function bannedMonitoring(Request $request): View
    {
        $filters = $this->dailyDashboardService->resolveFilters($request);
        $dashboard = $this->dailyDashboardService->buildDashboard($filters);

        return view('AutoBanned.banned-monitoring.index', [
            'navActive' => 'Monitoring Banned',
            'navItems' => $this->autoBannedNavItems(),
            'filters' => $dashboard['filters'],
            'period' => $dashboard['period'],
            'filterOptions' => $dashboard['filterOptions'],
            'stats' => $dashboard['stats'],
            'chartData' => $dashboard['chartData'],
            'bannedRows' => $dashboard['bannedRows'],
            'logRows' => $dashboard['logRows'],
            'scrTableAvailable' => $dashboard['scrTableAvailable'],
            'logTableAvailable' => $dashboard['logTableAvailable'],
        ]);
    }

    public function sendDailyBannedEmail(Request $request): RedirectResponse
    {
        $filterDate = trim((string) $request->input('filter_date', ''));
        $filterShift = trim((string) $request->input('filter_shift', ''));
        $force = (bool) $request->boolean('force');

        $result = $this->dailyBannedEmailService->sendForDateAndShift(
            $filterDate !== '' ? $filterDate : null,
            $filterShift !== '' ? $filterShift : null,
            $force,
        );

        $key = $result['action'] === 'error' ? 'error' : 'success';

        return redirect()
            ->route('auto-banned.banned-monitoring.index', $request->only(['filter_date', 'site', 'perusahaan', 'q']))
            ->with($key, $result['message']);
    }

    public function markHsctSent(Request $request, AutoBannedStatusSnapshot $snapshot): RedirectResponse
    {
        $this->pollService->markHsctSent($snapshot);
        $this->syncCampaignForSnapshot($snapshot);

        return redirect()
            ->route('auto-banned.banned-monitoring.index', $request->only(['filter_date', 'site', 'perusahaan', 'q']))
            ->with('success', 'SID '.$snapshot->sid.' ditandai terkirim ke HSECT.');
    }

    public function markHsctConfirmed(Request $request, AutoBannedStatusSnapshot $snapshot): RedirectResponse
    {
        $this->pollService->markHsctConfirmed($snapshot);
        $this->syncCampaignForSnapshot($snapshot);

        return redirect()
            ->route('auto-banned.banned-monitoring.index', $request->only(['filter_date', 'site', 'perusahaan', 'q']))
            ->with('success', 'SID '.$snapshot->sid.' dikonfirmasi banned oleh HSECT.');
    }

    private function syncCampaignForSnapshot(AutoBannedStatusSnapshot $snapshot): void
    {
        $campaign = $this->hsctEmailService->findCampaign($snapshot->week, $snapshot->iso_year);
        if ($campaign instanceof AutoBannedHsctCampaign) {
            $this->hsctEmailService->syncCampaignConfirmation($campaign);
        }
    }
}
