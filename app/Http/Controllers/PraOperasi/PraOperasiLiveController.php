<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Services\PraOperasi\PraOperasiLiveMonitoringService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Fase 2 (Saat Operasi) — dashboard live monitoring. index() render halaman
 * awal, data() dipanggil berkala oleh JS (polling) untuk memperbarui kartu
 * tanpa reload penuh.
 */
final class PraOperasiLiveController extends Controller
{
    public function __construct(
        private readonly PraOperasiLiveMonitoringService $service,
    ) {}

    public function index(Request $request): View
    {
        $date = $this->resolveDate($request);

        return view('pra-operasi.saat-operasi', $this->service->snapshot($date));
    }

    public function data(Request $request): JsonResponse
    {
        $date = $this->resolveDate($request);

        return response()->json($this->service->snapshot($date));
    }

    /**
     * Simpan catatan tindak lanjut supervisor untuk satu operator, lalu
     * kirim notifikasi WA ke PIC perusahaan operator tsb (jika ditemukan).
     */
    public function tindakLanjut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_sid' => ['required', 'string', 'max:20'],
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', 'max:20'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'nama' => ['nullable', 'string', 'max:150'],
            'perusahaan' => ['nullable', 'string', 'max:150'],
        ]);

        $result = $this->service->catatTindakLanjut(
            $validated['kode_sid'],
            $validated['date'],
            $validated['status'] ?? null,
            $validated['catatan'] ?? null,
            $request->user()?->id,
            $validated['nama'] ?? '',
            $validated['perusahaan'] ?? '',
        );

        if (! $result['ok']) {
            return response()->json(['message' => 'Gagal menyimpan catatan.'], 500);
        }

        $wa = $result['wa'];
        $message = 'Catatan tersimpan.';
        if ($wa['attempted'] > 0) {
            $message .= $wa['sent'] > 0
                ? ' WA terkirim ke '.$wa['sent'].' PIC.'.($wa['failed'] > 0 ? ' '.$wa['failed'].' gagal.' : '')
                : ' Gagal mengirim WA ke PIC.';
        }

        return response()->json(['message' => $message, 'wa' => $wa]);
    }

    private function resolveDate(Request $request): string
    {
        $date = (string) $request->query('date', '');
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = Carbon::now(config('app.timezone'))->toDateString();
        }

        return $date;
    }
}
