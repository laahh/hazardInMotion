<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoBanned;

use App\Http\Controllers\Controller;
use App\Http\Requests\AutoBanned\AutoBannedPublicStoreTreatmentEvidenceRequest;
use App\Models\AutoBannedUnbanRequest;
use App\Services\AutoBanned\AutoBannedOverviewService;
use App\Services\AutoBanned\AutoBannedTreatmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutoBannedPublicTreatmentController extends Controller
{
    public function __construct(
        private readonly AutoBannedOverviewService $overviewService,
        private readonly AutoBannedTreatmentService $treatmentService,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! (bool) config('auto_banned.treatment.public_form_enabled', true)) {
            abort(404);
        }

        $period = $this->overviewService->resolvePeriod([
            'week' => (string) $request->query('week', ''),
            'year' => (string) $request->query('year', ''),
        ]);

        return view('AutoBanned.public.treatment-form', [
            'period' => $period,
            'prefillSid' => strtoupper(trim((string) $request->query('sid', ''))),
            'maxUploadMb' => (int) ceil(((int) config('auto_banned.treatment.max_upload_kb', 10240)) / 1024),
            'allowedMimes' => config('auto_banned.treatment.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xlsx', 'xls']),
        ]);
    }

    public function success(Request $request): View
    {
        return view('AutoBanned.public.treatment-success', [
            'sid' => strtoupper(trim((string) $request->query('sid', ''))),
            'periodLabel' => trim((string) $request->query('period', '')),
            'submittedAt' => trim((string) $request->query('at', '')),
        ]);
    }

    public function lookupSid(Request $request): JsonResponse
    {
        $sid = strtoupper(trim((string) $request->query('sid', '')));

        if ($sid === '') {
            return response()->json(['found' => false, 'message' => 'Ketik SID Anda lalu tekan Cari.']);
        }

        $context = $this->treatmentService->resolveSidContextFromBannedLogs($sid);

        if ($context === null) {
            return response()->json([
                'found' => false,
                'message' => 'SID tidak ditemukan di riwayat banned. Periksa ejaan SID atau hubungi admin.',
            ]);
        }

        $bannedLogOptions = $this->treatmentService->treatmentBannedLogOptionsForSid($sid);

        if ($bannedLogOptions === []) {
            $alreadyRequested = AutoBannedUnbanRequest::hasAnyRequestedBannedRefsForSid($sid);

            return response()->json([
                'found' => true,
                'can_submit' => false,
                'message' => $alreadyRequested
                    ? 'Semua riwayat banned SUCCESS yang tersedia sudah pernah diajukan.'
                    : 'SID ditemukan, namun belum ada riwayat banned berstatus SUCCESS yang dapat diajukan.',
                'data' => $context,
                'banned_log_options' => [],
            ]);
        }

        return response()->json([
            'found' => true,
            'can_submit' => true,
            'message' => 'Data ditemukan. Lanjut isi formulir di bawah.',
            'data' => $context,
            'banned_log_options' => $bannedLogOptions,
        ]);
    }

    public function store(AutoBannedPublicStoreTreatmentEvidenceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $unbanRequest = $this->treatmentService->storeTreatmentEvidence(
            sid: (string) $validated['sid'],
            week: (string) $validated['week'],
            year: (string) $validated['year'],
            alasanPengajuan: (string) $validated['alasan_pengajuan'],
            file: $request->file('evidence_file'),
            user: null,
            scrDailyBannedId: null,
            noHp: (string) $validated['no_hp'],
            bannedLogRef: (string) $validated['banned_log_ref'],
        );

        $whatsappUrl = $this->treatmentService->resolveMasterSodWhatsappRedirectUrl($unbanRequest);
        if ($whatsappUrl !== null) {
            return redirect()->away($whatsappUrl);
        }

        $week = (string) $validated['week'];
        $year = (string) $validated['year'];

        return redirect()->route('auto-banned.public.treatment.success', [
            'sid' => strtoupper($validated['sid']),
            'period' => $week.' · '.$year,
            'at' => now()->timezone(config('auto_banned.hsct.timezone', 'Asia/Makassar'))->format('d M Y, H:i'),
        ]);
    }
}
