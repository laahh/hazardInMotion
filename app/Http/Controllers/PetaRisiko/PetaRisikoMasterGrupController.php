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

    public function index(Request $request): View
    {
        $data = ['siap' => DrmMasterDb::terisi(), 'bolehUbah' => $this->bolehUbah($request)];
        if (! $data['siap']) {
            return view('peta-risiko-pja.drm.master-grup', $data);
        }
        $daftar = $this->grup->daftarGrup();
        $gid = (int) $request->query('gid', 0);
        if (! collect($daftar)->contains('gid', $gid)) {
            // Bawaan: grup dengan baris terbanyak.
            $gid = (int) (collect($daftar)->sortByDesc('baris')->first()['gid'] ?? 0);
        }

        return view('peta-risiko-pja.drm.master-grup', $data + [
            'daftarGrup' => $daftar,
            'gid' => $gid,
            'grupAktif' => collect($daftar)->firstWhere('gid', $gid),
            'aktivitas' => $this->grup->aktivitasGrup($gid),
            'luar' => $data['bolehUbah'] ? $this->grup->aktivitasLuar($gid) : [],
            'dl' => $this->grup->detailLokasi($gid),
            'kelompok' => $this->grup->kelompok(),
        ]);
    }

    /** Tambah aktivitas (semua barisnya) ke grup. */
    public function tambah(Request $request, int $gid): RedirectResponse
    {
        $this->izin($request);
        $v = $request->validate(['aid' => ['required', 'integer', 'min:0']]);

        return $this->jalankan($gid, fn () => $this->grup->tambah($gid, (int) $v['aid']), fn ($n) => $n ? "{$n} baris aktivitas ditambahkan ke grup." : 'Semua baris aktivitas ini sudah ada di grup.');
    }

    /** Pindah atau hapus baris terpilih dari grup. */
    public function ubahBaris(Request $request, int $gid): RedirectResponse
    {
        $this->izin($request);
        $v = $request->validate([
            'aksi' => ['required', 'in:pindah,hapus'],
            'ri' => ['required', 'array', 'min:1', 'max:2000'],
            'ri.*' => ['integer', 'min:0'],
            'tujuan' => ['required_if:aksi,pindah', 'nullable', 'integer'],
        ], ['ri.required' => 'Pilih minimal satu baris.', 'tujuan.required_if' => 'Pilih grup tujuan.']);
        $ri = array_map('intval', $v['ri']);

        if ($v['aksi'] === 'hapus') {
            return $this->jalankan($gid, fn () => $this->grup->hapus($gid, $ri), fn ($n) => "{$n} baris dikeluarkan dari grup.");
        }

        return $this->jalankan($gid, fn () => $this->grup->pindah($gid, (int) $v['tujuan'], $ri), fn ($n) => "{$n} baris dipindah ke grup tujuan.");
    }

    /** Ubah kelompok aktivitas (M02). */
    public function kelompok(Request $request, int $aid): RedirectResponse
    {
        $this->izin($request);
        $v = $request->validate(['kelompok' => ['required', 'string', 'max:10'], 'gid' => ['nullable', 'integer']]);
        $gid = (int) ($v['gid'] ?? 0);

        return $this->jalankan($gid, fn () => $this->grup->ubahKelompok($aid, $v['kelompok']), fn ($ubah) => $ubah ? 'Kelompok aktivitas diperbarui.' : 'Kelompok aktivitas tidak berubah.');
    }

    private function jalankan(int $gid, callable $aksi, callable $pesan): RedirectResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        try {
            $hasil = $aksi();
        } catch (InvalidArgumentException $e) {
            return redirect()->route('peta-risiko-pja.master.grup.index', ['gid' => $gid])->withErrors(['grup' => $e->getMessage()]);
        }

        return redirect()->route('peta-risiko-pja.master.grup.index', ['gid' => $gid])
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
