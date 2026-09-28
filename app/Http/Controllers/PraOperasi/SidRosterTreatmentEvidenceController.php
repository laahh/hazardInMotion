<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PraOperasi\RosterTreatmentEvidenceReviewRequest;
use App\Models\SidRosterTreatmentEvidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Review (approve/reject) pengajuan bukti treatment yang masuk dari form
 * publik /form/pengajuan-treatment-banned. Read/write ke
 * sid_roster_treatment_evidence, di-auth (nested di grup pra-operasi).
 */
class SidRosterTreatmentEvidenceController extends Controller
{
    public function index(): View
    {
        return view('pra-operasi.roster-banned.treatment-evidence.index');
    }

    /**
     * DataTables server-side data (JSON).
     */
    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 0);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        if ($length < 1 || $length > 100) {
            $length = 25;
        }
        $search = trim((string) ($request->input('search.value') ?? ''));
        $status = strtoupper(trim((string) $request->input('status', '')));
        $orderColIndex = (int) $request->input('order.0.column', 1);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $orderColumns = [
            1 => 'submitted_at',
            2 => 'nik',
            3 => 'sid',
            6 => 'tanggal_treatment',
            7 => 'approval_status',
        ];
        $orderBy = $orderColumns[$orderColIndex] ?? 'submitted_at';

        $query = SidRosterTreatmentEvidence::query()->with('master');

        if (in_array($status, [
            SidRosterTreatmentEvidence::STATUS_PENDING,
            SidRosterTreatmentEvidence::STATUS_APPROVED,
            SidRosterTreatmentEvidence::STATUS_REJECTED,
        ], true)) {
            $query->where('approval_status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('nik', 'like', '%'.$search.'%')
                    ->orWhere('sid', 'like', '%'.$search.'%')
                    ->orWhere('submitted_by', 'like', '%'.$search.'%')
                    ->orWhere('catatan', 'like', '%'.$search.'%')
                    ->orWhereHas('master', function ($mq) use ($search): void {
                        $mq->where('nama', 'like', '%'.$search.'%')
                            ->orWhere('perusahaan', 'like', '%'.$search.'%');
                    });
            });
        }

        $recordsTotal = SidRosterTreatmentEvidence::count();
        $recordsFiltered = (clone $query)->count();
        $items = $query->orderBy($orderBy, $orderDir)->skip($start)->take($length)->get();

        $statusBadge = [
            SidRosterTreatmentEvidence::STATUS_PENDING => '<span class="badge bg-warning-focus text-warning-main">Pending</span>',
            SidRosterTreatmentEvidence::STATUS_APPROVED => '<span class="badge bg-success-focus text-success-main">Approved</span>',
            SidRosterTreatmentEvidence::STATUS_REJECTED => '<span class="badge bg-danger-focus text-danger-main">Rejected</span>',
        ];

        $data = [];
        foreach ($items as $idx => $item) {
            $csrf = csrf_token();
            $evidenceUrl = route('pra-operasi.roster-banned.treatment.evidence', $item->id);

            $aksi = '<a href="'.e($evidenceUrl).'" target="_blank" class="btn btn-sm btn-outline-secondary" title="Lihat file"><iconify-icon icon="solar:file-outline"></iconify-icon></a> ';

            if ($item->isPending()) {
                $approveUrl = route('pra-operasi.roster-banned.treatment.review', $item->id);
                $aksi .= '<form action="'.e($approveUrl).'" method="POST" class="d-inline" onsubmit="return confirm(\'Setujui pengajuan ini?\');">'
                    .'<input type="hidden" name="_token" value="'.e($csrf).'">'
                    .'<input type="hidden" name="action" value="approve">'
                    .'<button type="submit" class="btn btn-sm btn-outline-success" title="Approve"><iconify-icon icon="solar:check-circle-outline"></iconify-icon></button>'
                    .'</form> ';
                $aksi .= '<button type="button" class="btn btn-sm btn-outline-danger rb-reject-btn" title="Reject" data-review-url="'.e($approveUrl).'" data-csrf="'.e($csrf).'"><iconify-icon icon="solar:close-circle-outline"></iconify-icon></button>';
            }

            $data[] = [
                'DT_RowIndex' => $start + $idx + 1,
                'submitted_at' => $item->submitted_at?->format('d/m/Y H:i') ?? '-',
                'nik' => $item->nik ?: '-',
                'sid' => $item->sid ?: '-',
                'nama' => $item->master?->nama ?? '-',
                'perusahaan' => $item->master?->perusahaan ?? '-',
                'tanggal_treatment' => $item->tanggal_treatment ? $item->tanggal_treatment->format('d/m/Y') : '-',
                'approval_status' => $statusBadge[$item->approval_status] ?? e((string) $item->approval_status),
                'submitted_by' => $item->submitted_by ?: '-',
                'catatan' => $item->catatan ?: '-',
                'rejection_reason' => $item->rejection_reason ?: '-',
                'aksi' => $aksi,
            ];
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function review(RosterTreatmentEvidenceReviewRequest $request, SidRosterTreatmentEvidence $evidence): RedirectResponse
    {
        if (! $evidence->isPending()) {
            return redirect()
                ->route('pra-operasi.roster-banned.treatment.index')
                ->with('error', 'Pengajuan ini sudah pernah direview.');
        }

        $reviewer = Auth::user()?->name ?? 'system';

        if ($request->isApprove()) {
            $evidence->update([
                'approval_status' => SidRosterTreatmentEvidence::STATUS_APPROVED,
                'approved_by' => $reviewer,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);
            $message = 'Bukti treatment disetujui.';
        } else {
            $evidence->update([
                'approval_status' => SidRosterTreatmentEvidence::STATUS_REJECTED,
                'approved_by' => $reviewer,
                'approved_at' => now(),
                'rejection_reason' => (string) $request->input('rejection_reason'),
            ]);
            $message = 'Bukti treatment ditolak.';
        }

        return redirect()
            ->route('pra-operasi.roster-banned.treatment.index')
            ->with('success', $message);
    }

    public function downloadEvidence(SidRosterTreatmentEvidence $evidence): StreamedResponse
    {
        $path = (string) $evidence->evidence_file_path;

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            abort(404, 'File evidence tidak ditemukan.');
        }

        return Storage::disk('local')->response($path);
    }
}
