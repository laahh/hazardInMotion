<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dms\Roster\DmsRosterAksesPerusahaan;
use App\Services\Dms\Roster\DmsRosterKaryawanAktif;
use App\Services\Dms\Roster\DmsRosterMasterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * /dms/master-roster/assign-role — admin meng-assign perusahaan ke user.
 * User yang di-assign hanya melihat & meng-approve roster perusahaan itu di
 * Approval Roster (aturan lengkapnya di DmsRosterAksesPerusahaan).
 */
final class RosterMasterAksesController extends Controller
{
    public function __construct(
        private readonly DmsRosterAksesPerusahaan $akses,
        private readonly DmsRosterKaryawanAktif $karyawanAktif,
        private readonly DmsRosterMasterService $master,
    ) {}

    public function index(Request $request): View
    {
        $this->hanyaAdmin();
        $cari = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $daftar = $this->akses->daftar($cari);

        return view('dms.roster-master.assign-role', [
            'siap' => $this->akses->siap(),
            'daftar' => $daftar,
            'cari' => $cari,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email', 'role']),
            // Perusahaan yang sudah di-assign tetap jadi pilihan walau sudah tak ada
            // di daftar karyawan aktif, supaya "Ubah" tidak diam-diam membuangnya.
            'pilihanPt' => DmsRosterAksesPerusahaan::rapikan(array_merge(
                $this->pilihanPerusahaan(),
                ...array_map(static fn (array $d): array => $d['perusahaan'], $this->akses->daftar()),
            )),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->hanyaAdmin();
        if (! $this->akses->siap()) {
            return back()->with('error', 'Tabel '.DmsRosterAksesPerusahaan::TABEL.' belum ada — jalankan migrasinya dulu.');
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'perusahaan' => ['required', 'array', 'min:1'],
            'perusahaan.*' => ['string', 'max:255'],
        ], [
            'user_id.required' => 'Pilih user.',
            'user_id.exists' => 'User tidak ditemukan.',
            'perusahaan.required' => 'Pilih minimal satu perusahaan.',
            'perusahaan.min' => 'Pilih minimal satu perusahaan.',
        ]);

        $user = User::query()->findOrFail((int) $data['user_id']);
        try {
            $n = $this->akses->simpan($user->id, $data['perusahaan'], $this->pengguna());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Gagal menyimpan assign role.');
        }

        return redirect()->route('dms.master-roster.assign-role')
            ->with('success', $user->name.' di-assign ke '.$n.' perusahaan.');
    }

    public function destroy(int $user): RedirectResponse
    {
        $this->hanyaAdmin();
        if (! $this->akses->siap()) {
            return back()->with('error', 'Tabel '.DmsRosterAksesPerusahaan::TABEL.' belum ada — jalankan migrasinya dulu.');
        }

        $this->akses->hapus($user);
        $nama = User::query()->whereKey($user)->value('name') ?? 'User #'.$user;

        return redirect()->route('dms.master-roster.assign-role')
            ->with('success', 'Assign role '.$nama.' dihapus.');
    }

    /**
     * Pilihan perusahaan: perusahaan karyawan aktif (bep_vw_safety_karyawan_aktif,
     * salinan cache) digabung perusahaan yang sudah ada di roster ter-upload.
     *
     * @return list<string>
     */
    private function pilihanPerusahaan(): array
    {
        $pt = array_map(static fn (array $p): string => (string) $p['nama'], $this->karyawanAktif->perusahaan() ?? []);
        foreach ($this->master->perusahaanTerupload() as $p) {
            $pt[] = (string) $p['perusahaan'];
        }

        return DmsRosterAksesPerusahaan::rapikan($pt);
    }

    private function hanyaAdmin(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isAdmin(), 403, 'Menu Assign Role hanya untuk admin.');
    }

    private function pengguna(): string
    {
        return mb_substr((string) (Auth::user()?->name ?? 'system'), 0, 150);
    }
}
