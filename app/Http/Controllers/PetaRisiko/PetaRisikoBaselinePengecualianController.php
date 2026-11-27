<?php

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\Drm\DrmBaselinePengecualian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengecualian baseline Overview (Tidak Teridentifikasi / Open Risk dkk) — keputusan user 27 Nov 2026: baris
 * aktivitas × bahaya-risiko yang sudah tidak berlaku bisa dinonaktifkan langsung dari kartu/modal Overview tanpa
 * menunggu peta-risiko:sync, dengan riwayat (siapa, kapan, alasan) tetap tersimpan.
 */
class PetaRisikoBaselinePengecualianController extends Controller
{
    public function __construct(private readonly DrmBaselinePengecualian $pengecualian)
    {
    }

    /** Daftar {kel, hid} yang sedang aktif — dimuat Overview untuk menyaring rincian & kartu secara langsung. */
    public function aktif(): JsonResponse
    {
        return response()->json(['daftar' => $this->pengecualian->daftarAktif()]);
    }

    public function tandai(Request $request): JsonResponse
    {
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');
        $v = $request->validate([
            'kel' => ['required', 'string', 'max:50'],
            'hid' => ['required', 'string', 'max:50'],
            'aktivitas' => ['nullable', 'string', 'max:500'],
            'sub' => ['nullable', 'string', 'max:500'],
            'bahaya' => ['nullable', 'string', 'max:1000'],
            'alasan' => ['required', 'string', 'max:500'],
            'lv' => ['nullable', 'string', 'max:20'],
            'kode' => ['nullable', 'string', 'max:20'],
            'inc' => ['nullable', 'boolean'],
            'tr' => ['nullable', 'boolean'],
        ], ['required' => ':attribute wajib diisi.', 'max' => ':attribute terlalu panjang.'],
            ['kel' => 'Kelompok aktivitas', 'hid' => 'ID baris', 'alasan' => 'Alasan']);
        $oleh = (string) ($request->user()?->email ?? $request->user()?->getAuthIdentifier() ?? '');
        $id = $this->pengecualian->tandai($v['kel'], $v['hid'], (string) ($v['aktivitas'] ?? ''), (string) ($v['sub'] ?? ''),
            (string) ($v['bahaya'] ?? ''), $v['alasan'], (string) ($v['lv'] ?? ''), (string) ($v['kode'] ?? ''),
            $request->boolean('inc'), $request->boolean('tr'), $oleh ?: null);

        return response()->json(['ok' => true, 'id' => $id, 'pesan' => 'Baris dinonaktifkan dari Open Risk.']);
    }

    public function kembalikan(Request $request, int $id): JsonResponse
    {
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');
        $oleh = (string) ($request->user()?->email ?? $request->user()?->getAuthIdentifier() ?? '');
        if (! $this->pengecualian->kembalikan($id, $oleh ?: null)) {
            return response()->json(['ok' => false, 'pesan' => 'Baris tidak ditemukan atau sudah dikembalikan aktif.'], 422);
        }

        return response()->json(['ok' => true, 'pesan' => 'Baris dikembalikan aktif (dihitung lagi di Open Risk setelah sync berikutnya).']);
    }

    public function riwayat(Request $request): View
    {
        return view('peta-risiko-pja.drm.baseline-pengecualian', [
            'riwayat' => $this->pengecualian->riwayat(),
            'bolehUbah' => $this->bolehUbah($request),
        ]);
    }

    private function bolehUbah(Request $request): bool
    {
        $editor = (array) config('peta_risiko_drm.master.editor', []);
        $user = $request->user();
        if ($user === null) {
            return false;
        }

        return $editor === [] || in_array((string) $user->getAuthIdentifier(), $editor, true) || in_array((string) ($user->email ?? ''), $editor, true);
    }
}
