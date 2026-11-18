<?php

declare(strict_types=1);

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\Drm\DrmKorelasi;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Peta korelasi master DRM 2.1 (/peta-risiko-pja/korelasi): graf grup lokasi ↔ detail lokasi ↔ PJA ↔ aktivitas ↔
 * kelompok ↔ bahaya-risiko ↔ label SAP, dalam mode fokus (satu simpul pusat + tetangganya).
 */
final class PetaRisikoKorelasiController extends Controller
{
    public function __construct(private readonly DrmKorelasi $korelasi)
    {
    }

    public function index(Request $request): View
    {
        $siap = DrmMasterDb::terisi();
        $jenis = (string) $request->query('jenis', '');
        $id = (string) $request->query('id', '');
        $awal = $siap && in_array($jenis, DrmKorelasi::JENIS, true) && $id !== '' ? ['jenis' => $jenis, 'id' => $id] : ($siap ? $this->korelasi->pusatBawaan() : null);

        return view('peta-risiko-pja.drm.korelasi', ['siap' => $siap, 'awal' => $awal]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        $v = $request->validate(['jenis' => ['required', 'in:'.implode(',', DrmKorelasi::JENIS)], 'id' => ['required', 'string', 'max:600']]);
        try {
            return response()->json($this->korelasi->graf($v['jenis'], $v['id']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['pesan' => $e->getMessage()], 404);
        }
    }

    public function cari(Request $request): JsonResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        $v = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        return response()->json(['hasil' => $this->korelasi->cari($v['q'])]);
    }
}
