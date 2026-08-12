<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hsecm;

use App\Actions\Hsecm\HsecmBuildTasklistSubmitMasterSodWaLinksAction;
use App\Actions\Hsecm\HsecmNotifyTasklistSubmitToSodAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hsecm\HsecmTasklistSubmitRequest;
use App\Services\Hsecm\HsecmTasklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class HsecmTasklistPublicController extends Controller
{
    public function __construct(
        private readonly HsecmTasklistService $tasklistService,
        private readonly HsecmBuildTasklistSubmitMasterSodWaLinksAction $buildMasterSodWaLinks,
        private readonly HsecmNotifyTasklistSubmitToSodAction $notifyTasklistSubmitToSod,
    ) {}

    /**
     * Resolve tasklist by site + perusahaan (+ optional batch_slot) lalu redirect ke token.
     */
    public function open(Request $request): RedirectResponse
    {
        $site = trim((string) $request->query('site', ''));
        $perusahaan = trim((string) $request->query('perusahaan', ''));
        $batchSlot = trim((string) $request->query('batch_slot', ''));

        abort_if($perusahaan === '', 404, 'Parameter perusahaan wajib.');

        $tasklist = $this->tasklistService->findByScope(
            $site,
            $perusahaan,
            $batchSlot !== '' ? $batchSlot : null,
        );
        abort_if($tasklist === null, 404, 'Tasklist tidak ditemukan untuk site/perusahaan ini.');

        return redirect()->route('hsecm.tasklist.show', ['token' => $tasklist->token]);
    }

    public function show(string $token): View
    {
        $tasklist = $this->tasklistService->findByToken($token);
        abort_if($tasklist === null, 404);

        $items = $this->tasklistService->withPreviousRecurrenceCounts($tasklist, $tasklist->items);

        return view('BaseRule.tasklist.show', [
            'tasklist' => $tasklist,
            'items' => $items,
            'navActive' => 'tasklist',
            'navItems' => [
                ['key' => 'tasklist', 'label' => 'Tasklist', 'route' => 'hsecm.tasklist.show', 'params' => ['token' => $token]],
            ],
            'programLabel' => 'Tasklist Monitoring & Intervensi',
            'programCode' => 'Daily',
            'isPublicAccess' => true,
        ]);
    }

    public function submit(HsecmTasklistSubmitRequest $request, string $token): RedirectResponse
    {
        $tasklist = $this->tasklistService->findByToken($token);
        abort_if($tasklist === null, 404);

        $itemIds = collect($request->input('items', []))
            ->map(static fn ($v): int => (int) $v)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        /** @var UploadedFile $sharedEvidence */
        $sharedEvidence = $request->file('evidence_shared');

        $submittedByName = (string) $request->input('submitted_by_name');

        $this->tasklistService->submitItems(
            $tasklist,
            $itemIds,
            $submittedByName,
            (string) $request->input('remediation_notes'),
            $sharedEvidence,
        );

        $sodWaLinks = $this->buildMasterSodWaLinks->execute(
            $tasklist,
            $submittedByName,
            count($itemIds),
        );

        $emailNotify = $this->notifyTasklistSubmitToSod->execute(
            $tasklist,
            $submittedByName,
            count($itemIds),
        );

        $successMessage = 'Submit berhasil untuk '.count($itemIds).' item. Menunggu ACC dari HSE.';
        if (($emailNotify['sent'] ?? 0) > 0) {
            $successMessage .= ' Email notifikasi dikirim ke '.(int) $emailNotify['sent'].' SOD.';
        } elseif (($emailNotify['failed'] ?? 0) > 0) {
            $successMessage .= ' Email notifikasi gagal dikirim; coba hubungi SOD via WhatsApp.';
        }

        $redirect = redirect()
            ->route('hsecm.tasklist.show', ['token' => $token])
            ->with('success', $successMessage);

        if ($sodWaLinks !== []) {
            $redirect->with('hsecm_sod_wa_links', $sodWaLinks);
        }

        if (($emailNotify['recipients'] ?? []) !== []) {
            $redirect->with('hsecm_sod_email_results', $emailNotify['recipients']);
        }

        return $redirect;
    }
}
