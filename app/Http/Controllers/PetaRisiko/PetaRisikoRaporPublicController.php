<?php

declare(strict_types=1);

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\Drm\DrmRaporPja;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Halaman publik (tanpa login) "Rapor PJA": personil memasukkan SID, lalu melihat performa PJA tempatnya
 * ditugaskan — ringkasan risiko, temuan SAP open, dan bahaya-risiko yang belum ditemukan. Hanya membaca snapshot.
 */
final class PetaRisikoRaporPublicController extends Controller
{
    public function __construct(private readonly DrmRaporPja $rapor)
    {
    }

    public function show(Request $request): View
    {
        return view('peta-risiko-pja.public.rapor-pja', [
            'prefillSid' => DrmRaporPja::normSid((string) $request->query('sid', '')) ?? '',
        ]);
    }

    /** GET ?sid=XXXX[&pja=ID] → data SID, daftar PJA-nya, dan rapor PJA terpilih (bawaan: PJA pertama). */
    public function cari(Request $request): JsonResponse
    {
        $sid = DrmRaporPja::normSid((string) $request->query('sid', ''));
        if ($sid === null) {
            return response()->json(['found' => false, 'message' => 'Format SID tidak valid. Gunakan huruf dan angka saja, contoh: U8WAP.'], 422);
        }
        if ($this->rapor->sampai() === null) {
            return response()->json(['found' => false, 'message' => 'Data rapor belum tersedia. Silakan coba lagi nanti.'], 503);
        }
        $hasil = $this->rapor->cari($sid);
        if ($hasil === null) {
            return response()->json(['found' => false, 'message' => 'SID tidak terdaftar sebagai personil PJA. Pastikan SID benar atau hubungi tim Safety.']);
        }

        // Rapor hanya untuk PJA yang memang memuat SID ini.
        $daftar = array_column($hasil['pja'], null, 'id');
        $pilih = (string) $request->query('pja', '');
        $id = isset($daftar[$pilih]) ? $pilih : (string) $hasil['pja'][0]['id'];
        $rapor = $this->rapor->rapor($id, $sid);
        if ($rapor === null) {
            return response()->json(['found' => false, 'message' => 'Data PJA belum tersedia. Silakan coba lagi nanti.'], 503);
        }

        return response()->json([
            'found' => true,
            'orang' => $hasil['orang'],
            'daftarPja' => $hasil['pja'],
            'rapor' => $rapor,
        ]);
    }
}
