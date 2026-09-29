<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PraOperasi\RosterTreatmentEvidenceReviewRequest;
use App\Models\SidRosterTreatmentEvidence;
use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $rfidReader,
    ) {}

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
            7 => 'periode_cuti',
            8 => 'approval_status',
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

            if ($item->sid && $item->periode_cuti) {
                $rfidUrl = route('pra-operasi.roster-banned.treatment.rfid', $item->id);
                $aksi .= '<button type="button" class="btn btn-sm btn-outline-primary rte-rfid-btn" title="Cek RFID selama periode cuti" data-rfid-url="'.e($rfidUrl).'"><iconify-icon icon="solar:card-search-outline"></iconify-icon></button> ';
            }

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
                'periode_cuti' => $item->periode_cuti ?: '-',
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

    /**
     * Cek aktivitas RFID (scan gate) SID ini dari tanggal mulai periode cuti
     * s/d hari ini — dipakai admin untuk verifikasi apakah karyawan yang
     * mengajukan treatment benar-benar tidak beraktivitas di site selama
     * cuti (kalau ada RFID di rentang itu, patut dicurigai/ditindaklanjuti).
     */
    public function rfidDetail(SidRosterTreatmentEvidence $evidence): JsonResponse
    {
        $sid = trim((string) $evidence->sid);
        if ($sid === '') {
            return response()->json(['available' => false, 'message' => 'Data ini tidak punya kode SID, tidak bisa dicek RFID-nya.']);
        }

        $start = $this->parsePeriodeCutiStart($evidence->periode_cuti);
        if ($start === null) {
            return response()->json(['available' => false, 'message' => 'Belum ada periode cuti yang diajukan untuk data ini.']);
        }

        if (! $this->rfidReader->isUp()) {
            return response()->json(['available' => false, 'message' => 'Koneksi ke data RFID sedang tidak tersedia. Coba lagi nanti.']);
        }

        $tz = config('app.timezone');
        $today = Carbon::now($tz)->startOfDay();

        if ($start->greaterThan($today)) {
            return response()->json([
                'available' => true,
                'sid' => $sid,
                'nama' => $evidence->master?->nama,
                'periode_cuti' => $evidence->periode_cuti,
                'range' => ['from' => $start->toDateString(), 'to' => $start->toDateString()],
                'summary' => ['total_days' => 0, 'days_with_rfid' => 0, 'days_without_rfid' => 0],
                'days' => [],
                'note' => 'Periode cuti belum dimulai.',
            ]);
        }

        // Pengaman: jangan pernah query rentang yang tidak wajar panjang (data salah format dsb).
        $to = $start->diffInDays($today) > 120 ? $start->copy()->addDays(120) : $today;

        $byDay = $this->rfidReader->firstPassedCheckinsByDayForSids($start->toDateString(), $to->toDateString(), [$sid]);
        $upper = mb_strtoupper($sid);

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($to)) {
            $key = $cursor->toDateString();
            $row = $byDay[$key][$upper] ?? null;
            $days[] = [
                'date' => $key,
                'has_rfid' => $row !== null,
                'checked_in_at' => $row['checked_in_at'] ?? null,
                'gate' => $row['gate'] ?? null,
            ];
            $cursor->addDay();
        }

        $daysWithRfid = count(array_filter($days, static fn (array $d): bool => $d['has_rfid']));

        return response()->json([
            'available' => true,
            'sid' => $sid,
            'nama' => $evidence->master?->nama,
            'periode_cuti' => $evidence->periode_cuti,
            'range' => ['from' => $start->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'total_days' => count($days),
                'days_with_rfid' => $daysWithRfid,
                'days_without_rfid' => count($days) - $daysWithRfid,
            ],
            'days' => $days,
        ]);
    }

    /**
     * periode_cuti disimpan sebagai satu string siap-tampil, mis.
     * "29/09/2026 s.d. 13/10/2026 (14 hari)" — ambil tanggal mulainya saja.
     */
    private function parsePeriodeCutiStart(?string $periodeCuti): ?Carbon
    {
        if ($periodeCuti === null || $periodeCuti === '') {
            return null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', trim($periodeCuti), $m) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $m[1].'/'.$m[2].'/'.$m[3], config('app.timezone'))?->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
