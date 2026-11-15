<?php

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\Drm\DrmRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Peta Risiko PJA (DRM 2.1): identifikasi bahaya-risiko HIRA oleh SAP per Site / Group Lokasi / PJA.
 * Data dari snapshot `php artisan peta-risiko:sync` (ClickHouse OBDS).
 */
class PetaRisikoDrmController extends Controller
{
    public function __construct(private readonly DrmRepository $repo)
    {
    }

    public function index(): View
    {
        return view('peta-risiko-pja.drm.index', ['meta' => $this->repo->meta(), 'adaExcel' => $this->repo->adaEkspor()]);
    }

    public function pja(string $pja): View
    {
        $meta = $this->repo->meta();
        abort_if($meta === null, 404, 'Snapshot Peta Risiko belum dibuat.');
        $ringkas = collect($meta['pja'])->firstWhere('id', $pja);
        abort_if($ringkas === null, 404, 'PJA tidak ditemukan di snapshot.');

        return view('peta-risiko-pja.drm.pja', ['meta' => $meta, 'ringkas' => $ringkas]);
    }

    /** Isi lengkap satu PJA (dimuat JS setelah halaman tampil). */
    public function pjaData(string $pja): JsonResponse
    {
        $data = $this->repo->pja($pja);
        abort_if($data === null, 404);

        return response()->json($data);
    }

    /** Excel mapping bahaya-risiko (ditulis oleh peta-risiko:sync). */
    public function unduhMapping(): BinaryFileResponse
    {
        abort_unless($this->repo->adaEkspor(), 404, 'Excel mapping belum dibuat. Jalankan php artisan peta-risiko:sync.');
        $sampai = $this->repo->meta()['sampai'] ?? now()->toDateString();

        return response()->download($this->repo->pathEkspor(), "mapping-bahaya-risiko-{$sampai}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Ringkasan semua cakupan (Site / Group Lokasi / PJA) untuk halaman indeks. */
    public function meta(Request $request): JsonResponse
    {
        $meta = $this->repo->meta();
        abort_if($meta === null, 404);

        return response()->json($meta);
    }
}
