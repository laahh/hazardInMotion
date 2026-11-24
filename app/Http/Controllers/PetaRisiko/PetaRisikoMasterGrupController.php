<?php

declare(strict_types=1);

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use App\Services\PetaRisiko\Drm\Master\DrmMasterGrup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Master "Grup lokasi & aktivitas" (/peta-risiko-pja/master/grup): lihat dan edit M05 (grup lokasi → baris HIRA)
 * per aktivitas, serta kelompok aktivitas (M02). Berlaku pada peta-risiko:sync berikutnya.
 */
final class PetaRisikoMasterGrupController extends Controller
{
    public function __construct(private readonly DrmMasterGrup $grup)
    {
    }

    /**
     * Tabel datar satu baris = satu baris HIRA (sama konsep dengan /master/baris): kolom "Grup lokasi" berisi
     * chip grup yang bisa ditambah/dikeluarkan per baris, disaring/dicari di browser (DataTables).
     */
    public function index(Request $request): View
    {
        $data = ['siap' => DrmMasterDb::terisi(), 'bolehUbah' => $this->bolehUbah($request)];
        if (! $data['siap']) {
            return view('peta-risiko-pja.drm.master-grup', $data);
        }

        return view('peta-risiko-pja.drm.master-grup', $data + [
            'daftarGrup' => $this->grup->daftarGrup(),
            'rows' => $this->grup->semuaBaris(),
            'dlPerGrup' => $this->grup->semuaDetailLokasi(),
            'kelompok' => $this->grup->kelompok(),
        ]);
    }

    /** Tambahkan baris terpilih (bisa lintas aktivitas) ke grup — chip "+ Tambah ke grup" per baris atau aksi borongan. */
    public function tambah(Request $request, int $gid): RedirectResponse
    {
        $this->izin($request);
        $v = $request->validate([
            'ri' => ['required', 'array', 'min:1', 'max:2000'],
            'ri.*' => ['integer', 'min:0'],
        ], ['ri.required' => 'Pilih minimal satu baris.']);
        $ri = array_map('intval', $v['ri']);

        return $this->jalankan(fn () => $this->grup->tambahBaris($gid, $ri), fn ($n) => $n ? "{$n} baris ditambahkan ke grup." : 'Baris terpilih sudah ada di grup ini.');
    }

    /** Keluarkan baris terpilih dari grup $gid (chip hapus per baris, atau aksi borongan). */
    public function ubahBaris(Request $request, int $gid): RedirectResponse
    {
        $this->izin($request);
        $v = $request->validate([
            'ri' => ['required', 'array', 'min:1', 'max:2000'],
            'ri.*' => ['integer', 'min:0'],
        ], ['ri.required' => 'Pilih minimal satu baris.']);
        $ri = array_map('intval', $v['ri']);

        return $this->jalankan(fn () => $this->grup->hapus($gid, $ri), fn ($n) => "{$n} baris dikeluarkan dari grup.");
    }

    /** Ubah kelompok aktivitas (M02). */
    public function kelompok(Request $request, int $aid): RedirectResponse
    {
        $this->izin($request);
        $v = $request->validate(['kelompok' => ['required', 'string', 'max:10']]);

        return $this->jalankan(fn () => $this->grup->ubahKelompok($aid, $v['kelompok']), fn ($ubah) => $ubah ? 'Kelompok aktivitas diperbarui.' : 'Kelompok aktivitas tidak berubah.');
    }

    private function jalankan(callable $aksi, callable $pesan): RedirectResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        try {
            $hasil = $aksi();
        } catch (InvalidArgumentException $e) {
            return redirect()->route('peta-risiko-pja.master.grup.index')->withErrors(['grup' => $e->getMessage()]);
        }

        return redirect()->route('peta-risiko-pja.master.grup.index')
            ->with('pesan', $pesan($hasil).' Berlaku setelah peta-risiko:sync berikutnya.');
    }

    private function izin(Request $request): void
    {
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');
    }

    private function bolehUbah(Request $request): bool
    {
        $editor = (array) config('peta_risiko_drm.master.editor', []);
        $user = $request->user();

        return $user !== null && ($editor === [] || in_array((string) $user->getAuthIdentifier(), $editor, true)
            || in_array((string) ($user->email ?? ''), $editor, true));
    }
}
