<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hsecm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Hsecm\Concerns\ProvidesHsecmLayout;
use App\Http\Requests\Hsecm\HsecmTasklistBulkApproveRequest;
use App\Http\Requests\Hsecm\HsecmTasklistBulkRejectRequest;
use App\Http\Requests\Hsecm\HsecmTasklistRejectRequest;
use App\Models\Hsecm\HsecmTasklist;
use App\Models\Hsecm\HsecmTasklistItem;
use App\Services\Hsecm\HsecmTasklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HsecmTasklistManageController extends Controller
{
    use ProvidesHsecmLayout;

    public function __construct(
        private readonly HsecmTasklistService $tasklistService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->tasklistService->tablesAvailable(), 503, 'Tabel tasklist belum dimigrate.');

        $status = trim((string) $request->query('status', ''));
        $user = auth()->user();
        $reviewerSite = $this->tasklistService->resolveReviewerSite($user);

        $query = HsecmTasklist::query()
            ->orderByDesc('batch_slot')
            ->orderByDesc('id');

        if ($status !== '' && in_array($status, ['open', 'partial', 'closed'], true)) {
            $query->where('status', $status);
        }

        if ($reviewerSite !== null) {
            $query->whereNotNull('site')
                ->where('site', '!=', '')
                ->where(function ($q) use ($reviewerSite): void {
                    $q->where('site', $reviewerSite);
                    // variasi spacing umum (BMO1 vs BMO 1)
                    $compact = preg_replace('/\s+/', '', $reviewerSite) ?? $reviewerSite;
                    if ($compact !== $reviewerSite) {
                        $q->orWhereRaw("REPLACE(site, ' ', '') = ?", [$compact]);
                    }
                });
        }

        $tasklists = $query->paginate(20)->withQueryString();

        return view('BaseRule.tasklist.index', $this->hsecmViewData('tasklist-review', [
            'tasklists' => $tasklists,
            'statusFilter' => $status,
            'reviewerSite' => $reviewerSite,
        ]));
    }

    public function manage(int $id): View
    {
        abort_unless($this->tasklistService->tablesAvailable(), 503, 'Tabel tasklist belum dimigrate.');

        $tasklist = HsecmTasklist::query()
            ->with(['items.evidences'])
            ->findOrFail($id);

        abort_unless(
            $this->tasklistService->userCanAccessTasklist(auth()->user(), $tasklist),
            403,
            'Anda tidak berhak mengakses tasklist site ini.'
        );

        $items = $this->tasklistService->withPreviousRecurrenceCounts($tasklist, $tasklist->items);

        return view('BaseRule.tasklist.manage', $this->hsecmViewData('tasklist-review', [
            'tasklist' => $tasklist,
            'items' => $items,
            'publicUrl' => $this->tasklistService->publicUrl($tasklist),
        ]));
    }

    public function approveBulk(HsecmTasklistBulkApproveRequest $request, int $id): RedirectResponse
    {
        $tasklist = HsecmTasklist::query()->findOrFail($id);
        $user = auth()->user();
        abort_unless($user !== null, 403);
        abort_unless(
            $this->tasklistService->userCanAccessTasklist($user, $tasklist),
            403,
            'Anda tidak berhak mengakses tasklist site ini.'
        );

        $itemIds = collect($request->input('items', []))
            ->map(static fn ($v): int => (int) $v)
            ->filter(static fn (int $v): bool => $v > 0)
            ->unique()
            ->values()
            ->all();

        $count = $this->tasklistService->approveItems($tasklist, $itemIds, $user);

        return redirect()
            ->route('hsecm.tasklist.manage', ['id' => $tasklist->id])
            ->with('success', $count.' item di-ACC.');
    }

    public function rejectBulk(HsecmTasklistBulkRejectRequest $request, int $id): RedirectResponse
    {
        $tasklist = HsecmTasklist::query()->findOrFail($id);
        $user = auth()->user();
        abort_unless($user !== null, 403);
        abort_unless(
            $this->tasklistService->userCanAccessTasklist($user, $tasklist),
            403,
            'Anda tidak berhak mengakses tasklist site ini.'
        );

        $itemIds = collect($request->input('items', []))
            ->map(static fn ($v): int => (int) $v)
            ->filter(static fn (int $v): bool => $v > 0)
            ->unique()
            ->values()
            ->all();

        $count = $this->tasklistService->rejectItems(
            $tasklist,
            $itemIds,
            $user,
            (string) $request->input('rejection_reason'),
        );

        return redirect()
            ->route('hsecm.tasklist.manage', ['id' => $tasklist->id])
            ->with('success', $count.' item ditolak. PJO dapat resubmit.');
    }

    public function approve(int $itemId): RedirectResponse
    {
        $item = HsecmTasklistItem::query()->with('tasklist')->findOrFail($itemId);
        $user = auth()->user();
        abort_unless($user !== null, 403);
        abort_unless(
            $item->tasklist !== null
                && $this->tasklistService->userCanAccessTasklist($user, $item->tasklist),
            403,
            'Anda tidak berhak mengakses tasklist site ini.'
        );

        $this->tasklistService->approveItem($item, $user);

        return redirect()
            ->route('hsecm.tasklist.manage', ['id' => $item->tasklist_id])
            ->with('success', 'Item #'.$item->id.' di-ACC.');
    }

    public function reject(HsecmTasklistRejectRequest $request, int $itemId): RedirectResponse
    {
        $item = HsecmTasklistItem::query()->with('tasklist')->findOrFail($itemId);
        $user = auth()->user();
        abort_unless($user !== null, 403);
        abort_unless(
            $item->tasklist !== null
                && $this->tasklistService->userCanAccessTasklist($user, $item->tasklist),
            403,
            'Anda tidak berhak mengakses tasklist site ini.'
        );

        $this->tasklistService->rejectItem(
            $item,
            $user,
            (string) $request->input('rejection_reason'),
        );

        return redirect()
            ->route('hsecm.tasklist.manage', ['id' => $item->tasklist_id])
            ->with('success', 'Item #'.$item->id.' ditolak. PJO dapat resubmit.');
    }
}
