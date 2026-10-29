<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Models\DmsRosterMasterUpload;
use App\Models\User;
use App\Services\Dms\Roster\DmsRosterAksesPerusahaan;
use App\Services\Dms\Roster\DmsRosterMasterEditService;
use App\Services\Dms\Roster\DmsRosterMasterService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Master Roster → Edit Roster: ubah jadwal roster yang sudah di-upload
 * (menunggu approval atau disetujui) langsung dari web. Hanya untuk user yang
 * di-assign di menu Assign Role, dan hanya baris karyawan perusahaannya —
 * admin tanpa assignment pun tidak bisa mengedit. Roster yang ditolak tidak
 * bisa diedit (upload ulang).
 */
class RosterMasterEditController extends Controller
{
    private const PER_HALAMAN = 25;

    public function __construct(
        private readonly DmsRosterMasterService $master,
        private readonly DmsRosterMasterEditService $edit,
        private readonly DmsRosterAksesPerusahaan $akses,
    ) {}

    public function index(Request $request): View
    {
        $assignment = $this->assignment();
        $status = (string) $request->query('status', '');

        $q = DmsRosterMasterUpload::query()
            ->where('status', '<>', DmsRosterMasterUpload::STATUS_REJECTED)
            ->orderByDesc('created_at');
        $this->akses->batasiUpload($q, $assignment);
        if (in_array($status, [DmsRosterMasterUpload::STATUS_PENDING, DmsRosterMasterUpload::STATUS_APPROVED], true)) {
            $q->where('status', $status);
        }

        return view('dms.roster-master.edit-index', [
            'daftar' => $q->paginate(20)->withQueryString(),
            'assignment' => $assignment,
            'status' => $status,
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        [$upload, $ejaan] = $this->bolehEdit($id);
        $halaman = max(1, (int) $request->query('hal', 1));
        $cari = mb_substr(trim((string) $request->query('q', '')), 0, 50);
        $grid = $this->master->grid($upload, $halaman, self::PER_HALAMAN, $cari, $ejaan);

        $tanggal = [];
        for ($t = CarbonImmutable::parse($upload->periode_mulai); $t->lte($upload->periode_selesai); $t = $t->addDay()) {
            $tanggal[] = $t;
        }

        return view('dms.roster-master.edit', [
            'upload' => $upload,
            'grid' => $grid,
            'tanggal' => $tanggal,
            'halaman' => $halaman,
            'perHalaman' => self::PER_HALAMAN,
            'totalHalaman' => max(1, (int) ceil($grid['total'] / self::PER_HALAMAN)),
            'cari' => $cari,
            'assignment' => $this->assignment(),
            'riwayat' => $this->edit->riwayat($upload->id),
            'adaLog' => DmsRosterMasterEditService::adaLog(),
        ]);
    }

    /**
     * Simpan perubahan dari editor (JSON):
     *   sel: [{sid, tanggal, kode}]  kode '' = kosongkan
     *   jam: [{sid, masuk, keluar, total}]
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$upload, $ejaan] = $this->bolehEdit($id);
        $data = $request->validate([
            'sel' => ['array', 'max:'.DmsRosterMasterEditService::MAKS_PERUBAHAN],
            'sel.*.sid' => ['required', 'string', 'max:50'],
            'sel.*.tanggal' => ['required', 'date_format:Y-m-d'],
            'sel.*.kode' => ['nullable', 'string', 'max:10'],
            'jam' => ['array', 'max:500'],
            'jam.*.sid' => ['required', 'string', 'max:50'],
            'jam.*.masuk' => ['nullable', 'string', 'max:5'],
            'jam.*.keluar' => ['nullable', 'string', 'max:5'],
            'jam.*.total' => ['nullable', 'string', 'max:6'],
        ]);
        $normal = static fn (array $baris, array $kolom): array => array_map(
            static fn (array $r): array => array_map(static fn ($v): string => trim((string) ($v ?? '')), array_intersect_key($r, array_flip($kolom)) + array_fill_keys($kolom, '')),
            $baris,
        );

        try {
            $hasil = $this->edit->simpan(
                $upload,
                $normal($data['sel'] ?? [], ['sid', 'tanggal', 'kode']),
                $normal($data['jam'] ?? [], ['sid', 'masuk', 'keluar', 'total']),
                $ejaan,
                mb_substr((string) (Auth::user()?->name ?? 'system'), 0, 150),
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'galat' => ['Gagal menyimpan: '.$e->getMessage()]], 500);
        }

        if ($hasil['galat'] !== []) {
            return response()->json(['ok' => false, 'galat' => $hasil['galat']], 422);
        }

        return response()->json([
            'ok' => true,
            'ubah' => $hasil['ubah'],
            'pesan' => $hasil['ubah'] === 0 ? 'Tidak ada perubahan.' : number_format($hasil['ubah'], 0, ',', '.').' jadwal diperbarui.',
        ]);
    }

    /**
     * Roster yang boleh diedit user ini + ejaan perusahaannya di roster.
     *
     * @return array{0: DmsRosterMasterUpload, 1: list<string>}
     */
    private function bolehEdit(int $id): array
    {
        $upload = DmsRosterMasterUpload::findOrFail($id);
        $assignment = $this->assignment();
        abort_if($assignment === [], 403, 'Edit roster hanya untuk user yang di-assign ke perusahaan di menu Assign Role.');
        abort_if($upload->status === DmsRosterMasterUpload::STATUS_REJECTED, 403, 'Roster yang ditolak tidak bisa diedit — upload ulang roster yang sudah diperbaiki.');
        abort_unless(
            DmsRosterAksesPerusahaan::terlihat($assignment, $this->akses->perusahaanUpload($upload->id)),
            403,
            'Roster ini bukan perusahaan yang di-assign ke akun Anda.',
        );

        return [$upload, DmsRosterAksesPerusahaan::ejaanCakupan($assignment)];
    }

    /**
     * @return list<string>
     */
    private function assignment(): array
    {
        $user = Auth::user();

        return $this->akses->assignment($user instanceof User ? $user : null);
    }
}
