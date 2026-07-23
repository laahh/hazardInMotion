<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hsecm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Hsecm\Concerns\ProvidesHsecmLayout;
use App\Http\Requests\Hsecm\HsecmWaNotifySendEmailRequest;
use App\Http\Requests\Hsecm\HsecmWaNotifyStoreRecipientRequest;
use App\Services\Hsecm\HsecmDashboardService;
use App\Services\Hsecm\HsecmWaNotifyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HsecmWaNotifyController extends Controller
{
    use ProvidesHsecmLayout;

    public function __construct(
        private readonly HsecmWaNotifyService $waNotifyService,
        private readonly HsecmDashboardService $dashboardService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->dashboardService->resolveFilters($request);
        $recipients = $this->waNotifyService->buildRecipientRows($request);
        $filterOptions = $this->dashboardService->buildScopeSummary([
            'site' => '',
            'perusahaan' => '',
            'week' => $filters['week'],
            'year' => $filters['year'],
            'q' => '',
        ])['filter_options'];

        return view('BaseRule.wa-notify.index', $this->hsecmViewData('wa-notify', [
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'recipients' => $recipients,
            'fonnteConfigured' => $this->waNotifyService->fonnteConfigured(),
        ]));
    }

    public function storeRecipient(HsecmWaNotifyStoreRecipientRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $recipient = $this->waNotifyService->addRecipient([
            'nama' => $data['nama'],
            'email' => $data['email'],
            'site' => $data['site'] ?? null,
            'perusahaan' => $data['perusahaan'] ?? '',
            'role' => $data['role'] ?? '',
            'no' => $data['no'] ?? '',
        ]);

        return $this->redirectToIndex($request)
            ->with('success', 'Penerima ditambahkan: '.$recipient['nama'].' ('.$recipient['email'].').');
    }

    public function destroyRecipient(Request $request, string $id): RedirectResponse
    {
        $deleted = $this->waNotifyService->deleteRecipient($id);

        return $this->redirectToIndex($request)
            ->with(
                $deleted ? 'success' : 'error',
                $deleted
                    ? 'Penerima custom berhasil dihapus.'
                    : 'Penerima tidak ditemukan atau tidak bisa dihapus (kontak bawaan config).'
            );
    }

    public function send(Request $request, int $index): RedirectResponse
    {
        $channel = $request->input('channel', 'wa_me') === 'fonnte' ? 'fonnte' : 'wa_me';
        $result = $this->waNotifyService->send($index, $request, $channel);

        $redirect = $this->redirectToIndex($request)
            ->with($result['success'] ? 'success' : 'error', $result['message']);

        if ($channel === 'wa_me' && $result['success'] && ! empty($result['wa_url'])) {
            $redirect->with('wa_url', $result['wa_url']);
        }

        return $redirect;
    }

    public function sendEmail(Request $request, int $index): RedirectResponse
    {
        $result = $this->waNotifyService->sendEmail($index, $request);

        return $this->redirectToIndex($request)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function sendEmailBulk(HsecmWaNotifySendEmailRequest $request): RedirectResponse
    {
        /** @var list<int> $indexes */
        $indexes = array_map('intval', $request->validated('indexes'));
        $result = $this->waNotifyService->sendEmails($indexes, $request);

        $flashKey = ($result['sent'] ?? 0) > 0
            ? (($result['failed'] ?? 0) > 0 ? 'success' : 'success')
            : 'error';

        return $this->redirectToIndex($request)
            ->with($flashKey, $result['message'])
            ->with('email_send_details', $result['details'] ?? []);
    }

    private function redirectToIndex(Request $request): RedirectResponse
    {
        $redirectQuery = array_filter([
            'week' => $request->input('week', $request->query('week')),
            'year' => $request->input('year', $request->query('year')),
        ], static fn ($v) => $v !== null && $v !== '');

        return redirect()->route('hsecm.wa-notify.index', $redirectQuery);
    }
}
