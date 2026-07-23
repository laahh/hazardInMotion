<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hsecm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Hsecm\Concerns\ProvidesHsecmLayout;
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
        $query = HsecmTasklist::query()
            ->withCount([
                'items',
                'items as open_count' => fn ($q) => $q->where('status', 'open'),
                'items as submitted_count' => fn ($q) => $q->where('status', 'submitted'),
                'items as rejected_count' => fn ($q) => $q->where('status', 'rejected'),
                'items as approved_count' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->orderByDesc('batch_slot')
            ->orderByDesc('id');

        if ($status !== '' && in_array($status, ['open', 'partial', 'closed'], true)) {
            $query->where('status', $status);
        }

        $tasklists = $query->paginate(20)->withQueryString();

        return view('BaseRule.tasklist.index', $this->hsecmViewData('tasklist-review', [
            'tasklists' => $tasklists,
            'statusFilter' => $status,
        ]));
    }

    public function manage(int $id): View
    {
        abort_unless($this->tasklistService->tablesAvailable(), 503, 'Tabel tasklist belum dimigrate.');

        $tasklist = HsecmTasklist::query()
            ->with(['items.evidences'])
            ->findOrFail($id);

        return view('BaseRule.tasklist.manage', $this->hsecmViewData('tasklist-review', [
            'tasklist' => $tasklist,
            'items' => $tasklist->items,
            'publicUrl' => $this->tasklistService->publicUrl($tasklist),
        ]));
    }

    public function approve(int $itemId): RedirectResponse
    {
        $item = HsecmTasklistItem::query()->with('tasklist')->findOrFail($itemId);
        $user = auth()->user();
        abort_unless($user !== null, 403);

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
