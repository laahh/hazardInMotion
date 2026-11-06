<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PraOperasi\PengawasUnbanStoreRequest;
use App\Services\PraOperasi\PengawasUnbanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Form publik (tanpa login) pengajuan unbanned pengawas. Tampilan dan alurnya
 * mengikuti /form/pengajuan-treatment (AutoBannedPublicTreatmentController).
 */
class PengawasUnbanPublicController extends Controller
{
    public function __construct(
        private readonly PengawasUnbanService $service,
    ) {}

    public function show(Request $request): View
    {
        return view('pra-operasi.public.pengawas-unban-form', [
            'prefillSid' => strtoupper(trim((string) $request->query('sid', ''))),
            'maxUploadMb' => (int) ceil(((int) config('auto_banned.treatment.max_upload_kb', 10240)) / 1024),
            'allowedMimes' => config('auto_banned.treatment.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xlsx', 'xls']),
        ]);
    }

    public function success(Request $request): View
    {
        return view('pra-operasi.public.pengawas-unban-success', [
            'sid' => strtoupper(trim((string) $request->query('sid', ''))),
            'tanggal' => trim((string) $request->query('tanggal', '')),
            'submittedAt' => trim((string) $request->query('at', '')),
        ]);
    }

    public function lookupSid(Request $request): JsonResponse
    {
        return response()->json($this->service->cariSid((string) $request->query('sid', '')));
    }

    public function store(PengawasUnbanStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $pengajuan = $this->service->ajukan(
            (string) $validated['sid'],
            (string) $validated['tanggal_kerja'],
            (string) $validated['no_hp'],
            (string) $validated['alasan_pengajuan'],
            $request->file('evidence_file'),
        );

        return redirect()->route('roster-treatment.public.success', [
            'sid' => $pengajuan->sid,
            'tanggal' => $pengajuan->tanggal_kerja->format('d M Y'),
            'at' => now()->timezone(config('auto_banned.hsct.timezone', 'Asia/Makassar'))->format('d M Y, H:i'),
        ]);
    }
}
