<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hsecm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hsecm\HsecmTasklistSubmitRequest;
use App\Services\Hsecm\HsecmTasklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class HsecmTasklistPublicController extends Controller
{
    public function __construct(
        private readonly HsecmTasklistService $tasklistService,
    ) {}

    public function show(string $token): View
    {
        $tasklist = $this->tasklistService->findByToken($token);
        abort_if($tasklist === null, 404);

        return view('BaseRule.tasklist.show', [
            'tasklist' => $tasklist,
            'items' => $tasklist->items,
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

        $this->tasklistService->submitItems(
            $tasklist,
            $itemIds,
            (string) $request->input('submitted_by_name'),
            (string) $request->input('remediation_notes'),
            $sharedEvidence,
        );

        return redirect()
            ->route('hsecm.tasklist.show', ['token' => $token])
            ->with('success', 'Submit berhasil untuk '.count($itemIds).' item. Menunggu ACC dari HSE.');
    }
}
